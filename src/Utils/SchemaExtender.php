<?php

declare(strict_types=1);

namespace GraphQL\Utils;

use function array_keys;
use function array_map;
use function array_merge;
use function count;
use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaTypeExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ImplementingType;
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
 */
class SchemaExtender
{
    /** @var array<string, Type> */
    protected static array $extendTypeCache;

    /** @var array<string, array<TypeExtensionNode>> */
    protected static array $typeExtensionsMap;

    protected static ASTDefinitionBuilder $astBuilder;

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

    /**
     * @param Type&NamedType $type
     *
     * @throws Error
     */
    protected static function assertTypeMatchesExtension(NamedType $type, Node $node): void
    {
        switch (true) {
            case $node instanceof ObjectTypeExtensionNode:
                if (! ($type instanceof ObjectType)) {
                    throw new Error(
                        'Cannot extend non-object type "' . $type->name . '".',
                        [$node]
                    );
                }

                break;
            case $node instanceof InterfaceTypeExtensionNode:
                if (! ($type instanceof InterfaceType)) {
                    throw new Error(
                        'Cannot extend non-interface type "' . $type->name . '".',
                        [$node]
                    );
                }

                break;
            case $node instanceof EnumTypeExtensionNode:
                if (! ($type instanceof EnumType)) {
                    throw new Error(
                        'Cannot extend non-enum type "' . $type->name . '".',
                        [$node]
                    );
                }

                break;
            case $node instanceof UnionTypeExtensionNode:
                if (! ($type instanceof UnionType)) {
                    throw new Error(
                        'Cannot extend non-union type "' . $type->name . '".',
                        [$node]
                    );
                }

                break;
            case $node instanceof InputObjectTypeExtensionNode:
                if (! ($type instanceof InputObjectType)) {
                    throw new Error(
                        'Cannot extend non-input object type "' . $type->name . '".',
                        [$node]
                    );
                }

                break;
        }
    }

    protected static function extendScalarType(ScalarType $type): CustomScalarType
    {
        return new CustomScalarType([
            'name' => $type->name,
            'description' => $type->description,
            'serialize' => [$type, 'serialize'],
            'parseValue' => [$type, 'parseValue'],
            'parseLiteral' => [$type, 'parseLiteral'],
            'astNode' => $type->astNode,
            'extensionASTNodes' => static::extensionASTNodes($type),
        ]);
    }

    protected static function extendUnionType(UnionType $type): UnionType
    {
        return new UnionType([
            'name' => $type->name,
            'description' => $type->description,
            'types' => static fn (): array => static::extendUnionPossibleTypes($type),
            'resolveType' => [$type, 'resolveType'],
            'astNode' => $type->astNode,
            'extensionASTNodes' => static::extensionASTNodes($type),
        ]);
    }

    protected static function extendEnumType(EnumType $type): EnumType
    {
        return new EnumType([
            'name' => $type->name,
            'description' => $type->description,
            'values' => static::extendEnumValueMap($type),
            'astNode' => $type->astNode,
            'extensionASTNodes' => static::extensionASTNodes($type),
        ]);
    }

