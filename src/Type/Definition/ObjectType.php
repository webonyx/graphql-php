<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Deferred;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Executor;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Utils\Utils;

/**
 * Object Type Definition.
 *
 * Most GraphQL types you define will be object types.
 * Object types have a name, but most importantly describe their fields.
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
 *
 * @phpstan-import-type FieldResolver from Executor
 * @phpstan-import-type ArgsMapper from Executor
 *
 * @phpstan-type InterfaceTypeReference InterfaceType|callable(): InterfaceType
 * @phpstan-type ObjectConfig array{
 *   name?: string|null,
 *   description?: string|null,
 *   resolveField?: FieldResolver|null,
 *   argsMapper?: ArgsMapper|null,
 *   fields: (callable(): iterable<mixed>)|iterable<mixed>,
 *   interfaces?: iterable<InterfaceTypeReference>|callable(): iterable<InterfaceTypeReference>,
 *   isTypeOf?: (callable(mixed $objectValue, mixed $context, ResolveInfo $resolveInfo): (bool|Deferred|null))|null,
 *   astNode?: ObjectTypeDefinitionNode|null,
 *   extensionASTNodes?: array<ObjectTypeExtensionNode>|null
 * }
 */
class ObjectType extends Type implements OutputType, CompositeType, NullableType, HasFieldsType, NamedType, ImplementingType
{
    use HasFieldsTypeImplementation;
    use NamedTypeImplementation;
    use ImplementingTypeImplementation;

    public ?ObjectTypeDefinitionNode $astNode;

    /** @var array<ObjectTypeExtensionNode> */
    public array $extensionASTNodes;

    /**
     * @var callable|null
     *
     * @phpstan-var FieldResolver|null
     */
    public $resolveFieldFn;

    /**
     * @var callable|null
     *
     * @phpstan-var ArgsMapper|null
     */
    public $argsMapper;

    /** @phpstan-var ObjectConfig */
    public array $config;

    /**
     * @throws InvariantViolation
     *
     * @phpstan-param ObjectConfig $config
     */
    public function __construct(array $config)
    {
        $this->name = $config['name'] ?? $this->inferName();
        $this->description = $config['description'] ?? null;
        $this->resolveFieldFn = $config['resolveField'] ?? null;
        $this->argsMapper = $config['argsMapper'] ?? null;
        $this->astNode = $config['astNode'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? [];

        $this->config = $config;
    }

    /**
     * @param mixed $type
     *
     * @throws InvariantViolation
     */
    public static function assertObjectType($type): self
    {
        if (! ($type instanceof self)) {
            $notObjectType = Utils::printSafe($type);
            throw new InvariantViolation("Expected {$notObjectType} to be a GraphQL Object type.");
        }

        return $type;
    }

    /**
     * @param mixed $objectValue The resolved value for the object type
     * @param mixed $context The context that was passed to GraphQL::execute()
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
     * @throws Error
     * @throws InvariantViolation
     */
    public function assertValid(): void
    {
        Utils::assertValidName($this->name);

        $isTypeOf = $this->config['isTypeOf'] ?? null;
        // @phpstan-ignore-next-line not necessary according to types, but can happen during runtime
        if (isset($isTypeOf) && ! is_callable($isTypeOf)) {
            $notCallable = Utils::printSafe($isTypeOf);
            throw new InvariantViolation("{$this->name} must provide \"isTypeOf\" as null or a callable, but got: {$notCallable}.");
        }

        foreach ($this->getFields() as $field) {
            $field->assertValid($this);
        }

        $this->assertValidInterfaces();
    }

    public function astNode(): ?ObjectTypeDefinitionNode
    {
        return $this->astNode;
    }

    /** @return array<ObjectTypeExtensionNode> */
    public function extensionASTNodes(): array
    {
        return $this->extensionASTNodes;
    }
}
