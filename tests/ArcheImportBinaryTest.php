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

namespace acdhOeaw\arche\ingest\tests;

use zozlak\RdfConstants as RDF;
use quickRdf\DataFactory as DF;
use termTemplates\PredicateTemplate as PT;
use termTemplates\ValueTemplate as VT;
use acdhOeaw\arche\lib\Repo;
use acdhOeaw\arche\lib\Schema;
use acdhOeaw\arche\lib\RepoResource;
use acdhOeaw\arche\lib\SearchTerm;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\exception\NotFound;

/**
 * Description of IndexerTest
 *
 * @author zozlak
 */
class ArcheImportBinaryTest extends \PHPUnit\Framework\TestCase {

    const ACDHI = 'https://id.acdh.oeaw.ac.at/';

    static private Repo $repo;
    static private Schema $schema;

    static public function setUpBeforeClass(): void {
        $guzzleOpts   = [
            'headers' => ['eppn' => 'admin']
        ];
        self::$repo   = Repo::factoryFromUrl('http://127.0.0.1/api/', $guzzleOpts);
        self::$schema = self::$repo->getSchema();
    }

    static public function tearDownAfterClass(): void {
        
    }

    public function setUp(): void {
        
    }

    public function tearDown(): void {
        
    }

    /**
     * @group indexer
     */
    public function testVersioning(): void {
        $toDel = [self::ACDHI . 'res3', self::ACDHI . 'res2', self::ACDHI . 'res1',
            self::ACDHI . 'vid/%', self::ACDHI . 'topcol'];
        $this->removeResources($toDel);
        //TODO: setup
        //- turn of named entity checks

        $argv = ['', __DIR__ . '/data/meta.ttl', 'http://127.0.0.1/api/', 'admin',
            'admin'];
        require __DIR__ . '/../import_metadata_sample.php';

        $argv = ['', '--versioning', 'digest', __DIR__ . '/data/v1', self::ACDHI,
            self::$repo->getBaseUrl(), 'admin', 'admin'];
        require __DIR__ . '/../import_binary_sample.php';

        $argv = ['', '--versioning', 'digest', __DIR__ . '/data/v2', self::ACDHI,
            self::$repo->getBaseUrl(),
            'admin', 'admin'];
        require __DIR__ . '/../import_binary_sample.php';

        $newRes  = self::$repo->getResourceById(self::ACDHI . 'file.txt');
        $newMeta = $newRes->getGraph();
        $oldUrl  = $newMeta->getObjectValue(new PT(self::$schema->isNewVersionOf));
        $this->assertNotEmpty($oldUrl);
        $oldMeta = (new RepoResource($oldUrl, self::$repo))->getGraph();

        // classes
        $classTmpl = new PT(DF::namedNode(RDF::RDF_TYPE));
        $this->assertTrue(self::$schema->classes->resource->equals($newMeta->getObject($classTmpl)));
        $this->assertTrue(self::$schema->classes->oldResource->equals($oldMeta->getObject($classTmpl)));

        // ids in the ACDHI namespaces
        $id1Tmpl = new PT(self::$schema->id, DF::namedNode(self::ACDHI . 'file.txt'));
        $id2Tmpl = new PT(self::$schema->id, DF::namedNode(self::ACDHI . 'res2'));
        $this->assertTrue($oldMeta->none($id1Tmpl));
        $this->assertTrue($oldMeta->none($id2Tmpl));
        $this->assertTrue($newMeta->any($id1Tmpl));
        $this->assertTrue($newMeta->any($id2Tmpl));

        // VID id for the old version
        $vidTmpl = new PT(self::$schema->id, new VT('`^' . self::ACDHI . 'vid/`', VT::REGEX));
        $this->assertEquals(1, count($oldMeta->copy($vidTmpl)));
        $this->assertTrue($newMeta->none($vidTmpl));

        // PID
        $pidTmpl   = new PT(self::$schema->pid);
        $pidIdTmpl = new PT(self::$schema->id, DF::namedNode('http://pid'));
        $this->assertTrue(DF::literal('http://pid', datatype: RDF::XSD_ANY_URI)->equals($oldMeta->getObject($pidTmpl)));
        $this->assertTrue($oldMeta->any($pidIdTmpl));
        // TODO - find a way to test creation of the PID for the new resource
        $this->assertTrue(DF::literal('create', datatype: RDF::XSD_ANY_URI)->equals($newMeta->getObject($pidTmpl)));
        $this->assertTrue($newMeta->none($pidIdTmpl));

        // CMDI PID
        $pidTmpl   = new PT(self::$schema->cmdiPid);
        $pidIdTmpl = new PT(self::$schema->id, DF::namedNode('http://cmdi.pid'));
        $this->assertEquals('http://cmdi.pid', $oldMeta->getObjectValue($pidTmpl));
        // TODO - find a way to test creation of the PID for the new resource
        $this->assertTrue($newMeta->none($pidTmpl));
        $this->assertTrue($newMeta->none($pidIdTmpl));

        // parent and oldParent
        $parentTmpl    = new PT(self::$schema->parent);
        $oldParentTmpl = new PT(self::$schema->oldParent);
        $this->assertTrue($oldMeta->none($parentTmpl));
        $this->assertTrue($oldMeta->getObject($oldParentTmpl)->equals($newMeta->getObject($parentTmpl)));
        $this->assertTrue($newMeta->none($oldParentTmpl));

        // version
        $versionTmpl = new PT(self::$schema->version);
        $this->assertTrue(DF::literal('1')->equals($oldMeta->getObject($versionTmpl)));
        $this->assertTrue(DF::literal('2')->equals($newMeta->getObject($versionTmpl)));

        // oai-pmh set
        $oaiTmpl = new PT(self::$schema->oaipmhSet);
        $this->assertTrue($oldMeta->none($oaiTmpl));
        $oaiRes  = self::$repo->getResourceById('https://vocabs.acdh.oeaw.ac.at/archeoaisets/ariadne');
        $this->assertTrue($oaiRes->getUri()->equals($newMeta->getObject($oaiTmpl)));

        // nextItem
        $nextTmpl = new PT(self::$schema->nextItem);
        $prevMeta = self::$repo->getResourceById(self::ACDHI . 'res1')->getGraph();
        $nextRes  = self::$repo->getResourceById(self::ACDHI . 'res3')->getUri();
        $this->assertTrue($oldMeta->none($nextTmpl));
        $this->assertTrue($nextRes->equals($newMeta->getObject($nextTmpl)));
        $this->assertTrue($newRes->getUri()->equals($prevMeta->getObject($nextTmpl)));
    }

    /**
     * @param array<string> $ids
     */
    private function removeResources(array $ids): void {
        $config = new SearchConfig();
        $query  = "SELECT id FROM identifiers WHERE ids LIKE ?";
        self::$repo->begin();
        foreach ($ids as $id) {
            try {
                foreach (self::$repo->getResourcesBySqlQuery($query, [$id], $config) as $res) {
                    $res->delete(true, true);
                }
            } catch (NotFound $ex) {
                
            }
        }
        self::$repo->commit();
    }
}
