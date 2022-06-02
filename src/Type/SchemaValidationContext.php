<?php declare(strict_types=1);

namespace GraphQL\Type;

use function array_filter;
use function array_key_exists;
use function array_merge;
use function count;
use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
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
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaTypeExtensionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ImplementingType;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Validation\InputObjectCircularRefs;
use GraphQL\Utils\TypeComparators;
use GraphQL\Utils\Utils;
use function in_array;
use function is_array;
use function is_object;
use function property_exists;

class SchemaValidationContext
{
    /** @var array<int, Error> */
    private array $errors = [];

    private Schema $schema;

    private InputObjectCircularRefs $inputObjectCircularRefs;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
        $this->inputObjectCircularRefs = new InputObjectCircularRefs($this);
    }

    /**
     * @return array<int, Error>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function validateRootTypes(): void
    {
        if (null === $this->schema->getQueryType()) {
            $this->reportError(
                'Query root type must be provided.',
                $this->schema->getAstNode()
            );
        }

        // Triggers a type error if wrong
        $this->schema->getMutationType();
        $this->schema->getSubscriptionType();
    }

    /**
     * @param array<Node|null>|Node|null $nodes
     */
    public function reportError(string $message, $nodes = null): void
    {
        $nodes = array_filter(is_array($nodes) ? $nodes : [$nodes]);
        $this->addError(new Error($message, $nodes));
    }

    private function addError(Error $error): void
    {
        $this->errors[] = $error;
    }

    public function validateDirectives(): void
    {
        $this->validateDirectiveDefinitions();

        // Validate directives that are used on the schema
        $this->validateDirectivesAtLocation(
            $this->getDirectives($this->schema),
            DirectiveLocation::SCHEMA
        );
    }

    public function validateDirectiveDefinitions(): void
    {
        $directiveDefinitions = [];

        $directives = $this->schema->getDirectives();
        foreach ($directives as $directive) {
            // Ensure all directives are in fact GraphQL directives.
            // @phpstan-ignore-next-line The generic type says this should not happen, but a user may use it wrong nonetheless
            if (! $directive instanceof Directive) {
                $notDirective = Utils::printSafe($directive);
                // @phpstan-ignore-next-line The generic type says this should not happen, but a user may use it wrong nonetheless
                $node = is_object($directive) && property_exists($directive, 'astNode')
                    ? $directive->astNode
                    : null;

                $this->reportError(
                    "Expected directive but got: {$notDirective}.",
                    $node
                );
                continue;
            }

            $existingDefinitions = $directiveDefinitions[$directive->name] ?? [];
            $existingDefinitions[] = $directive;
            $directiveDefinitions[$directive->name] = $existingDefinitions;

            // Ensure they are named correctly.
            $this->validateName($directive);

            // TODO: Ensure proper locations.

            $argNames = [];
            foreach ($directive->args as $arg) {
                // Ensure they are named correctly.
                $this->validateName($arg);

                $argName = $arg->name;

                if (isset($argNames[$argName])) {
                    $this->reportError(
                        "Argument @{$directive->name}({$argName}:) can only be defined once.",
                        $this->getAllDirectiveArgNodes($directive, $argName)
                    );
                    continue;
                }

                $argNames[$argName] = true;

                // Ensure the type is an input type.
                // @phpstan-ignore-next-line necessary until PHP supports union types
                if (Type::isInputType($arg->getType())) {
                    continue;
                }

                $type = Utils::printSafe($arg->getType());
                $this->reportError(
                    "The type of @{$directive->name}({$argName}:) must be Input Type but got: {$type}.",
                    $this->getDirectiveArgTypeNode($directive, $argName)
                );
            }
        }

        foreach ($directiveDefinitions as $directiveName => $directiveList) {
            if (count($directiveList) <= 1) {
                continue;
            }

            $node = [];
            foreach ($directiveList as $dir) {
                if (null === $dir->astNode) {
                    continue;
                }

                $node[] = $dir->astNode;
            }

            $this->reportError(
                "Directive @{$directiveName} defined multiple times.",
                $node
            );
        }
    }

    /**
     * @param (Type&NamedType)|Directive|FieldDefinition|EnumValueDefinition|InputObjectField|Argument $object
     */
    private function validateName(object $object): void
    {
        // Ensure names are valid, however introspection types opt out.
        $error = Utils::isValidNameError($object->name, $object->astNode);
        if (
            null === $error
            || ($object instanceof Type && Introspection::isIntrospectionType($object))
        ) {
            return;
        }

        $this->addError($error);
    }

    /**
     * @return array<int, InputValueDefinitionNode>
     */
    private function getAllDirectiveArgNodes(Directive $directive, string $argName): array
    {
        $astNode = $directive->astNode;
        if (null === $astNode) {
            return [];
        }

        $matchingSubnodes = [];
        foreach ($astNode->arguments as $subNode) {
            if ($subNode->name->value !== $argName) {
                continue;
            }

            $matchingSubnodes[] = $subNode;
        }

        return $matchingSubnodes;
    }

    /**
     * @return NamedTypeNode|ListTypeNode|NonNullTypeNode|null
     */
    private function getDirectiveArgTypeNode(Directive $directive, string $argName): ?TypeNode
    {
        $argNode = $this->getAllDirectiveArgNodes($directive, $argName)[0] ?? null;

        return null === $argNode
            ? null
            : $argNode->type;
    }

    public function validateTypes(): void
    {
        $typeMap = $this->schema->getTypeMap();
        foreach ($typeMap as $type) {
            // Ensure all provided types are in fact GraphQL type.
            // @phpstan-ignore-next-line The generic type says this should not happen, but a user may use it wrong nonetheless
            if (! $type instanceof NamedType) {
                $notNamedType = Utils::printSafe($type);
                // @phpstan-ignore-next-line The generic type says this should not happen, but a user may use it wrong nonetheless
                $node = $type instanceof Type
                    ? $type->astNode
                    : null;

                $this->reportError(
                    "Expected GraphQL named type but got: {$notNamedType}.",
                    $node
                );
                continue;
            }

            $this->validateName($type);

            if ($type instanceof ObjectType) {
                // Ensure fields are valid
                $this->validateFields($type);

                // Ensure objects implement the interfaces they claim to.
                $this->validateInterfaces($type);

                // Ensure directives are valid
                $this->validateDirectivesAtLocation(
                    $this->getDirectives($type),
                    DirectiveLocation::OBJECT
                );
            } elseif ($type instanceof InterfaceType) {
                // Ensure fields are valid.
                $this->validateFields($type);

                // Ensure interfaces implement the interfaces they claim to.
                $this->validateInterfaces($type);

                // Ensure directives are valid
                $this->validateDirectivesAtLocation(
                    $this->getDirectives($type),
                    DirectiveLocation::IFACE
                );
            } elseif ($type instanceof UnionType) {
                // Ensure Unions include valid member types.
                $this->validateUnionMembers($type);

                // Ensure directives are valid
                $this->validateDirectivesAtLocation(
                    $this->getDirectives($type),
                    DirectiveLocation::UNION
                );
            } elseif ($type instanceof EnumType) {
                // Ensure Enums have valid values.
                $this->validateEnumValues($type);

                // Ensure directives are valid
                $this->validateDirectivesAtLocation(
                    $this->getDirectives($type),
                    DirectiveLocation::ENUM
                );
            } elseif ($type instanceof InputObjectType) {
                // Ensure Input Object fields are valid.
                $this->validateInputFields($type);

                // Ensure directives are valid
                $this->validateDirectivesAtLocation(
                    $this->getDirectives($type),
                    DirectiveLocation::INPUT_OBJECT
                );

                // Ensure Input Objects do not contain non-nullable circular references
                $this->inputObjectCircularRefs->validate($type);
            } elseif ($type instanceof ScalarType) {
                // Ensure directives are valid
                $this->validateDirectivesAtLocation(
                    $this->getDirectives($type),
                    DirectiveLocation::SCALAR
                );
            }
        }
    }

    /**
     * @param NodeList<DirectiveNode> $directives
     */
    private function validateDirectivesAtLocation(NodeList $directives, string $location): void
    {
        /** @var array<string, array<int, DirectiveNode>> $potentiallyDuplicateDirectives */
        $potentiallyDuplicateDirectives = [];
        $schema = $this->schema;
        foreach ($directives as $directive) {
            $directiveName = $directive->name->value;

            // Ensure directive used is also defined
            $schemaDirective = $schema->getDirective($directiveName);
            if (null === $schemaDirective) {
                $this->reportError(
                    "No directive @{$directiveName} defined.",
                    $directive
                );
                continue;
            }

            $includes = Utils::some(
                $schemaDirective->locations,
                static function ($schemaLocation) use ($location): bool {
                    return $schemaLocation === $location;
                }
            );
            if (! $includes) {
                $errorNodes = null === $schemaDirective->astNode
                    ? [$directive]
                    : [$directive, $schemaDirective->astNode];
                $this->reportError(
                    "Directive @{$directiveName} not allowed at {$location} location.",
                    $errorNodes
                );
            }

            if ($schemaDirective->isRepeatable) {
                continue;
            }

            $existingNodes = $potentiallyDuplicateDirectives[$directiveName] ?? [];
            $existingNodes[] = $directive;
            $potentiallyDuplicateDirectives[$directiveName] = $existingNodes;
        }

        foreach ($potentiallyDuplicateDirectives as $directiveName => $directiveList) {
            if (count($directiveList) <= 1) {
                continue;
            }

            $this->reportError(
                "Non-repeatable directive @{$directiveName} used more than once at the same location.",
                $directiveList
            );
        }
    }

    /**
     * @param ObjectType|InterfaceType $type
     */
    private function validateFields(Type $type): void
    {
        $fieldMap = $type->getFields();

        // Objects and Interfaces both must define one or more fields.
        if ([] === $fieldMap) {
            $this->reportError(
                "Type {$type->name} must define one or more fields.",
                $this->getAllNodes($type)
            );
        }

        foreach ($fieldMap as $fieldName => $field) {
            // Ensure they are named correctly.
            $this->validateName($field);

            // Ensure they were defined at most once.
            $fieldNodes = $this->getAllFieldNodes($type, $fieldName);
            if (count($fieldNodes) > 1) {
                $this->reportError(
                    "Field {$type->name}.{$fieldName} can only be defined once.",
                    $fieldNodes
                );
                continue;
            }

            // Ensure the type is an output type
            // @phpstan-ignore-next-line not statically provable until we can use union types
            if (! Type::isOutputType($field->getType())) {
                $safeFieldType = Utils::printSafe($field->getType());
                $this->reportError(
                    "The type of {$type->name}.{$fieldName} must be Output Type but got: {$safeFieldType}.",
                    $this->getFieldTypeNode($type, $fieldName)
                );
            }

            // Ensure the arguments are valid
            $argNames = [];
            foreach ($field->args as $arg) {
                $argName = $arg->name;

                // Ensure they are named correctly.
                $this->validateName($arg);

                if (isset($argNames[$argName])) {
                    $this->reportError(
                        "Field argument {$type->name}.{$fieldName}({$argName}:) can only be defined once.",
                        $this->getAllFieldArgNodes($type, $fieldName, $argName)
                    );
                }

                $argNames[$argName] = true;

                // Ensure the type is an input type
                // @phpstan-ignore-next-line the type of $arg->getType() says it is an input type, but it might not always be true
                if (! Type::isInputType($arg->getType())) {
                    $safeType = Utils::printSafe($arg->getType());
                    $this->reportError(
                        "The type of {$type->name}.{$fieldName}({$argName}:) must be Input Type but got: {$safeType}.",
                        $this->getFieldArgTypeNode($type, $fieldName, $argName)
                    );
                }

                // Ensure argument definition directives are valid
                if (! isset($arg->astNode, $arg->astNode->directives)) {
                    continue;
                }

                $this->validateDirectivesAtLocation(
                    $arg->astNode->directives,
                    DirectiveLocation::ARGUMENT_DEFINITION
                );
            }

            // Ensure any directives are valid
            if (! isset($field->astNode, $field->astNode->directives)) {
                continue;
            }

            $this->validateDirectivesAtLocation(
                $field->astNode->directives,
                DirectiveLocation::FIELD_DEFINITION
            );
        }
    }

    /**
     * @param Schema|ObjectType|InterfaceType|UnionType|EnumType|InputObjectType|Directive $obj
     *
     * @return array<int, SchemaDefinitionNode|SchemaTypeExtensionNode>|array<int, ObjectTypeDefinitionNode|ObjectTypeExtensionNode>|array<int, InterfaceTypeDefinitionNode|InterfaceTypeExtensionNode>|array<int, UnionTypeDefinitionNode|UnionTypeExtensionNode>|array<int, EnumTypeDefinitionNode|EnumTypeExtensionNode>|array<int, InputObjectTypeDefinitionNode|InputObjectTypeExtensionNode>|array<int, DirectiveDefinitionNode>
     */
    private function getAllNodes(object $obj): array
    {
        if ($obj instanceof Schema) {
            $astNode = $obj->getAstNode();
            $extensionNodes = $obj->extensionASTNodes;
        } elseif ($obj instanceof Directive) {
            $astNode = $obj->astNode;
            $extensionNodes = [];
        } else {
            $astNode = $obj->astNode;
            $extensionNodes = $obj->extensionASTNodes;
        }

        return null !== $astNode
            ? array_merge([$astNode], $extensionNodes)
            : $extensionNodes;
    }

    /**
     * @param ObjectType|InterfaceType $type
     *
     * @return array<int, FieldDefinitionNode>
     */
    private function getAllFieldNodes(Type $type, string $fieldName): array
    {
        $allNodes = null !== $type->astNode
            ? array_merge([$type->astNode], $type->extensionASTNodes)
            : $type->extensionASTNodes;

        $matchingFieldNodes = [];

        foreach ($allNodes as $node) {
            foreach ($node->fields as $field) {
                if ($field->name->value !== $fieldName) {
                    continue;
                }

                $matchingFieldNodes[] = $field;
            }
        }

        return $matchingFieldNodes;
    }

    /**
     * @param ObjectType|InterfaceType $type
     *
     * @return NamedTypeNode|ListTypeNode|NonNullTypeNode|null
     */
    private function getFieldTypeNode(Type $type, string $fieldName): ?TypeNode
    {
        $fieldNode = $this->getFieldNode($type, $fieldName);

        return null === $fieldNode
            ? null
            : $fieldNode->type;
    }

    /**
     * @param ObjectType|InterfaceType $type
     */
    private function getFieldNode(Type $type, string $fieldName): ?FieldDefinitionNode
    {
        $nodes = $this->getAllFieldNodes($type, $fieldName);

        return $nodes[0] ?? null;
    }

    /**
     * @param ObjectType|InterfaceType $type
     *
     * @return array<int, InputValueDefinitionNode>
     */
    private function getAllFieldArgNodes(Type $type, string $fieldName, string $argName): array
    {
        $argNodes = [];
        $fieldNode = $this->getFieldNode($type, $fieldName);
        if (null !== $fieldNode) {
            foreach ($fieldNode->arguments as $node) {
                if ($node->name->value === $argName) {
                    $argNodes[] = $node;
                }
            }
        }

        return $argNodes;
    }

    /**
     * @param ObjectType|InterfaceType $type
     *
     * @return NamedTypeNode|ListTypeNode|NonNullTypeNode|null
     */
    private function getFieldArgTypeNode(Type $type, string $fieldName, string $argName): ?TypeNode
    {
        $fieldArgNode = $this->getFieldArgNode($type, $fieldName, $argName);

        return null === $fieldArgNode
            ? null
            : $fieldArgNode->type;
    }

    /**
     * @param ObjectType|InterfaceType $type
     */
    private function getFieldArgNode(Type $type, string $fieldName, string $argName): ?InputValueDefinitionNode
    {
        $nodes = $this->getAllFieldArgNodes($type, $fieldName, $argName);

        return $nodes[0] ?? null;
    }

    /**
     * @param ObjectType|InterfaceType $type
     */
    private function validateInterfaces(ImplementingType $type): void
    {
        $ifaceTypeNames = [];
        foreach ($type->getInterfaces() as $interface) {
            // @phpstan-ignore-next-line The generic type says this should not happen, but a user may use it wrong nonetheless
            if (! $interface instanceof InterfaceType) {
                $notInterface = Utils::printSafe($interface);
                $this->reportError(
                    "Type {$type->name} must only implement Interface types, it cannot implement {$notInterface}.",
                    $this->getImplementsInterfaceNode($type, $interface)
                );
                continue;
            }

            if ($type === $interface) {
                $this->reportError(
                    "Type {$type->name} cannot implement itself because it would create a circular reference.",
                    $this->getImplementsInterfaceNode($type, $interface)
                );
                continue;
            }

            if (isset($ifaceTypeNames[$interface->name])) {
                $this->reportError(
                    "Type {$type->name} can only implement {$interface->name} once.",
                    $this->getAllImplementsInterfaceNodes($type, $interface)
                );
                continue;
            }

            $ifaceTypeNames[$interface->name] = true;

            $this->validateTypeImplementsAncestors($type, $interface);
            $this->validateTypeImplementsInterface($type, $interface);
        }
    }

    /**
     * @param Schema|(Type&NamedType) $object
     *
     * @return NodeList<DirectiveNode>
     */
    private function getDirectives(object $object): NodeList
    {
        $directives = [];
        /**
         * Excluding directiveNode, since $object is not Directive.
         *
         * @var SchemaDefinitionNode|SchemaTypeExtensionNode|ObjectTypeDefinitionNode|ObjectTypeExtensionNode|InterfaceTypeDefinitionNode|InterfaceTypeExtensionNode|UnionTypeDefinitionNode|UnionTypeExtensionNode|EnumTypeDefinitionNode|EnumTypeExtensionNode|InputObjectTypeDefinitionNode|InputObjectTypeExtensionNode $node
         */
        // @phpstan-ignore-next-line union types are not pervasive
        foreach ($this->getAllNodes($object) as $node) {
            foreach ($node->directives as $directive) {
                $directives[] = $directive;
            }
        }

        return new NodeList($directives);
    }

    /**
     * @param ObjectType|InterfaceType $type
     * @param Type                     &NamedType $shouldBeInterface
     */
    private function getImplementsInterfaceNode(ImplementingType $type, NamedType $shouldBeInterface): ?NamedTypeNode
    {
        $nodes = $this->getAllImplementsInterfaceNodes($type, $shouldBeInterface);

        return $nodes[0] ?? null;
    }

    /**
     * @param ObjectType|InterfaceType $type
     * @param Type                     &NamedType $shouldBeInterface
     *
     * @return array<int, NamedTypeNode>
     */
    private function getAllImplementsInterfaceNodes(ImplementingType $type, NamedType $shouldBeInterface): array
    {
        $allNodes = null !== $type->astNode
            ? array_merge([$type->astNode], $type->extensionASTNodes)
            : $type->extensionASTNodes;

        $shouldBeInterfaceName = $shouldBeInterface->name;
        $matchingInterfaceNodes = [];

        foreach ($allNodes as $node) {
            foreach ($node->interfaces as $interface) {
                if ($interface->name->value !== $shouldBeInterfaceName) {
                    continue;
                }

                $matchingInterfaceNodes[] = $interface;
            }
        }

        return $matchingInterfaceNodes;
    }

    /**
     * @param ObjectType|InterfaceType $type
     */
    private function validateTypeImplementsInterface(ImplementingType $type, InterfaceType $iface): void
    {
        $typeFieldMap = $type->getFields();
        $ifaceFieldMap = $iface->getFields();

        // Assert each interface field is implemented.
        foreach ($ifaceFieldMap as $fieldName => $ifaceField) {
            $typeField = array_key_exists($fieldName, $typeFieldMap)
                ? $typeFieldMap[$fieldName]
                : null;

            // Assert interface field exists on type.
            if (null === $typeField) {
                $this->reportError(
                    "Interface field {$iface->name}.{$fieldName} expected but {$type->name} does not provide it.",
                    array_merge(
                        [$this->getFieldNode($iface, $fieldName)],
                        $this->getAllNodes($type)
                    )
                );
                continue;
            }

            // Assert interface field type is satisfied by type field type, by being
            // a valid subtype. (covariant)
            if (
                ! TypeComparators::isTypeSubTypeOf(
                    $this->schema,
                    $typeField->getType(),
                    $ifaceField->getType()
                )
            ) {
                $safeType = Utils::printSafe($typeField->getType());
                $this->reportError(
                    "Interface field {$iface->name}.{$fieldName} expects type {$ifaceField->getType()} but {$type->name}.{$fieldName} is type {$safeType}.",
                    [
                        $this->getFieldTypeNode($iface, $fieldName),
                        $this->getFieldTypeNode($type, $fieldName),
                    ]
                );
            }

            // Assert each interface field arg is implemented.
            foreach ($ifaceField->args as $ifaceArg) {
                $argName = $ifaceArg->name;
                $typeArg = null;

                foreach ($typeField->args as $arg) {
                    if ($arg->name === $argName) {
                        $typeArg = $arg;
                        break;
                    }
                }

                // Assert interface field arg exists on type field.
                if (null === $typeArg) {
                    $this->reportError(
                        "Interface field argument {$iface->name}.{$fieldName}({$argName}:) expected but {$type->name}.{$fieldName} does not provide it.",
                        [
                            $this->getFieldArgNode($iface, $fieldName, $argName),
                            $this->getFieldNode($type, $fieldName),
                        ]
                    );
                    continue;
                }

                // Assert interface field arg type matches type field arg type.
                // (invariant)
                // TODO: change to contravariant?
                if (! TypeComparators::isEqualType($ifaceArg->getType(), $typeArg->getType())) {
                    $safeIFaceArgType = Utils::printSafe($ifaceArg->getType());
                    $safeTypeArgType = Utils::printSafe($typeArg->getType());

                    $this->reportError(
                        "Interface field argument {$iface->name}.{$fieldName}({$argName}:) expects type {$safeIFaceArgType} but {$type->name}.{$fieldName}({$argName}:) is type {$safeTypeArgType}.",
                        [
                            $this->getFieldArgTypeNode($iface, $fieldName, $argName),
                            $this->getFieldArgTypeNode($type, $fieldName, $argName),
                        ]
                    );
                }

                // TODO: validate default values?
            }

            // Assert additional arguments must not be required.
            foreach ($typeField->args as $typeArg) {
                $argName = $typeArg->name;
                $ifaceArg = null;

                foreach ($ifaceField->args as $arg) {
                    if ($arg->name === $argName) {
                        $ifaceArg = $arg;
                        break;
                    }
                }

                if (null !== $ifaceArg || ! $typeArg->isRequired()) {
                    continue;
                }

                $this->reportError(
                    "Object field {$type->name}.{$fieldName} includes required argument {$argName} that is missing from the Interface field {$iface->name}.{$fieldName}.",
                    [
                        $this->getFieldArgNode($type, $fieldName, $argName),
                        $this->getFieldNode($iface, $fieldName),
                    ]
                );
            }
        }
    }

    /**
     * @param ObjectType|InterfaceType $type
     */
    private function validateTypeImplementsAncestors(ImplementingType $type, InterfaceType $iface): void
    {
        $typeInterfaces = $type->getInterfaces();
        foreach ($iface->getInterfaces() as $transitive) {
            if (in_array($transitive, $typeInterfaces, true)) {
                continue;
            }

            $error = $transitive === $type
                ? "Type {$type->name} cannot implement {$iface->name} because it would create a circular reference."
                : "Type {$type->name} must implement {$transitive->name} because it is implemented by {$iface->name}.";
            $this->reportError(
                $error,
                array_merge(
                    $this->getAllImplementsInterfaceNodes($iface, $transitive),
                    $this->getAllImplementsInterfaceNodes($type, $iface)
                )
            );
        }
    }

    private function validateUnionMembers(UnionType $union): void
    {
        $memberTypes = $union->getTypes();

        if ([] === $memberTypes) {
            $this->reportError(
                "Union type {$union->name} must define one or more member types.",
                $this->getAllNodes($union)
            );
        }

        $includedTypeNames = [];

        foreach ($memberTypes as $memberType) {
            // @phpstan-ignore-next-line The generic type says this should not happen, but a user may use it wrong nonetheless
            if (! $memberType instanceof ObjectType) {
                $notObjectType = Utils::printSafe($memberType);
                $this->reportError(
                    "Union type {$union->name} can only include Object types, it cannot include {$notObjectType}.",
                    $this->getUnionMemberTypeNodes($union, $notObjectType)
                );
                continue;
            }

            if (isset($includedTypeNames[$memberType->name])) {
                $this->reportError(
                    "Union type {$union->name} can only include type {$memberType->name} once.",
                    $this->getUnionMemberTypeNodes($union, $memberType->name)
                );
                continue;
            }

            $includedTypeNames[$memberType->name] = true;
        }
    }

    /**
     * @return array<int, NamedTypeNode>
     */
    private function getUnionMemberTypeNodes(UnionType $union, string $typeName): array
    {
        $allNodes = null !== $union->astNode
            ? array_merge([$union->astNode], $union->extensionASTNodes)
            : $union->extensionASTNodes;

        $types = [];
        foreach ($allNodes as $node) {
            foreach ($node->types as $type) {
                if ($type->name->value !== $typeName) {
                    continue;
                }

                $types[] = $type;
            }
        }

        return $types;
    }

    private function validateEnumValues(EnumType $enumType): void
    {
        $enumValues = $enumType->getValues();

        if ([] === $enumValues) {
            $this->reportError(
                "Enum type {$enumType->name} must define one or more values.",
                $this->getAllNodes($enumType)
            );
        }

        foreach ($enumValues as $enumValue) {
            $valueName = $enumValue->name;

            // Ensure valid name.
            $this->validateName($enumValue);
            if ('true' === $valueName || 'false' === $valueName || 'null' === $valueName) {
                $this->reportError(
                    "Enum type {$enumType->name} cannot include value: {$valueName}.",
                    $enumValue->astNode
                );
            }

            // Ensure valid directives
            if (! isset($enumValue->astNode, $enumValue->astNode->directives)) {
                continue;
            }

            $this->validateDirectivesAtLocation(
                $enumValue->astNode->directives,
                DirectiveLocation::ENUM_VALUE
            );
        }
    }

    private function validateInputFields(InputObjectType $inputObj): void
    {
        $fieldMap = $inputObj->getFields();

        if ([] === $fieldMap) {
            $this->reportError(
                "Input Object type {$inputObj->name} must define one or more fields.",
                $this->getAllNodes($inputObj)
            );
        }

        // Ensure the arguments are valid
        foreach ($fieldMap as $fieldName => $field) {
            // Ensure they are named correctly.
            $this->validateName($field);

            // TODO: Ensure they are unique per field.

            // Ensure the type is an input type.
            $type = $field->getType();
            // @phpstan-ignore-next-line The generic type says this should not happen, but a user may use it wrong nonetheless
            if (! Type::isInputType($type)) {
                $notInputType = Utils::printSafe($type);
                $this->reportError(
                    "The type of {$inputObj->name}.{$fieldName} must be Input Type but got: {$notInputType}.",
                    $field->astNode->type ?? null
                );
            }

            // Ensure valid directives
            if (! isset($field->astNode, $field->astNode->directives)) {
                continue;
            }

            $this->validateDirectivesAtLocation(
                $field->astNode->directives,
                DirectiveLocation::INPUT_FIELD_DEFINITION
            );
        }
    }
}
