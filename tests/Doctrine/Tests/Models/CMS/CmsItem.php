<?php

namespace Doctrine\Tests\Models\CMS;

use Doctrine\ODM\PHPCR\DocumentRepository;
use Doctrine\ODM\PHPCR\Id\RepositoryIdInterface;
use Doctrine\ODM\PHPCR\Mapping\Annotations as PHPCRODM;

/**
 * @PHPCRODM\Document(repositoryClass="Doctrine\Tests\Models\CMS\CmsItemRepository", referenceable=true)
 */
class CmsItem
{
    /** @PHPCRODM\Id(strategy="repository") */
    public $id;

    /** @PHPCRODM\Node */
    public $node;

    /** @PHPCRODM\Field(type="string") */
    public $name;

    /** @PHPCRODM\ReferenceOne(strategy="hard", cascade="persist") */
    public $documentTarget;

    public function getId()
    {
        return $this->id;
    }

    public function setDocumentTarget($documentTarget)
    {
        $this->documentTarget = $documentTarget;

        return $this;
    }

    public function getDocumentTarget()
    {
        return $this->documentTarget;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }
}

class CmsItemRepository extends DocumentRepository implements RepositoryIdInterface
{
    public function generateId(object $document, object $parent = null): string
    {
        return '/functional/'.$document->name;
    }
}
