<?php

namespace Doctrine\ODM\PHPCR\Query\Builder;

use Doctrine\ODM\PHPCR\Exception\BadMethodCallException;
use Doctrine\ODM\PHPCR\Exception\InvalidArgumentException;
use Doctrine\ODM\PHPCR\Exception\OutOfBoundsException;

/**
 * All QueryBuilder nodes extend this class.
 *
 * Each query builder node must declare its node type
 * (one of the NT_* constants declared below) and provide
 * a cardinality map describing how many of each type of nodes
 * are allowed to be added.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
abstract class AbstractNode
{
    public const NT_BUILDER = 'builder';

    public const NT_CONSTRAINT = 'constraint';

    public const NT_CONSTRAINT_FACTORY = 'constraint_factory';

    public const NT_FROM = 'from';

    public const NT_OPERAND_DYNAMIC = 'operand_dynamic';

    public const NT_OPERAND_DYNAMIC_FACTORY = 'operand_dynamic_factory';

    public const NT_OPERAND_STATIC = 'operand_static';

    public const NT_OPERAND_FACTORY = 'operand_static_factory';

    public const NT_ORDERING = 'ordering';

    public const NT_ORDER_BY = 'order_by';

    public const NT_PROPERTY = 'property';

    public const NT_SELECT = 'select';

    public const NT_SOURCE = 'source';

    public const NT_SOURCE_FACTORY = 'source_factory';

    public const NT_SOURCE_JOIN_CONDITION = 'source_join_condition';

    public const NT_SOURCE_JOIN_CONDITION_FACTORY = 'source_join_condition_factory';

    public const NT_SOURCE_JOIN_LEFT = 'source_join_left';

    public const NT_SOURCE_JOIN_RIGHT = 'source_join_right';

    public const NT_WHERE = 'where';

    public const NT_WHERE_AND = 'where_and';

    public const NT_WHERE_OR = 'where_or';

    protected $children = [];

    protected $parent;

    public function __construct(self $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * Return the type of node.
     *
     * Must be one of self::NT_*
     *
     * @return string
     */
    abstract public function getNodeType();

    /**
     * Return the parent of this node.
     *
     * @return AbstractNode
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Return the last part of the this classes FQN (i.e. the basename).
     *
     * <strike>This should only be used when generating exceptions</strike>
     * This is also used to determine the dispatching method -- should it be?
     *
     * @return string
     */
    final public function getName()
    {
        $refl = new \ReflectionClass($this);
        $short = $refl->getShortName();

        return $short;
    }

    /**
     * Return the cardinality map for this node.
     *
     * e.g.
     *     array(
     *         self::NT_JOIN_CONDITION => array(1, 1), // require exactly 1 join condition
     *         self::NT_SOURCE => array(2, 2), // exactly 2 sources
     *     );
     *
     * or:
     *     array(
     *         self::NT_PROPERTY => array(1, null), // require one to many Columns
     *     );
     *
     * or:
     *     array(
     *         self::NT_PROPERTY => array(null, 1), // require none to 1 properties
     *     );
     *
     * @return array
     */
    abstract public function getCardinalityMap();

    /**
     * Remove any previous children and add
     * given node via. addChild.
     *
     * @see removeChildrenOfType
     * @see addChild
     *
     * @return AbstractNode
     */
    public function setChild(self $node)
    {
        $this->removeChildrenOfType($node->getNodeType());

        return $this->addChild($node);
    }

    /**
     * Add a child to this node.
     *
     * Exception will be thrown if child node type is not
     * described in the cardinality map, or if the maxiumum
     * permitted number of nodes would be exceeded by adding
     * the given child node.
     *
     * The given node will be returned EXCEPT when the current
     * node is a leaf node, in which case we return the parent.
     *
     * @return AbstractNode
     *
     * @throws OutOfBoundsException
     */
    public function addChild(self $node)
    {
        $cardinalityMap = $this->getCardinalityMap();
        $nodeType = $node->getNodeType();

        // if proposed child node is of an invalid type
        if (!array_key_exists($nodeType, $cardinalityMap)) {
            throw new OutOfBoundsException(sprintf(
                'QueryBuilder node "%s" of type "%s" cannot be appended to "%s". '.
                'Must be one type of "%s"',
                $node->getName(),
                $nodeType,
                $this->getName(),
                implode(', ', array_keys($cardinalityMap))
            ));
        }

        $currentCardinality = array_key_exists($node->getName(), $this->children) ?
            count($this->children[$node->getName()]) : 0;

        [$min, $max] = $cardinalityMap[$nodeType];

        // if bounded and cardinality will exceed max
        if (null !== $max && $currentCardinality + 1 > $max) {
            throw new OutOfBoundsException(sprintf(
                'QueryBuilder node "%s" cannot be appended to "%s". '.
                'Number of "%s" nodes cannot exceed "%s"',
                $node->getName(),
                $this->getName(),
                $nodeType,
                $max
            ));
        }

        $this->children[$nodeType][] = $node;

        return $node->getNext();
    }

    /**
     * Return the next object in the fluid interface
     * chain. Leaf nodes always return the parent, deafult
     * behavior is to return /this/ class.
     *
     * @return AbstractNode
     */
    public function getNext()
    {
        return $this;
    }

    /**
     * Return all child nodes.
     *
     * Note that this will returned a flattened version
     * of the classes type => children map.
     *
     * @return AbstractNode[]
     */
    public function getChildren()
    {
        $children = [];
        foreach ($this->children as $type) {
            foreach ($type as $child) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * Return children of specified type.
     *
     * @param string $type the type name
     *
     * @return AbstractNode[]
     */
    public function getChildrenOfType($type)
    {
        return $this->children[$type] ?? [];
    }

    public function removeChildrenOfType($type)
    {
        unset($this->children[$type]);
    }

    /**
     * Return child of node, there must be exactly one child of any type.
     *
     * @return AbstractNode
     *
     * @throws OutOfBoundsException if there are more than one or none
     */
    public function getChild()
    {
        $children = $this->getChildren();

        if (!$children) {
            throw new OutOfBoundsException(sprintf(
                'Expected exactly one child, got "%s"',
                count($children)
            ));
        }

        if (count($children) > 1) {
            throw new OutOfBoundsException(sprintf(
                'More than one child node but getChild will only ever return one. "%d" returned.',
                count($children)
            ));
        }

        return current($children);
    }

    /**
     * Return child of specified type.
     *
     * Note: This does not take inheritance into account.
     *
     * @param string $type the name of the type
     *
     * @return AbstractNode
     *
     * @throws OutOfBoundsException if there are more than one or none
     */
    public function getChildOfType($type)
    {
        $children = $this->getChildrenOfType($type);

        if (!$children) {
            throw new OutOfBoundsException(sprintf(
                'Expected exactly one child of type "%s", got "%s"',
                $type,
                count($children)
            ));
        }

        if (count($children) > 1) {
            throw new OutOfBoundsException(sprintf(
                'More than one node of type "%s" but getChildOfType will only ever return one.',
                $type
            ));
        }

        return current($children);
    }

    /**
     * Validate the current node.
     *
     * Validation is performed both when the query is being
     * built and when the dev explicitly calls "end()".
     *
     * This method simply checks the minimum boundries are satisfied,
     * the addChild() method already validates maximum boundries and
     * types.
     *
     * @throws OutOfBoundsException
     */
    public function validate()
    {
        $cardinalityMap = $this->getCardinalityMap();
        $typeCount = [];

        foreach (array_keys($cardinalityMap) as $type) {
            $typeCount[$type] = 0;
        }

        foreach ($this->children as $type => $children) {
            $typeCount[$type] += count($children);
        }

        foreach ($typeCount as $type => $count) {
            [$min, $max] = $cardinalityMap[$type];
            if (null !== $min && $count < $min) {
                throw new OutOfBoundsException(sprintf(
                    'QueryBuilder node "%s" must have at least "%s" '.
                    'child nodes of type "%s". "%s" given.',
                    $this->getName(),
                    $min,
                    $type,
                    $count
                ));
            }
        }
    }

    /**
     * Validates this node and returns its parent.
     * This should be used if the developer wants to
     * define the entire Query in a fluid manner.
     *
     * @return AbstractNode
     */
    public function end()
    {
        $this->validate();

        return $this->parent;
    }

    /**
     * Catch any undefined method calls and tell the developer what
     * methods can be called.
     *
     * @param string $methodName the requested nonexistent method
     * @param array  $args       the arguments that where used
     *
     * @throws BadMethodCallException if an unknown method is called
     */
    public function __call($methodName, $args)
    {
        throw new BadMethodCallException(sprintf(
            'Unknown method "%s" called on node "%s", did you mean one of: "%s"',
            $methodName,
            $this->getName(),
            implode(', ', $this->getFactoryMethods())
        ));
    }

    public function ensureNoArguments($method, $void)
    {
        if ($void) {
            throw new InvalidArgumentException(sprintf(
                'Method "%s" is a factory method and accepts no arguments',
                $method
            ));
        }
    }

    public function getFactoryMethods()
    {
        $refl = new \ReflectionClass($this);

        $fMethods = [];
        foreach ($refl->getMethods() as $rMethod) {
            $comment = $rMethod->getDocComment();
            if ($comment) {
                if (false !== strpos($comment, '@factoryMethod')) {
                    $fMethods[] = $rMethod->name;
                }
            }
        }

        return $fMethods;
    }
}
