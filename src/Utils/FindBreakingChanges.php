<?php
/**
 * Utility for finding breaking/dangerous changes between two schemas.
 */

namespace GraphQL\Utils;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;

class FindBreakingChanges
{

    const BREAKING_CHANGE_FIELD_CHANGED = 'FIELD_CHANGED_KIND';
    const BREAKING_CHANGE_FIELD_REMOVED = 'FIELD_REMOVED';
    const BREAKING_CHANGE_TYPE_CHANGED = 'TYPE_CHANGED_KIND';
    const BREAKING_CHANGE_TYPE_REMOVED = 'TYPE_REMOVED';
    const BREAKING_CHANGE_TYPE_REMOVED_FROM_UNION = 'TYPE_REMOVED_FROM_UNION';
    const BREAKING_CHANGE_VALUE_REMOVED_FROM_ENUM = 'VALUE_REMOVED_FROM_ENUM';
    const BREAKING_CHANGE_ARG_REMOVED = 'ARG_REMOVED';
    const BREAKING_CHANGE_ARG_CHANGED = 'ARG_CHANGED_KIND';
    const BREAKING_CHANGE_NON_NULL_ARG_ADDED = 'NON_NULL_ARG_ADDED';
    const BREAKING_CHANGE_NON_NULL_INPUT_FIELD_ADDED = 'NON_NULL_INPUT_FIELD_ADDED';
    const BREAKING_CHANGE_INTERFACE_REMOVED_FROM_OBJECT = 'INTERFACE_REMOVED_FROM_OBJECT';

