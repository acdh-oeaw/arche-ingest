#!/usr/bin/php
<?php

// This file imports binaries from a given directory
// path to the data (e.g. '../data')
$dataDir        = 'PATH_TO_THE_DATA_DIRECTORY';
// Prefix used to create ingested files IDs (e.g. 'https://id.acdh.oeaw.ac.at/wollmilchsau')
// The id is created by replacing $dataDir with $idPrefix in the ingested file's path
$idPrefix       = 'ID_PREFIX';
// Filename filter - depending on the $filterType either only files with filenames matching or not matching the filter will be ingested
// Filter value should be a valid first argument of the PHP's preg_match(), e.g. '/(Aachen_Prot_1.xml|Aachen_Dok_50.xml)$/'
$filenameFilter = '';
// FILTER_MATCH or FILTER_SKIP - should the $filenamefilter match resources to be included or skipped during the ingestion
$filterType     = 'FILTER_MATCH';

// advanced config (generally shouldn't need adjustments)
$sizeLimit          = -1;                 // Uploaded file size limit (in bytes, -1 means any size). Files bigger then this limit will be created with full metadata but their binary content won't be uploaded
// SKIP_NOT_EXIST skip files which don't have corresponding repository resource. Use it if you imported metadata first and you want to make sure only files matching already imported metadata resources will be ingested. It's a secure option preventing you from disasters in case of making a typo in the $containerToUriPrefix config setting.
// SKIP_NONE import all files
// SKIP_BINARY_EXIST skip files which have corresponding repository resource with not empty binary payload. Use it if your upload failed in the middle but you used autocommit and some resources were ingested. In such a case the ingestion will skip resources which were already ingested and ingest only the ones which are still missing (saving your time and increases chances additional resources will be ingested).
// SKIP_EXIST skip files which have corresponding repository resource. Like SKIP_BINARY_EXIST but it's enough if a resource exists (it doesn't have to have a binary payload).
$skip               = ['SKIP_NOT_EXIST', 'SKIP_BINARY_EXIST'];
$assignDefaultClass = false;              // Should collections/binary resources be assigned a default class (e.g. acdh:Collection and acdh:Resource). In case of ingesting binary data for already existing repository resources it might be safer to choose "false" (preserve their existing classes)
$parentResourceId   = '';                 // Parent resource ID - typically the top-level collection ID (e.g. 'https://id.acdh.oeaw.ac.at/wollmilchsau'). ParentResourceId may be empty. In such a case files in the indexed directory root won't be attached to any parent by the Indexer (but they can still have parents defined e.g. trough a metadata import).
$versioning         = 'VERSIONING_NONE';  // VERSIONING_NONE, VERSIONING_ALWAYS, VERSIONING_DIGEST, VERSIONING_DATE
$migratePid         = 'PID_KEEP';         // PID_KEEP, PID_PASS
$errMode            = 'ERRMODE_CONTINUE'; // ERRMODE_FAIL (fail on first error), ERRMODE_PASS (continue on error and fail at the end) or ERRMODE_CONTINUE (continue no matter errors)
$configLocation     = '/ARCHE/config.yaml';
$composerLocation   = '/ARCHE';           // directory where you run "composer update"; if doesn't exist, the script's directory will be used instead
$autocommit         = 1000;               // how often commit the changes
$flatStructure      = false;              // don't create collection resources for directories
$maxDepth           = -1;                 // maximum ingested directories depth (0 - only $dataDir, 1 - with direct subdirs, etc.; -1 - without limit)
$verbose            = true;               // should output be verbose? 'true' is generally better :)
$runComposerUpdate  = true;               // should `composer update` be run in $composerLocation dir (makes ingestion initialization longer but releases us from remembering about running `composer update` by hand)
$concurrency        = 3;                  // number of parallel requests (Indexer->import() 2nd parameter)
$retriesOnConflict  = 3;                  // number of parallel requests (Indexer->import() 3rd parameter)
// NO CHANGES NEEDED BELOW THIS LINE

