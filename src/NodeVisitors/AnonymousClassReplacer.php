<?php

namespace Spatie\Php7to5\NodeVisitors;

use PhpParser\Node;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;
use Spatie\Php7to5\Exceptions\InvalidPhpCode;

class AnonymousClassReplacer extends NodeVisitorAbstract
{
    /**
     * @var array
     */
    protected $anonymousClassNodes = [];

    /**
     * {@inheritdoc}
     */
    public function leaveNode(Node $node)
    {
        if (!$node instanceof Node\Expr\New_) {
            return;
        }

        $classNode = $node->class;
        if (!$classNode instanceof Node\Stmt\Class_) {
            return;
        }

        $newClassName = 'AnonymousClass'.count($this->anonymousClassNodes);

        $classNode->name = $newClassName;

        $this->anonymousClassNodes[] = $classNode;

        // Generate new code that instantiate our new class
        $newNode = new Node\Expr\New_(
            new Node\Expr\ConstFetch(
                new Node\Name($newClassName)
            )
        );

        return $newNode;
    }

    /**
     * {@inheritdoc}
     */
    public function afterTraverse(array $nodes)
    {
        if (count($this->anonymousClassNodes) === 0) {
            return $nodes;
        }

        $anonymousClassStatements = $this->anonymousClassNodes;

        $hookIndex = $this->getAnonymousClassHookIndex($nodes);

        $nodes = $this->moveAnonymousClassesToHook($nodes, $hookIndex, $anonymousClassStatements);

        return $nodes;
    }

    /**
     * Find the index of the first statement that is not a declare, use or namespace statement.
     *
     * @param array $statements
     *
     * @return int
     *
     * @throws \Spatie\Php7to5\Exceptions\InvalidPhpCode
     */
    protected function getAnonymousClassHookIndex(array $statements)
    {
        $hookIndex = false;

        foreach ($statements as $index => $statement) {
            if (!$statement instanceof Declare_ &&
                !$statement instanceof Use_ &&
                !$statement instanceof Namespace_) {
                $hookIndex = $index;

                break;
            }
        }

        if ($hookIndex === false) {
            throw InvalidPhpCode::noValidLocationFoundToInsertClasses();
        }

        return $hookIndex;
    }

    /**
     * @param array $nodes
     * @param $hookIndex
     * @param $anonymousClassStatements
     * 
     * @return array
     */
    protected function moveAnonymousClassesToHook(array $nodes, $hookIndex, $anonymousClassStatements)
    {
        $preStatements = array_slice($nodes, 0, $hookIndex);
        $postStatements = array_slice($nodes, $hookIndex);

        return array_merge($preStatements, $anonymousClassStatements, $postStatements);
    }
}
