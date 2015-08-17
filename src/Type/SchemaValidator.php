<?php
namespace GraphQL\Type;

use GraphQL\Error;
use GraphQL\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

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
                self::interfacePossibleTypesMustImplementTheInterfaceRule()
            ];
        }
        return self::$rules;
    }

    public static function noInputTypesAsOutputFieldsRule()
    {
        return function ($context) {
            $operationMayNotBeInputType = function (Type $type, $operation) {
                if (!Type::isOutputType($type)) {
                    return new Error("Schema $operation type $type must be an object type!");
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