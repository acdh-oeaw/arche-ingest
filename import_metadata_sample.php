<?php

// This script imports metadata from a ttl file
// config
$rdfLocation       = 'sample.ttl';
$errMode           = 'ERRMODE_PASS';                // ERRMODE_FAIL (fail on first error) or ERRMODE_PASS (continue on error and fail at the end)
// advanced config (generally shouldn't need adjustments)
$configLocation    = '/ARCHE/config.yaml';          // file containing list of repositories - see e.g. config-sample.yaml
$composerLocation  = '/ARCHE';                      // directory where you run "composer update"; if doesn't exist, the script's directory will be used instead
$autocommit        = 0;                             // don't touch until you encounter problems
$verbose           = true;                          // should output be verbose? 'true' is generally better :)
$runComposerUpdate = true;                          // should `composer update` be run in $composerLocation dir (makes ingestion initialization longer but releases us from remembering about running `composer update` by hand)
$idNamespace       = 'https://id.acdh.oeaw.ac.at/'; // identifiers namespace (MetadataCollection->import() first parameter)
$concurrency       = 8;                             // number of parallel requests (MetadataCollection->import() 3rd parameter)
$retriesOnConflict = 3;                             // number of parallel requests (MetadataCollection->import() 4th parameter)
// NO CHANGES NEEDED BELOW THIS LINE

$composerLocation = getenv('COMPOSER_DIR') ?: (file_exists($composerLocation) ? $composerLocation : __DIR__);
if ($runComposerUpdate && count($argv) < 2) {
    echo "\n######################################################\nUpdating libraries\n######################################################\n";
    exec('cd ' . escapeshellarg($composerLocation) . ' && composer update --no-dev');
    echo "\n######################################################\nUpdate ended\n######################################################\n\n";
}

use acdhOeaw\arche\lib\Repo;
use acdhOeaw\arche\lib\ingest\MetadataCollection;
use acdhOeaw\arche\lib\exception\ExceptionUtil;
use zozlak\argparse\ArgumentParser;

require_once "$composerLocation/vendor/autoload.php";
$rc = new ReflectionClass(MetadataCollection::class);

if (count($argv) > 1) {
    $errModes          = ['fail', 'pass'];
    $parser            = new ArgumentParser();
    $parser->addArgument('--silent', action: ArgumentParser::ACTION_STORE_TRUE, default: false);
    $parser->addArgument('--errMode', choices: $errModes, default: 'pass', help: '(default %(default)s)');
    $parser->addArgument('--autocommit', type: ArgumentParser::TYPE_INT, default: 0, help: '(default %(default)s)', metavar: 'N');
    $parser->addArgument('--idNamespace', default: $idNamespace, help: '(default %(default)s)');
    $parser->addArgument('--concurrency', type: ArgumentParser::TYPE_INT, default: $concurrency, help: '(default %(default)s)', metavar: 'N');
    $parser->addArgument('--retriesOnConflict', type: ArgumentParser::TYPE_INT, default: $retriesOnConflict, help: '(default %(default)s)', metavar: 'N');
    $parser->addArgument('rdfFile');
    $parser->addArgument('repoUrl');
    $parser->addArgument('user');
    $parser->addArgument('password');
    $args              = $parser->parseArgs(array_slice($argv, 1));
    $verbose           = !$args->silent;
    $autocommit        = $args->autocommit;
    $idNamespace       = $args->idNamespace;
    $concurrency       = $args->concurrency;
    $retriesOnConflict = $args->retriesOnConflict;
    $errMode           = 'ERRMODE_' . mb_strtoupper($args->errMode);
    $rdfLocation       = $args->rdfFile;
    $auth              = [$args->user, $args->password];
    $repo              = Repo::factoryFromUrl($args->repoUrl, ['auth' => $auth]);
} else {
    $repo = Repo::factoryInteractive(empty($configLocation) ? null : $configLocation);
}
$errMode = $rc->getConstant($errMode);

MetadataCollection::$debug = $verbose;

$graph = new MetadataCollection($repo, $rdfLocation);
$graph->setAutoCommit($autocommit);
try {
    echo "\n######################################################\nImporting structure\n######################################################\n";
    $graph->preprocess();
    $txId      = $repo->begin();
    echo "##### transaction id: $txId #####\n";
    $resources = $graph->import($idNamespace, MetadataCollection::SKIP, $errMode, $concurrency, $retriesOnConflict);
    echo "##### commiting transaction $txId #####\n";
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
    echo ExceptionUtil::unwrap($e, $verbose) . "\n";
    $ret = 1;
}
if (count(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)) > 0) {
    return $ret;
} else {
    exit($ret);
}
