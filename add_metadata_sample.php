<?php
// This script adds metadata triples to resources preserving all already existing triples

// config
$ttlFile = 'add_metadata_sample.ttl';

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

use \EasyRdf\Graph;
use \acdhOeaw\acdhRepoLib\Repo;
use \acdhOeaw\acdhRepoLib\RepoResource;
require_once $composerLocation . '/vendor/autoload.php';

$cfg     = json_decode(json_encode(yaml_parse_file($configLocation)));
$graph   = new Graph();
$graph->parseFile($ttlFile);
$repo    = Repo::factoryInteractive($configLocation);

foreach ($graph->resources() as $r) {
    if (count($r->propertyUris()) > 0) {
        echo "Adding metadata to " . $r->getUri() . "\n";
        $repo->begin();
        try {
            $res = $repo->getResourceById($r->getUri());
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

