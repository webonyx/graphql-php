<?php declare(strict_types=1);

namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\OutputType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;

/**
 * @see FieldDefinition, InputObjectField
 *
 * @phpstan-import-type UnnamedFieldDefinitionConfig from FieldDefinition
 * @phpstan-import-type InputObjectFieldConfig from InputObjectField
 * @phpstan-import-type UnnamedInputObjectFieldConfig from InputObjectField
 *
 * @phpstan-type ResolveType callable(string, Node|null): Type&NamedType
 * @phpstan-type TypeConfigDecorator callable(array<string, mixed>, Node&TypeDefinitionNode, array<string, Node&TypeDefinitionNode>): array<string, mixed>
 */
class ASTDefinitionBuilder
{
    /** @var array<string, Node&TypeDefinitionNode> */
    private array $typeDefinitionsMap;

    /**
     * @var callable
     *
     * @phpstan-var ResolveType
     */
    private $resolveType;

    /**
     * @var callable|null
     *
     * @phpstan-var TypeConfigDecorator|null
     */
    private $typeConfigDecorator;

    /** @var array<string, Type&NamedType> */
    private array $cache;

    /** @var array<string, array<int, Node&TypeExtensionNode>> */
    private array $typeExtensionsMap;

    /**
     * @param array<string, Node&TypeDefinitionNode> $typeDefinitionsMap
     * @param array<string, array<int, Node&TypeExtensionNode>> $typeExtensionsMap
     *
     * @phpstan-param ResolveType $resolveType
     * @phpstan-param TypeConfigDecorator|null $typeConfigDecorator
     *
     * @throws InvariantViolation
     */
    public function __construct(
        array $typeDefinitionsMap,
        array $typeExtensionsMap,
        callable $resolveType,
        callable $typeConfigDecorator = null
    ) {
        $this->typeDefinitionsMap = $typeDefinitionsMap;
        $this->typeExtensionsMap = $typeExtensionsMap;
        $this->resolveType = $resolveType;
        $this->typeConfigDecorator = $typeConfigDecorator;

        $this->cache = Type::builtInTypes();
    }

    /** @throws \Exception */
    public function buildDirective(DirectiveDefinitionNode $directiveNode): Directive
    {
        $locations = [];
        foreach ($directiveNode->locations as $location) {
            $locations[] = $location->value;
        }

        return new Directive([
            'name' => $directiveNode->name->value,
            'description' => $directiveNode->description->value ?? null,
            'args' => $this->makeInputValues($directiveNode->arguments),
            'isRepeatable' => $directiveNode->repeatable,
            'locations' => $locations,
            'astNode' => $directiveNode,
        ]);
    }

    /**
     * @param NodeList<InputValueDefinitionNode> $values
     *
     * @throws \Exception
     *
     * @return array<string, UnnamedInputObjectFieldConfig>
     */
    private function makeInputValues(NodeList $values): array
    {
        /** @var array<string, UnnamedInputObjectFieldConfig> $map */
        $map = [];
        foreach ($values as $value) {
            // Note: While this could make assertions to get the correctly typed
            // value, that would throw immediately while type system validation
            // with validateSchema() will produce more actionable results.
            /** @var Type&InputType $type */
            $type = $this->buildWrappedType($value->type);

            $config = [
                'name' => $value->name->value,
                'type' => $type,
                'description' => $value->description->value ?? null,
                'deprecationReason' => $this->getDeprecationReason($value),
                'astNode' => $value,
            ];

            if ($value->defaultValue !== null) {
                $config['defaultValue'] = AST::valueFromAST($value->defaultValue, $type);
            }

            $map[$value->name->value] = $config;
        }

        return $map;
    }

    /**
     * @param array<InputObjectTypeDefinitionNode|InputObjectTypeExtensionNode> $nodes
     *
     * @throws \Exception
     *
     * @return array<string, UnnamedInputObjectFieldConfig>
     */
    private function makeInputFields(array $nodes): array
    {
        /** @var array<int, InputValueDefinitionNode>> $fields */
        $fields = [];
        foreach ($nodes as $node) {
            \array_push($fields, ...$node->fields);
        }

        return $this->makeInputValues(new NodeList($fields));
    }

