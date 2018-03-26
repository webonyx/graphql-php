<?php
namespace GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\TypeComparators;
use GraphQL\Utils\Utils;

class SchemaValidationContext
{
    /**
     * @var Error[]
     */
    private $errors = [];

    /**
     * @var Schema
     */
    private $schema;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * @return Error[]
     */
    public function getErrors() {
        return $this->errors;
    }

    public function validateRootTypes() {
        $queryType = $this->schema->getQueryType();
        if (!$queryType) {
            $this->reportError(
                'Query root type must be provided.',
                $this->schema->getAstNode()
            );
        } else if (!$queryType instanceof ObjectType) {
            $this->reportError(
                'Query root type must be Object type, it cannot be ' . Utils::printSafe($queryType) . '.',
                $this->getOperationTypeNode($queryType, 'query')
            );
        }

        $mutationType = $this->schema->getMutationType();
        if ($mutationType && !$mutationType instanceof ObjectType) {
            $this->reportError(
                'Mutation root type must be Object type if provided, it cannot be ' . Utils::printSafe($mutationType) . '.',
                $this->getOperationTypeNode($mutationType, 'mutation')
            );
        }

        $subscriptionType = $this->schema->getSubscriptionType();
        if ($subscriptionType && !$subscriptionType instanceof ObjectType) {
            $this->reportError(
                'Subscription root type must be Object type if provided, it cannot be ' . Utils::printSafe($subscriptionType) . '.',
                $this->getOperationTypeNode($subscriptionType, 'subscription')
            );
        }
    }

    /**
     * @param Type $type
     * @param string $operation
     *
     * @return TypeNode|TypeDefinitionNode
     */
    private function getOperationTypeNode($type, $operation)
    {
        $astNode = $this->schema->getAstNode();

        $operationTypeNode = null;
        if ($astNode instanceof SchemaDefinitionNode) {
            $operationTypeNode = null;

            foreach($astNode->operationTypes as $operationType) {
                if ($operationType->operation === $operation) {
                    $operationTypeNode = $operationType;
                    break;
                }
            }
        }

        return $operationTypeNode ? $operationTypeNode->type : ($type ? $type->astNode : null);
    }

    public function validateDirectives()
    {
        $directives = $this->schema->getDirectives();
        foreach($directives as $directive) {
            // Ensure all directives are in fact GraphQL directives.
            if (!$directive instanceof Directive) {
                $this->reportError(
                    "Expected directive but got: " . Utils::printSafe($directive) . '.',
                    is_object($directive) ? $directive->astNode : null
                );
                continue;
            }

            // Ensure they are named correctly.
            $this->validateName($directive);

            // TODO: Ensure proper locations.

            $argNames = [];
            foreach ($directive->args as $arg) {
                $argName = $arg->name;

                // Ensure they are named correctly.
                $this->validateName($directive);

                if (isset($argNames[$argName])) {
                    $this->reportError(
                        "Argument @{$directive->name}({$argName}:) can only be defined once.",
                        $this->getAllDirectiveArgNodes($directive, $argName)
                    );
                    continue;
                }

                $argNames[$argName] = true;

                // Ensure the type is an input type.
                if (!Type::isInputType($arg->getType())) {
                    $this->reportError(
                        "The type of @{$directive->name}({$argName}:) must be Input Type " .
                        'but got: ' . Utils::printSafe($arg->getType()) . '.',
                        $this->getDirectiveArgTypeNode($directive, $argName)
                    );
                }
            }
        }
    }

    /**
     * @param Type|Directive|FieldDefinition|EnumValueDefinition|InputObjectField $node
     */
    private function validateName($node)
    {
        // Ensure names are valid, however introspection types opt out.
        $error = Utils::isValidNameError($node->name, $node->astNode);
        if ($error && !Introspection::isIntrospectionType($node)) {
            $this->addError($error);
        }
    }

