#!/usr/bin/php
<?php

// This script removes a given resource/collection
// config
$resourceId        = 'https://id.acdh.oeaw.ac.at/resourceIwantToDelete';
$recursively       = false; // should collection children be removed as well (doesn't count if you remember a binary resource)
$removeTombstone   = true;  // should tombstone be removed as well
$removeReferences  = false; // should metadata references to removed resource(s) be removed as well (when `false` and such references exist, the removal will fail)
// advanced config (generally shouldn't need adjustments)
$configLocation    = '/ARCHE/config.yaml';
$composerLocation  = '/ARCHE'; // directory where you run "composer update"; if doesn't exist, the script's directory will be used instead
$recursiveProperty = null;     // RDF property used for recursive deletion (if null repository's parent property is used)
$runComposerUpdate = true;     // should `composer update` be run in $composerLocation dir (makes ingestion initialization longer but releases us from remembering about running `composer update` by hand)
// NO CHANGES NEEDED BELOW THIS LINE

$composerLocation = file_exists($composerLocation) ? $composerLocation : (getenv('COMPOSER_DIR') ?: __DIR__);
if ($runComposerUpdate && count($argv) < 2) {
    echo "\n######################################################\nUpdating libraries\n######################################################\n";
    exec('cd ' . escapeshellarg($composerLocation) . ' && composer update --no-dev');
    echo "\n######################################################\nUpdate ended\n######################################################\n\n";
}

use acdhOeaw\arche\lib\Repo;
use zozlak\argparse\ArgumentParser;

require_once "$composerLocation/vendor/autoload.php";

if (count($argv) > 1) {
    $parser            = new ArgumentParser();
    $parser->addArgument('--recursively', action: ArgumentParser::ACTION_STORE_TRUE, default: false);
    $parser->addArgument('--recursiveProperty', default: '');
    $parser->addArgument('--tombstone', action: ArgumentParser::ACTION_STORE_TRUE, default: false);
    $parser->addArgument('--references', action: ArgumentParser::ACTION_STORE_TRUE, default: false);
    $parser->addArgument('resourceId');
    $parser->addArgument('repoUrl');
    $parser->addArgument('user');
    $parser->addArgument('password');
    $args              = $parser->parseArgs();
    $recursively       = $args->recursively;
    $recursiveProperty = $args->recursiveProperty;
    $removeTombstone   = $args->tombstone;
    $removeReferences  = $args->references;
    $resourceId        = $args->resourceId;
    $auth              = [$args->user, $args->password];
    $repo              = Repo::factoryFromUrl($args->repoUrl, ['auth' => $auth]);
} else {
    $repo    = Repo::factoryInteractive(empty($configLocation) ? null : $configLocation);
}
$recursiveProperty = empty($recursiveProperty) ? $repo->getSchema()->parent : $recursiveProperty;
$recursiveProperty = $recursively ? $recursiveProperty : '';

$res = $repo->getResourceById($resourceId);
echo "\n######################################################\nDeleting\n######################################################\n";
$repo->begin();
echo "deleting " . $res->getUri() . " tombstone: " . (int) $removeTombstone . " references: " . (int) $removeReferences . " recusively following: $recursiveProperty\n";
$res->delete($removeTombstone, $removeReferences, $recursiveProperty);
$repo->commit();
echo "\n######################################################\nDeleted\n######################################################\n";