    /**
     * @param ListTypeNode|NonNullTypeNode|NamedTypeNode $typeNode
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws Error
     * @throws InvariantViolation
     */
    private function buildWrappedType(TypeNode $typeNode): Type
    {
        if ($typeNode instanceof ListTypeNode) {
            return Type::listOf($this->buildWrappedType($typeNode->type));
        }

        if ($typeNode instanceof NonNullTypeNode) {
            // @phpstan-ignore-next-line contained type is NullableType
            return Type::nonNull($this->buildWrappedType($typeNode->type));
        }

        return $this->buildType($typeNode);
    }

    /**
     * @param string|(Node&NamedTypeNode)|(Node&TypeDefinitionNode) $ref
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws Error
     * @throws InvariantViolation
     *
     * @return Type&NamedType
     */
    public function buildType($ref): Type
    {
        if ($ref instanceof TypeDefinitionNode) {
            return $this->internalBuildType($ref->getName()->value, $ref);
        }
        if ($ref instanceof NamedTypeNode) {
            return $this->internalBuildType($ref->name->value, $ref);
        }

        return $this->internalBuildType($ref);
    }

    /**
     * Calling this method is an equivalent of `typeMap[typeName]` in `graphql-js`.
     * It is legal to access a type from the map of already-built types that doesn't exist in the map.
     * Since we build types lazily, and we don't have a such map of built types,
     * this method provides a way to build a type that may not exist in the SDL definitions and returns null instead.
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws Error
     * @throws InvariantViolation
     *
     * @return (Type&NamedType)|null
     */
    public function maybeBuildType(string $name): ?Type
    {
        return isset($this->typeDefinitionsMap[$name])
            ? $this->buildType($name)
            : null;
    }

    /**
     * @param (Node&NamedTypeNode)|(Node&TypeDefinitionNode)|null $typeNode
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws Error
     * @throws InvariantViolation
     *
     * @return Type&NamedType
     */
    private function internalBuildType(string $typeName, Node $typeNode = null): Type
    {
        if (isset($this->cache[$typeName])) {
            return $this->cache[$typeName];
        }

        if (isset($this->typeDefinitionsMap[$typeName])) {
            $type = $this->makeSchemaDef($this->typeDefinitionsMap[$typeName]);

            if (isset($this->typeConfigDecorator)) {
                try {
                    $config = ($this->typeConfigDecorator)(
                        $type->config,
                        $this->typeDefinitionsMap[$typeName],
                        $this->typeDefinitionsMap
                    );
                } catch (\Throwable $e) {
                    $class = static::class;
                    throw new Error(
                        "Type config decorator passed to {$class} threw an error when building {$typeName} type: {$e->getMessage()}",
                        null,
                        null,
                        [],
                        null,
                        $e
                    );
                }

                // @phpstan-ignore-next-line should not happen, but function types are not enforced by PHP
                if (! \is_array($config) || isset($config[0])) {
                    $class = static::class;
                    $notArray = Utils::printSafe($config);
                    throw new Error("Type config decorator passed to {$class} is expected to return an array, but got {$notArray}");
                }

                $type = $this->makeSchemaDefFromConfig($this->typeDefinitionsMap[$typeName], $config);
            }

            return $this->cache[$typeName] = $type;
        }

        return $this->cache[$typeName] = ($this->resolveType)($typeName, $typeNode);
    }

    /**
     * @param TypeDefinitionNode&Node $def
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws InvariantViolation
     *
     * @return CustomScalarType|EnumType|InputObjectType|InterfaceType|ObjectType|UnionType
     */
    private function makeSchemaDef(Node $def): Type
    {
        switch (true) {
            case $def instanceof ObjectTypeDefinitionNode:
                return $this->makeTypeDef($def);

            case $def instanceof InterfaceTypeDefinitionNode:
                return $this->makeInterfaceDef($def);

            case $def instanceof EnumTypeDefinitionNode:
                return $this->makeEnumDef($def);

            case $def instanceof UnionTypeDefinitionNode:
                return $this->makeUnionDef($def);

            case $def instanceof ScalarTypeDefinitionNode:
                return $this->makeScalarDef($def);

            default:
                assert($def instanceof InputObjectTypeDefinitionNode, 'all implementations are known');

                return $this->makeInputObjectDef($def);
        }
    }

