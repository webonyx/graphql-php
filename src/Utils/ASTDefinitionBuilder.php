<?php

declare(strict_types=1);

namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use function is_array;
use function is_string;
use function sprintf;
use Throwable;

/**
 * @phpstan-import-type FieldMapConfig from FieldDefinition
 * @phpstan-import-type UnnamedFieldDefinitionConfig from FieldDefinition
 * @phpstan-type ResolveType callable(string, Node|null): Type
 * @phpstan-type TypeConfigDecorator callable(array<string, mixed>, Node&TypeDefinitionNode, array<string, Node&TypeDefinitionNode>): array<string, mixed>
 */
class ASTDefinitionBuilder
{
    /** @var array<string, Node&TypeDefinitionNode> */
    private array $typeDefinitionsMap;

    /**
     * @var callable
     * @phpstan-var ResolveType
     */
    private $resolveType;

    /**
     * @var callable|null
     * @phpstan-var TypeConfigDecorator|null
     */
    private $typeConfigDecorator;

    /** @var array<string, Type> */
    private array $cache;

    /**
     * @param array<string, Node&TypeDefinitionNode> $typeDefinitionsMap
     * @phpstan-param ResolveType $resolveType
     * @phpstan-param TypeConfigDecorator|null $typeConfigDecorator
     */
    public function __construct(
        array $typeDefinitionsMap,
        callable $resolveType,
        ?callable $typeConfigDecorator = null
    ) {
        $this->typeDefinitionsMap = $typeDefinitionsMap;
        $this->resolveType = $resolveType;
        $this->typeConfigDecorator = $typeConfigDecorator;

        $this->cache = Type::getAllBuiltInTypes();
    }

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
     * @return array<string, array<string, mixed>>
     */
    private function makeInputValues(NodeList $values): array
    {
        $map = [];
        foreach ($values as $value) {
            // Note: While this could make assertions to get the correctly typed
            // value, that would throw immediately while type system validation
            // with validateSchema() will produce more actionable results.
            $type = $this->buildWrappedType($value->type);

            $config = [
                'name' => $value->name->value,
                'type' => $type,
                'description' => $value->description->value ?? null,
                'astNode' => $value,
            ];
            if (isset($value->defaultValue)) {
                $config['defaultValue'] = AST::valueFromAST($value->defaultValue, $type);
            }

            $map[$value->name->value] = $config;
        }

        return $map;
    }

    /**
     * @param ListTypeNode|NonNullTypeNode|NamedTypeNode $typeNode
     */
    private function buildWrappedType(TypeNode $typeNode): Type
    {
        if ($typeNode instanceof ListTypeNode) {
            return Type::listOf($this->buildWrappedType($typeNode->type));
        }

        if ($typeNode instanceof NonNullTypeNode) {
            return Type::nonNull($this->buildWrappedType($typeNode->type));
        }

        return $this->buildType($typeNode);
    }

    /**
     * @param string|(Node&NamedTypeNode)|(Node&TypeDefinitionNode) $ref
     */
    public function buildType($ref): Type
    {
        if (is_string($ref)) {
            return $this->internalBuildType($ref);
        }

        return $this->internalBuildType($ref->name->value, $ref);
    }