$composerLocation = getenv('COMPOSER_DIR') ?: (file_exists($composerLocation) ? $composerLocation : __DIR__);
if ($runComposerUpdate && count($argv) < 2) {
    echo "\n######################################################\nUpdating libraries\n######################################################\n";
    exec('cd ' . escapeshellarg($composerLocation) . ' && composer update --no-dev');
    echo "\n######################################################\nUpdate ended\n######################################################\n\n";
}

use acdhOeaw\arche\lib\Repo;
use acdhOeaw\arche\lib\ingest\Indexer;
use acdhOeaw\arche\lib\exception\ExceptionUtil;
use acdhOeaw\arche\ingest\IndexerVersioner;
use zozlak\argparse\ArgumentParser;

require_once "$composerLocation/vendor/autoload.php";
$rc = new ReflectionClass(Indexer::class);

if (count($argv) > 1) {
    $errModes           = ['fail', 'pass', 'continue'];
    $skipModes          = ['none', 'not_exist', 'exist', 'binary_exist'];
    $versioningModes    = ['none', 'always', 'digest', 'date'];
    $filterTypes        = ['match', 'skip'];
    $parser             = new ArgumentParser();
    $parser->addArgument('--parentId');
    $parser->addArgument('--skip', choices: $skipModes, nargs: ArgumentParser::NARGS_STAR, default: [
        'none'], help: '(default %(default)s)');
    $parser->addArgument('--versioning', choices: $versioningModes, default: 'none', help: '(default %(default)s)');
    $parser->addArgument('--migratePid', choices: ['keep', 'pass'], default: 'keep', help: 'In case of new version creation, should the pid be kept with an old resource or passed to the new one(default %(default)s)');
    $parser->addArgument('--sizeLimit', type: ArgumentParser::TYPE_INT, default: -1, help: 'Maximum uploaded file size in bytes. -1 means no limit. (default %(default)s)', metavar: 'BYTES');
    $parser->addArgument('--filenameFilter');
    $parser->addArgument('--filterType', choices: $filterTypes, default: 'match', help: 'Taken into account only when --filenameFilter is provided (default %(default)s)');
    $parser->addArgument('--assignDefaultClass', action: ArgumentParser::ACTION_STORE_TRUE, default: false);
    $parser->addArgument('--flatStructure', action: ArgumentParser::ACTION_STORE_TRUE, default: false);
    $parser->addArgument('--maxDepth', type: ArgumentParser::TYPE_INT, default: -1, help: 'Maximum ingested directories depth (0 - only dataDir, 1 - with direct subdirs, etc.; -1 means no limit). (default %(default)s)', metavar: 'N');
    $parser->addArgument('--noCertCheck', action: ArgumentParser::ACTION_STORE_TRUE, default: false, help: 'Do not check servers SSL certificate.');
    $parser->addArgument('--silent', action: ArgumentParser::ACTION_STORE_TRUE, default: false);
    $parser->addArgument('--autocommit', type: ArgumentParser::TYPE_INT, default: 0, help: '(default %(default)s)', metavar: 'N');
    $parser->addArgument('--errMode', choices: $errModes, default: 'fail', help: '(default %(default)s)');
    $parser->addArgument('--concurrency', type: ArgumentParser::TYPE_INT, default: $concurrency, help: '(default %(default)s)', metavar: 'N');
    $parser->addArgument('--retriesOnConflict', type: ArgumentParser::TYPE_INT, default: $retriesOnConflict, help: '(default %(default)s)', metavar: 'N');
    $parser->addArgument('dataDir');
    $parser->addArgument('idPrefix');
    $parser->addArgument('repoUrl');
    $parser->addArgument('user');
    $parser->addArgument('password');
    $args               = $parser->parseArgs(array_slice($argv, 1));
    $parentResourceId   = $args->parentId;
    $skip               = array_map(fn($x) => 'SKIP_' . mb_strtoupper($x), $args->skip);
    $versioning         = 'VERSIONING_' . mb_strtoupper($args->versioning);
    $migratePid         = 'PID_' . mb_strtoupper($args->migratePid);
    $sizeLimit          = $args->sizeLimit;
    $filenameFilter     = '`' . $args->filenameFilter . '`';
    $filterType         = 'FILTER_' . mb_strtoupper($args->filterType);
    $assignDefaultClass = $args->assignDefaultClass;
    $flatStructure      = $args->flatStructure;
    $maxDepth           = $args->maxDepth;
    $verbose            = !$args->silent;
    $autocommit         = $args->autocommit;
    $concurrency        = $args->concurrency;
    $retriesOnConflict  = $args->retriesOnConflict;
    $errMode            = 'ERRMODE_' . mb_strtoupper($args->errMode);
    $dataDir            = $args->dataDir;
    $idPrefix           = $args->idPrefix;
    $auth               = [$args->user, $args->password];
    $guzzleOpts         = [
        'auth'   => $auth,
        'verify' => !$args->noCertCheck
    ];
    $repo               = Repo::factoryFromUrl($args->repoUrl, $guzzleOpts);
} else {
    $repo = Repo::factoryInteractive(empty($configLocation) ? null : $configLocation);
}
$errMode    = $rc->getConstant($errMode);
$skip       = is_array($skip) ? $skip : [$skip];
$skip       = array_map(fn($x) => $rc->getConstant($x), $skip);
$skip       = array_sum($skip);
$versioning = $rc->getConstant($versioning);
$filterType = $rc->getConstant($filterType);

