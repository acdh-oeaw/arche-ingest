#!/usr/bin/php
<?php

// This script adds metadata triples to resources preserving all already existing triples
// config
$ttlFile = 'add_metadata_sample.ttl';

// advanced config (generally shouldn't need adjustments)
$configLocation    = '/ARCHE/config.yaml';
$composerLocation  = '/ARCHE'; // directory where you run "composer update"; if doesn't exist, the script's directory will be used instead
$runComposerUpdate = true;     // should `composer update` be run in $composerLocation dir (makes ingestion initialization longer but releases us from remembering about running `composer update` by hand)
// NO CHANGES NEEDED BELOW THIS LINE

$composerLocation = file_exists($composerLocation) ? $composerLocation : __DIR__;
if ($runComposerUpdate && count($argv) < 2) {
    echo "\n######################################################\nUpdating libraries\n######################################################\n";
    exec('cd ' . escapeshellarg($composerLocation) . ' && composer update --no-dev');
    echo "\n######################################################\nUpdate ended\n######################################################\n\n";
}

use EasyRdf\Graph;
use acdhOeaw\arche\lib\Repo;
use acdhOeaw\arche\lib\RepoResource;
require_once "$composerLocation/vendor/autoload.php";

$graph = new Graph();
$graph->parseFile($ttlFile);
$repo  = Repo::factoryInteractive(empty($configLocation) ? null : $configLocation);

foreach ($graph->resources() as $r) {
    if (count($r->propertyUris()) > 0) {
        echo "Adding metadata to " . $r->getUri() . "\n";
        $repo->begin();
        try {
            $res  = $repo->getResourceById($r->getUri());
            $meta = $res->getMetadata();
            foreach ($r->propertyUris() as $p) {
                foreach ($r->all($p) as $v) {
                    $meta->add($p, $v);
                }
            }
            $res->setMetadata($meta);
            $res->updateMetadata(RepoResource::UPDATE_OVERWRITE);

            $repo->commit();
        } catch (Exception $e) {
            echo "\t" . $e->getMessage() . "\n";
            $repo->rollback();
        }
    }
}

