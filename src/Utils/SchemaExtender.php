<?php declare(strict_types=1);

namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ImplementingType;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Validator\DocumentValidator;

/**
 * @phpstan-import-type TypeConfigDecorator from ASTDefinitionBuilder
 * @phpstan-import-type UnnamedArgumentConfig from Argument
 * @phpstan-import-type UnnamedInputObjectFieldConfig from InputObjectField
 */
class SchemaExtender
{
    /** @var array<string, Type> */
    protected array $extendTypeCache = [];

    /** @var array<string, array<TypeExtensionNode>> */
    protected array $typeExtensionsMap = [];

    protected ASTDefinitionBuilder $astBuilder;

    /**
     * @param array<string, bool> $options
     *
     * @phpstan-param TypeConfigDecorator|null $typeConfigDecorator
     *
     * @api
     *
     * @throws \Exception
     * @throws InvariantViolation
     */
    public static function extend(
        Schema $schema,
        DocumentNode $documentAST,
        array $options = [],
        callable $typeConfigDecorator = null
    ): Schema {
        return (new static())->doExtend($schema, $documentAST, $options, $typeConfigDecorator);
    }

    /**
     * @param array<string, bool> $options
     *
     * @phpstan-param TypeConfigDecorator|null $typeConfigDecorator
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws Error
     * @throws InvariantViolation
     */
    protected function doExtend(
        Schema $schema,
        DocumentNode $documentAST,
        array $options = [],
        callable $typeConfigDecorator = null
    ): Schema {
        if (
            ! ($options['assumeValid'] ?? false)
            && ! ($options['assumeValidSDL'] ?? false)
        ) {
            DocumentValidator::assertValidSDLExtension($documentAST, $schema);
        }

        /** @var array<string, Node&TypeDefinitionNode> $typeDefinitionMap */
        $typeDefinitionMap = [];

        /** @var array<int, DirectiveDefinitionNode> $directiveDefinitions */
        $directiveDefinitions = [];

        /** @var SchemaDefinitionNode|null $schemaDef */
        $schemaDef = null;

        /** @var array<int, SchemaExtensionNode> $schemaExtensions */
        $schemaExtensions = [];

        foreach ($documentAST->definitions as $def) {
            if ($def instanceof SchemaDefinitionNode) {
                $schemaDef = $def;
            } elseif ($def instanceof SchemaExtensionNode) {
                $schemaExtensions[] = $def;
            } elseif ($def instanceof TypeDefinitionNode) {
                $name = $def->getName()->value;
                $typeDefinitionMap[$name] = $def;
            } elseif ($def instanceof TypeExtensionNode) {
                $name = $def->getName()->value;
                $this->typeExtensionsMap[$name][] = $def;
            } elseif ($def instanceof DirectiveDefinitionNode) {
                $directiveDefinitions[] = $def;
            }
        }

        if (
            $this->typeExtensionsMap === []
            && $typeDefinitionMap === []
            && $directiveDefinitions === []
            && $schemaExtensions === []
            && $schemaDef === null
        ) {
            return $schema;
        }

        $this->astBuilder = new ASTDefinitionBuilder(
            $typeDefinitionMap,
            [],
            // @phpstan-ignore-next-line no idea what is wrong here
            function (string $typeName) use ($schema): Type {
                $existingType = $schema->getType($typeName);
                if ($existingType === null) {
                    throw new InvariantViolation("Unknown type: \"{$typeName}\".");
                }

                return $this->extendNamedType($existingType);
            },
            $typeConfigDecorator
        );

        $this->extendTypeCache = [];

        $types = [];

        // Iterate through all types, getting the type definition for each, ensuring
        // that any type not directly referenced by a field will get created.
        foreach ($schema->getTypeMap() as $type) {
            $types[] = $this->extendNamedType($type);
        }

        // Do the same with new types.
        foreach ($typeDefinitionMap as $type) {
            $types[] = $this->astBuilder->buildType($type);
        }

        $operationTypes = [
            'query' => $this->extendMaybeNamedType($schema->getQueryType()),
            'mutation' => $this->extendMaybeNamedType($schema->getMutationType()),
            'subscription' => $this->extendMaybeNamedType($schema->getSubscriptionType()),
        ];

        if ($schemaDef !== null) {
            foreach ($schemaDef->operationTypes as $operationType) {
                $operationTypes[$operationType->operation] = $this->astBuilder->buildType($operationType->type);
            }
        }

        foreach ($schemaExtensions as $schemaExtension) {
            foreach ($schemaExtension->operationTypes as $operationType) {
                $operationTypes[$operationType->operation] = $this->astBuilder->buildType($operationType->type);
            }
        }

        $schemaExtensionASTNodes = \array_merge($schema->extensionASTNodes, $schemaExtensions);

        return new Schema(
            (new SchemaConfig())
            // @phpstan-ignore-next-line the root types may be invalid, but just passing them leads to more actionable errors
            ->setQuery($operationTypes['query'])
            // @phpstan-ignore-next-line the root types may be invalid, but just passing them leads to more actionable errors
            ->setMutation($operationTypes['mutation'])
            // @phpstan-ignore-next-line the root types may be invalid, but just passing them leads to more actionable errors
            ->setSubscription($operationTypes['subscription'])
            ->setTypes($types)
            ->setDirectives($this->getMergedDirectives($schema, $directiveDefinitions))
            ->setAstNode($schema->astNode ?? $schemaDef)
            ->setExtensionASTNodes($schemaExtensionASTNodes)
        );
    }

