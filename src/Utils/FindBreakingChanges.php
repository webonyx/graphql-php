<?php
/**
 * Utility for finding breaking/dangerous changes between two schemas.
 */

namespace GraphQL\Utils;

use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;

class FindBreakingChanges
{

    const BREAKING_CHANGE_FIELD_CHANGED_KIND = 'FIELD_CHANGED_KIND';
    const BREAKING_CHANGE_FIELD_REMOVED = 'FIELD_REMOVED';
    const BREAKING_CHANGE_TYPE_CHANGED_KIND = 'TYPE_CHANGED_KIND';
    const BREAKING_CHANGE_TYPE_REMOVED = 'TYPE_REMOVED';
    const BREAKING_CHANGE_TYPE_REMOVED_FROM_UNION = 'TYPE_REMOVED_FROM_UNION';
    const BREAKING_CHANGE_VALUE_REMOVED_FROM_ENUM = 'VALUE_REMOVED_FROM_ENUM';
    const BREAKING_CHANGE_ARG_REMOVED = 'ARG_REMOVED';
    const BREAKING_CHANGE_ARG_CHANGED_KIND = 'ARG_CHANGED_KIND';
    const BREAKING_CHANGE_NON_NULL_ARG_ADDED = 'NON_NULL_ARG_ADDED';
    const BREAKING_CHANGE_NON_NULL_INPUT_FIELD_ADDED = 'NON_NULL_INPUT_FIELD_ADDED';
    const BREAKING_CHANGE_INTERFACE_REMOVED_FROM_OBJECT = 'INTERFACE_REMOVED_FROM_OBJECT';
    const BREAKING_CHANGE_DIRECTIVE_REMOVED = 'DIRECTIVE_REMOVED';
    const BREAKING_CHANGE_DIRECTIVE_ARG_REMOVED = 'DIRECTIVE_ARG_REMOVED';
    const BREAKING_CHANGE_DIRECTIVE_LOCATION_REMOVED = 'DIRECTIVE_LOCATION_REMOVED';
    const BREAKING_CHANGE_NON_NULL_DIRECTIVE_ARG_ADDED = 'NON_NULL_DIRECTIVE_ARG_ADDED';

    const DANGEROUS_CHANGE_ARG_DEFAULT_VALUE_CHANGED = 'ARG_DEFAULT_VALUE_CHANGE';
    const DANGEROUS_CHANGE_VALUE_ADDED_TO_ENUM = 'VALUE_ADDED_TO_ENUM';
    const DANGEROUS_CHANGE_INTERFACE_ADDED_TO_OBJECT = 'INTERFACE_ADDED_TO_OBJECT';
    const DANGEROUS_CHANGE_TYPE_ADDED_TO_UNION = 'TYPE_ADDED_TO_UNION';
    const DANGEROUS_CHANGE_NULLABLE_INPUT_FIELD_ADDED = 'NULLABLE_INPUT_FIELD_ADDED';
    const DANGEROUS_CHANGE_NULLABLE_ARG_ADDED = 'NULLABLE_ARG_ADDED';

    /**
     * Given two schemas, returns an Array containing descriptions of all the types
     * of breaking changes covered by the other functions down below.
     *
     * @return array
     */
    public static function findBreakingChanges(Schema $oldSchema, Schema $newSchema)
    {
        return array_merge(
            self::findRemovedTypes($oldSchema, $newSchema),
            self::findTypesThatChangedKind($oldSchema, $newSchema),
            self::findFieldsThatChangedTypeOnObjectOrInterfaceTypes($oldSchema, $newSchema),
            self::findFieldsThatChangedTypeOnInputObjectTypes($oldSchema, $newSchema)['breakingChanges'],
            self::findTypesRemovedFromUnions($oldSchema, $newSchema),
            self::findValuesRemovedFromEnums($oldSchema, $newSchema),
            self::findArgChanges($oldSchema, $newSchema)['breakingChanges'],
            self::findInterfacesRemovedFromObjectTypes($oldSchema, $newSchema),
            self::findRemovedDirectives($oldSchema, $newSchema),
            self::findRemovedDirectiveArgs($oldSchema, $newSchema),
            self::findAddedNonNullDirectiveArgs($oldSchema, $newSchema),
            self::findRemovedDirectiveLocations($oldSchema, $newSchema)
        );
    }

