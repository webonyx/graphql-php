<?php
namespace GraphQL\Validator;

class Messages
{
    static function missingArgMessage($fieldName, $argName, $typeName)
    {
        return "Field $fieldName argument $argName of type $typeName, is required but not provided.";
    }

    static function badValueMessage($argName, $typeName, $value)
    {
        return "Argument $argName expected type $typeName but got: $value.";
    }

    static function defaultForNonNullArgMessage($varName, $typeName, $guessTypeName)
    {
        return "Variable \$$varName of type $typeName " .
        "is required and will never use the default value. " .
        "Perhaps you meant to use type $guessTypeName.";
    }

    static function badValueForDefaultArgMessage($varName, $typeName, $value)
    {
        return "Variable \$$varName of type $typeName has invalid default value: $value.";
    }

    static function undefinedFieldMessage($field, $type)
    {
        return 'Cannot query field ' . $field . ' on ' . $type;
    }

    static function fragmentOnNonCompositeErrorMessage($fragName, $typeName)
    {
        return "Fragment $fragName cannot condition on non composite type \"$typeName\".";
    }

    static function inlineFragmentOnNonCompositeErrorMessage($typeName)
    {
        return "Fragment cannot condition on non composite type \"$typeName\".";
    }

    static function unknownArgMessage($argName, $fieldName, $typeName)
    {
        return "Unknown argument $argName on field $fieldName of type $typeName.";
    }

    static function unknownTypeMessage($typeName)
    {
        return "Unknown type $typeName.";
    }

    static function undefinedVarMessage($varName)
    {
        return "Variable \$$varName is not defined.";
    }

    static function undefinedVarByOpMessage($varName, $opName)
    {
        return "Variable \$$varName is not defined by operation $opName.";
    }

    static function unusedFragMessage($fragName)
    {
        return "Fragment $fragName is not used.";
    }

    static function unusedVariableMessage($varName)
    {
        return "Variable \$$varName is not used.";
    }

    static function typeIncompatibleSpreadMessage($fragName, $parentType, $fragType)
    {
        return "Fragment \"$fragName\" cannot be spread here as objects of " .
        "type \"$parentType\" can never be of type \"$fragType\".";
    }

    static function typeIncompatibleAnonSpreadMessage($parentType, $fragType)
    {
        return "Fragment cannot be spread here as objects of " .
        "type \"$parentType\" can never be of type \"$fragType\".";
    }

    static function noSubselectionAllowedMessage($field, $type)
    {
        return "Field \"$field\" of type $type must not have a sub selection.";
    }

    static function requiredSubselectionMessage($field, $type)
    {
        return "Field \"$field\" of type $type must have a sub selection.";
    }

    static function nonInputTypeOnVarMessage($variableName, $typeName)
    {
        return "Variable $${variableName} cannot be non input type $typeName.";
    }

    static function cycleErrorMessage($fragmentName, $spreadNames)
    {
        return "Cannot spread fragment $fragmentName within itself" .
        (!empty($spreadNames) ? (" via " . implode(', ', $spreadNames)) : '') . '.';
    }

    static function unknownDirectiveMessage($directiveName)
    {
        return "Unknown directive $directiveName.";
    }

    static function misplacedDirectiveMessage($directiveName, $placement)
    {
        return "Directive $directiveName may not be used on $placement.";
    }

    static function missingDirectiveValueMessage($directiveName, $typeName)
    {
        return "Directive $directiveName expects a value of type $typeName.";
    }

    static function noDirectiveValueMessage($directiveName)
    {
        return "Directive $directiveName expects no value.";
    }

    static function badDirectiveValueMessage($directiveName, $typeName, $value)
    {
        return "Directive $directiveName expected type $typeName but " .
        "got: $value.";
    }

    static function badVarPosMessage($varName, $varType, $expectedType)
    {
        return "Variable \$$varName of type $varType used in position expecting ".
        "type $expectedType.";
    }

    static function fieldsConflictMessage($responseName, $reason)
    {
        $reasonMessage = self::reasonMessage($reason);
        return "Fields $responseName conflict because {$reasonMessage}.";
    }

    /**
     * @param array|string $reason
     * @return array
     */
    private static function reasonMessage($reason)
    {
        if (is_array($reason)) {
            $tmp = array_map(function($tmp) {
                list($responseName, $subReason) = $tmp;
                $reasonMessage = self::reasonMessage($subReason);
                return "subfields $responseName conflict because $reasonMessage";
            }, $reason);
            return implode(' and ', $tmp);
        }
        return $reason;
    }
}
