#!/usr/bin/php
<?php
// This script reingests a single resource's binary content (to be used when file name and/or location changed)

// identifier of the reingested resource
$resourceId = 'http://127.0.0.1/api/417';
// path to the file
$filePath   = 'reimport_single_binary.php';

// advanced config (generally shouldn't need adjustments)
$mimeType          = null;     // mime type of the binary (when null, it will be guesed from the file content)
$configLocation    = '/ARCHE/config.yaml';
$composerLocation  = '/ARCHE'; // directory where you run "composer update"; if doesn't exist, the script's directory will be used instead
$runComposerUpdate = true;     // should `composer update` be run in $composerLocation dir (makes ingestion initialization longer but releases us from remembering about running `composer update` by hand)
// NO CHANGES NEEDED BELOW THIS LINE

$composerLocation = file_exists($composerLocation) ? $composerLocation : (getenv('COMPOSER_DIR') ?: __DIR__);
if ($runComposerUpdate && count($argv) < 2) {
    echo "\n######################################################\nUpdating libraries\n######################################################\n";
    exec('cd ' . escapeshellarg($composerLocation) . ' && composer update --no-dev');
    echo "\n######################################################\nUpdate ended\n######################################################\n\n";
}

use acdhOeaw\arche\lib\Repo;
use acdhOeaw\arche\lib\BinaryPayload;
require_once "$composerLocation/vendor/autoload.php";

$repo = Repo::factoryInteractive(empty($configLocation) ? null : $configLocation);
$res  = $repo->getResourceById($resourceId);
$repo->begin();
$res->updateContent(new BinaryPayload(null, $filePath, $mimeType));
$repo->commit();

echo "\n######################################################\nImport finished \n######################################################\n";

