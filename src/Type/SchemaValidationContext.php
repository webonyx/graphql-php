<?php
namespace GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\TypeComparators;
use GraphQL\Utils\Utils;

/**
 *
 */
class SchemaValidationContext
{
    /**
     * @var array
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
                'Query root type must be Object type but got: ' . Utils::getVariableType($queryType) . '.',
                $this->getOperationTypeNode($queryType, 'query')
            );
        }

        $mutationType = $this->schema->getMutationType();
        if ($mutationType && !$mutationType instanceof ObjectType) {
            $this->reportError(
                'Mutation root type must be Object type if provided but got: ' . Utils::getVariableType($mutationType) . '.',
                $this->getOperationTypeNode($mutationType, 'mutation')
            );
        }

        $subscriptionType = $this->schema->getSubscriptionType();
        if ($subscriptionType && !$subscriptionType instanceof ObjectType) {
            $this->reportError(
                'Subscription root type must be Object type if provided but got: ' . Utils::getVariableType($subscriptionType) . '.',
                $this->getOperationTypeNode($subscriptionType, 'subscription')
            );
        }
    }

    public function validateDirectives()
    {
        $directives = $this->schema->getDirectives();
        foreach($directives as $directive) {
            if (!$directive instanceof Directive) {
                $this->reportError(
                    "Expected directive but got: " . $directive,
                    is_object($directive) ? $directive->astNode : null
                );
            }
        }
    }

    public function validateTypes()
    {
        $typeMap = $this->schema->getTypeMap();
        foreach($typeMap as $typeName => $type) {
            // Ensure all provided types are in fact GraphQL type.
            if (!Type::isType($type)) {
                $this->reportError(
                    "Expected GraphQL type but got: " . Utils::getVariableType($type),
                    is_object($type) ? $type->astNode : null
                );
            }

            // Ensure objects implement the interfaces they claim to.
            if ($type instanceof ObjectType) {
                $implementedTypeNames = [];

                foreach($type->getInterfaces() as $iface) {
                    if (isset($implementedTypeNames[$iface->name])) {
                        $this->reportError(
                            "{$type->name} must declare it implements {$iface->name} only once.",
                            $this->getAllImplementsInterfaceNode($type, $iface)
                        );
                    }
                    $implementedTypeNames[$iface->name] = true;
                    $this->validateObjectImplementsInterface($type, $iface);
                }
            }
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
                $object .
                " must only implement Interface types, it cannot implement " .
                $iface . ".",
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
                    "\"{$iface->name}\" expects field \"{$fieldName}\" but \"{$object->name}\" does not provide it.",
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
                    "{$iface->name}.{$fieldName} expects type ".
                    "\"{$ifaceField->getType()}\"" .
                    " but {$object->name}.{$fieldName} is type " .
                    "\"{$objectField->getType()}\".",
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
                        "{$iface->name}.{$fieldName} expects argument \"{$argName}\" but ".
                        "{$object->name}.{$fieldName} does not provide it.",
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
                        "{$iface->name}.{$fieldName}({$argName}:) expects type ".
                        "\"{$ifaceArg->getType()}\"" .
                        " but {$object->name}.{$fieldName}({$argName}:) is type " .
                        "\"{$objectArg->getType()}\".",
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
                        "{$object->name}.{$fieldName}({$argName}:) is of required type " .
                        "\"{$objectArg->getType()}\"" .
                        " but is not also provided by the interface {$iface->name}.{$fieldName}.",
                        [
                            $this->getFieldArgTypeNode($object, $fieldName, $argName),
                            $this->getFieldNode($iface, $fieldName),
                        ]
                    );
                }
            }
        }
    }

    /**
     * @param ObjectType $type
     * @param InterfaceType|null $iface
     * @return NamedTypeNode|null
     */
    private function getImplementsInterfaceNode(ObjectType $type, $iface)
    {
        $nodes = $this->getAllImplementsInterfaceNode($type, $iface);
        return $nodes && isset($nodes[0]) ? $nodes[0] : null;
    }

    /**
     * @param ObjectType $type
     * @param InterfaceType|null $iface
     * @return NamedTypeNode[]
     */
    private function getAllImplementsInterfaceNode(ObjectType $type, $iface)
    {
        $implementsNodes = [];
        /** @var ObjectTypeDefinitionNode|ObjectTypeExtensionNode[] $astNodes */
        $astNodes = array_merge([$type->astNode], $type->extensionASTNodes ?: []);

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
        /** @var ObjectTypeDefinitionNode|ObjectTypeExtensionNode[] $astNodes */
        $astNodes = array_merge([$type->astNode], $type->extensionASTNodes ?: []);

        foreach($astNodes as $astNode) {
            if ($astNode && $astNode->fields) {
                foreach($astNode->fields as $node) {
                    if ($node->name->value === $fieldName) {
                        return $node;
                    }
                }
            }
        }
    }

    /**
     * @param ObjectType|InterfaceType $type
     * @param string $fieldName
     * @return TypeNode|null
     */
    private function getFieldTypeNode($type, $fieldName)
    {
        $fieldNode = $this->getFieldNode($type, $fieldName);
        if ($fieldNode) {
            return $fieldNode->type;
        }
    }

    /**
     * @param ObjectType|InterfaceType $type
     * @param string $fieldName
     * @param string $argName
     * @return InputValueDefinitionNode|null
     */
    private function getFieldArgNode($type, $fieldName, $argName)
    {
        $fieldNode = $this->getFieldNode($type, $fieldName);
        if ($fieldNode && $fieldNode->arguments) {
            foreach ($fieldNode->arguments as $node) {
                if ($node->name->value === $argName) {
                    return $node;
                }
            }
        }
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
        if ($fieldArgNode) {
            return $fieldArgNode->type;
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

    /**
     * @param string $message
     * @param array|Node|TypeNode|TypeDefinitionNode $nodes
     */
    private function reportError($message, $nodes = null) {
        $nodes = array_filter($nodes && is_array($nodes) ? $nodes : [$nodes]);
        $this->errors[] = new Error($message, $nodes);
    }
}