    public function validateTypes()
    {
        $typeMap = $this->schema->getTypeMap();
        foreach($typeMap as $typeName => $type) {
            // Ensure all provided types are in fact GraphQL type.
            if (!$type instanceof NamedType) {
                $this->reportError(
                    "Expected GraphQL named type but got: " . Utils::printSafe($type) . '.',
                    is_object($type) ? $type->astNode : null
                );
                continue;
            }

            $this->validateName($type);

            if ($type instanceof ObjectType) {
                // Ensure fields are valid
                $this->validateFields($type);

                // Ensure objects implement the interfaces they claim to.
                $this->validateObjectInterfaces($type);
            } else if ($type instanceof InterfaceType) {
                // Ensure fields are valid.
                $this->validateFields($type);
            } else if ($type instanceof UnionType) {
                // Ensure Unions include valid member types.
                $this->validateUnionMembers($type);
            } else if ($type instanceof EnumType) {
                // Ensure Enums have valid values.
                $this->validateEnumValues($type);
            } else if ($type instanceof InputObjectType) {
                // Ensure Input Object fields are valid.
                $this->validateInputFields($type);
            }
        }
    }

    /**
     * @param ObjectType|InterfaceType $type
     */
    private function validateFields($type) {
        $fieldMap = $type->getFields();

        // Objects and Interfaces both must define one or more fields.
        if (!$fieldMap) {
            $this->reportError(
                "Type {$type->name} must define one or more fields.",
                $this->getAllObjectOrInterfaceNodes($type)
            );
        }

        foreach ($fieldMap as $fieldName => $field) {
            // Ensure they are named correctly.
            $this->validateName($field);

            // Ensure they were defined at most once.
            $fieldNodes = $this->getAllFieldNodes($type, $fieldName);
            if ($fieldNodes && count($fieldNodes) > 1) {
                $this->reportError(
                    "Field {$type->name}.{$fieldName} can only be defined once.",
                    $fieldNodes
                );
                continue;
            }

            // Ensure the type is an output type
            if (!Type::isOutputType($field->getType())) {
                $this->reportError(
                    "The type of {$type->name}.{$fieldName} must be Output Type " .
                    'but got: ' . Utils::printSafe($field->getType()) . '.',
                    $this->getFieldTypeNode($type, $fieldName)
                );
            }

            // Ensure the arguments are valid
            $argNames = [];
            foreach($field->args as $arg) {
                $argName = $arg->name;

                // Ensure they are named correctly.
                $this->validateName($arg);

                if (isset($argNames[$argName])) {
                    $this->reportError(
                        "Field argument {$type->name}.{$fieldName}({$argName}:) can only " .
                        'be defined once.',
                        $this->getAllFieldArgNodes($type, $fieldName, $argName)
                    );
                }
                $argNames[$argName] = true;

                // Ensure the type is an input type
                if (!Type::isInputType($arg->getType())) {
                    $this->reportError(
                        "The type of {$type->name}.{$fieldName}({$argName}:) must be Input " .
                        'Type but got: '. Utils::printSafe($arg->getType()) . '.',
                        $this->getFieldArgTypeNode($type, $fieldName, $argName)
                    );
                }
            }
        }
    }

    private function validateObjectInterfaces(ObjectType $object) {
        $implementedTypeNames = [];
        foreach($object->getInterfaces() as $iface) {
            if (isset($implementedTypeNames[$iface->name])) {
                $this->reportError(
                    "Type {$object->name} can only implement {$iface->name} once.",
                    $this->getAllImplementsInterfaceNodes($object, $iface)
                );
                continue;
            }
            $implementedTypeNames[$iface->name] = true;
            $this->validateObjectImplementsInterface($object, $iface);
        }
    }

