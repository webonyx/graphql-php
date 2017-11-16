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
    /*
    export const DangerousChangeType = {
      ARG_DEFAULT_VALUE_CHANGE: 'ARG_DEFAULT_VALUE_CHANGE',
      VALUE_ADDED_TO_ENUM: 'VALUE_ADDED_TO_ENUM',
      TYPE_ADDED_TO_UNION: 'TYPE_ADDED_TO_UNION',
    };*/

    /**
     * Given two schemas, returns an Array containing descriptions of all the types
     * of potentially dangerous changes covered by the other functions down below.
     *
     * @return array
     */
    public function findDangerousChanges(
        Schema $oldSchema, Schema $newSchema
    )
    {
        return [
            /*   ...findArgChanges(oldSchema, newSchema).dangerousChanges,
               ...findValuesAddedToEnums(oldSchema, newSchema),
               ...findTypesAddedToUnions(oldSchema, newSchema)
             */
        ];
    }

    /**
     * Given two schemas, returns an Array containing descriptions of all the types
     * of breaking changes covered by the other functions down below.
     *
     * @return array
     */
    public function findBreakingChanges(
        $oldSchema, $newSchema
    )
    {
        return [
            /*...findRemovedTypes(oldSchema, newSchema),
            ...findTypesThatChangedKind(oldSchema, newSchema),
            ...findFieldsThatChangedType(oldSchema, newSchema),
            ...findTypesRemovedFromUnions(oldSchema, newSchema),
            ...findValuesRemovedFromEnums(oldSchema, newSchema),
            ...findArgChanges(oldSchema, newSchema).breakingChanges,
            ...findInterfacesRemovedFromObjectTypes(oldSchema, newSchema),
         */
        ];
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any breaking
     * changes in the newSchema related to removing an entire type.
     */
    public function findRemovedTypes(
        Schema $oldSchema, Schema $newSchema
    )
    {
        $oldTypeMap = $oldSchema->getTypeMap();
        $newTypeMap = $newSchema->getTypeMap();

        $breakingChanges = [];
        foreach ($oldTypeMap as $typeName => $typeDefinition) {
            if (!isset($newTypeMap[$typeName])) {
                $breakingChanges[] =
                    ['type' => self::BREAKING_CHANGE_TYPE_REMOVED, 'description' => "${$typeName} was removed."];
            }
        }

        return $breakingChanges;
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any breaking
     * changes in the newSchema related to changing the type of a type.
     */
    public function findTypesThatChangedKind(
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
                    'description' => "${$typeName} changed from ${oldTypeKindName} to ${newTypeKindName}."
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
     */
    public function findArgChanges(
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

                foreach ($fieldDefinition->args as $oldArgName => $oldArgDef) {
                    $newArgs = $newTypeFields[$fieldName]->args;
                    $newArgDef = Utils::find(
                        $newArgs, function ($arg) use ($oldArgDef) {
                        return $arg->name === $oldArgDef->name;
                    }
                    );
                    if (!$newArgDef) {
                        $breakingChanges[] = [
                            'type' => self::BREAKING_CHANGE_ARG_REMOVED,
                            'description' => "${$oldTypeName}->${$fieldName} arg ${oldArgName} was removed"
                        ];
                    } else {
                        $isSafe = self::isChangeSafeForInputObjectFieldOrFieldArg($oldArgDef->getType(), $newArgDef->getType());
                        if (!$isSafe) {
                            $oldArgType = $oldArgDef->getType();
                            $newArgType = $newArgDef->getType();
                            $breakingChanges[] = [
                                'type' => self::BREAKING_CHANGE_ARG_CHANGED,
                                'description' => "${oldTypeName}->${fieldName} arg ${oldArgName} has changed type from ${oldArgType} to ${newArgType}"
                            ];
                        } elseif ($oldArgDef->defaultValueExists() && $oldArgDef->defaultValue !== $newArgDef->defaultValue) {
                            $dangerousChanges[] = []; // TODO
                        }
                    }
                    // Check if a non-null arg was added to the field
                    foreach ($newTypeFields[$fieldName]->args as $newArgName => $newArgDef) {
                        $oldArgs = $oldTypeFields[$fieldName]->args;
                        $oldArgDef = Utils::find(
                            $oldArgs, function ($arg) use ($newArgDef) {
                            return $arg->name === $newArgDef->name;
                        }
                        );

                        if (!$oldArgDef && $newArgDef->getType() instanceof NonNull) {
                            $newTypeName = $newTypeDefinition->name;
                            $breakingChanges[] = [
                                'type' => self::BREAKING_CHANGE_NON_NULL_ARG_ADDED,
                                'description' => "A non-null arg ${$newArgName} on ${newTypeName}->${fieldName} was added."
                            ];
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
     * Given two schemas, returns an Array containing descriptions of any breaking
     * changes in the newSchema related to the fields on a type. This includes if
     * a field has been removed from a type, if a field has changed type, or if
     * a non-null field is added to an input type.
     */
    public static function findFieldsThatChangedType(
        Schema $oldSchema, Schema $newSchema
    )
    {
        return array_merge(
            self::findFieldsThatChangedTypeOnObjectOrInterfaceTypes($oldSchema, $newSchema),
            self::findFieldsThatChangedTypeOnInputObjectTypes($oldSchema, $newSchema)
        );
    }

    /**
     * @param $oldSchema
     * @param $newSchema
     *
     * @return array
     */
    private static function findFieldsThatChangedTypeOnObjectOrInterfaceTypes(
        $oldSchema, $newSchema
    )
    {
        /*const oldTypeMap = oldSchema.getTypeMap();
        const newTypeMap = newSchema.getTypeMap();

        const breakingFieldChanges = [];
        Object.keys(oldTypeMap).forEach(typeName => {
          const oldType = oldTypeMap[typeName];
          const newType = newTypeMap[typeName];
          if (
              !(oldType instanceof GraphQLObjectType ||
                oldType instanceof GraphQLInterfaceType) ||
              !(newType instanceof oldType.constructor)
          ) {
            return;
          }

          const oldTypeFieldsDef = oldType.getFields();
          const newTypeFieldsDef = newType.getFields();
          Object.keys(oldTypeFieldsDef).forEach(fieldName => {
            // Check if the field is missing on the type in the new schema.
            if (!(fieldName in newTypeFieldsDef)) {
              breakingFieldChanges.push({
                type: BreakingChangeType.FIELD_REMOVED,
                description: `${typeName}.${fieldName} was removed.`,
              });
            } else {
              const oldFieldType = oldTypeFieldsDef[fieldName].type;
              const newFieldType = newTypeFieldsDef[fieldName].type;
              const isSafe =
              isChangeSafeForObjectOrInterfaceField(oldFieldType, newFieldType);
              if (!isSafe) {
                const oldFieldTypeString = isNamedType(oldFieldType) ?
                    oldFieldType.name :
                    oldFieldType.toString();
                const newFieldTypeString = isNamedType(newFieldType) ?
                    newFieldType.name :
                    newFieldType.toString();
                breakingFieldChanges.push({
                  type: BreakingChangeType.FIELD_CHANGED_KIND,
                  description: `${typeName}.${fieldName} changed type from ` +
                               `${oldFieldTypeString} to ${newFieldTypeString}.`,
                });
              }
            }
          });
        });
        return breakingFieldChanges;*/
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
        /*  const oldTypeMap = oldSchema.getTypeMap();
          const newTypeMap = newSchema.getTypeMap();

          const breakingFieldChanges = [];
          Object.keys(oldTypeMap).forEach(typeName => {
            const oldType = oldTypeMap[typeName];
            const newType = newTypeMap[typeName];
            if (
                !(oldType instanceof GraphQLInputObjectType) ||
                !(newType instanceof GraphQLInputObjectType)
            ) {
              return;
            }

            const oldTypeFieldsDef = oldType.getFields();
            const newTypeFieldsDef = newType.getFields();
            Object.keys(oldTypeFieldsDef).forEach(fieldName => {
              // Check if the field is missing on the type in the new schema.
              if (!(fieldName in newTypeFieldsDef)) {
                breakingFieldChanges.push({
                  type: BreakingChangeType.FIELD_REMOVED,
                  description: `${typeName}.${fieldName} was removed.`,
                });
              } else {
                const oldFieldType = oldTypeFieldsDef[fieldName].type;
                const newFieldType = newTypeFieldsDef[fieldName].type;

                const isSafe =
                isChangeSafeForInputObjectFieldOrFieldArg(oldFieldType, newFieldType);
                if (!isSafe) {
                  const oldFieldTypeString = isNamedType(oldFieldType) ?
                      oldFieldType.name :
                      oldFieldType.toString();
                  const newFieldTypeString = isNamedType(newFieldType) ?
                      newFieldType.name :
                      newFieldType.toString();
                  breakingFieldChanges.push({
                    type: BreakingChangeType.FIELD_CHANGED_KIND,
                    description: `${typeName}.${fieldName} changed type from ` +
                                 `${oldFieldTypeString} to ${newFieldTypeString}.`,
                  });
                }
              }
            });
            // Check if a non-null field was added to the input object type
            Object.keys(newTypeFieldsDef).forEach(fieldName => {
              if (
              !(fieldName in oldTypeFieldsDef) &&
                newTypeFieldsDef[fieldName].type instanceof GraphQLNonNull
              ) {
                breakingFieldChanges.push({
                  type: BreakingChangeType.NON_NULL_INPUT_FIELD_ADDED,
                  description: `A non-null field ${fieldName} on ` +
                               `input type ${newType.name} was added.`,
                });
              }
            });
          });
          return breakingFieldChanges;*/
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
                    $typesRemovedFromUnion[] = ['type' => self::BREAKING_CHANGE_TYPE_REMOVED_FROM_UNION, 'description' => "${missingTypeName} was removed from union type ${typeName}"];
                }
            }
        }
        return $typesRemovedFromUnion;
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any dangerous
     * changes in the newSchema related to adding types to a union type.
     */
    public static function findTypesAddedToUnions(
        Schema $oldSchema, Schema $newSchema
    )
    {
        /* const oldTypeMap = oldSchema.getTypeMap();
         const newTypeMap = newSchema.getTypeMap();

         const typesAddedToUnion = [];
         Object.keys(newTypeMap).forEach(typeName => {
           const oldType = oldTypeMap[typeName];
           const newType = newTypeMap[typeName];
           if (!(oldType instanceof GraphQLUnionType) ||
               !(newType instanceof GraphQLUnionType)) {
             return;
           }
           const typeNamesInOldUnion = Object.create(null);
           oldType.getTypes().forEach(type => {
             typeNamesInOldUnion[type.name] = true;
           });
           newType.getTypes().forEach(type => {
             if (!typeNamesInOldUnion[type.name]) {
               typesAddedToUnion.push({
                 type: DangerousChangeType.TYPE_ADDED_TO_UNION,
                 description: `${type.name} was added to union type ${typeName}.`
               });
             }
           });
         });
         return typesAddedToUnion;*/
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any breaking
     * changes in the newSchema related to removing values from an enum type.
     */
    public static function findValuesRemovedFromEnums(
        Schema $oldSchema, Schema $newSchema
    )
    {
        /* const oldTypeMap = oldSchema.getTypeMap();
         const newTypeMap = newSchema.getTypeMap();

         const valuesRemovedFromEnums = [];
         Object.keys(oldTypeMap).forEach(typeName => {
           const oldType = oldTypeMap[typeName];
           const newType = newTypeMap[typeName];
           if (!(oldType instanceof GraphQLEnumType) ||
               !(newType instanceof GraphQLEnumType)) {
             return;
           }
           const valuesInNewEnum = Object.create(null);
           newType.getValues().forEach(value => {
             valuesInNewEnum[value.name] = true;
           });
           oldType.getValues().forEach(value => {
             if (!valuesInNewEnum[value.name]) {
               valuesRemovedFromEnums.push({
                 type: BreakingChangeType.VALUE_REMOVED_FROM_ENUM,
                 description: `${value.name} was removed from enum type ${typeName}.`
               });
             }
           });
         });
         return valuesRemovedFromEnums;*/
    }

    /**
     * Given two schemas, returns an Array containing descriptions of any dangerous
     * changes in the newSchema related to adding values to an enum type.
     */
    public static function findValuesAddedToEnums(
        Schema $oldSchema, Schema $newSchema
    )
    {
        /* const oldTypeMap = oldSchema.getTypeMap();
         const newTypeMap = newSchema.getTypeMap();

         const valuesAddedToEnums = [];
         Object.keys(oldTypeMap).forEach(typeName => {
           const oldType = oldTypeMap[typeName];
           const newType = newTypeMap[typeName];
           if (!(oldType instanceof GraphQLEnumType) ||
               !(newType instanceof GraphQLEnumType)) {
             return;
           }

           const valuesInOldEnum = Object.create(null);
           oldType.getValues().forEach(value => {
             valuesInOldEnum[value.name] = true;
           });
           newType.getValues().forEach(value => {
             if (!valuesInOldEnum[value.name]) {
               valuesAddedToEnums.push({
                 type: DangerousChangeType.VALUE_ADDED_TO_ENUM,
                 description: `${value.name} was added to enum type ${typeName}.`
               });
             }
           });
         });
         return valuesAddedToEnums;*/
    }

    public static function findInterfacesRemovedFromObjectTypes(
        Schema $oldSchema, Schema $newSchema
    )
    {
        /* const oldTypeMap = oldSchema.getTypeMap();
         const newTypeMap = newSchema.getTypeMap();
         const breakingChanges = [];

         Object.keys(oldTypeMap).forEach(typeName => {
           const oldType = oldTypeMap[typeName];
           const newType = newTypeMap[typeName];
           if (
               !(oldType instanceof GraphQLObjectType) ||
               !(newType instanceof GraphQLObjectType)
           ) {
             return;
           }

           const oldInterfaces = oldType.getInterfaces();
           const newInterfaces = newType.getInterfaces();
           oldInterfaces.forEach(oldInterface => {
             if (!newInterfaces.some(int => int.name === oldInterface.name)) {
               breakingChanges.push({
                 type: BreakingChangeType.INTERFACE_REMOVED_FROM_OBJECT,
                 description: `${typeName} no longer implements interface ` +
                              `${oldInterface.name}.`
               });
             }
           });
         });
         return breakingChanges;*/
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