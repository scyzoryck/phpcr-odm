<?php

namespace Doctrine\ODM\PHPCR\Query\Builder;

use Doctrine\ODM\PHPCR\Exception\RuntimeException;
use Doctrine\ODM\PHPCR\Query\Builder\AbstractNode as QBConstants;
use Doctrine\ODM\PHPCR\Query\Query;
use PHPCR\Query\QOM\QueryObjectModelConstantsInterface as QOMConstants;

/**
 * The Query Builder root node.
 *
 * This is the node which is returned when a query builder is asked for::
 *
 * <code>
 * $dm = // get document manager
 * $qb = $dm->createQueryBuilder();
 * $qb->fromDocument('Blog\Post', 'p');
 * $qb->where()->eq()->field('p.title')->literal('My Post');
 * $docs = $qb->getQuery()->execute();
 * </code>
 *
 * A converter is required to be set if the purpose of the query builder
 * is to be fulfilled. The PHPCR converter walks over the query builder node
 * hierarchy and converts the object graph the PHPCR QOM object graph.
 *
 * @IgnoreException('factoryMethod')
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class QueryBuilder extends AbstractNode
{
    protected $converter;

    protected $firstResult;

    protected $maxResults;

    protected $locale;

    protected $primaryAlias;

    /**
     * @return string the locale for this query
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set the locale to use in this query.
     *
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * This is an NT_BUILDER
     */
    public function getNodeType()
    {
        return self::NT_BUILDER;
    }

    /**
     * @return Query
     */
    public function getQuery()
    {
        return $this->getConverter()->getQuery($this);
    }

    public function setConverter(ConverterInterface $converter)
    {
        $this->converter = $converter;
    }

    /**
     * @return ConverterInterface
     */
    protected function getConverter()
    {
        if (!$this->converter) {
            throw new RuntimeException('No query converter has been set on Builder node.');
        }

        return $this->converter;
    }

    public function getCardinalityMap()
    {
        return [
            self::NT_SELECT => [0, null],    // 1..*
            self::NT_FROM => [1, 1],         // 1..1
            self::NT_WHERE => [0, 1],        // 0..1
            self::NT_ORDER_BY => [0, null],  // 0..*
        ];
    }

    /**
     * Where factory node is used to specify selection criteria::.
     *
     * <code>
     * $qb->where()
     *   ->eq()
     *     ->field('a.foobar')->literal('bar')->end()
     *   ->end()
     * ->end();
     * </code>
     *
     * @factoryMethod Where
     *
     * @return Where
     */
    public function where($void = null)
    {
        $this->ensureNoArguments(__METHOD__, $void);

        return $this->setChild(new Where($this));
    }

    /**
     * Add additional selection criteria using the AND operator.
     *
     * @factoryMethod WhereAnd
     *
     * @return WhereAnd
     */
    public function andWhere($void = null)
    {
        $this->ensureNoArguments(__METHOD__, $void);

        return $this->addChild(new WhereAnd($this));
    }

    /**
     * Add additional selection criteria using the OR operator.
     *
     * @factoryMethod WhereOr
     *
     * @return WhereOr
     */
    public function orWhere($void = null)
    {
        $this->ensureNoArguments(__METHOD__, $void);

        return $this->addChild(new WhereOr($this));
    }

    /**
     * Set the from source for the query::.
     *
     * <code>
     * $qb->from()->document('Foobar', 'a');
     *
     * // or with a join ...
     *
     * $qb->from('a')->joinInner()
     *   ->left()->document('Foobar', 'a')->end()
     *   ->right()->document('Barfoo', 'b')->end()
     *   ->condition()->equi('a.prop_1', 'b.prop_1')->end()
     * ->end();
     * </code>
     *
     * @param string $primaryAlias - Alias to use as primary source (optional for single sources)
     *
     * @factoryMethod From
     *
     * @return From
     */
    public function from($primaryAlias = null)
    {
        $this->primaryAlias = $primaryAlias;

        return $this->setChild(new From($this));
    }

    /**
     * Shortcut for::.
     *
     * <code>
     * $qb->from()
     *   ->document('Foobar', 'a')->end()
     * ->end();
     * </code>
     *
     * Which becomes::
     *
     * <code>
     * $qb->fromDocument('Foobar', 'a')->end();
     * </code>
     *
     * Replaces any existing from source.
     *
     * @param string $documentFqn  - Fully qualified class name for document
     * @param string $primaryAlias - Alias for document source and primary alias when using multiple sources
     *
     * @factoryMethod From
     *
     * @return QueryBuilder
     */
    public function fromDocument($documentFqn, $primaryAlias)
    {
        $this->primaryAlias = $primaryAlias;

        $from = new From($this);
        $from->document($documentFqn, $primaryAlias);
        $this->setChild($from);

        return $from->end();
    }

    /**
     * This method is currently private in accordance with the rule that
     * factory methods should have no arguments (thus it is easier to determine
     * which nodes are leaf nodes).
     */
    private function addJoin($joinType)
    {
        $from = $this->getChildOfType(QBConstants::NT_FROM);
        $curSource = $from->getChild();

        $src = new SourceJoin($this, $joinType);
        $src->left()->addChild($curSource);
        $from->setChild($src);

        return $src;
    }

    /**
     * Replace the existing source with a left outer join source using the existing
     * source as the left operand::.
     *
     * <code>
     * $qb->fromDocument('Foobar', 'a')
     *   ->addJoinLeftOuter()
     *     ->right()->document('Barfoo', 'b')->end()
     *     ->condition()->equi('a.prop_1', 'b.prop_2')->end()
     *   ->end()
     * ->end();
     * </code>
     *
     * Note that for outer joins to work correctly, documents being joined to must be mapped with
     * a node type that is unique to the repository workspace, and the ``uniqueNodeType`` property
     * must be set to ``true`` for the document (see :ref:`<_annref_document>`). Otherwise, the join
     * will behave as an inner join.
     *
     * @factoryMethod Select
     *
     * @return SourceJoin
     */
    public function addJoinLeftOuter($void = null)
    {
        $this->ensureNoArguments(__METHOD__, $void);

        return $this->addJoin(QOMConstants::JCR_JOIN_TYPE_LEFT_OUTER);
    }

    /**
     * Replace the existing source with a right outer join source using the existing
     * source as the left operand::.
     *
     * <code>
     * $qb->fromDocument('Foobar', 'a')
     *   ->addJoinRightOuter()
     *     ->right()->document('Barfoo', 'b')->end()->end()
     *     ->condition()->equi('a.prop_1', 'b.prop_2')->end()
     *   ->end()
     * ->end();
     * </code>
     *
     * Note that for outer joins to work correctly, documents being joined to must be mapped with
     * a node type that is unique to the repository workspace, and the ``uniqueNodeType`` property
     * must be set to ``true`` for the document (see :ref:`<_annref_document>`). Otherwise, the join
     * will behave as an inner join.
     *
     * @factoryMethod Select
     *
     * @return SourceJoin
     */
    public function addJoinRightOuter($void = null)
    {
        $this->ensureNoArguments(__METHOD__, $void);

        return $this->addJoin(QOMConstants::JCR_JOIN_TYPE_RIGHT_OUTER);
    }

    /**
     * Replace the existing source with an inner join source using the existing
     * source as the left operand::.
     *
     * <code>
     * $qb->fromDocument('Foobar', 'a')
     *   ->addJoinInner()
     *     ->right()->document('Barfoo', 'b')->end()
     *     ->condition()->equi('a.prop_1', 'b.prop_2')->end()
     *   ->end()
     * ->end();
     * </code>
     *
     * Note that for outer joins to work correctly, documents being joined to must be mapped with
     * a node type that is unique to the repository workspace, and the ``uniqueNodeType`` property
     * must be set to ``true`` for the document (see :ref:`<_annref_document>`). Otherwise, the join
     * will behave as an inner join.
     *
     * @factoryMethod Select
     *
     * @return SourceJoin
     */
    public function addJoinInner($void = null)
    {
        $this->ensureNoArguments(__METHOD__, $void);

        return $this->addJoin(QOMConstants::JCR_JOIN_TYPE_INNER);
    }

    /**
     * Method to add properties for selection to builder tree, replaces any
     * existing select.
     *
     * Number of property nodes is unbounded::
     *
     * <code>
     * $qb->select()
     *   ->field('a.prop_1')
     *   ->field('a.prop_2')
     *   ->field('a.prop_3')
     * ->end();
     * </code>
     *
     * @factoryMethod Select
     *
     * @return Select
     */
    public function select($void = null)
    {
        $this->ensureNoArguments(__METHOD__, $void);

        return $this->setChild(new Select($this));
    }

    /**
     * Add additional properties to selection::.
     *
     * <code>
     * $qb->select()
     *     ->field('a.prop_1')
     *   ->end()
     *   ->addSelect()
     *     ->field('a.prop_2')
     *     ->field('a.prop_3')
     *     ->field('a.prop_4')
     *   ->end();
     * </code>
     *
     * @factoryMethod SelectAdd
     *
     * @return SelectAdd
     */
    public function addSelect($void = null)
    {
        $this->ensureNoArguments(__METHOD__, $void);

        return $this->setChild(new SelectAdd($this));
    }

    /**
     * Add orderings to the builder tree.
     *
     * Number of orderings is unbounded::
     *
     * <code>
     * $qb->orderBy()
     *   ->asc()->field('a.prop_1')->end()
     *   ->desc()->field('a.prop_2')->end()
     * ->end();
     * </code>
     *
     * @factoryMethod OrderBy
     *
     * @return OrderBy
     */
    public function orderBy($void = null)
    {
        $this->ensureNoArguments(__METHOD__, $void);

        return $this->setChild(new OrderBy($this));
    }

    /**
     * Add additional orderings to the builder tree.
     *
     * @factoryMethod OrderByAdd
     *
     * @return OrderByAdd
     */
    public function addOrderBy($void = null)
    {
        $this->ensureNoArguments(__METHOD__, $void);

        return $this->addChild(new OrderByAdd($this));
    }

    /**
     * Return the offset of the first result in the resultset.
     *
     * @return int
     */
    public function getFirstResult()
    {
        return $this->firstResult;
    }

    /**
     * Set the offset of the first result in the resultset.
     *
     * @param int $firstResult
     */
    public function setFirstResult($firstResult)
    {
        $this->firstResult = $firstResult;
    }

    /**
     * Return the maximum number of results to be imposed on the generated query.
     *
     * @return int
     */
    public function getMaxResults()
    {
        return $this->maxResults;
    }

    /**
     * Set the maximum number of results to be returned by the generated query.
     *
     * @param int $maxResults
     */
    public function setMaxResults($maxResults)
    {
        $this->maxResults = $maxResults;
    }

    /**
     * Creates an SQL2 representation of the PHPCR query built by this builder.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getQuery()->getStatement();
    }

    public function getPrimaryAlias()
    {
        return $this->primaryAlias;
    }

    /**
     * Ensure cloned query builder objects have unique instances of the converter.
     */
    public function __clone()
    {
        if (null !== $this->converter) {
            $this->converter = clone $this->converter;
        }
    }
}