    /**
     * @param ObjectType $object
     * @param InterfaceType $iface
     */
    private function validateObjectImplementsInterface(ObjectType $object, $iface)
    {
        if (!$iface instanceof InterfaceType) {
            $this->reportError(
                "Type {$object->name} must only implement Interface types, " .
                "it cannot implement ". Utils::printSafe($iface) . ".",
                $this->getImplementsInterfaceNode($object, $iface)
            );
            return;
        }

        $objectFieldMap = $object->getFields();
        $ifaceFieldMap = $iface->getFields();

        // Assert each interface field is implemented.
        foreach ($ifaceFieldMap as $fieldName => $ifaceField) {
            $objectField = array_key_exists($fieldName, $objectFieldMap)
                ? $objectFieldMap[$fieldName]
                : null;

            // Assert interface field exists on object.
            if (!$objectField) {
                $this->reportError(
                    "Interface field {$iface->name}.{$fieldName} expected but " .
                    "{$object->name} does not provide it.",
                    [$this->getFieldNode($iface, $fieldName), $object->astNode]
                );
                continue;
            }

            // Assert interface field type is satisfied by object field type, by being
            // a valid subtype. (covariant)
            if (
                !TypeComparators::isTypeSubTypeOf(
                    $this->schema,
                    $objectField->getType(),
                    $ifaceField->getType()
                )
            ) {
                $this->reportError(
                    "Interface field {$iface->name}.{$fieldName} expects type ".
                    "{$ifaceField->getType()} but {$object->name}.{$fieldName} " .
                    "is type " . Utils::printSafe($objectField->getType()) . ".",
                    [
                        $this->getFieldTypeNode($iface, $fieldName),
                        $this->getFieldTypeNode($object, $fieldName),
                    ]
                );
            }

            // Assert each interface field arg is implemented.
            foreach($ifaceField->args as $ifaceArg) {
                $argName = $ifaceArg->name;
                $objectArg = null;

                foreach($objectField->args as $arg) {
                    if ($arg->name === $argName) {
                        $objectArg = $arg;
                        break;
                    }
                }

                // Assert interface field arg exists on object field.
                if (!$objectArg) {
                    $this->reportError(
                        "Interface field argument {$iface->name}.{$fieldName}({$argName}:) " .
                        "expected but {$object->name}.{$fieldName} does not provide it.",
                        [
                            $this->getFieldArgNode($iface, $fieldName, $argName),
                            $this->getFieldNode($object, $fieldName),
                        ]
                    );
                    continue;
                }

                // Assert interface field arg type matches object field arg type.
                // (invariant)
                // TODO: change to contravariant?
                if (!TypeComparators::isEqualType($ifaceArg->getType(), $objectArg->getType())) {
                    $this->reportError(
                        "Interface field argument {$iface->name}.{$fieldName}({$argName}:) ".
                        "expects type " . Utils::printSafe($ifaceArg->getType()) . " but " .
                        "{$object->name}.{$fieldName}({$argName}:) is type " .
                        Utils::printSafe($objectArg->getType()) . ".",
                        [
                            $this->getFieldArgTypeNode($iface, $fieldName, $argName),
                            $this->getFieldArgTypeNode($object, $fieldName, $argName),
                        ]
                    );
                }

                // TODO: validate default values?
            }

            // Assert additional arguments must not be required.
            foreach($objectField->args as $objectArg) {
                $argName = $objectArg->name;
                $ifaceArg = null;

                foreach($ifaceField->args as $arg) {
                    if ($arg->name === $argName) {
                        $ifaceArg = $arg;
                        break;
                    }
                }

                if (!$ifaceArg && $objectArg->getType() instanceof NonNull) {
                    $this->reportError(
                        "Object field argument {$object->name}.{$fieldName}({$argName}:) " .
                        "is of required type " . Utils::printSafe($objectArg->getType()) . " but is not also " .
                        "provided by the Interface field {$iface->name}.{$fieldName}.",
                        [
                            $this->getFieldArgTypeNode($object, $fieldName, $argName),
                            $this->getFieldNode($iface, $fieldName),
                        ]
                    );
                }
            }
        }
    }

    private function validateUnionMembers(UnionType $union)
    {
        $memberTypes = $union->getTypes();

        if (!$memberTypes) {
            $this->reportError(
                "Union type {$union->name} must define one or more member types.",
                $union->astNode
            );
        }

        $includedTypeNames = [];

        foreach($memberTypes as $memberType) {
            if (isset($includedTypeNames[$memberType->name])) {
                $this->reportError(
                    "Union type {$union->name} can only include type ".
                    "{$memberType->name} once.",
                    $this->getUnionMemberTypeNodes($union, $memberType->name)
                );
                continue;
            }
            $includedTypeNames[$memberType->name] = true;
            if (!$memberType instanceof ObjectType) {
                $this->reportError(
                    "Union type {$union->name} can only include Object types, ".
                    "it cannot include " . Utils::printSafe($memberType) . ".",
                    $this->getUnionMemberTypeNodes($union, Utils::printSafe($memberType))
                );
            }
        }
    }

