<?php

/*
 * The MIT License
 *
 * Copyright 2025 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\arche\ingest;

use zozlak\RdfConstants as RDF;
use rdfInterface\QuadInterface;
use rdfInterface\DatasetNodeInterface;
use termTemplates\AnyOfTemplate;
use termTemplates\PredicateTemplate as PT;
use quickRdf\DataFactory as DF;
use acdhOeaw\arche\lib\Schema;
use acdhOeaw\arche\lib\RepoResource;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\ingest\util\UUID;

/**
 * An implementation of an ACDH-CH schema-compliant automatic metadata 
 * versioning for the arche-lib-ingest
 *
 * @author zozlak
 */
class IndexerVersioner {

    const PID_CREATE_VALUE = 'create';

    /**
     * 
     * @return array{0: DatasetNodeInterface, 1: DatasetNodeInterface}
     */
    static public function versionMetadata(DatasetNodeInterface $oldMeta,
                                           Schema $schema): array {
        $repoIdNmsp = preg_replace('`/[0-9]+$`', '', (string) $oldMeta->getNode());
        $skipProp   = [$schema->id, $schema->pid, $schema->cmdiPid];

        $newMeta = $oldMeta->copyExcept(new PT(new AnyOfTemplate($skipProp)));
        $newMeta->add(DF::quadNoSubject($schema->isNewVersionOf, $oldMeta->getNode()));
        // migrate all ids but the one in the repo namespace and pids
        foreach (iterator_to_array($oldMeta->getIterator(new PT($schema->id))) as $quad) {
            $id = (string) $quad->getObject();
            if (!str_starts_with($id, $repoIdNmsp) && $oldMeta->none($quad->withPredicate($schema->pid)) && $oldMeta->none($quad->withPredicate($schema->cmdiPid))) {
                $newMeta->add($quad);
                $oldMeta->delete($quad);
            }
        };
        // there is at least one non-internal id required; as all are passed to the new resource, let's create a dummy one
        $oldMeta->add(DF::quadNoSubject($schema->id, DF::namedNode($schema->namespaces->vid . UUID::v4())));
        // change class
        $oldMeta->forEach(fn(QuadInterface $q) => $q->withObject($schema->classes->oldResource), new PT(RDF::RDF_TYPE));
        // switch parent property to old parent property
        /** @phpstan-ignore property.notFound */
        $oldMeta->forEach(fn(QuadInterface $q) => $q->withPredicate($schema->oldParent), new PT($schema->parent));
        // remove hasNextItem
        $oldMeta->delete(new PT($schema->nextItem));
        // remove old resource from all oai-pmh sets
        /** @phpstan-ignore property.notFound */
        $oldMeta->delete(new PT($schema->oaipmhSet));
        // link to the previous version
        $newMeta->delete(new PT($schema->isNewVersionOf));
        $newMeta->add(DF::quadNoSubject($schema->isNewVersionOf, $oldMeta->getNode()));
        // hadle version numbers
        $versionTmpl = new PT($schema->version);
        $version     = $oldMeta->getObjectValue($versionTmpl);
        if (empty($version)) {
            $oldMeta->add(DF::quadNoSubject($schema->version, DF::literal('1')));
            $version = '2';
        } else {
            $newMeta->delete($versionTmpl);
            $version = max(2, ((int) $version) + 1);
        }
        $newMeta->add(DF::quadNoSubject($schema->version, DF::literal((string) $version)));
        // trigger new PID generation if the old resource contains a PID
        if ($oldMeta->any(new PT($schema->pid))) {
            $newMeta->add(DF::quadNoSubject($schema->pid, DF::literal(self::PID_CREATE_VALUE)));
        }

        return [$oldMeta, $newMeta];
    }

    static public function updateReferences(RepoResource $oldRes,
                                            RepoResource $newRes): void {
        $newUri = $newRes->getUri();
        $oldUri = $oldRes->getUri();
        $oldId  = (int) preg_replace('`^.*/`', '', $oldUri);

        $repo              = $oldRes->getRepo();
        $cfg               = new SearchConfig();
        $cfg->metadataMode = RepoResource::META_RESOURCE;
        $query             = "SELECT id FROM relations WHERE target_id = ?";
        $refResources      = $repo->getResourcesBySqlQuery($query, [$oldId], $cfg);
        foreach ($refResources as $res) {
            /** @var RepoResource $res */
            if ($res->getUri()->equals($newUri)) {
                continue;
            }
            $meta = $res->getGraph();
            $meta->forEach(fn(QuadInterface $q) => $q->getObject()->equals($oldUri) ? $q->withObject($newUri) : $q);
            $res->setGraph($meta);
            $res->updateMetadata(RepoResource::UPDATE_MERGE, RepoResource::META_NONE);
        }
    }
}
