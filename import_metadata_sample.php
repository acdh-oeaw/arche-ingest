<?php
// config
$ttlLocation = 'WRITE_DOWN_PATH_TO_YOUR_TTL_HERE';
$errMode     = 'ERRMODE_FAIL'; // ERRMODE_FAIL (fail on first error) or ERRMODE_PASS (continue on error and fail at the end)

// advanced config (generally shouldn't need adjustments)
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

use \acdhOeaw\acdhRepoLib\Repo;
use \acdhOeaw\acdhRepoIngest\MetadataCollection;
require_once $composerLocation . '/vendor/autoload.php';
$rc = new ReflectionClass('\acdhOeaw\acdhRepoIngest\MetadataCollection');

MetadataCollection::$debug = $verbose;
$repo = Repo::factoryInteractive($configLocation);

echo "\n######################################################\nImporting structure\n######################################################\n\n";
$graph = new MetadataCollection($repo, $ttlLocation);
$graph->setAutoCommit($autocommit);
$repo->begin();
$resources = $graph->import('https://id.acdh.oeaw.ac.at/', MetadataCollection::SKIP, $rc->getConstant($errMode));
$repo->commit();
echo "\n######################################################\nImporting ended\n######################################################\n";