    /**
     * @param Type&NamedType $type
     *
     * @return array<TypeExtensionNode>|null
     */
    protected function extensionASTNodes(NamedType $type): ?array
    {
        return \array_merge(
            $type->extensionASTNodes ?? [],
            $this->typeExtensionsMap[$type->name] ?? []
        );
    }

    /**
     * @throws \Exception
     * @throws \ReflectionException
     * @throws InvariantViolation
     */
    protected function extendScalarType(ScalarType $type): CustomScalarType
    {
        /** @var array<int, ScalarTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = $this->extensionASTNodes($type);

        return new CustomScalarType([
            'name' => $type->name,
            'description' => $type->description,
            'serialize' => [$type, 'serialize'],
            'parseValue' => [$type, 'parseValue'],
            'parseLiteral' => [$type, 'parseLiteral'],
            'astNode' => $type->astNode,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    /** @throws InvariantViolation */
    protected function extendUnionType(UnionType $type): UnionType
    {
        /** @var array<int, UnionTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = $this->extensionASTNodes($type);

        return new UnionType([
            'name' => $type->name,
            'description' => $type->description,
            'types' => fn (): array => $this->extendUnionPossibleTypes($type),
            'resolveType' => [$type, 'resolveType'],
            'astNode' => $type->astNode,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    /**
     * @throws \Exception
     * @throws \ReflectionException
     * @throws InvariantViolation
     */
    protected function extendEnumType(EnumType $type): EnumType
    {
        /** @var array<int, EnumTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = $this->extensionASTNodes($type);

        return new EnumType([
            'name' => $type->name,
            'description' => $type->description,
            'values' => $this->extendEnumValueMap($type),
            'astNode' => $type->astNode,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    /** @throws InvariantViolation */
    protected function extendInputObjectType(InputObjectType $type): InputObjectType
    {
        /** @var array<int, InputObjectTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = $this->extensionASTNodes($type);

        return new InputObjectType([
            'name' => $type->name,
            'description' => $type->description,
            'fields' => fn (): array => $this->extendInputFieldMap($type),
            'astNode' => $type->astNode,
            'extensionASTNodes' => $extensionASTNodes,
            'parseValue' => [$type, 'parseValue'],
        ]);
    }

    /**
     * @throws \Exception
     * @throws InvariantViolation
     *
     * @return array<string, UnnamedInputObjectFieldConfig>
     */
    protected function extendInputFieldMap(InputObjectType $type): array
    {
        /** @var array<string, UnnamedInputObjectFieldConfig> $newFieldMap */
        $newFieldMap = [];

        $oldFieldMap = $type->getFields();
        foreach ($oldFieldMap as $fieldName => $field) {
            $extendedType = $this->extendType($field->getType());

            $newFieldConfig = [
                'description' => $field->description,
                'type' => $extendedType,
                'deprecationReason' => $field->deprecationReason,
                'astNode' => $field->astNode,
            ];

            if ($field->defaultValueExists()) {
                $newFieldConfig['defaultValue'] = $field->defaultValue;
            }

            $newFieldMap[$fieldName] = $newFieldConfig;
        }

        if (isset($this->typeExtensionsMap[$type->name])) {
            foreach ($this->typeExtensionsMap[$type->name] as $extension) {
                assert($extension instanceof InputObjectTypeExtensionNode, 'proven by schema validation');

                foreach ($extension->fields as $field) {
                    $newFieldMap[$field->name->value] = $this->astBuilder->buildInputField($field);
                }
            }
        }

        return $newFieldMap;
    }

    /**
     * @throws \Exception
     * @throws InvariantViolation
     *
     * @return array<string, array<string, mixed>>
     */
    protected function extendEnumValueMap(EnumType $type): array
    {
        $newValueMap = [];

        foreach ($type->getValues() as $value) {
            $newValueMap[$value->name] = [
                'name' => $value->name,
                'description' => $value->description,
                'value' => $value->value,
                'deprecationReason' => $value->deprecationReason,
                'astNode' => $value->astNode,
            ];
        }

        if (isset($this->typeExtensionsMap[$type->name])) {
            foreach ($this->typeExtensionsMap[$type->name] as $extension) {
                assert($extension instanceof EnumTypeExtensionNode, 'proven by schema validation');

                foreach ($extension->values as $value) {
                    $newValueMap[$value->name->value] = $this->astBuilder->buildEnumValue($value);
                }
            }
        }

        return $newValueMap;
    }

    /**
     * @throws \Exception
     * @throws \ReflectionException
     * @throws Error
     * @throws InvariantViolation
     *
     * @return array<int, ObjectType>
     */
    protected function extendUnionPossibleTypes(UnionType $type): array
    {
        $possibleTypes = \array_map(
            [$this, 'extendNamedType'],
            $type->getTypes()
        );

        if (isset($this->typeExtensionsMap[$type->name])) {
            foreach ($this->typeExtensionsMap[$type->name] as $extension) {
                assert($extension instanceof UnionTypeExtensionNode, 'proven by schema validation');

                foreach ($extension->types as $namedType) {
                    $possibleTypes[] = $this->astBuilder->buildType($namedType);
                }
            }
        }

        // @phpstan-ignore-next-line proven by schema validation
        return $possibleTypes;
    }

    /**
     * @param ObjectType|InterfaceType $type
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws Error
     * @throws InvariantViolation
     *
     * @return array<int, InterfaceType>
     */
    protected function extendImplementedInterfaces(ImplementingType $type): array
    {
        $interfaces = \array_map(
            [$this, 'extendNamedType'],
            $type->getInterfaces()
        );

        if (isset($this->typeExtensionsMap[$type->name])) {
            foreach ($this->typeExtensionsMap[$type->name] as $extension) {
                assert(
                    $extension instanceof ObjectTypeExtensionNode || $extension instanceof InterfaceTypeExtensionNode,
                    'proven by schema validation'
                );

                foreach ($extension->interfaces as $namedType) {
                    $interface = $this->astBuilder->buildType($namedType);
                    assert($interface instanceof InterfaceType, 'we know this, but PHP templates cannot express it');

                    $interfaces[] = $interface;
                }
            }
        }

        // @phpstan-ignore-next-line will be caught in schema validation
        return $interfaces;
    }

    /**
     * @template T of Type
     *
     * @param T $typeDef
     *
     * @return T
     */
    protected function extendType(Type $typeDef): Type
    {
        if ($typeDef instanceof ListOfType) {
            // @phpstan-ignore-next-line PHPStan does not understand this is the same generic type as the input
            return Type::listOf($this->extendType($typeDef->getWrappedType()));
        }

        if ($typeDef instanceof NonNull) {
            // @phpstan-ignore-next-line PHPStan does not understand this is the same generic type as the input
            return Type::nonNull($this->extendType($typeDef->getWrappedType()));
        }

        // @phpstan-ignore-next-line PHPStan does not understand this is the same generic type as the input
        return $this->extendNamedType($typeDef);
    }

    /**
     * @param array<Argument> $args
     *
     * @return array<string, UnnamedArgumentConfig>
     */
    protected function extendArgs(array $args): array
    {
        $extended = [];
        foreach ($args as $arg) {
            $extendedType = $this->extendType($arg->getType());

            $def = [
                'type' => $extendedType,
                'description' => $arg->description,
                'deprecationReason' => $arg->deprecationReason,
                'astNode' => $arg->astNode,
            ];

            if ($arg->defaultValueExists()) {
                $def['defaultValue'] = $arg->defaultValue;
            }

            $extended[$arg->name] = $def;
        }

        return $extended;
    }

    /**
     * @param InterfaceType|ObjectType $type
     *
     * @throws \Exception
     * @throws Error
     * @throws InvariantViolation
     *
     * @return array<string, array<string, mixed>>
     */
    protected function extendFieldMap(Type $type): array
    {
        $newFieldMap = [];
        $oldFieldMap = $type->getFields();

        foreach (\array_keys($oldFieldMap) as $fieldName) {
            $field = $oldFieldMap[$fieldName];

            $newFieldMap[$fieldName] = [
                'name' => $fieldName,
                'description' => $field->description,
                'deprecationReason' => $field->deprecationReason,
                'type' => $this->extendType($field->getType()),
                'args' => $this->extendArgs($field->args),
                'resolve' => $field->resolveFn,
                'astNode' => $field->astNode,
            ];
        }

        if (isset($this->typeExtensionsMap[$type->name])) {
            foreach ($this->typeExtensionsMap[$type->name] as $extension) {
                assert(
                    $extension instanceof ObjectTypeExtensionNode || $extension instanceof InterfaceTypeExtensionNode,
                    'proven by schema validation'
                );

                foreach ($extension->fields as $field) {
                    $newFieldMap[$field->name->value] = $this->astBuilder->buildField($field);
                }
            }
        }

        return $newFieldMap;
    }

    /** @throws InvariantViolation */
    protected function extendObjectType(ObjectType $type): ObjectType
    {
        /** @var array<int, ObjectTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = $this->extensionASTNodes($type);

        return new ObjectType([
            'name' => $type->name,
            'description' => $type->description,
            'interfaces' => fn (): array => $this->extendImplementedInterfaces($type),
            'fields' => fn (): array => $this->extendFieldMap($type),
            'isTypeOf' => [$type, 'isTypeOf'],
            'resolveField' => $type->resolveFieldFn ?? null,
            'astNode' => $type->astNode,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    /** @throws InvariantViolation */
    protected function extendInterfaceType(InterfaceType $type): InterfaceType
    {
        /** @var array<int, InterfaceTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = $this->extensionASTNodes($type);

        return new InterfaceType([
            'name' => $type->name,
            'description' => $type->description,
            'interfaces' => fn (): array => $this->extendImplementedInterfaces($type),
            'fields' => fn (): array => $this->extendFieldMap($type),
            'resolveType' => [$type, 'resolveType'],
            'astNode' => $type->astNode,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    protected function isSpecifiedScalarType(Type $type): bool
    {
        return $type instanceof NamedType
            && (
                $type->name === Type::STRING
                || $type->name === Type::INT
                || $type->name === Type::FLOAT
                || $type->name === Type::BOOLEAN
                || $type->name === Type::ID
            );
    }

    /**
     * @template T of Type
     *
     * @param T&NamedType $type
     *
     * @throws \ReflectionException
     * @throws InvariantViolation
     *
     * @return T&NamedType
     */
    protected function extendNamedType(Type $type): Type
    {
        if (Introspection::isIntrospectionType($type) || $this->isSpecifiedScalarType($type)) {
            return $type;
        }

        // @phpstan-ignore-next-line the subtypes line up
        return $this->extendTypeCache[$type->name] ??= $this->extendNamedTypeWithoutCache($type);
    }

    /** @throws \Exception */
    protected function extendNamedTypeWithoutCache(Type $type): Type
    {
        switch (true) {
            case $type instanceof ScalarType: return $this->extendScalarType($type);
            case $type instanceof ObjectType: return $this->extendObjectType($type);
            case $type instanceof InterfaceType: return $this->extendInterfaceType($type);
            case $type instanceof UnionType: return $this->extendUnionType($type);
            case $type instanceof EnumType: return $this->extendEnumType($type);
            case $type instanceof InputObjectType: return $this->extendInputObjectType($type);
            default:
                $unconsideredType = get_class($type);
                throw new \Exception("Unconsidered type: {$unconsideredType}.");
        }
    }

    /**
     * @template T of Type
     *
     * @param (T&NamedType)|null $type
     *
     * @throws \ReflectionException
     * @throws InvariantViolation
     *
     * @return (T&NamedType)|null
     */
    protected function extendMaybeNamedType(Type $type = null): ?Type
    {
        if ($type !== null) {
            return $this->extendNamedType($type);
        }

        return null;
    }

    /**
     * @param array<DirectiveDefinitionNode> $directiveDefinitions
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws InvariantViolation
     *
     * @return array<int, Directive>
     */
    protected function getMergedDirectives(Schema $schema, array $directiveDefinitions): array
    {
        $directives = \array_map(
            [$this, 'extendDirective'],
            $schema->getDirectives()
        );

        if ($directives === []) {
            throw new InvariantViolation('Schema must have default directives.');
        }

        foreach ($directiveDefinitions as $directive) {
            $directives[] = $this->astBuilder->buildDirective($directive);
        }

        return $directives;
    }

    protected function extendDirective(Directive $directive): Directive
    {
        return new Directive([
            'name' => $directive->name,
            'description' => $directive->description,
            'locations' => $directive->locations,
            'args' => $this->extendArgs($directive->args),
            'isRepeatable' => $directive->isRepeatable,
            'astNode' => $directive->astNode,
        ]);
    }
}
