<?php declare(strict_types=1);

namespace GraphQL\Executor;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\LeafType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Executes queries while trusting the values that resolvers return.
 *
 * Compared to the spec-compliant {@see ReferenceExecutor}, this executor skips
 * the per-value validation of resolved values:
 *
 * - leaf values are not passed through the `serialize` method of their scalar
 *   or enum type and are put into the response as they are
 * - `isTypeOf` checks on object types are not executed
 * - the type that an abstract type resolves to is used without validating that
 *   it is an object type that belongs to the abstract type
 *
 * Structural checks that are needed to produce a well-formed response stay in
 * place: `null` values still propagate to the parent field when returned for
 * non-nullable types, list fields still require iterables and thrown errors are
 * still caught and collected.
 *
 * Since the validation code paths do not exist in this implementation, enabling
 * it does not cost a single check per field. In exchange, resolvers must return
 * response-ready values. When a scalar's `serialize` normally transforms the
 * internal representation - e.g. a `\DateTime` to a string - resolvers have to
 * return the serialized value directly. Invalid resolver output leads to
 * malformed responses or `TypeError`s instead of spec-compliant errors.
 *
 * Only use this when the returned values are already known to be correct, e.g.
 * because they are enforced by static analysis or otherwise trusted.
 */
class TrustingExecutor extends ReferenceExecutor
{
    /**
     * @param mixed $result
     *
     * @return mixed
     */
    protected function completeLeafValue(LeafType $returnType, $result)
    {
        return $result;
    }

    /**
     * @param \ArrayObject<int, FieldNode> $fieldNodes
     * @param list<string|int> $path
     * @param list<string|int> $unaliasedPath
     * @param mixed $result
     * @param mixed $contextValue
     *
     * @throws \Exception
     *
     * @return array<mixed>|Promise|\stdClass
     */
    protected function completeObjectValue(
        ObjectType $returnType,
        \ArrayObject $fieldNodes,
        ResolveInfo $info,
        array $path,
        array $unaliasedPath,
        $result,
        $contextValue
    ) {
        return $this->collectAndExecuteSubfields(
            $returnType,
            $fieldNodes,
            $path,
            $unaliasedPath,
            $result,
            $contextValue
        );
    }

    /**
     * The runtime type is still needed to dispatch sub-field selection, so a
     * resolved type name is looked up in the schema - but it is trusted to be a
     * valid object type of the abstract type without further checks.
     *
     * @param mixed $runtimeTypeOrName
     * @param AbstractType&\GraphQL\Type\Definition\Type $returnType
     * @param mixed $result
     */
    protected function ensureValidRuntimeType(
        $runtimeTypeOrName,
        AbstractType $returnType,
        ResolveInfo $info,
        $result
    ): ObjectType {
        $runtimeType = is_string($runtimeTypeOrName)
            ? $this->exeContext->schema->getType($runtimeTypeOrName)
            : $runtimeTypeOrName;

        assert($runtimeType instanceof ObjectType, 'Runtime type must resolve to an ObjectType');

        return $runtimeType;
    }
}
