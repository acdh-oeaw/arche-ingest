#!/usr/bin/php
<?php

// This script removes metadata properties without deleting repository resources
// config
$rdfLocation = 'delete_metadata_sample.ttl';

// advanced config (generally shouldn't need adjustments)
$configLocation    = '/ARCHE/config.yaml';
$composerLocation  = '/ARCHE'; // directory where you run "composer update"; if doesn't exist, the script's directory will be used instead
$runComposerUpdate = true;     // should `composer update` be run in $composerLocation dir (makes ingestion initialization longer but releases us from remembering about running `composer update` by hand)
$verbose           = true;     // should output be verbose? 'true' is generally better :)
// NO CHANGES NEEDED BELOW THIS LINE

$composerLocation = getenv('COMPOSER_DIR') ?: (file_exists($composerLocation) ? $composerLocation : __DIR__);
if ($runComposerUpdate && count($argv) < 2) {
    echo "\n######################################################\nUpdating libraries\n######################################################\n";
    exec('cd ' . escapeshellarg($composerLocation) . ' && composer update --no-dev');
    echo "\n######################################################\nUpdate ended\n######################################################\n\n";
}

use rdfInterface\LiteralInterface;
use quickRdf\Dataset;
use quickRdf\DataFactory;
use quickRdfIo\Util as RdfIoUtil;
use termTemplates\QuadTemplate as QT;
use acdhOeaw\arche\lib\Repo;
use acdhOeaw\arche\lib\RepoResource;
use acdhOeaw\arche\lib\exception\ExceptionUtil;
use zozlak\argparse\ArgumentParser;

require_once "$composerLocation/vendor/autoload.php";

if (count($argv) > 1) {
    $parser      = new ArgumentParser();
    $parser->addArgument('--silent', action: ArgumentParser::ACTION_STORE_TRUE, default: false);
    $parser->addArgument('rdfFile');
    $parser->addArgument('repoUrl');
    $parser->addArgument('user');
    $parser->addArgument('password');
    $args        = $parser->parseArgs();
    $verbose     = !$args->silent;
    $rdfLocation = $args->rdfFile;
    $auth        = [$args->user, $args->password];
    $repo        = Repo::factoryFromUrl($args->repoUrl, ['auth' => $auth]);
} else {
    $repo = Repo::factoryInteractive(empty($configLocation) ? null : $configLocation);
}

$graph = new Dataset();
$graph->add(RdfIoUtil::parse($rdfLocation, new DataFactory()));

$idProp = $repo->getSchema()->id;
foreach ($graph->listSubjects() as $r) {
    echo "Removing metadata from $r\n";
    $repo->begin();
    try {
        $res  = $repo->getResourceById((string) $r);
        $meta = $res->getMetadata();
        echo $meta . "-----\n";
        foreach ($graph->getIterator(new QT($r)) as $triple) {
            $p      = $triple->getPredicate();
            $v      = $triple->getObject();
            $dtLang = '';
            if ($v instanceof LiteralInterface) {
                $dtLang = empty($v->getLang()) ? '^^' . $v->getDatatype() : '@' . $v->getLang();
            } elseif (!$idProp->equals($p)) {
                $dr     = $repo->getResourceById((string) $v);
                $v      = DataFactory::namedNode($dr->getUri());
                $triple = $triple->withObject($v);
            }
            $triple = $triple->withSubject($res->getUri());
            echo "\tremoving $p " . (string) $v . $dtLang . "\n";
            unset($meta[$triple]);
        }
        echo $meta . "-----\n";
        $res->setMetadata($meta);
        $res->updateMetadata(RepoResource::UPDATE_OVERWRITE);

        $repo->commit();
    } catch (Exception $e) {
        echo ExceptionUtil::unwrap($e, $verbose);
        $repo->rollback();
    }
}
