<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Deferred;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Utils\Utils;

use function is_callable;
use function is_string;
use function sprintf;

/**
 * Object Type Definition
 *
 * Almost all of the GraphQL types you define will be object types. Object types
 * have a name, but most importantly describe their fields.
 *
 * Example:
 *
 *     $AddressType = new ObjectType([
 *       'name' => 'Address',
 *       'fields' => [
 *         'street' => [ 'type' => GraphQL\Type\Definition\Type::string() ],
 *         'number' => [ 'type' => GraphQL\Type\Definition\Type::int() ],
 *         'formatted' => [
 *           'type' => GraphQL\Type\Definition\Type::string(),
 *           'resolve' => function($obj) {
 *             return $obj->number . ' ' . $obj->street;
 *           }
 *         ]
 *       ]
 *     ]);
 *
 * When two types need to refer to each other, or a type needs to refer to
 * itself in a field, you can use a function expression (aka a closure or a
 * thunk) to supply the fields lazily.
 *
 * Example:
 *
 *     $PersonType = null;
 *     $PersonType = new ObjectType([
 *       'name' => 'Person',
 *       'fields' => function() use (&$PersonType) {
 *          return [
 *              'name' => ['type' => GraphQL\Type\Definition\Type::string() ],
 *              'bestFriend' => [ 'type' => $PersonType ],
 *          ];
 *        }
 *     ]);
 */
class ObjectType extends Type implements OutputType, CompositeType, NullableType, HasFieldsType, NamedType, ImplementingType
{
    use HasFieldsTypeTrait;
    use NamedTypeTrait;
    use ImplementingTypeTrait;

    public ?ObjectTypeDefinitionNode $astNode;

    /** @var array<int, ObjectTypeExtensionNode> */
    public array $extensionASTNodes;

    /** @var callable|null */
    public $resolveFieldFn;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $config['name'] ??= $this->tryInferName();
        Utils::invariant(is_string($config['name']), 'Must provide name.');

        $this->name              = $config['name'];
        $this->description       = $config['description'] ?? null;
        $this->resolveFieldFn    = $config['resolveField'] ?? null;
        $this->astNode           = $config['astNode'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? [];

        $this->config = $config;
    }

    /**
     * @param mixed $type
     *
     * @return $this
     *
     * @throws InvariantViolation
     */
    public static function assertObjectType($type): self
    {
        Utils::invariant(
            $type instanceof self,
            'Expected ' . Utils::printSafe($type) . ' to be a GraphQL Object type.'
        );

        return $type;
    }

    /**
     * @param mixed $objectValue The resolved value for the object type
     * @param mixed $context     The context that was passed to GraphQL::execute()
     *
     * @return bool|Deferred|null
     */
    public function isTypeOf($objectValue, $context, ResolveInfo $info)
    {
        return isset($this->config['isTypeOf'])
            ? $this->config['isTypeOf'](
                $objectValue,
                $context,
                $info
            )
            : null;
    }

    /**
     * Validates type config and throws if one of type options is invalid.
     * Note: this method is shallow, it won't validate object fields and their arguments.
     *
     * @throws InvariantViolation
     */
    public function assertValid(): void
    {
        Utils::assertValidName($this->name);

        Utils::invariant(
            $this->description === null || is_string($this->description),
            sprintf(
                '%s description must be string if set, but it is: %s',
                $this->name,
                Utils::printSafe($this->description)
            )
        );

        $isTypeOf = $this->config['isTypeOf'] ?? null;

        Utils::invariant(
            $isTypeOf === null || is_callable($isTypeOf),
            sprintf('%s must provide "isTypeOf" as a function, but got: %s', $this->name, Utils::printSafe($isTypeOf))
        );

        foreach ($this->getFields() as $field) {
            $field->assertValid($this);
        }
    }
}