    /**
     * @param (Node&NamedTypeNode)|(Node&TypeDefinitionNode)|null $typeNode
     *
     * @throws Error
     */
    private function internalBuildType(string $typeName, ?Node $typeNode = null): Type
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
                } catch (Throwable $e) {
                    throw new Error(
                        'Type config decorator passed to ' . static::class . ' threw an error when building ' . $typeName . ' type: ' . $e->getMessage(),
                        null,
                        null,
                        [],
                        null,
                        $e
                    );
                }

                // @phpstan-ignore-next-line should not happen, but function types are not enforced by PHP
                if (! is_array($config) || isset($config[0])) {
                    throw new Error(
                        'Type config decorator passed to ' . static::class . ' is expected to return an array, but got ' . Utils::printSafe($config)
                    );
                }

                $type = $this->makeSchemaDefFromConfig($this->typeDefinitionsMap[$typeName], $config);
            }

            return $this->cache[$typeName] = $type;
        }

        return $this->cache[$typeName] = ($this->resolveType)($typeName, $typeNode);
    }

    /**
     * @param ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode|EnumTypeDefinitionNode|ScalarTypeDefinitionNode|InputObjectTypeDefinitionNode|UnionTypeDefinitionNode $def
     *
     * @throws Error
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
                return $this->makeInputObjectDef($def);
        }
    }

    private function makeTypeDef(ObjectTypeDefinitionNode $def): ObjectType
    {
        return new ObjectType([
            'name' => $def->name->value,
            'description' => $def->description->value ?? null,
            'fields' => fn (): array => $this->makeFieldDefMap($def),
            'interfaces' => fn (): array => $this->makeImplementedInterfaces($def),
            'astNode' => $def,
        ]);
    }

    /**
     * @param ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $def
     *
     * @phpstan-return array<string, UnnamedFieldDefinitionConfig>
     */
    private function makeFieldDefMap(Node $def): array
    {
        $map = [];
        foreach ($def->fields as $field) {
            $map[$field->name->value] = $this->buildField($field);
        }

        return $map;
    }

    /**
     * @return UnnamedFieldDefinitionConfig
     */
    public function buildField(FieldDefinitionNode $field): array
    {
        return [
            // Note: While this could make assertions to get the correctly typed
            // value, that would throw immediately while type system validation
            // with validateSchema() will produce more actionable results.
            'type' => $this->buildWrappedType($field->type),
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
     * @param EnumValueDefinitionNode|FieldDefinitionNode $node
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
     * @param ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $def
     *
     * @return array<int, InterfaceType>
     */
    private function makeImplementedInterfaces($def): array
    {
        // Note: While this could make early assertions to get the correctly
        // typed values, that would throw immediately while type system
        // validation with validateSchema() will produce more actionable results.

        $interfaces = [];
        foreach ($def->interfaces as $interface) {
            $interfaces[] = $this->buildType($interface);
        }

        // @phpstan-ignore-next-line generic type will be validated during schema validation
        return $interfaces;
    }

    private function makeInterfaceDef(InterfaceTypeDefinitionNode $def): InterfaceType
    {
        return new InterfaceType([
            'name' => $def->name->value,
            'description' => $def->description->value ?? null,
            'fields' => fn (): array => $this->makeFieldDefMap($def),
            'interfaces' => fn (): array => $this->makeImplementedInterfaces($def),
            'astNode' => $def,
        ]);
    }

    private function makeEnumDef(EnumTypeDefinitionNode $def): EnumType
    {
        $values = [];
        foreach ($def->values as $value) {
            $values[$value->name->value] = [
                'description' => $value->description->value ?? null,
                'deprecationReason' => $this->getDeprecationReason($value),
                'astNode' => $value,
            ];
        }

        return new EnumType([
            'name' => $def->name->value,
            'description' => $def->description->value ?? null,
            'values' => $values,
            'astNode' => $def,
        ]);
    }

    private function makeUnionDef(UnionTypeDefinitionNode $def): UnionType
    {
        return new UnionType([
            'name' => $def->name->value,
            'description' => $def->description->value ?? null,
            // Note: While this could make assertions to get the correctly typed
            // values below, that would throw immediately while type system
            // validation with validateSchema() will produce more actionable results.
            'types' => function () use ($def): array {
                $types = [];
                foreach ($def->types as $type) {
                    $types[] = $this->buildType($type);
                }

                return $types;
            },
            'astNode' => $def,
        ]);
    }

    private function makeScalarDef(ScalarTypeDefinitionNode $def): CustomScalarType
    {
        return new CustomScalarType([
            'name' => $def->name->value,
            'description' => $def->description->value ?? null,
            'astNode' => $def,
            'serialize' => static fn ($value) => $value,
        ]);
    }

    private function makeInputObjectDef(InputObjectTypeDefinitionNode $def): InputObjectType
    {
        return new InputObjectType([
            'name' => $def->name->value,
            'description' => $def->description->value ?? null,
            'fields' => fn (): array => $this->makeInputValues($def->fields),
            'astNode' => $def,
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
                return new ObjectType($config);

            case $def instanceof InterfaceTypeDefinitionNode:
                return new InterfaceType($config);

            case $def instanceof EnumTypeDefinitionNode:
                return new EnumType($config);

            case $def instanceof UnionTypeDefinitionNode:
                return new UnionType($config);

            case $def instanceof ScalarTypeDefinitionNode:
                return new CustomScalarType($config);

            case $def instanceof InputObjectTypeDefinitionNode:
                return new InputObjectType($config);

            default:
                throw new Error(sprintf('Type kind of %s not supported.', $def->kind));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildInputField(InputValueDefinitionNode $value): array
    {
        $type = $this->buildWrappedType($value->type);

        $config = [
            'name' => $value->name->value,
            'type' => $type,
            'description' => $value->description->value ?? null,
            'astNode' => $value,
        ];

        if (null !== $value->defaultValue) {
            $config['defaultValue'] = $value->defaultValue;
        }

        return $config;
    }

    /**
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
