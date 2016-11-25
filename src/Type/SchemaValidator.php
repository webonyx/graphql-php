<?php
namespace GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Schema;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils;

class SchemaValidator
{
    private static $rules;

    public static function getAllRules()
    {
        if (null === self::$rules) {
            self::$rules = [
                self::noInputTypesAsOutputFieldsRule(),
                self::noOutputTypesAsInputArgsRule(),
                self::typesInterfacesMustShowThemAsPossibleRule(),
                self::interfacePossibleTypesMustImplementTheInterfaceRule(),
                self::interfacesAreCorrectlyImplemented()
            ];
        }
        return self::$rules;
    }

    public static function noInputTypesAsOutputFieldsRule()
    {
        return function ($context) {
            $operationMayNotBeInputType = function (Type $type, $operation) {
                if (!Type::isOutputType($type)) {
                    return new Error("Schema $operation must be Object Type but got: $type.");
                }
                return null;
            };

            /** @var Schema $schema */
            $schema = $context['schema'];
            $typeMap = $schema->getTypeMap();
            $errors = [];

            $queryType = $schema->getQueryType();
            if ($queryType) {
                $queryError = $operationMayNotBeInputType($queryType, 'query');
                if ($queryError !== null) {
                    $errors[] = $queryError;
                }
            }

            $mutationType = $schema->getMutationType();
            if ($mutationType) {
                $mutationError = $operationMayNotBeInputType($mutationType, 'mutation');
                if ($mutationError !== null) {
                    $errors[] = $mutationError;
                }
            }

            $subscriptionType = $schema->getSubscriptionType();
            if ($subscriptionType) {
                $subscriptionError = $operationMayNotBeInputType($subscriptionType, 'subscription');
                if ($subscriptionError !== null) {
                    $errors[] = $subscriptionError;
                }
            }

            foreach ($typeMap as $typeName => $type) {
                if ($type instanceof ObjectType || $type instanceof InterfaceType) {
                    $fields = $type->getFields();
                    foreach ($fields as $fieldName => $field) {
                        if ($field->getType() instanceof InputObjectType) {
                            $errors[] = new Error(
                                "Field $typeName.{$field->name} is of type " .
                                "{$field->getType()->name}, which is an input type, but field types " .
                                "must be output types!"
                            );
                        }
                    }
                }
            }

            return !empty($errors) ? $errors : null;
        };
    }

    public static function noOutputTypesAsInputArgsRule()
    {
        return function($context) {
            /** @var Schema $schema */
            $schema = $context['schema'];
            $typeMap = $schema->getTypeMap();
            $errors = [];

            foreach ($typeMap as $typeName => $type) {
                if ($type instanceof InputObjectType) {
                    $fields = $type->getFields();

                    foreach ($fields as $fieldName => $field) {
                        if (!Type::isInputType($field->getType())) {
                            $errors[] = new Error(
                                "Input field {$type->name}.{$field->name} has type ".
                                "{$field->getType()}, which is not an input type!"
                            );
                        }
                    }
                }
            }
            return !empty($errors) ? $errors : null;
        };
    }

    public static function interfacePossibleTypesMustImplementTheInterfaceRule()
    {
        return function($context) {
            /** @var Schema $schema */
            $schema = $context['schema'];
            $typeMap = $schema->getTypeMap();
            $errors = [];

            foreach ($typeMap as $typeName => $type) {
                if ($type instanceof InterfaceType) {
                    $possibleTypes = $type->getPossibleTypes();
                    foreach ($possibleTypes as $possibleType) {
                        if (!in_array($type, $possibleType->getInterfaces())) {
                            $errors[] = new Error(
                                "$possibleType is a possible type of interface $type but does " .
                                "not implement it!"
                            );
                        }
                    }
                }
            }

            return !empty($errors) ? $errors : null;
        };
    }

