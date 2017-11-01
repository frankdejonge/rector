<?php declare(strict_types=1);

namespace Rector\Rector\Dynamic;

use PhpParser\BuilderHelpers;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\NodeAnalyzer\ClassMethodAnalyzer;
use Rector\NodeAnalyzer\MethodCallAnalyzer;
use Rector\Rector\AbstractRector;

final class ArgumentReplacerRector extends AbstractRector
{
    /**
     * @var mixed[]
     */
    private $argumentChangesMethodAndClass = [];

    /**
     * @var MethodCallAnalyzer
     */
    private $methodCallAnalyzer;

    /**
     * @var mixed[]|null
     */
    private $activeArgumentChangesByPosition;
    /**
     * @var ClassMethodAnalyzer
     */
    private $classMethodAnalyzer;

    /**
     * @param mixed[] $argumentChangesByMethodAndType
     */
    public function __construct(
        array $argumentChangesByMethodAndType,
        MethodCallAnalyzer $methodCallAnalyzer,
        ClassMethodAnalyzer $classMethodAnalyzer
    ) {
        $this->argumentChangesMethodAndClass = $argumentChangesByMethodAndType;
        $this->methodCallAnalyzer = $methodCallAnalyzer;
        $this->classMethodAnalyzer = $classMethodAnalyzer;
    }

    public function isCandidate(Node $node): bool
    {
        $this->activeArgumentChangesByPosition = $this->matchArgumentChanges($node);
        if ($this->activeArgumentChangesByPosition === null) {
            return false;
        }

        /** @var MethodCall $node */
        foreach ($this->activeArgumentChangesByPosition as $position => $argumentChange) {
            $argumentOrParameterCount = $this->countArgumentsOrParameters($node);
            if ($argumentOrParameterCount < $position + 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param MethodCall $methodCallNode
     */
    public function refactor(Node $methodCallNode): ?Node
    {
        $arguments = $methodCallNode->args;

        foreach ($this->activeArgumentChangesByPosition as $position => $argumentChange) {
            $key = key($argumentChange);
            $value = array_shift($argumentChange);

            if ($key === '~') {
                if ($value === '~') { // remove argument
                    unset($arguments[$position]);
                } else { // new default value
                    $arguments[$position] = BuilderHelpers::normalizeValue($value);
                }
            }
        }

        $methodCallNode->args = $arguments;

        return $methodCallNode;
    }

    /**
     * @return mixed[]|null
     */
    private function matchArgumentChanges(Node $node): ?array
    {
        if (! $node instanceof ClassMethod && ! $node instanceof MethodCall && ! $node instanceof StaticCall) {
            return null;
        }

        foreach ($this->argumentChangesMethodAndClass as $type => $argumentChangesByMethod) {
            $methods = array_keys($argumentChangesByMethod);
            if ($this->methodCallAnalyzer->isTypeAndMethods($node, $type, $methods)) {
                return $argumentChangesByMethod[$node->name->toString()];
            }

            if ($this->classMethodAnalyzer->isTypeAndMethods($node, $type, $methods)) {
                return $argumentChangesByMethod[$node->name->toString()];
            }
        }

        return null;
    }

    private function countArgumentsOrParameters(Node $node): int
    {
        if ($node instanceof MethodCall || $node instanceof StaticCall) {
            return count($node->args);
        }

        if ($node instanceof ClassMethod) {
            return count($node->params);
        }

        return 0;
    }
}
