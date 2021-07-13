<?php
// This file imports binaries from a given directory

// path to the data (e.g. '../data')
$containerDir = 'PATH_TO_THE_DATA_DIRECTORY';
// prefix used to create ingested files IDs (e.g. 'https://id.acdh.oeaw.ac.at/wollmilchsau/')
// (see https://github.com/acdh-oeaw/repo-php-util/#how-files-are-matched-with-repository-resources for more information)
$containerToUriPrefix = 'ID_PREFIX';
// Parent resource ID - typically the top-level collection ID (e.g. 'https://id.acdh.oeaw.ac.at/wollmilchsau').
// ParentResourceId may be empty. In such a case files in the indexed directory root won't be attached to any parent by the Indexer (but they can still have parents defined e.g. trough a metadata import).
$parentResourceId = 'PARENT_RESOURCE_ID';
// SKIP_NOT_EXIST skip files which don't have corresponding repository resource. Use it if you imported metadata first and you want to make sure only files matching already imported metadata resources will be ingested. It's a secure option preventing you from disasters in case of making a typo in the $containerToUriPrefix config setting.
// SKIP_NONE import all files
// SKIP_BINARY_EXIST skip files which have corresponding repository resource with not empty binary payload. Use it if your upload failed in the middle but you used autocommit and some resources were ingested. In such a case the ingestion will skip resources which were already ingested and ingest only the ones which are still missing (saving your time and increases chances additional resources will be ingested).
// SKIP_EXIST skip files which have corresponding repository resource. Like SKIP_BINARY_EXIST but it's enough if a resource exists (it doesn't have to have a binary payload).
$skip = 'SKIP_NOT_EXIST';
// uploaded file size limit (in bytes, -1 means any size)
// files bigger then this limit will be created with full metadata but their binary content won't be uploaded
$sizeLimit = -1;
// Filename filter - depending on the $filterType either only files with filenames matching or not matching the filter will be ingested
// Use it 
// Filter value should be a valid first argument of the PHP's preg_match(), e.g. '/(Aachen_Prot_1.xml|Aachen_Dok_50.xml)$/'
$filenameFilter = '';
// MATCH or SKIP - should the $filenamefilter match resources to be included or skipped during the ingestion
$filterType = 'MATCH';
// Should collections/binary resources be assigned a default class (e.g. acdh:Collection and acdh:Resource)
// In case of ingesting binary data for already existing repository resources it might be safer to choose "false" (preserve their existing classes)
$assignDefaultClass = false;

// advanced config (generally shouldn't need adjustments)
$versioning = 'VERSIONING_NONE'; // VERSIONING_NONE, VERSIONING_ALWAYS, VERSIONING_DIGEST, VERSIONING_DATE
$configLocation    = '/ARCHE/config.yaml';
$composerLocation  = '/ARCHE'; // directory where you run "composer update"
$autocommit        = 0;        // don't touch until you encounter problems
$verbose           = true;     // should output be verbose? 'true' is generally better :)
$runComposerUpdate = true;     // should `composer update` be run in $composerLocation dir (makes ingestion initialization longer but releases us from remembering about running `composer update` by hand)

// NO CHANGES NEEDED BELOW THIS LINE

if ($runComposerUpdate) {
    echo "\n######################################################\nUpdating libraries\n######################################################\n";
    system('cd ' . escapeshellarg($composerLocation) . ' && composer update --no-dev');
    echo "\n######################################################\nUpdate ended\n######################################################\n\n";
}

use \acdhOeaw\arche\lib\Repo;
use \acdhOeaw\arche\lib\ingest\Indexer;
require_once $composerLocation . '/vendor/autoload.php';
$rc = new ReflectionClass('\acdhOeaw\arche\lib\ingest\Indexer');

Indexer::$debug = $verbose;
$repo = Repo::factoryInteractive($configLocation);

$ind = new Indexer($containerDir, $containerToUriPrefix);
if (!empty($parentResourceId)) {
    $resource = $repo->getResourceById($parentResourceId);
    $ind->setParent($resource);
} else {
    $ind->setRepo($repo);
}
if ($assignDefaultClass) {
    $ind->setBinaryClass($repo->getSchema()->ingest->defaultBinaryClass);
    $ind->setCollectionClass($repo->getSchema()->ingest->defaultCollectionClass);
} else {
    $ind->setBinaryClass('');
    $ind->setCollectionClass('');
}
$ind->setSkip($rc->getConstant($skip));
$ind->setVersioning($rc->getConstant($versioning));
$ind->setUploadSizeLimit($sizeLimit);
$ind->setAutoCommit($autocommit);
if (!empty($filenameFilter)) {
    $ind->setFilter($filenameFilter, $rc->getConstant($filterType));
}

echo "\n######################################################\nImporting binaries \n######################################################\n";
$repo->begin();
$rs = $ind->index();
$repo->commit();
echo "count resources: ".count($rs);

echo "\n######################################################\nImport finished \n######################################################\n";