    private function validateEnumValues(EnumType $enumType)
    {
        $enumValues = $enumType->getValues();

        if (!$enumValues) {
            $this->reportError(
                "Enum type {$enumType->name} must define one or more values.",
                $enumType->astNode
            );
        }

        foreach($enumValues as $enumValue) {
            $valueName = $enumValue->name;

            // Ensure no duplicates
            $allNodes = $this->getEnumValueNodes($enumType, $valueName);
            if ($allNodes && count($allNodes) > 1) {
                $this->reportError(
                    "Enum type {$enumType->name} can include value {$valueName} only once.",
                    $allNodes
                );
            }

            // Ensure valid name.
            $this->validateName($enumValue);
            if ($valueName === 'true' || $valueName === 'false' || $valueName === 'null') {
                $this->reportError(
                    "Enum type {$enumType->name} cannot include value: {$valueName}.",
                    $enumValue->astNode
                );
            }
        }
    }

    private function validateInputFields(InputObjectType $inputObj)
    {
        $fieldMap = $inputObj->getFields();

        if (!$fieldMap) {
            $this->reportError(
                "Input Object type {$inputObj->name} must define one or more fields.",
                $inputObj->astNode
            );
        }

        // Ensure the arguments are valid
        foreach ($fieldMap as $fieldName => $field) {
            // Ensure they are named correctly.
            $this->validateName($field);

            // TODO: Ensure they are unique per field.

            // Ensure the type is an input type
            if (!Type::isInputType($field->getType())) {
                $this->reportError(
                    "The type of {$inputObj->name}.{$fieldName} must be Input Type " .
                    "but got: " . Utils::printSafe($field->getType()) . ".",
                    $field->astNode ? $field->astNode->type : null
                );
            }
        }
    }

    /**
     * @param ObjectType|InterfaceType $type
     * @return ObjectTypeDefinitionNode[]|ObjectTypeExtensionNode[]|InterfaceTypeDefinitionNode[]|InterfaceTypeExtensionNode[]
     */
    private function getAllObjectOrInterfaceNodes($type)
    {
        return $type->astNode
            ? ($type->extensionASTNodes
                ? array_merge([$type->astNode], $type->extensionASTNodes)
                : [$type->astNode])
            : ($type->extensionASTNodes ?: []);
    }

    /**
     * @param ObjectType $type
     * @param InterfaceType $iface
     * @return NamedTypeNode|null
     */
    private function getImplementsInterfaceNode(ObjectType $type, $iface)
    {
        $nodes = $this->getAllImplementsInterfaceNodes($type, $iface);
        return $nodes && isset($nodes[0]) ? $nodes[0] : null;
    }

    /**
     * @param ObjectType $type
     * @param InterfaceType $iface
     * @return NamedTypeNode[]
     */
    private function getAllImplementsInterfaceNodes(ObjectType $type, $iface)
    {
        $implementsNodes = [];
        $astNodes = $this->getAllObjectOrInterfaceNodes($type);

        foreach($astNodes as $astNode) {
            if ($astNode && $astNode->interfaces) {
                foreach($astNode->interfaces as $node) {
                    if ($node->name->value === $iface->name) {
                        $implementsNodes[] = $node;
                    }
                }
            }
        }

        return $implementsNodes;
    }

    /**
     * @param ObjectType|InterfaceType $type
     * @param string $fieldName
     * @return FieldDefinitionNode|null
     */
    private function getFieldNode($type, $fieldName)
    {
        $nodes = $this->getAllFieldNodes($type, $fieldName);
        return $nodes && isset($nodes[0]) ? $nodes[0] : null;
    }