    public static function typesInterfacesMustShowThemAsPossibleRule()
    {
        return function($context) {
            /** @var Schema $schema */
            $schema = $context['schema'];
            $typeMap = $schema->getTypeMap();
            $errors = [];

            foreach ($typeMap as $typeName => $type) {
                if ($type instanceof ObjectType) {
                    $interfaces = $type->getInterfaces();
                    foreach ($interfaces as $interfaceType) {
                        if (!$interfaceType->isPossibleType($type)) {
                            $errors[] = new Error(
                                "$typeName implements interface {$interfaceType->name}, but " .
                                "{$interfaceType->name} does not list it as possible!"
                            );
                        }
                    }
                }
            }
            return !empty($errors) ? $errors : null;
        };
    }

    // Enforce correct interface implementations
    public static function interfacesAreCorrectlyImplemented()
    {
        return function($context) {
            /** @var Schema $schema */
            $schema = $context['schema'];

            $errors = [];
            foreach ($schema->getTypeMap() as $typeName => $type) {
                if ($type instanceof ObjectType) {
                    foreach ($type->getInterfaces() as $iface) {
                        try {
                            // FIXME: rework to return errors instead
                            self::assertObjectImplementsInterface($schema, $type, $iface);
                        } catch (\Exception $e) {
                            $errors[] = $e;
                        }
                    }
                }
            }
            return $errors;
        };
    }

    /**
     * @param ObjectType $object
     * @param InterfaceType $iface
     * @throws \Exception
     */
    private static function assertObjectImplementsInterface(Schema $schema, ObjectType $object, InterfaceType $iface)
    {
        $objectFieldMap = $object->getFields();
        $ifaceFieldMap = $iface->getFields();

        foreach ($ifaceFieldMap as $fieldName => $ifaceField) {
            Utils::invariant(
                isset($objectFieldMap[$fieldName]),
                "\"$iface\" expects field \"$fieldName\" but \"$object\" does not provide it"
            );

            /** @var $ifaceField FieldDefinition */
            /** @var $objectField FieldDefinition */
            $objectField = $objectFieldMap[$fieldName];

            Utils::invariant(
                Utils\TypeInfo::isTypeSubTypeOf($schema, $objectField->getType(), $ifaceField->getType()),
                "$iface.$fieldName expects type \"{$ifaceField->getType()}\" but " .
                "$object.$fieldName provides type \"{$objectField->getType()}\"."
            );

            foreach ($ifaceField->args as $ifaceArg) {
                /** @var $ifaceArg FieldArgument */
                /** @var $objectArg FieldArgument */
                $argName = $ifaceArg->name;
                $objectArg = $objectField->getArg($argName);

                // Assert interface field arg exists on object field.
                Utils::invariant(
                    $objectArg,
                    "$iface.$fieldName expects argument \"$argName\" but $object.$fieldName does not provide it."
                );

                // Assert interface field arg type matches object field arg type.
                // (invariant)
                Utils::invariant(
                    Utils\TypeInfo::isEqualType($ifaceArg->getType(), $objectArg->getType()),
                    "$iface.$fieldName($argName:) expects type \"{$ifaceArg->getType()}\" " .
                    "but $object.$fieldName($argName:) provides " .
                    "type \"{$objectArg->getType()}\""
                );

                // Assert argument set invariance.
                foreach ($objectField->args as $objectArg) {
                    $argName = $objectArg->name;
                    $ifaceArg = $ifaceField->getArg($argName);
                    Utils::invariant(
                        $ifaceArg,
                        "$iface.$fieldName does not define argument \"$argName\" but " .
                        "$object.$fieldName provides it."
                    );
                }
            }
        }
    }

    /**
     * @param Schema $schema
     * @param array <callable>|null $argRules
     * @return array
     */
    public static function validate(Schema $schema, $argRules = null)
    {
        $context = ['schema' => $schema];
        $errors = [];
        $rules = $argRules ?: self::getAllRules();

        for ($i = 0; $i < count($rules); ++$i) {
            $newErrors = call_user_func($rules[$i], $context);
            if ($newErrors) {
                $errors = array_merge($errors, $newErrors);
            }
        }
        return $errors;
    }
}
