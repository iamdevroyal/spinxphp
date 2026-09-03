<?php

declare(strict_types=1);

namespace Spinx\Analysis;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Flags static properties inside app/Modules that aren't readonly.
 *
 * This is the static-analysis half of the state-safety layer described in
 * build spec §4. RequestScopePass (see
 * Spinx\Container\Compiler\RequestScopePass) handles state safety for
 * services resolved through the container, but a static property bypasses
 * the container entirely — it lives directly on the class and persists for
 * the life of the worker/coroutine process regardless of how the class is
 * instantiated. No amount of request-scoping the *service* protects
 * against a *static property* on that same class leaking data across
 * requests. This rule catches that case at analysis time instead.
 *
 * @implements Rule<Node\Stmt\Property>
 */
final class NoMutableStaticStateRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Property::class;
    }

    /**
     * @param Node\Stmt\Property $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->isStatic() || $node->isReadonly()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        if (
            $classReflection === null
            || !str_starts_with($classReflection->getName(), 'App\\Modules\\')
            || $classReflection->isSubclassOf('Spinx\\Database\\Model')
            || is_subclass_of($classReflection->getName(), \Spinx\Database\Model::class)
        ) {
            // Enforced within app/Modules only — framework internals and Model schema definitions are exempt.
            return [];
        }



        $errors = [];

        foreach ($node->props as $prop) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Static property $%s in a Spinx module can leak request state across requests on a persistent-process runtime (RoadRunner/Swoole). Use a request-scoped service instead, or mark it readonly if it is genuinely immutable.',
                $prop->name->toString()
            ))
                ->identifier('spinx.mutableStaticProperty')
                ->build();
        }

        return $errors;
    }
}