    /** @throws InvariantViolation */
    private function makeTypeDef(ObjectTypeDefinitionNode $def): ObjectType
    {
        $name = $def->name->value;
        /** @var array<int, ObjectTypeExtensionNode> $extensionASTNodes (proven by schema validation) */
        $extensionASTNodes = $this->typeExtensionsMap[$name] ?? [];
        $allNodes = [$def, ...$extensionASTNodes];

        return new ObjectType([
            'name' => $name,
            'description' => $def->description->value ?? null,
            'fields' => fn (): array => $this->makeFieldDefMap($allNodes),
            'interfaces' => fn (): array => $this->makeImplementedInterfaces($allNodes),
            'astNode' => $def,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    /**
     * @param array<ObjectTypeDefinitionNode|ObjectTypeExtensionNode|InterfaceTypeDefinitionNode|InterfaceTypeExtensionNode> $nodes
     *
     * @phpstan-return array<string, UnnamedFieldDefinitionConfig>
     *
     * @throws \Exception
     */
    private function makeFieldDefMap(array $nodes): array
    {
        $map = [];
        foreach ($nodes as $node) {
            foreach ($node->fields as $field) {
                $map[$field->name->value] = $this->buildField($field);
            }
        }

        return $map;
    }

    /**
     * @throws \Exception
     * @throws Error
     *
     * @return UnnamedFieldDefinitionConfig
     */
    public function buildField(FieldDefinitionNode $field): array
    {
        // Note: While this could make assertions to get the correctly typed
        // value, that would throw immediately while type system validation
        // with validateSchema() will produce more actionable results.
        /** @var OutputType&Type $type */
        $type = $this->buildWrappedType($field->type);

        return [
            'type' => $type,
            'description' => $field->description->value ?? null,
            'args' => $this->makeInputValues($field->arguments),
            'deprecationReason' => $this->getDeprecationReason($field),
            'astNode' => $field,
        ];
    }

    /**
     * Given a collection of directives, returns the string value for the
     * deprecation reason.
     *
     * @param EnumValueDefinitionNode|FieldDefinitionNode|InputValueDefinitionNode $node
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws InvariantViolation
     */
    private function getDeprecationReason(Node $node): ?string
    {
        $deprecated = Values::getDirectiveValues(
            Directive::deprecatedDirective(),
            $node
        );

        return $deprecated['reason'] ?? null;
    }

    /**
     * @param array<ObjectTypeDefinitionNode|ObjectTypeExtensionNode|InterfaceTypeDefinitionNode|InterfaceTypeExtensionNode> $nodes
     *
     * @throws \Exception
     * @throws Error
     * @throws InvariantViolation
     *
     * @return array<int, InterfaceType>
     */
    private function makeImplementedInterfaces(array $nodes): array
    {
        // Note: While this could make early assertions to get the correctly
        // typed values, that would throw immediately while type system
        // validation with validateSchema() will produce more actionable results.

        $interfaces = [];
        foreach ($nodes as $node) {
            foreach ($node->interfaces as $interface) {
                $interfaces[] = $this->buildType($interface);
            }
        }

        // @phpstan-ignore-next-line generic type will be validated during schema validation
        return $interfaces;
    }

    /** @throws InvariantViolation */
    private function makeInterfaceDef(InterfaceTypeDefinitionNode $def): InterfaceType
    {
        $name = $def->name->value;
        /** @var array<int, InterfaceTypeExtensionNode> $extensionASTNodes (proven by schema validation) */
        $extensionASTNodes = $this->typeExtensionsMap[$name] ?? [];
        $allNodes = [$def, ...$extensionASTNodes];

        return new InterfaceType([
            'name' => $name,
            'description' => $def->description->value ?? null,
            'fields' => fn (): array => $this->makeFieldDefMap($allNodes),
            'interfaces' => fn (): array => $this->makeImplementedInterfaces($allNodes),
            'astNode' => $def,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    /**
     * @throws \Exception
     * @throws \ReflectionException
     * @throws InvariantViolation
     */
    private function makeEnumDef(EnumTypeDefinitionNode $def): EnumType
    {
        $name = $def->name->value;
        /** @var array<int, EnumTypeExtensionNode> $extensionASTNodes (proven by schema validation) */
        $extensionASTNodes = $this->typeExtensionsMap[$name] ?? [];

        $values = [];
        foreach ([$def, ...$extensionASTNodes] as $node) {
            foreach ($node->values as $value) {
                $values[$value->name->value] = [
                    'description' => $value->description->value ?? null,
                    'deprecationReason' => $this->getDeprecationReason($value),
                    'astNode' => $value,
                ];
            }
        }

        return new EnumType([
            'name' => $name,
            'description' => $def->description->value ?? null,
            'values' => $values,
            'astNode' => $def,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    /** @throws InvariantViolation */
    private function makeUnionDef(UnionTypeDefinitionNode $def): UnionType
    {
        $name = $def->name->value;
        /** @var array<int, UnionTypeExtensionNode> $extensionASTNodes (proven by schema validation) */
        $extensionASTNodes = $this->typeExtensionsMap[$name] ?? [];

        return new UnionType([
            'name' => $name,
            'description' => $def->description->value ?? null,
            // Note: While this could make assertions to get the correctly typed
            // values below, that would throw immediately while type system
            // validation with validateSchema() will produce more actionable results.
            'types' => function () use ($def, $extensionASTNodes): array {
                $types = [];
                foreach ([$def, ...$extensionASTNodes] as $node) {
                    foreach ($node->types as $type) {
                        $types[] = $this->buildType($type);
                    }
                }

                /** @var array<int, ObjectType> $types */
                return $types;
            },
            'astNode' => $def,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    /** @throws InvariantViolation */
    private function makeScalarDef(ScalarTypeDefinitionNode $def): CustomScalarType
    {
        $name = $def->name->value;
        /** @var array<int, ScalarTypeExtensionNode> $extensionASTNodes (proven by schema validation) */
        $extensionASTNodes = $this->typeExtensionsMap[$name] ?? [];

        return new CustomScalarType([
            'name' => $name,
            'description' => $def->description->value ?? null,
            'astNode' => $def,
            'extensionASTNodes' => $extensionASTNodes,
            'serialize' => static fn ($value) => $value,
        ]);
    }

    /** @throws InvariantViolation */
    private function makeInputObjectDef(InputObjectTypeDefinitionNode $def): InputObjectType
    {
        $name = $def->name->value;
        /** @var array<int, InputObjectTypeExtensionNode> $extensionASTNodes (proven by schema validation) */
        $extensionASTNodes = $this->typeExtensionsMap[$name] ?? [];

        return new InputObjectType([
            'name' => $name,
            'description' => $def->description->value ?? null,
            'fields' => fn (): array => $this->makeInputFields([$def, ...$extensionASTNodes]),
            'astNode' => $def,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws Error
     *
     * @return CustomScalarType|EnumType|InputObjectType|InterfaceType|ObjectType|UnionType
     */
    private function makeSchemaDefFromConfig(Node $def, array $config): Type
    {
        switch (true) {
            case $def instanceof ObjectTypeDefinitionNode:
                // @phpstan-ignore-next-line assume the config matches
                return new ObjectType($config);

            case $def instanceof InterfaceTypeDefinitionNode:
                // @phpstan-ignore-next-line assume the config matches
                return new InterfaceType($config);

            case $def instanceof EnumTypeDefinitionNode:
                // @phpstan-ignore-next-line assume the config matches
                return new EnumType($config);

            case $def instanceof UnionTypeDefinitionNode:
                // @phpstan-ignore-next-line assume the config matches
                return new UnionType($config);

            case $def instanceof ScalarTypeDefinitionNode:
                // @phpstan-ignore-next-line assume the config matches
                return new CustomScalarType($config);

            case $def instanceof InputObjectTypeDefinitionNode:
                // @phpstan-ignore-next-line assume the config matches
                return new InputObjectType($config);

            default:
                throw new Error("Type kind of {$def->kind} not supported.");
        }
    }

    /**
     * @throws \Exception
     *
     * @return InputObjectFieldConfig
     */
    public function buildInputField(InputValueDefinitionNode $value): array
    {
        $type = $this->buildWrappedType($value->type);
        assert($type instanceof InputType, 'proven by schema validation');

        $config = [
            'name' => $value->name->value,
            'type' => $type,
            'description' => $value->description->value ?? null,
            'astNode' => $value,
        ];

        if ($value->defaultValue !== null) {
            $config['defaultValue'] = AST::valueFromAST($value->defaultValue, $type);
        }

        return $config;
    }

    /**
     * @throws \Exception
     *
     * @return array<string, mixed>
     */
    public function buildEnumValue(EnumValueDefinitionNode $value): array
    {
        return [
            'description' => $value->description->value ?? null,
            'deprecationReason' => $this->getDeprecationReason($value),
            'astNode' => $value,
        ];
    }
}