    const DANGEROUS_CHANGE_ARG_DEFAULT_VALUE = 'ARG_DEFAULT_VALUE_CHANGE';
    const DANGEROUS_CHANGE_VALUE_ADDED_TO_ENUM = 'VALUE_ADDED_TO_ENUM';
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
            self::findInterfacesRemovedFromObjectTypes($oldSchema, $newSchema)
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
        Schema $oldSchema, Schema $newSchema
    )
    {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $breakingChanges = [];
        foreach ($oldTypeMap as $typeName => $typeDefinition) {
            if (!isset($newTypeMap[$typeName])) {
                $breakingChanges[] =
                    ['type' => self::BREAKING_CHANGE_TYPE_REMOVED, 'description' => "${typeName} was removed."];
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
        Schema $oldSchema, Schema $newSchema
    )
    {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $breakingChanges = [];
        foreach ($oldTypeMap as $typeName => $typeDefinition) {
            if (!isset($newTypeMap[$typeName])) {
                continue;
            }
            $newTypeDefinition = $newTypeMap[$typeName];
            if (!($typeDefinition instanceof $newTypeDefinition)) {
                $oldTypeKindName = self::typeKindName($typeDefinition);
                $newTypeKindName = self::typeKindName($newTypeDefinition);
                $breakingChanges[] = [
                    'type' => self::BREAKING_CHANGE_TYPE_CHANGED,
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
        Schema $oldSchema, Schema $newSchema
    )
    {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $breakingChanges = [];
        $dangerousChanges = [];
        foreach ($oldTypeMap as $oldTypeName => $oldTypeDefinition) {
            $newTypeDefinition = isset($newTypeMap[$oldTypeName]) ? $newTypeMap[$oldTypeName] : null;
            if (!($oldTypeDefinition instanceof ObjectType || $oldTypeDefinition instanceof InterfaceType) ||
                !($newTypeDefinition instanceof $oldTypeDefinition)) {
                continue;
            }

            $oldTypeFields = $oldTypeDefinition->getFields();
            $newTypeFields = $newTypeDefinition->getFields();

            foreach ($oldTypeFields as $fieldName => $fieldDefinition) {
                if (!isset($newTypeFields[$fieldName])) {
                    continue;
                }

                foreach ($fieldDefinition->args as $oldArgDef) {
                    $newArgs = $newTypeFields[$fieldName]->args;
                    $newArgDef = Utils::find(
                        $newArgs, function ($arg) use ($oldArgDef) {
                        return $arg->name === $oldArgDef->name;
                    }
                    );
                    if (!$newArgDef) {
                        $argName = $oldArgDef->name;
                        $breakingChanges[] = [
                            'type' => self::BREAKING_CHANGE_ARG_REMOVED,
                            'description' => "${oldTypeName}->${fieldName} arg ${argName} was removed"
                        ];
                    } else {
                        $isSafe = self::isChangeSafeForInputObjectFieldOrFieldArg($oldArgDef->getType(), $newArgDef->getType());
                        $oldArgType = $oldArgDef->getType();
                        $oldArgName = $oldArgDef->name;
                        if (!$isSafe) {
                            $newArgType = $newArgDef->getType();
                            $breakingChanges[] = [
                                'type' => self::BREAKING_CHANGE_ARG_CHANGED,
                                'description' => "${oldTypeName}->${fieldName} arg ${oldArgName} has changed type from ${oldArgType} to ${newArgType}."
                            ];
                        } elseif ($oldArgDef->defaultValueExists() && $oldArgDef->defaultValue !== $newArgDef->defaultValue) {
                            $dangerousChanges[] = [
                                'type' => FindBreakingChanges::DANGEROUS_CHANGE_ARG_DEFAULT_VALUE,
                                'description' => "${oldTypeName}->${fieldName} arg ${oldArgName} has changed defaultValue"
                            ];
                        }
                    }
                    // Check if a non-null arg was added to the field
                    foreach ($newTypeFields[$fieldName]->args as $newArgDef) {
                        $oldArgs = $oldTypeFields[$fieldName]->args;
                        $oldArgDef = Utils::find(
                            $oldArgs, function ($arg) use ($newArgDef) {
                                return $arg->name === $newArgDef->name;
                            }
                        );

                        if (!$oldArgDef) {
                            $newTypeName = $newTypeDefinition->name;
                            $newArgName = $newArgDef->name;
                            if ($newArgDef->getType() instanceof NonNull) {
                                $breakingChanges[] = [
                                    'type' => self::BREAKING_CHANGE_NON_NULL_ARG_ADDED,
                                    'description' => "A non-null arg ${newArgName} on ${newTypeName}->${fieldName} was added."
                                ];
                            } else {
                                $dangerousChanges[] = [
                                    'type' => self::DANGEROUS_CHANGE_NULLABLE_ARG_ADDED,
                                    'description' => "A nullable arg ${newArgName} on ${newTypeName}->${fieldName} was added."
                                ];
                            }
                        }
                    }
                }
            }
        }

        return ['breakingChanges' => $breakingChanges, 'dangerousChanges' => $dangerousChanges];
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

    /**
     * @param Schema $oldSchema
     * @param Schema $newSchema
     *
     * @return array
     */
    public static function findFieldsThatChangedTypeOnObjectOrInterfaceTypes(Schema $oldSchema, Schema $newSchema)
    {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $breakingChanges = [];
        foreach ($oldTypeMap as $typeName => $oldType) {
            $newType = isset($newTypeMap[$typeName]) ? $newTypeMap[$typeName] : null;
            if (!($oldType instanceof ObjectType || $oldType instanceof InterfaceType) || !($newType instanceof $oldType)) {
                continue;
            }
            $oldTypeFieldsDef = $oldType->getFields();
            $newTypeFieldsDef = $newType->getFields();
            foreach ($oldTypeFieldsDef as $fieldName => $fieldDefinition) {
                if (!isset($newTypeFieldsDef[$fieldName])) {
                    $breakingChanges[] = ['type' => self::BREAKING_CHANGE_FIELD_REMOVED, 'description' => "${typeName}->${fieldName} was removed."];
                } else {
                    $oldFieldType = $oldTypeFieldsDef[$fieldName]->getType();
                    $newfieldType = $newTypeFieldsDef[$fieldName]->getType();
                    $isSafe = self::isChangeSafeForObjectOrInterfaceField($oldFieldType, $newfieldType);
                    if (!$isSafe) {

                        $oldFieldTypeString = self::isNamedType($oldFieldType) ? $oldFieldType->name : $oldFieldType;
                        $newFieldTypeString = self::isNamedType($newfieldType) ? $newfieldType->name : $newfieldType;
                        $breakingChanges[] = ['type' => self::BREAKING_CHANGE_FIELD_CHANGED, 'description' => "${typeName}->${fieldName} changed type from ${oldFieldTypeString} to ${newFieldTypeString}."];
                    }
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
    public static function findFieldsThatChangedTypeOnInputObjectTypes(
        Schema $oldSchema, Schema $newSchema
    )
    {
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
            foreach ($oldTypeFieldsDef as $fieldName => $fieldDefinition) {
                if (!isset($newTypeFieldsDef[$fieldName])) {
                    $breakingChanges[] = [
                        'type' => self::BREAKING_CHANGE_FIELD_REMOVED,
                        'description' => "${typeName}->${fieldName} was removed."
                    ];
                } else {
                    $oldFieldType = $oldTypeFieldsDef[$fieldName]->getType();
                    $newfieldType = $newTypeFieldsDef[$fieldName]->getType();
                    $isSafe = self::isChangeSafeForInputObjectFieldOrFieldArg($oldFieldType, $newfieldType);
                    if (!$isSafe) {
                        $oldFieldTypeString = self::isNamedType($oldFieldType) ? $oldFieldType->name : $oldFieldType;
                        $newFieldTypeString = self::isNamedType($newfieldType) ? $newfieldType->name : $newfieldType;
                        $breakingChanges[] = [
                            'type' => self::BREAKING_CHANGE_FIELD_CHANGED,
                            'description' => "${typeName}->${fieldName} changed type from ${oldFieldTypeString} to ${newFieldTypeString}."];
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

        return ['breakingChanges' => $breakingChanges, 'dangerousChanges' => $dangerousChanges];

    }

    private static function isChangeSafeForObjectOrInterfaceField(
        Type $oldType, Type $newType
    )
    {
        if (self::isNamedType($oldType)) {
            // if they're both named types, see if their names are equivalent
            return (self::isNamedType($newType) && $oldType->name === $newType->name)
                // moving from nullable to non-null of the same underlying type is safe
                || ($newType instanceof NonNull
                    && self::isChangeSafeForObjectOrInterfaceField(
                        $oldType, $newType->getWrappedType()
                    ));
        } elseif ($oldType instanceof ListOfType) {
            // if they're both lists, make sure the underlying types are compatible
            return ($newType instanceof ListOfType &&
                    self::isChangeSafeForObjectOrInterfaceField($oldType->getWrappedType(), $newType->getWrappedType())) ||
                // moving from nullable to non-null of the same underlying type is safe
                ($newType instanceof NonNull &&
                    self::isChangeSafeForObjectOrInterfaceField($oldType, $newType->getWrappedType()));
        } elseif ($oldType instanceof NonNull) {
            // if they're both non-null, make sure the underlying types are compatible
            return $newType instanceof NonNull &&
                self::isChangeSafeForObjectOrInterfaceField($oldType->getWrappedType(), $newType->getWrappedType());
        }

        return false;
    }

    /**
     * @param Type $oldType
     * @param Schema $newSchema
     *
     * @return bool
     */
    private static function isChangeSafeForInputObjectFieldOrFieldArg(
        Type $oldType, Type $newType
    )
    {
        if (self::isNamedType($oldType)) {
            return self::isNamedType($newType) && $oldType->name === $newType->name;
        } elseif ($oldType instanceof ListOfType) {
            return $newType instanceof ListOfType && self::isChangeSafeForInputObjectFieldOrFieldArg($oldType->getWrappedType(), $newType->getWrappedType());
        } elseif ($oldType instanceof NonNull) {
            return (
                    $newType instanceof NonNull && self::isChangeSafeForInputObjectFieldOrFieldArg($oldType->getWrappedType(), $newType->getWrappedType())
                ) || (
                    !($newType instanceof NonNull) && self::isChangeSafeForInputObjectFieldOrFieldArg($oldType->getWrappedType(), $newType)
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
        Schema $oldSchema, Schema $newSchema
    )
    {
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
                    $missingTypeName = $type->name;
                    $typesRemovedFromUnion[] = ['type' => self::BREAKING_CHANGE_TYPE_REMOVED_FROM_UNION, 'description' => "${missingTypeName} was removed from union type ${typeName}."];
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
        Schema $oldSchema, Schema $newSchema
    )
    {
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
                    $addedTypeName = $type->name;
                    $typesAddedToUnion[] = ['type' => self::DANGEROUS_CHANGE_TYPE_ADDED_TO_UNION, 'description' => "${addedTypeName} was added to union type ${typeName}"];
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
        Schema $oldSchema, Schema $newSchema
    )
    {
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
                    $valueName = $value->name;
                    $valuesRemovedFromEnums[] = ['type' => self::BREAKING_CHANGE_VALUE_REMOVED_FROM_ENUM, 'description' => "${valueName} was removed from enum type ${typeName}."];
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
        Schema $oldSchema, Schema $newSchema
    )
    {
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
                    $valueName = $value->name;
                    $valuesAddedToEnums[] = ['type' => self::DANGEROUS_CHANGE_VALUE_ADDED_TO_ENUM, 'description' => "${valueName} was added to enum type ${typeName}"];
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
        Schema $oldSchema, Schema $newSchema
    )
    {
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
                    $oldInterfaceName = $oldInterface->name;
                    $breakingChanges[] = ['type' => self::BREAKING_CHANGE_INTERFACE_REMOVED_FROM_OBJECT,
                        'description' => "${typeName} no longer implements interface ${oldInterfaceName}."
                    ];
                }
            }
        }
        return $breakingChanges;
    }

    /**
     * @param Type $type
     *
     * @return bool
     */
    private static function isNamedType(Type $type)
    {
        return (
            $type instanceof ScalarType ||
            $type instanceof ObjectType ||
            $type instanceof InterfaceType ||
            $type instanceof UnionType ||
            $type instanceof EnumType ||
            $type instanceof InputObjectType
        );
    }
}