    /**
     * Given two schemas, returns an Array containing descriptions of all the types
     * of potentially dangerous changes covered by the other functions down below.
     *
     * @return array
     */
    public static function findDangerousChanges(Schema $oldSchema, Schema $newSchema)
    {
        return array_merge(
            self::findArgChanges($oldSchema, $newSchema)['dangerousChanges'],
            self::findValuesAddedToEnums($oldSchema, $newSchema),
            self::findInterfacesAddedToObjectTypes($oldSchema, $newSchema),
            self::findTypesAddedToUnions($oldSchema, $newSchema),
            self::findFieldsThatChangedTypeOnInputObjectTypes($oldSchema, $newSchema)['dangerousChanges']
        );
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any breaking
     * changes in the newSchema related to removing an entire type.
     *
     * @return array
     */
    public static function findRemovedTypes(
        Schema $oldSchema,
        Schema $newSchema
    ) {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $breakingChanges = [];
        foreach (array_keys($oldTypeMap) as $typeName) {
            if (!isset($newTypeMap[$typeName])) {
                $breakingChanges[] = [
                    'type' => self::BREAKING_CHANGE_TYPE_REMOVED,
                    'description' => "${typeName} was removed."
                ];
            }
        }
        return $breakingChanges;
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any breaking
     * changes in the newSchema related to changing the type of a type.
     *
     * @return array
     */
    public static function findTypesThatChangedKind(
        Schema $oldSchema,
        Schema $newSchema
    ) {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $breakingChanges = [];
        foreach ($oldTypeMap as $typeName => $oldType) {
            if (!isset($newTypeMap[$typeName])) {
                continue;
            }
            $newType = $newTypeMap[$typeName];
            if (!($oldType instanceof $newType)) {
                $oldTypeKindName = self::typeKindName($oldType);
                $newTypeKindName = self::typeKindName($newType);
                $breakingChanges[] = [
                    'type' => self::BREAKING_CHANGE_TYPE_CHANGED_KIND,
                    'description' => "${typeName} changed from ${oldTypeKindName} to ${newTypeKindName}."
                ];
            }
        }
        return $breakingChanges;
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any
     * breaking or dangerous changes in the newSchema related to arguments
     * (such as removal or change of type of an argument, or a change in an
     * argument's default value).
     *
     * @return array
     */
    public static function findArgChanges(
        Schema $oldSchema,
        Schema $newSchema
    ) {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $breakingChanges = [];
        $dangerousChanges = [];

        foreach ($oldTypeMap as $typeName => $oldType) {
            $newType = isset($newTypeMap[$typeName]) ? $newTypeMap[$typeName] : null;
            if (
                !($oldType instanceof ObjectType || $oldType instanceof InterfaceType) ||
                !($newType instanceof ObjectType || $newType instanceof InterfaceType) ||
                !($newType instanceof $oldType)
            ) {
                continue;
            }

            $oldTypeFields = $oldType->getFields();
            $newTypeFields = $newType->getFields();

            foreach ($oldTypeFields as $fieldName => $oldField) {
                if (!isset($newTypeFields[$fieldName])) {
                    continue;
                }

                foreach ($oldField->args as $oldArgDef) {
                    $newArgs = $newTypeFields[$fieldName]->args;
                    $newArgDef = Utils::find(
                        $newArgs,
                        function ($arg) use ($oldArgDef) {
                            return $arg->name === $oldArgDef->name;
                        }
                    );
                    if (!$newArgDef) {
                        $breakingChanges[] = [
                            'type' => self::BREAKING_CHANGE_ARG_REMOVED,
                            'description' => "${typeName}.${fieldName} arg {$oldArgDef->name} was removed"
                        ];
                    } else {
                        $isSafe = self::isChangeSafeForInputObjectFieldOrFieldArg(
                            $oldArgDef->getType(),
                            $newArgDef->getType()
                        );
                        $oldArgType = $oldArgDef->getType();
                        $oldArgName = $oldArgDef->name;
                        if (!$isSafe) {
                            $newArgType = $newArgDef->getType();
                            $breakingChanges[] = [
                                'type' => self::BREAKING_CHANGE_ARG_CHANGED_KIND,
                                'description' => "${typeName}.${fieldName} arg ${oldArgName} has changed type from ${oldArgType} to ${newArgType}"
                            ];
                        } elseif ($oldArgDef->defaultValueExists() && $oldArgDef->defaultValue !== $newArgDef->defaultValue) {
                            $dangerousChanges[] = [
                                'type' => FindBreakingChanges::DANGEROUS_CHANGE_ARG_DEFAULT_VALUE_CHANGED,
                                'description' => "${typeName}.${fieldName} arg ${oldArgName} has changed defaultValue"
                            ];
                        }
                    }
                    // Check if a non-null arg was added to the field
                    foreach ($newTypeFields[$fieldName]->args as $newArgDef) {
                        $oldArgs = $oldTypeFields[$fieldName]->args;
                        $oldArgDef = Utils::find(
                            $oldArgs,
                            function ($arg) use ($newArgDef) {
                                return $arg->name === $newArgDef->name;
                            }
                        );

                        if (!$oldArgDef) {
                            $newTypeName = $newType->name;
                            $newArgName = $newArgDef->name;
                            if ($newArgDef->getType() instanceof NonNull) {
                                $breakingChanges[] = [
                                    'type' => self::BREAKING_CHANGE_NON_NULL_ARG_ADDED,
                                    'description' => "A non-null arg ${newArgName} on ${newTypeName}.${fieldName} was added"
                                ];
                            } else {
                                $dangerousChanges[] = [
                                    'type' => self::DANGEROUS_CHANGE_NULLABLE_ARG_ADDED,
                                    'description' => "A nullable arg ${newArgName} on ${newTypeName}.${fieldName} was added"
                                ];
                            }
                        }
                    }
                }
            }
        }

        return [
            'breakingChanges' => $breakingChanges,
            'dangerousChanges' => $dangerousChanges,
        ];
    }

    /**
     * @param Type $type
     * @return string
     *
     * @throws \TypeError
     */
    private static function typeKindName(Type $type)
    {
        if ($type instanceof ScalarType) {
            return 'a Scalar type';
        } elseif ($type instanceof ObjectType) {
            return 'an Object type';
        } elseif ($type instanceof InterfaceType) {
            return 'an Interface type';
        } elseif ($type instanceof UnionType) {
            return 'a Union type';
        } elseif ($type instanceof EnumType) {
            return 'an Enum type';
        } elseif ($type instanceof InputObjectType) {
            return 'an Input type';
        }

        throw new \TypeError('unknown type ' . $type->name);
    }

    public static function findFieldsThatChangedTypeOnObjectOrInterfaceTypes(
        Schema $oldSchema,
        Schema $newSchema
    ) {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $breakingChanges = [];
        foreach ($oldTypeMap as $typeName => $oldType) {
            $newType = isset($newTypeMap[$typeName]) ? $newTypeMap[$typeName] : null;
            if (
                !($oldType instanceof ObjectType || $oldType instanceof InterfaceType) ||
                !($newType instanceof ObjectType || $newType instanceof InterfaceType) ||
                !($newType instanceof $oldType)
            ) {
                continue;
            }

            $oldTypeFieldsDef = $oldType->getFields();
            $newTypeFieldsDef = $newType->getFields();
            foreach ($oldTypeFieldsDef as $fieldName => $fieldDefinition) {
                // Check if the field is missing on the type in the new schema.
                if (!isset($newTypeFieldsDef[$fieldName])) {
                    $breakingChanges[] = [
                        'type' => self::BREAKING_CHANGE_FIELD_REMOVED,
                        'description' => "${typeName}.${fieldName} was removed."
                    ];
                } else {
                    $oldFieldType = $oldTypeFieldsDef[$fieldName]->getType();
                    $newFieldType = $newTypeFieldsDef[$fieldName]->getType();
                    $isSafe = self::isChangeSafeForObjectOrInterfaceField(
                        $oldFieldType,
                        $newFieldType
                    );
                    if (!$isSafe) {
                        $oldFieldTypeString = $oldFieldType instanceof NamedType
                            ? $oldFieldType->name
                            : $oldFieldType;
                        $newFieldTypeString = $newFieldType instanceof NamedType
                            ? $newFieldType->name
                            : $newFieldType;
                        $breakingChanges[] = [
                            'type' => self::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                            'description' => "${typeName}.${fieldName} changed type from ${oldFieldTypeString} to ${newFieldTypeString}."
                        ];
                    }
                }
            }
        }
        return $breakingChanges;
    }

    public static function findFieldsThatChangedTypeOnInputObjectTypes(
        Schema $oldSchema,
        Schema $newSchema
    ) {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $breakingChanges = [];
        $dangerousChanges = [];
        foreach ($oldTypeMap as $typeName => $oldType) {
            $newType = isset($newTypeMap[$typeName]) ? $newTypeMap[$typeName] : null;
            if (!($oldType instanceof InputObjectType) || !($newType instanceof InputObjectType)) {
                continue;
            }

            $oldTypeFieldsDef = $oldType->getFields();
            $newTypeFieldsDef = $newType->getFields();
            foreach (array_keys($oldTypeFieldsDef) as $fieldName) {
                if (!isset($newTypeFieldsDef[$fieldName])) {
                    $breakingChanges[] = [
                        'type' => self::BREAKING_CHANGE_FIELD_REMOVED,
                        'description' => "${typeName}.${fieldName} was removed."
                    ];
                } else {
                    $oldFieldType = $oldTypeFieldsDef[$fieldName]->getType();
                    $newFieldType = $newTypeFieldsDef[$fieldName]->getType();

                    $isSafe = self::isChangeSafeForInputObjectFieldOrFieldArg(
                        $oldFieldType,
                        $newFieldType
                    );
                    if (!$isSafe) {
                        $oldFieldTypeString = $oldFieldType instanceof NamedType
                            ? $oldFieldType->name
                            : $oldFieldType;
                        $newFieldTypeString = $newFieldType instanceof NamedType
                            ? $newFieldType->name
                            : $newFieldType;
                        $breakingChanges[] = [
                            'type' => self::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                            'description' => "${typeName}.${fieldName} changed type from ${oldFieldTypeString} to ${newFieldTypeString}."];
                    }
                }
            }
            // Check if a field was added to the input object type
            foreach ($newTypeFieldsDef as $fieldName => $fieldDef) {
                if (!isset($oldTypeFieldsDef[$fieldName])) {
                    $newTypeName = $newType->name;
                    if ($fieldDef->getType() instanceof NonNull) {
                        $breakingChanges[] = [
                            'type' => self::BREAKING_CHANGE_NON_NULL_INPUT_FIELD_ADDED,
                            'description' => "A non-null field ${fieldName} on input type ${newTypeName} was added."
                        ];
                    } else {
                        $dangerousChanges[] = [
                            'type' => self::DANGEROUS_CHANGE_NULLABLE_INPUT_FIELD_ADDED,
                            'description' => "A nullable field ${fieldName} on input type ${newTypeName} was added."
                        ];
                    }
                }
            }
        }

        return [
            'breakingChanges' => $breakingChanges,
            'dangerousChanges' => $dangerousChanges,
        ];

    }

    private static function isChangeSafeForObjectOrInterfaceField(
        Type $oldType,
        Type $newType
    ) {
        if ($oldType instanceof NamedType) {
            return (
                // if they're both named types, see if their names are equivalent
                ($newType instanceof NamedType && $oldType->name === $newType->name) ||
                // moving from nullable to non-null of the same underlying type is safe
                ($newType instanceof NonNull &&
                    self::isChangeSafeForObjectOrInterfaceField($oldType, $newType->getWrappedType())
                )
            );
        } elseif ($oldType instanceof ListOfType) {
            return (
                // if they're both lists, make sure the underlying types are compatible
                ($newType instanceof ListOfType &&
                    self::isChangeSafeForObjectOrInterfaceField($oldType->getWrappedType(), $newType->getWrappedType())) ||
                // moving from nullable to non-null of the same underlying type is safe
                ($newType instanceof NonNull &&
                    self::isChangeSafeForObjectOrInterfaceField($oldType, $newType->getWrappedType()))
            );
        } elseif ($oldType instanceof NonNull) {
            // if they're both non-null, make sure the underlying types are compatible
            return (
                $newType instanceof NonNull &&
                self::isChangeSafeForObjectOrInterfaceField($oldType->getWrappedType(), $newType->getWrappedType())
            );
        }
        return false;
    }

    /**
     * @param Type $oldType
     * @param Type $newType
     *
     * @return bool
     */
    private static function isChangeSafeForInputObjectFieldOrFieldArg(
        Type $oldType,
        Type $newType
    ) {
        if ($oldType instanceof NamedType) {
            // if they're both named types, see if their names are equivalent
            return $newType instanceof NamedType && $oldType->name === $newType->name;
        } elseif ($oldType instanceof ListOfType) {
            // if they're both lists, make sure the underlying types are compatible
            return $newType instanceof ListOfType && self::isChangeSafeForInputObjectFieldOrFieldArg($oldType->getWrappedType(), $newType->getWrappedType());
        } elseif ($oldType instanceof NonNull) {
            return (
                // if they're both non-null, make sure the underlying types are
                // compatible
                ($newType instanceof NonNull &&
                    self::isChangeSafeForInputObjectFieldOrFieldArg(
                        $oldType->getWrappedType(),
                        $newType->getWrappedType()
                    )) ||
                // moving from non-null to nullable of the same underlying type is safe
                (!($newType instanceof NonNull) &&
                    self::isChangeSafeForInputObjectFieldOrFieldArg($oldType->getWrappedType(), $newType))
            );
        }
        return false;
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any breaking
     * changes in the newSchema related to removing types from a union type.
     *
     * @return array
     */
    public static function findTypesRemovedFromUnions(
        Schema $oldSchema,
        Schema $newSchema
    ) {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $typesRemovedFromUnion = [];
        foreach ($oldTypeMap as $typeName => $oldType) {
            $newType = isset($newTypeMap[$typeName]) ? $newTypeMap[$typeName] : null;
            if (!($oldType instanceof UnionType) || !($newType instanceof UnionType)) {
                continue;
            }
            $typeNamesInNewUnion = [];
            foreach ($newType->getTypes() as $type) {
                $typeNamesInNewUnion[$type->name] = true;
            }
            foreach ($oldType->getTypes() as $type) {
                if (!isset($typeNamesInNewUnion[$type->name])) {
                    $typesRemovedFromUnion[] = [
                        'type' => self::BREAKING_CHANGE_TYPE_REMOVED_FROM_UNION,
                        'description' => "{$type->name} was removed from union type ${typeName}.",
                    ];
                }
            }
        }
        return $typesRemovedFromUnion;
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any dangerous
     * changes in the newSchema related to adding types to a union type.
     *
     * @return array
     */
    public static function findTypesAddedToUnions(
        Schema $oldSchema,
        Schema $newSchema
    ) {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $typesAddedToUnion = [];
        foreach ($newTypeMap as $typeName => $newType) {
            $oldType = isset($oldTypeMap[$typeName]) ? $oldTypeMap[$typeName] : null;
            if (!($oldType instanceof UnionType) || !($newType instanceof UnionType)) {
                continue;
            }

            $typeNamesInOldUnion = [];
            foreach ($oldType->getTypes() as $type) {
                $typeNamesInOldUnion[$type->name] = true;
            }
            foreach ($newType->getTypes() as $type) {
                if (!isset($typeNamesInOldUnion[$type->name])) {
                    $typesAddedToUnion[] = [
                        'type' => self::DANGEROUS_CHANGE_TYPE_ADDED_TO_UNION,
                        'description' => "{$type->name} was added to union type ${typeName}.",
                    ];
                }
            }
        }
        return $typesAddedToUnion;
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any breaking
     * changes in the newSchema related to removing values from an enum type.
     *
     * @return array
     */
    public static function findValuesRemovedFromEnums(
        Schema $oldSchema,
        Schema $newSchema
    ) {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $valuesRemovedFromEnums = [];
        foreach ($oldTypeMap as $typeName => $oldType) {
            $newType = isset($newTypeMap[$typeName]) ? $newTypeMap[$typeName] : null;
            if (!($oldType instanceof EnumType) || !($newType instanceof EnumType)) {
                continue;
            }
            $valuesInNewEnum = [];
            foreach ($newType->getValues() as $value) {
                $valuesInNewEnum[$value->name] = true;
            }
            foreach ($oldType->getValues() as $value) {
                if (!isset($valuesInNewEnum[$value->name])) {
                    $valuesRemovedFromEnums[] = [
                        'type' => self::BREAKING_CHANGE_VALUE_REMOVED_FROM_ENUM,
                        'description' => "{$value->name} was removed from enum type ${typeName}.",
                    ];
                }
            }
        }
        return $valuesRemovedFromEnums;
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any dangerous
     * changes in the newSchema related to adding values to an enum type.
     *
     * @return array
     */
    public static function findValuesAddedToEnums(
        Schema $oldSchema,
        Schema $newSchema
    ) {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $valuesAddedToEnums = [];
        foreach ($oldTypeMap as $typeName => $oldType) {
            $newType = isset($newTypeMap[$typeName]) ? $newTypeMap[$typeName] : null;
            if (!($oldType instanceof EnumType) || !($newType instanceof EnumType)) {
                continue;
            }
            $valuesInOldEnum = [];
            foreach ($oldType->getValues() as $value) {
                $valuesInOldEnum[$value->name] = true;
            }
            foreach ($newType->getValues() as $value) {
                if (!isset($valuesInOldEnum[$value->name])) {
                    $valuesAddedToEnums[] = [
                        'type' => self::DANGEROUS_CHANGE_VALUE_ADDED_TO_ENUM,
                        'description' => "{$value->name} was added to enum type ${typeName}.",
                    ];
                }
            }
        }
        return $valuesAddedToEnums;
    }

    /**
     * @param Schema $oldSchema
     * @param Schema $newSchema
     *
     * @return array
     */
    public static function findInterfacesRemovedFromObjectTypes(
        Schema $oldSchema,
        Schema $newSchema
    ) {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();
        $breakingChanges = [];

        foreach ($oldTypeMap as $typeName => $oldType) {
            $newType = isset($newTypeMap[$typeName]) ? $newTypeMap[$typeName] : null;
            if (!($oldType instanceof ObjectType) || !($newType instanceof ObjectType)) {
                continue;
            }

            $oldInterfaces = $oldType->getInterfaces();
            $newInterfaces = $newType->getInterfaces();
            foreach ($oldInterfaces as $oldInterface) {
                if (!Utils::find($newInterfaces, function (InterfaceType $interface) use ($oldInterface) {
                    return $interface->name === $oldInterface->name;
                })) {
                    $breakingChanges[] = [
                        'type' => self::BREAKING_CHANGE_INTERFACE_REMOVED_FROM_OBJECT,
                        'description' => "${typeName} no longer implements interface {$oldInterface->name}."
                    ];
                }
            }
        }
        return $breakingChanges;
    }

    /**
     * @param Schema $oldSchema
     * @param Schema $newSchema
     *
     * @return array
     */
    public static function findInterfacesAddedToObjectTypes(
        Schema $oldSchema,
        Schema $newSchema
    ) {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();
        $interfacesAddedToObjectTypes = [];

        foreach ($newTypeMap as $typeName => $newType) {
            $oldType = isset($oldTypeMap[$typeName]) ? $oldTypeMap[$typeName] : null;
            if (!($oldType instanceof ObjectType) || !($newType instanceof ObjectType)) {
                continue;
            }

            $oldInterfaces = $oldType->getInterfaces();
            $newInterfaces = $newType->getInterfaces();
            foreach ($newInterfaces as $newInterface) {
                if (!Utils::find($oldInterfaces, function (InterfaceType $interface) use ($newInterface) {
                    return $interface->name === $newInterface->name;
                })) {
                    $interfacesAddedToObjectTypes[] = [
                        'type' => self::DANGEROUS_CHANGE_INTERFACE_ADDED_TO_OBJECT,
                        'description' => "{$newInterface->name} added to interfaces implemented by {$typeName}.",
                    ];
                }
            }
        }
        return $interfacesAddedToObjectTypes;
    }

    public static function findRemovedDirectives(Schema $oldSchema, Schema $newSchema)
    {
        $removedDirectives = [];

        $newSchemaDirectiveMap = self::getDirectiveMapForSchema($newSchema);
        foreach($oldSchema->getDirectives() as $directive) {
            if (!isset($newSchemaDirectiveMap[$directive->name])) {
                $removedDirectives[] = [
                    'type' => self::BREAKING_CHANGE_DIRECTIVE_REMOVED,
                    'description' => "{$directive->name} was removed",
                ];
            }
        }

        return $removedDirectives;
    }

    public static function findRemovedArgsForDirectives(Directive $oldDirective, Directive $newDirective)
    {
        $removedArgs = [];
        $newArgMap = self::getArgumentMapForDirective($newDirective);
        foreach((array) $oldDirective->args as $arg) {
            if (!isset($newArgMap[$arg->name])) {
                $removedArgs[] = $arg;
            }
        }

        return $removedArgs;
    }

    public static function findRemovedDirectiveArgs(Schema $oldSchema, Schema $newSchema)
    {
        $removedDirectiveArgs = [];
        $oldSchemaDirectiveMap = self::getDirectiveMapForSchema($oldSchema);

        foreach($newSchema->getDirectives() as $newDirective) {
            if (!isset($oldSchemaDirectiveMap[$newDirective->name])) {
                continue;
            }

            foreach(self::findRemovedArgsForDirectives($oldSchemaDirectiveMap[$newDirective->name], $newDirective) as $arg) {
                $removedDirectiveArgs[] = [
                    'type' => self::BREAKING_CHANGE_DIRECTIVE_ARG_REMOVED,
                    'description' => "{$arg->name} was removed from {$newDirective->name}",
                ];
            }
        }

        return $removedDirectiveArgs;
    }

    public static function findAddedArgsForDirective(Directive $oldDirective, Directive $newDirective)
    {
        $addedArgs = [];
        $oldArgMap = self::getArgumentMapForDirective($oldDirective);
        foreach((array) $newDirective->args as $arg) {
            if (!isset($oldArgMap[$arg->name])) {
                $addedArgs[] = $arg;
            }
        }

        return $addedArgs;
    }

    public static function findAddedNonNullDirectiveArgs(Schema $oldSchema, Schema $newSchema)
    {
        $addedNonNullableArgs = [];
        $oldSchemaDirectiveMap = self::getDirectiveMapForSchema($oldSchema);

        foreach($newSchema->getDirectives() as $newDirective) {
            if (!isset($oldSchemaDirectiveMap[$newDirective->name])) {
                continue;
            }

            foreach(self::findAddedArgsForDirective($oldSchemaDirectiveMap[$newDirective->name], $newDirective) as $arg) {
                if (!$arg->getType() instanceof NonNull) {
                    continue;
                }
                $addedNonNullableArgs[] = [
                    'type' => self::BREAKING_CHANGE_NON_NULL_DIRECTIVE_ARG_ADDED,
                    'description' => "A non-null arg {$arg->name} on directive {$newDirective->name} was added",
                ];
            }
        }

        return $addedNonNullableArgs;
    }

    public static function findRemovedLocationsForDirective(Directive $oldDirective, Directive $newDirective)
    {
        $removedLocations = [];
        $newLocationSet = array_flip($newDirective->locations);
        foreach($oldDirective->locations as $oldLocation) {
            if (!array_key_exists($oldLocation, $newLocationSet)) {
                $removedLocations[] = $oldLocation;
            }
        }

        return $removedLocations;
    }

    public static function findRemovedDirectiveLocations(Schema $oldSchema, Schema $newSchema)
    {
        $removedLocations = [];
        $oldSchemaDirectiveMap = self::getDirectiveMapForSchema($oldSchema);

        foreach($newSchema->getDirectives() as $newDirective) {
            if (!isset($oldSchemaDirectiveMap[$newDirective->name])) {
                continue;
            }

            foreach(self::findRemovedLocationsForDirective($oldSchemaDirectiveMap[$newDirective->name], $newDirective) as $location) {
                $removedLocations[] = [
                    'type' => self::BREAKING_CHANGE_DIRECTIVE_LOCATION_REMOVED,
                    'description' => "{$location} was removed from {$newDirective->name}",
                ];
            }
        }

        return $removedLocations;
    }

    private static function getDirectiveMapForSchema(Schema $schema)
    {
        return Utils::keyMap($schema->getDirectives(), function ($dir) { return $dir->name; });
    }

    private static function getArgumentMapForDirective(Directive $directive)
    {
        return Utils::keyMap($directive->args ?: [], function ($arg) { return $arg->name; });
    }
}
