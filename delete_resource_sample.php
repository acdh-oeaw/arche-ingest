<?php
// This script removes a given resource/collection

// config
$resourceId = 'https://id.acdh.oeaw.ac.at/resourceIwantToDelete';
$recursively = false; // should collection children be removed as well (doesn't count if you remember a binary resource)
$removeReferences = false; // should metadata references to removed resource(s) be removed as well (when `false` and such references exist, the removal will fail)

// advanced config (generally shouldn't need adjustments)
$configLocation    = '/ARCHE/config.yaml';
$composerLocation  = '/ARCHE'; // directory where you run "composer update"
$runComposerUpdate = true;     // should `composer update` be run in $composerLocation dir (makes ingestion initialization longer but releases us from remembering about running `composer update` by hand)

// NO CHANGES NEEDED BELOW THIS LINE

if ($runComposerUpdate) {
    echo "\n######################################################\nUpdating libraries\n######################################################\n";
    system('cd ' . escapeshellarg($composerLocation) . ' && composer update --no-dev');
    echo "\n######################################################\nUpdate ended\n######################################################\n\n";
}

use \acdhOeaw\arche\lib\Repo;
require_once $composerLocation . '/vendor/autoload.php';

$cfg     = json_decode(json_encode(yaml_parse_file($configLocation)));
$repo    = Repo::factoryInteractive($configLocation);

$res = $repo->getResourceById($resourceId);
$repo->begin();
echo "\n######################################################\nDeleting\n######################################################\n";
if ($recursively) {
    $res->deleteRecursively($cfg->schema->parent, true, $removeReferences);
} else {
    $res->delete(true, $removeReferences);
}
$repo->commit();