if (in_array($skip, [Indexer::SKIP_EXIST, Indexer::SKIP_BINARY_EXIST]) && $versioning !== Indexer::VERSIONING_NONE) {
    echo "Conflicting skip and versioning modes selected!\n ";
    exit(1);
}

Indexer::$debug = $verbose;
$ind            = new Indexer($dataDir, $idPrefix, $repo);
if (!empty($parentResourceId)) {
    $resource = $repo->getResourceById($parentResourceId);
    $ind->setParent($resource);
}
if ($assignDefaultClass) {
    $ind->setBinaryClass($repo->getSchema()->ingest->defaultBinaryClass);
    $ind->setCollectionClass($repo->getSchema()->ingest->defaultCollectionClass);
} else {
    $ind->setBinaryClass('');
    $ind->setCollectionClass('');
}
$ind->setSkip($skip);
$ind->setVersioning($versioning, fn($a, $b) => IndexerVersioner::versionMetadata($a, $b), fn($a, $b) => IndexerVersioner::updateReferences($a, $b));
$ind->setUploadSizeLimit($sizeLimit);
$ind->setAutoCommit($autocommit);
$ind->setFlatStructure($flatStructure);
$ind->setDepth($maxDepth < 0 ? PHP_INT_MAX : $maxDepth);
if (!empty($filenameFilter)) {
    $ind->setFilter($filenameFilter, $filterType);
}

try {
    echo "\n######################################################\nImporting binaries\n######################################################\n";
    $txId      = $repo->begin();
    echo "##### transaction id: $txId #####\n";
    $resources = $ind->import($errMode, $concurrency, $retriesOnConflict);
    $repo->commit();
    $errors    = array_filter($resources, fn($x) => $x instanceof \Exception);
    echo "Ingested resources count: " . (count($resources) - count($errors)) . (count($errors) > 0 ? " errors count: " . count($errors) : " impressive job pal!") . "\n";
    echo "\n######################################################\nImport ended\n######################################################\n";
    foreach ($errors as $i) {
        echo ExceptionUtil::unwrap($i, $verbose) . "\n----------\n";
    }
    $ret = count($errors) === 0 ? 0 : 1;
} catch (Throwable $e) {
    echo "\n######################################################\nImport failed\n######################################################\n";
    echo ExceptionUtil::unwrap($e, $verbose);
    $ret = 1;
}
if (count(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)) > 0) {
    return $ret;
} else {
    exit($ret);
}