    /**
     * @param ObjectType|InterfaceType $type
     * @param string $fieldName
     * @return FieldDefinitionNode[]
     */
    private function getAllFieldNodes($type, $fieldName)
    {
        $fieldNodes = [];
        $astNodes = $this->getAllObjectOrInterfaceNodes($type);
        foreach($astNodes as $astNode) {
            if ($astNode && $astNode->fields) {
                foreach($astNode->fields as $node) {
                    if ($node->name->value === $fieldName) {
                        $fieldNodes[] = $node;
                    }
                }
            }
        }
        return $fieldNodes;
    }

    /**
     * @param ObjectType|InterfaceType $type
     * @param string $fieldName
     * @return TypeNode|null
     */
    private function getFieldTypeNode($type, $fieldName)
    {
        $fieldNode = $this->getFieldNode($type, $fieldName);
        return $fieldNode ? $fieldNode->type : null;
    }

    /**
     * @param ObjectType|InterfaceType $type
     * @param string $fieldName
     * @param string $argName
     * @return InputValueDefinitionNode|null
     */
    private function getFieldArgNode($type, $fieldName, $argName)
    {
        $nodes = $this->getAllFieldArgNodes($type, $fieldName, $argName);
        return $nodes && isset($nodes[0]) ? $nodes[0] : null;
    }

    /**
     * @param ObjectType|InterfaceType $type
     * @param string $fieldName
     * @param string $argName
     * @return InputValueDefinitionNode[]
     */
    private function getAllFieldArgNodes($type, $fieldName, $argName)
    {
        $argNodes = [];
        $fieldNode = $this->getFieldNode($type, $fieldName);
        if ($fieldNode && $fieldNode->arguments) {
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
     * @param string $fieldName
     * @param string $argName
     * @return TypeNode|null
     */
    private function getFieldArgTypeNode($type, $fieldName, $argName)
    {
        $fieldArgNode = $this->getFieldArgNode($type, $fieldName, $argName);
        return $fieldArgNode ? $fieldArgNode->type : null;
    }

    /**
     * @param Directive $directive
     * @param string $argName
     * @return InputValueDefinitionNode[]
     */
    private function getAllDirectiveArgNodes(Directive $directive, $argName)
    {
        $argNodes = [];
        $directiveNode = $directive->astNode;
        if ($directiveNode && $directiveNode->arguments) {
            foreach($directiveNode->arguments as $node) {
                if ($node->name->value === $argName) {
                    $argNodes[] = $node;
                }
            }
        }

        return $argNodes;
    }

    /**
     * @param Directive $directive
     * @param string $argName
     * @return TypeNode|null
     */
    private function getDirectiveArgTypeNode(Directive $directive, $argName)
    {
        $argNode = $this->getAllDirectiveArgNodes($directive, $argName)[0];
        return $argNode ? $argNode->type : null;
    }

    /**
     * @param UnionType $union
     * @param string $typeName
     * @return NamedTypeNode[]
     */
    private function getUnionMemberTypeNodes(UnionType $union, $typeName)
    {
        if ($union->astNode && $union->astNode->types) {
            return array_filter(
                $union->astNode->types,
                function (NamedTypeNode $value) use ($typeName) {
                    return $value->name->value === $typeName;
                }
            );
        }
        return $union->astNode ?
            $union->astNode->types : null;
    }

    /**
     * @param EnumType $enum
     * @param string $valueName
     * @return EnumValueDefinitionNode[]
     */
    private function getEnumValueNodes(EnumType $enum, $valueName)
    {
        if ($enum->astNode && $enum->astNode->values) {
            return array_filter(
                iterator_to_array($enum->astNode->values),
                function (EnumValueDefinitionNode $value) use ($valueName) {
                    return $value->name->value === $valueName;
                }
            );
        }
        return $enum->astNode ?
            $enum->astNode->values : null;
    }

    /**
     * @param string $message
     * @param array|Node|TypeNode|TypeDefinitionNode $nodes
     */
    private function reportError($message, $nodes = null) {
        $nodes = array_filter($nodes && is_array($nodes) ? $nodes : [$nodes]);
        $this->addError(new Error($message, $nodes));
    }

    /**
     * @param Error $error
     */
    private function addError($error) {
        $this->errors[] = $error;
    }
}
