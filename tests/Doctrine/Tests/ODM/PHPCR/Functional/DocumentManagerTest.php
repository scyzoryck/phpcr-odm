<?php

namespace Doctrine\Tests\ODM\PHPCR\Functional;

use Doctrine\ODM\PHPCR\DocumentManager;
use Doctrine\ODM\PHPCR\Mapping\Annotations as PHPCRODM;
use Doctrine\Tests\ODM\PHPCR\PHPCRFunctionalTestCase;
use PHPCR\Util\UUIDHelper;

class DocumentManagerTest extends PHPCRFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    public function setUp(): void
    {
        $this->dm = $this->createDocumentManager([__DIR__]);
        $this->resetFunctionalNode($this->dm);
    }

    public function testFindManyWithNonExistingUuuid(): void
    {
        $user = new TestUser();
        $user->username = 'test-name';
        $user->id = '/functional/test';
        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();

        $actualUuid = $user->uuid;
        $unusedUuid = UUIDHelper::generateUUID();

        $this->assertNotNull($this->dm->find(get_class($user), $user->id));
        $this->assertNotNull($this->dm->find(get_class($user), $actualUuid));
        $this->assertNull($this->dm->find(get_class($user), $unusedUuid));

        $uuids = [$actualUuid, $unusedUuid];

        $documents = $this->dm->findMany(get_class($user), $uuids);
        $this->assertCount(1, $documents);
    }
}

/**
 * @PHPCRODM\Document(referenceable=true)
 */
class TestUser
{
    /** @PHPCRODM\Id */
    public $id;

    /** @PHPCRODM\Node */
    public $node;

    /** @PHPCRODM\Field(type="string") */
    public $username;

    /** @PHPCRODM\Uuid */
    public $uuid;
}
