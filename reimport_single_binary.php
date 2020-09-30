<?php
// This script reingests a single resource's binary content (to be used when file name and/or location changed)

// identifier of the resource which binary will be updated
$resourceId = 'RESOURCE_ID';
// path to the file which will overwrite repository resource's binary
$filePath   = 'FILE_PATH';

// advanced config (generally shouldn't need adjustments)
$mimeType          = 'application/php'; // mime type of the binary (when null, it will be guesed from the file content)
$configLocation    = '/ARCHE/config.yaml';
$composerLocation  = '/ARCHE'; // directory where you run "composer update"
$configLocation    = '/home/zozlak/roboty/ACDH/repo/arche-lib-ingest/tests/config.yaml';
$composerLocation  = '/home/zozlak/roboty/ACDH/repo/arche-lib-ingest'; // directory where you run "composer update"
$runComposerUpdate = true;     // should `composer update` be run in $composerLocation dir (makes ingestion initialization longer but releases us from remembering about running `composer update` by hand)

// NO CHANGES NEEDED BELOW THIS LINE

if ($runComposerUpdate) {
    echo "\n######################################################\nUpdating libraries\n######################################################\n";
    system('cd ' . escapeshellarg($composerLocation) . ' && composer update --no-dev');
    echo "\n######################################################\nUpdate ended\n######################################################\n\n";
}

use \acdhOeaw\acdhRepoLib\Repo;
use \acdhOeaw\acdhRepoLib\BinaryPayload;
require_once $composerLocation . '/vendor/autoload.php';

$repo = Repo::factoryInteractive($configLocation);
$res  = $repo->getResourceById($resourceId);
$repo->begin();
$res->updateContent(new BinaryPayload(null, $filePath, $mimeType));
$repo->commit();

echo "\n######################################################\nImport finished \n######################################################\n";

