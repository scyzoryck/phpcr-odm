<?php

namespace Doctrine\Tests\ODM\PHPCR\Functional;

use Doctrine\ODM\PHPCR\DocumentManager;
use Doctrine\ODM\PHPCR\Mapping\Annotations as PHPCRODM;
use Doctrine\Tests\Models\References\RefType1TestObj;
use Doctrine\Tests\Models\References\RefType2TestObj;
use Doctrine\Tests\ODM\PHPCR\PHPCRFunctionalTestCase;

/**
 * @group functional
 */
class TargetDocumentTest extends PHPCRFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    public function setUp(): void
    {
        $this->dm = $this->createDocumentManager();
        $this->resetFunctionalNode($this->dm);
    }

    public function testReferenceManyDifferentTargetDocuments(): void
    {
        $ref1 = new RefType1TestObj();
        $ref1->id = '/functional/ref1';
        $ref1->name = 'Ref1';
        $ref2 = new RefType2TestObj();
        $ref2->id = '/functional/ref2';
        $ref2->name = 'Ref2';

        $referer = new ReferenceManyObj();
        $referer->id = '/functional/referer';
        $referer->name = 'Referer';
        $referer->references[] = $ref1;
        $referer->references[] = $ref2;

        $this->dm->persist($referer);
        $this->dm->flush();

        $this->dm->clear();
        $referer = $this->dm->find(ReferenceManyObj::class, '/functional/referer');
        $this->assertEquals('Referer', $referer->name);
        $this->assertCount(2, $referer->references);
        $this->assertInstanceOf(RefType1TestObj::class, $referer->references[0]);
        $this->assertInstanceOf(RefType2TestObj::class, $referer->references[1]);
    }

    public function testReferenceOneDifferentTargetDocuments(): void
    {
        $ref1 = new RefType1TestObj();
        $ref1->id = '/functional/ref1';
        $ref1->name = 'Ref1';
        $ref2 = new RefType2TestObj();
        $ref2->id = '/functional/ref2';
        $ref2->name = 'Ref2';

        $this->dm->persist($ref1);
        $this->dm->persist($ref2);

        $referer1 = new ReferenceOneObj();
        $referer1->id = '/functional/referer1';
        $referer1->reference = $ref1;
        $this->dm->persist($referer1);

        $referer2 = new ReferenceOneObj();
        $referer2->id = '/functional/referer2';
        $referer2->reference = $ref2;
        $this->dm->persist($referer2);

        $this->dm->flush();
        $this->dm->clear();

        $referer = $this->dm->find(ReferenceOneObj::class, '/functional/referer1');
        $this->assertInstanceOf(RefType1TestObj::class, $referer->reference);
        $referer = $this->dm->find(ReferenceOneObj::class, '/functional/referer2');
        $this->assertInstanceOf(RefType2TestObj::class, $referer->reference);
    }
}

/**
 * @PHPCRODM\Document()
 */
class ReferenceManyObj
{
    /** @PHPCRODM\Id */
    public $id;

    /** @PHPCRODM\Field(type="string") */
    public $name;

    /** @PHPCRODM\ReferenceMany(cascade="persist") */
    public $references;
}

/**
 * @PHPCRODM\Document()
 */
class ReferenceOneObj
{
    /** @PHPCRODM\Id */
    public $id;

    /** @PHPCRODM\Field(type="string", nullable=true) */
    public $name;

    /** @PHPCRODM\ReferenceOne(cascade="persist") */
    public $reference;
}