    protected static function extendInputObjectType(InputObjectType $type): InputObjectType
    {
        return new InputObjectType([
            'name' => $type->name,
            'description' => $type->description,
            'fields' => static fn (): array => static::extendInputFieldMap($type),
            'astNode' => $type->astNode,
            'extensionASTNodes' => static::extensionASTNodes($type),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function extendInputFieldMap(InputObjectType $type): array
    {
        $newFieldMap = [];
        $oldFieldMap = $type->getFields();
        foreach ($oldFieldMap as $fieldName => $field) {
            $newFieldMap[$fieldName] = [
                'description' => $field->description,
                'type' => static::extendType($field->getType()),
                'astNode' => $field->astNode,
            ];

            if (! $field->defaultValueExists()) {
                continue;
            }

            $newFieldMap[$fieldName]['defaultValue'] = $field->defaultValue;
        }

        if (isset(static::$typeExtensionsMap[$type->name])) {
            /**
             * Proven by @see assertTypeMatchesExtension().
             *
             * @var InputObjectTypeExtensionNode $extension
             */
            foreach (static::$typeExtensionsMap[$type->name] as $extension) {
                foreach ($extension->fields as $field) {
                    $fieldName = $field->name->value;
                    if (isset($oldFieldMap[$fieldName])) {
                        throw new Error('Field "' . $type->name . '.' . $fieldName . '" already exists in the schema. It cannot also be defined in this type extension.', [$field]);
                    }

                    $newFieldMap[$fieldName] = static::$astBuilder->buildInputField($field);
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
            /**
             * Proven by @see assertTypeMatchesExtension().
             *
             * @var EnumTypeExtensionNode $extension
             */
            foreach (static::$typeExtensionsMap[$type->name] as $extension) {
                foreach ($extension->values as $value) {
                    $newValueMap[$value->name->value] = static::$astBuilder->buildEnumValue($value);
                }
            }
        }

        return $newValueMap;
    }

    /**
     * @return array<int, Type> Should be ObjectType, will be caught in schema validation
     */
    protected static function extendUnionPossibleTypes(UnionType $type): array
    {
        $possibleTypes = array_map(
            [static::class, 'extendNamedType'],
            $type->getTypes()
        );

        if (isset(static::$typeExtensionsMap[$type->name])) {
            /**
             * Proven by @see assertTypeMatchesExtension().
             *
             * @var UnionTypeExtensionNode $extension
             */
            foreach (static::$typeExtensionsMap[$type->name] as $extension) {
                foreach ($extension->types as $namedType) {
                    $possibleTypes[] = static::$astBuilder->buildType($namedType);
                }
            }
        }

        return $possibleTypes;
    }

    /**
     * @param ObjectType|InterfaceType $type
     *
     * @return array<int, Type> Should be InterfaceType, will be caught in schema validation
     */
    protected static function extendImplementedInterfaces(ImplementingType $type): array
    {
        $interfaces = array_map(
            [static::class, 'extendNamedType'],
            $type->getInterfaces()
        );

        if (isset(static::$typeExtensionsMap[$type->name])) {
            /**
             * Proven by @see assertTypeMatchesExtension().
             *
             * @var ObjectTypeExtensionNode|InterfaceTypeExtensionNode $extension
             */
            foreach (static::$typeExtensionsMap[$type->name] as $extension) {
                foreach ($extension->interfaces as $namedType) {
                    /** @var InterfaceType $interface we know this, but PHP templates cannot express it */
                    $interface = static::$astBuilder->buildType($namedType);
                    $interfaces[] = $interface;
                }
            }
        }

        return $interfaces;
    }

    /**
     * @template T of Type
     * @param T $typeDef
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
     * @return array<string, array<string, mixed>>
     */
    protected static function extendArgs(array $args): array
    {
        $extended = [];
        foreach ($args as $arg) {
            $def = [
                'type' => static::extendType($arg->getType()),
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
            /**
             * Proven by @see assertTypeMatchesExtension().
             *
             * @var ObjectTypeExtensionNode|InterfaceTypeExtensionNode $extension
             */
            foreach (static::$typeExtensionsMap[$type->name] as $extension) {
                foreach ($extension->fields as $field) {
                    $fieldName = $field->name->value;
                    if (isset($oldFieldMap[$fieldName])) {
                        throw new Error('Field "' . $type->name . '.' . $fieldName . '" already exists in the schema. It cannot also be defined in this type extension.', [$field]);
                    }

                    $newFieldMap[$fieldName] = static::$astBuilder->buildField($field);
                }
            }
        }

        return $newFieldMap;
    }

    protected static function extendObjectType(ObjectType $type): ObjectType
    {
        return new ObjectType([
            'name' => $type->name,
            'description' => $type->description,
            'interfaces' => static fn (): array => static::extendImplementedInterfaces($type),
            'fields' => static fn (): array => static::extendFieldMap($type),
            'isTypeOf' => [$type, 'isTypeOf'],
            'resolveField' => $type->resolveFieldFn ?? null,
            'astNode' => $type->astNode,
            'extensionASTNodes' => static::extensionASTNodes($type),
        ]);
    }

    protected static function extendInterfaceType(InterfaceType $type): InterfaceType
    {
        return new InterfaceType([
            'name' => $type->name,
            'description' => $type->description,
            'interfaces' => static fn (): array => static::extendImplementedInterfaces($type),
            'fields' => static fn (): array => static::extendFieldMap($type),
            'resolveType' => [$type, 'resolveType'],
            'astNode' => $type->astNode,
            'extensionASTNodes' => static::extensionASTNodes($type),
        ]);
    }

    protected static function isSpecifiedScalarType(Type $type): bool
    {
        return $type instanceof NamedType
            && (
                Type::STRING === $type->name
                || Type::INT === $type->name
                || Type::FLOAT === $type->name
                || Type::BOOLEAN === $type->name
                || Type::ID === $type->name
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
        if (null !== $type) {
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

        Utils::invariant(count($directives) > 0, 'schema must have default directives');

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

    /**
     * @param array<string, bool> $options
     * @phpstan-param TypeConfigDecorator|null $typeConfigDecorator
     */
    public static function extend(
        Schema $schema,
        DocumentNode $documentAST,
        array $options = [],
        ?callable $typeConfigDecorator = null
    ): Schema {
        if (
            ! ($options['assumeValid'] ?? false)
            && ! ($options['assumeValidSDL'] ?? false)
        ) {
            DocumentValidator::assertValidSDLExtension($documentAST, $schema);
        }

        /** @var array<string, Node&TypeDefinitionNode> $typeDefinitionMap */
        $typeDefinitionMap = [];
        static::$typeExtensionsMap = [];
        $directiveDefinitions = [];
        /** @var SchemaDefinitionNode|null $schemaDef */
        $schemaDef = null;
        /** @var array<int, SchemaTypeExtensionNode> $schemaExtensions */
        $schemaExtensions = [];

        foreach ($documentAST->definitions as $def) {
            if ($def instanceof SchemaDefinitionNode) {
                $schemaDef = $def;
            } elseif ($def instanceof SchemaTypeExtensionNode) {
                $schemaExtensions[] = $def;
            } elseif ($def instanceof TypeDefinitionNode) {
                $typeName = isset($def->name)
                    ? $def->name->value
                    : null;

                try {
                    $type = $schema->getType($typeName);
                } catch (Error $error) {
                    $type = null;
                }

                if (null !== $type) {
                    throw new Error('Type "' . $typeName . '" already exists in the schema. It cannot also be defined in this type definition.', [$def]);
                }

                $typeDefinitionMap[$typeName] = $def;
            } elseif ($def instanceof TypeExtensionNode) {
                $extendedTypeName = $def->name->value;
                $existingType = $schema->getType($extendedTypeName);
                if (null === $existingType) {
                    throw new Error('Cannot extend type "' . $extendedTypeName . '" because it does not exist in the existing schema.', [$def]);
                }

                static::assertTypeMatchesExtension($existingType, $def);
                static::$typeExtensionsMap[$extendedTypeName][] = $def;
            } elseif ($def instanceof DirectiveDefinitionNode) {
                $directiveName = $def->name->value;
                $existingDirective = $schema->getDirective($directiveName);
                if (null !== $existingDirective) {
                    throw new Error('Directive "' . $directiveName . '" already exists in the schema. It cannot be redefined.', [$def]);
                }

                $directiveDefinitions[] = $def;
            }
        }

        if (
            0 === count(static::$typeExtensionsMap)
            && 0 === count($typeDefinitionMap)
            && 0 === count($directiveDefinitions)
            && 0 === count($schemaExtensions)
            && null === $schemaDef
        ) {
            return $schema;
        }

        static::$astBuilder = new ASTDefinitionBuilder(
            $typeDefinitionMap,
            static function (string $typeName) use ($schema): Type {
                $existingType = $schema->getType($typeName);
                if (null !== $existingType) {
                    return static::extendNamedType($existingType);
                }

                throw new Error('Unknown type: "' . $typeName . '". Ensure that this type exists either in the original schema, or is added in a type definition.');
            },
            $typeConfigDecorator
        );

        static::$extendTypeCache = [];

        $operationTypes = [
            'query' => static::extendMaybeNamedType($schema->getQueryType()),
            'mutation' => static::extendMaybeNamedType($schema->getMutationType()),
            'subscription' => static::extendMaybeNamedType($schema->getSubscriptionType()),
        ];

        if (null !== $schemaDef) {
            foreach ($schemaDef->operationTypes as $operationType) {
                $operationTypes[$operationType->operation] = static::$astBuilder->buildType($operationType->type);
            }
        }

        foreach ($schemaExtensions as $schemaExtension) {
            if (! isset($schemaExtension->operationTypes)) {
                continue;
            }

            foreach ($schemaExtension->operationTypes as $operationType) {
                $operationTypes[$operationType->operation] = static::$astBuilder->buildType($operationType->type);
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
}
