<?php declare(strict_types=1);

namespace GraphQL\Utils;

use function array_keys;
use function array_map;
use function array_merge;
use function count;
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
use GraphQL\Validator\DocumentValidator;

/**
 * @phpstan-import-type TypeConfigDecorator from ASTDefinitionBuilder
 * @phpstan-import-type UnnamedArgumentConfig from Argument
 * @phpstan-import-type UnnamedInputObjectFieldConfig from InputObjectField
 */
class SchemaExtender
{
    /** @var array<string, Type> */
    protected static array $extendTypeCache;

    /** @var array<string, array<TypeExtensionNode>> */
    protected static array $typeExtensionsMap;

    protected static ASTDefinitionBuilder $astBuilder;

    /**
     * @param array<string, bool> $options
     * @phpstan-param TypeConfigDecorator|null $typeConfigDecorator
     *
     * @api
     */
    public static function extend(
        Schema $schema,
        DocumentNode $documentAST,
        array $options = [],
        ?callable $typeConfigDecorator = null
    ): Schema {
        // Ensure the schema is fully loaded
        // TODO why does this fix a failing test?
//        $schema->getTypeMap();

        if (
            ! ($options['assumeValid'] ?? false)
            && ! ($options['assumeValidSDL'] ?? false)
        ) {
            DocumentValidator::assertValidSDLExtension($documentAST, $schema);
        }

        /** @var array<string, Node&TypeDefinitionNode> $typeDefinitionMap */
        $typeDefinitionMap = [];

        static::$typeExtensionsMap = [];

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
                $typeDefinitionMap[$def->name->value] = $def;
            } elseif ($def instanceof TypeExtensionNode) {
                static::$typeExtensionsMap[$def->name->value][] = $def;
            } elseif ($def instanceof DirectiveDefinitionNode) {
                $directiveDefinitions[] = $def;
            }
        }

        if (
            count(static::$typeExtensionsMap) === 0
            && count($typeDefinitionMap) === 0
            && count($directiveDefinitions) === 0
            && count($schemaExtensions) === 0
            && $schemaDef === null
        ) {
            return $schema;
        }

        static::$astBuilder = new ASTDefinitionBuilder(
            $typeDefinitionMap,
            static function (string $typeName) use ($schema): Type {
                $existingType = $schema->getType($typeName);
                if ($existingType === null) {
                    throw new InvariantViolation('Unknown type: "' . $typeName . '".');
                }

                return static::extendNamedType($existingType);
            },
            $typeConfigDecorator
        );

        static::$extendTypeCache = [];

        $operationTypes = [
            'query' => static::extendMaybeNamedType($schema->getQueryType()),
            'mutation' => static::extendMaybeNamedType($schema->getMutationType()),
            'subscription' => static::extendMaybeNamedType($schema->getSubscriptionType()),
        ];

        if ($schemaDef !== null) {
            foreach ($schemaDef->operationTypes as $operationType) {
                $operationTypes[$operationType->operation] = static::$astBuilder->buildType($operationType->type);
            }
        }

        foreach ($schemaExtensions as $schemaExtension) {
            if (isset($schemaExtension->operationTypes)) {
                foreach ($schemaExtension->operationTypes as $operationType) {
                    $operationTypes[$operationType->operation] = static::$astBuilder->buildType($operationType->type);
                }
            }
        }

        $schemaExtensionASTNodes = array_merge($schema->extensionASTNodes, $schemaExtensions);

        $types = [];
        // Iterate through all types, getting the type definition for each, ensuring
        // that any type not directly referenced by a field will get created.
        foreach ($schema->getTypeMap() as $type) {
            $types[] = static::extendNamedType($type);
        }

        // Do the same with new types.
        foreach ($typeDefinitionMap as $type) {
            $types[] = static::$astBuilder->buildType($type);
        }

        return new Schema([
            'query' => $operationTypes['query'],
            'mutation' => $operationTypes['mutation'],
            'subscription' => $operationTypes['subscription'],
            'types' => $types,
            'directives' => static::getMergedDirectives($schema, $directiveDefinitions),
            'astNode' => $schema->getAstNode(),
            'extensionASTNodes' => $schemaExtensionASTNodes,
        ]);
    }

    /**
     * @param Type&NamedType $type
     *
     * @return array<TypeExtensionNode>|null
     */
    protected static function extensionASTNodes(NamedType $type): ?array
    {
        return array_merge(
            $type->extensionASTNodes ?? [],
            static::$typeExtensionsMap[$type->name] ?? []
        );
    }

    protected static function extendScalarType(ScalarType $type): CustomScalarType
    {
        /** @var array<int, ScalarTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = static::extensionASTNodes($type);

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

    protected static function extendUnionType(UnionType $type): UnionType
    {
        /** @var array<int, UnionTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = static::extensionASTNodes($type);

        return new UnionType([
            'name' => $type->name,
            'description' => $type->description,
            'types' => static fn (): array => static::extendUnionPossibleTypes($type),
            'resolveType' => [$type, 'resolveType'],
            'astNode' => $type->astNode,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    protected static function extendEnumType(EnumType $type): EnumType
    {
        /** @var array<int, EnumTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = static::extensionASTNodes($type);

        return new EnumType([
            'name' => $type->name,
            'description' => $type->description,
            'values' => static::extendEnumValueMap($type),
            'astNode' => $type->astNode,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    protected static function extendInputObjectType(InputObjectType $type): InputObjectType
    {
        /** @var array<int, InputObjectTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = static::extensionASTNodes($type);

        return new InputObjectType([
            'name' => $type->name,
            'description' => $type->description,
            'fields' => static fn (): array => static::extendInputFieldMap($type),
            'astNode' => $type->astNode,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    /**
     * @return array<string, UnnamedInputObjectFieldConfig>
     */
    protected static function extendInputFieldMap(InputObjectType $type): array
    {
        /** @var array<string, UnnamedInputObjectFieldConfig> $newFieldMap */
        $newFieldMap = [];

        $oldFieldMap = $type->getFields();
        foreach ($oldFieldMap as $fieldName => $field) {
            $extendedType = static::extendType($field->getType());

            $newFieldConfig = [
                'description' => $field->description,
                'type' => $extendedType,
                'astNode' => $field->astNode,
            ];

            if ($field->defaultValueExists()) {
                $newFieldConfig['defaultValue'] = $field->defaultValue;
            }

            $newFieldMap[$fieldName] = $newFieldConfig;
        }

        if (isset(static::$typeExtensionsMap[$type->name])) {
            foreach (static::$typeExtensionsMap[$type->name] as $extension) {
                assert($extension instanceof InputObjectTypeExtensionNode, 'proven by schema validation');

                foreach ($extension->fields as $field) {
                    $newFieldMap[$field->name->value] = static::$astBuilder->buildInputField($field);
                }
            }
        }

        return $newFieldMap;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function extendEnumValueMap(EnumType $type): array
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

        if (isset(static::$typeExtensionsMap[$type->name])) {
            foreach (static::$typeExtensionsMap[$type->name] as $extension) {
                assert($extension instanceof EnumTypeExtensionNode, 'proven by schema validation');

                foreach ($extension->values as $value) {
                    $newValueMap[$value->name->value] = static::$astBuilder->buildEnumValue($value);
                }
            }
        }

        return $newValueMap;
    }

    /**
     * @return array<int, ObjectType>
     */
    protected static function extendUnionPossibleTypes(UnionType $type): array
    {
        $possibleTypes = array_map(
            [static::class, 'extendNamedType'],
            $type->getTypes()
        );

        if (isset(static::$typeExtensionsMap[$type->name])) {
            foreach (static::$typeExtensionsMap[$type->name] as $extension) {
                assert($extension instanceof UnionTypeExtensionNode, 'proven by schema validation');

                foreach ($extension->types as $namedType) {
                    $possibleTypes[] = static::$astBuilder->buildType($namedType);
                }
            }
        }

        // @phpstan-ignore-next-line proven by schema validation
        return $possibleTypes;
    }

    /**
     * @param ObjectType|InterfaceType $type
     *
     * @return array<int, InterfaceType>
     */
    protected static function extendImplementedInterfaces(ImplementingType $type): array
    {
        $interfaces = array_map(
            [static::class, 'extendNamedType'],
            $type->getInterfaces()
        );

        if (isset(static::$typeExtensionsMap[$type->name])) {
            foreach (static::$typeExtensionsMap[$type->name] as $extension) {
                assert(
                    $extension instanceof ObjectTypeExtensionNode || $extension instanceof InterfaceTypeExtensionNode,
                    'proven by schema validation'
                );

                foreach ($extension->interfaces as $namedType) {
                    $interface = static::$astBuilder->buildType($namedType);
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
    protected static function extendType(Type $typeDef): Type
    {
        if ($typeDef instanceof ListOfType) {
            // @phpstan-ignore-next-line PHPStan does not understand this is the same generic type as the input
            return Type::listOf(static::extendType($typeDef->getWrappedType()));
        }

        if ($typeDef instanceof NonNull) {
            // @phpstan-ignore-next-line PHPStan does not understand this is the same generic type as the input
            return Type::nonNull(static::extendType($typeDef->getWrappedType()));
        }

        // @phpstan-ignore-next-line PHPStan does not understand this is the same generic type as the input
        return static::extendNamedType($typeDef);
    }

    /**
     * @param array<Argument> $args
     *
     * @return array<string, UnnamedArgumentConfig>
     */
    protected static function extendArgs(array $args): array
    {
        $extended = [];
        foreach ($args as $arg) {
            $extendedType = static::extendType($arg->getType());

            $def = [
                'type' => $extendedType,
                'description' => $arg->description,
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
     * @throws Error
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function extendFieldMap(Type $type): array
    {
        $newFieldMap = [];
        $oldFieldMap = $type->getFields();

        foreach (array_keys($oldFieldMap) as $fieldName) {
            $field = $oldFieldMap[$fieldName];

            $newFieldMap[$fieldName] = [
                'name' => $fieldName,
                'description' => $field->description,
                'deprecationReason' => $field->deprecationReason,
                'type' => static::extendType($field->getType()),
                'args' => static::extendArgs($field->args),
                'resolve' => $field->resolveFn,
                'astNode' => $field->astNode,
            ];
        }

        if (isset(static::$typeExtensionsMap[$type->name])) {
            foreach (static::$typeExtensionsMap[$type->name] as $extension) {
                assert(
                    $extension instanceof ObjectTypeExtensionNode || $extension instanceof InterfaceTypeExtensionNode,
                    'proven by schema validation'
                );

                foreach ($extension->fields as $field) {
                    $newFieldMap[$field->name->value] = static::$astBuilder->buildField($field);
                }
            }
        }

        return $newFieldMap;
    }

    protected static function extendObjectType(ObjectType $type): ObjectType
    {
        /** @var array<int, ObjectTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = static::extensionASTNodes($type);

        return new ObjectType([
            'name' => $type->name,
            'description' => $type->description,
            'interfaces' => static fn (): array => static::extendImplementedInterfaces($type),
            'fields' => static fn (): array => static::extendFieldMap($type),
            'isTypeOf' => [$type, 'isTypeOf'],
            'resolveField' => $type->resolveFieldFn ?? null,
            'astNode' => $type->astNode,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    protected static function extendInterfaceType(InterfaceType $type): InterfaceType
    {
        /** @var array<int, InterfaceTypeExtensionNode> $extensionASTNodes */
        $extensionASTNodes = static::extensionASTNodes($type);

        return new InterfaceType([
            'name' => $type->name,
            'description' => $type->description,
            'interfaces' => static fn (): array => static::extendImplementedInterfaces($type),
            'fields' => static fn (): array => static::extendFieldMap($type),
            'resolveType' => [$type, 'resolveType'],
            'astNode' => $type->astNode,
            'extensionASTNodes' => $extensionASTNodes,
        ]);
    }

    protected static function isSpecifiedScalarType(Type $type): bool
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
     * @return T&NamedType
     */
    protected static function extendNamedType(Type $type): Type
    {
        if (Introspection::isIntrospectionType($type) || static::isSpecifiedScalarType($type)) {
            return $type;
        }

        $name = $type->name;
        if (! isset(static::$extendTypeCache[$name])) {
            if ($type instanceof ScalarType) {
                static::$extendTypeCache[$name] = static::extendScalarType($type);
            } elseif ($type instanceof ObjectType) {
                static::$extendTypeCache[$name] = static::extendObjectType($type);
            } elseif ($type instanceof InterfaceType) {
                static::$extendTypeCache[$name] = static::extendInterfaceType($type);
            } elseif ($type instanceof UnionType) {
                static::$extendTypeCache[$name] = static::extendUnionType($type);
            } elseif ($type instanceof EnumType) {
                static::$extendTypeCache[$name] = static::extendEnumType($type);
            } elseif ($type instanceof InputObjectType) {
                static::$extendTypeCache[$name] = static::extendInputObjectType($type);
            }
        }

        // @phpstan-ignore-next-line the lines above ensure only matching subtypes get in here
        return static::$extendTypeCache[$name];
    }

    /**
     * @template T of Type
     *
     * @param (T&NamedType)|null $type
     *
     * @return (T&NamedType)|null
     */
    protected static function extendMaybeNamedType(?Type $type = null): ?Type
    {
        if ($type !== null) {
            return static::extendNamedType($type);
        }

        return null;
    }

    /**
     * @param array<DirectiveDefinitionNode> $directiveDefinitions
     *
     * @return array<int, Directive>
     */
    protected static function getMergedDirectives(Schema $schema, array $directiveDefinitions): array
    {
        $directives = array_map(
            [static::class, 'extendDirective'],
            $schema->getDirectives()
        );

        if (count($directives) === 0) {
            throw new InvariantViolation('Schema must have default directives.');
        }

        foreach ($directiveDefinitions as $directive) {
            $directives[] = static::$astBuilder->buildDirective($directive);
        }

        return $directives;
    }

    protected static function extendDirective(Directive $directive): Directive
    {
        return new Directive([
            'name' => $directive->name,
            'description' => $directive->description,
            'locations' => $directive->locations,
            'args' => static::extendArgs($directive->args),
            'isRepeatable' => $directive->isRepeatable,
            'astNode' => $directive->astNode,
        ]);
    }
}
