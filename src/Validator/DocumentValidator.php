<?php
namespace GraphQL\Validator;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\LeafType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\Rules\ArgumentsOfCorrectType;
use GraphQL\Validator\Rules\DefaultValuesOfCorrectType;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\FieldsOnCorrectType;
use GraphQL\Validator\Rules\FragmentsOnCompositeTypes;
use GraphQL\Validator\Rules\KnownArgumentNames;
use GraphQL\Validator\Rules\KnownDirectives;
use GraphQL\Validator\Rules\KnownFragmentNames;
use GraphQL\Validator\Rules\KnownTypeNames;
use GraphQL\Validator\Rules\LoneAnonymousOperation;
use GraphQL\Validator\Rules\NoFragmentCycles;
use GraphQL\Validator\Rules\NoUndefinedVariables;
use GraphQL\Validator\Rules\NoUnusedFragments;
use GraphQL\Validator\Rules\NoUnusedVariables;
use GraphQL\Validator\Rules\OverlappingFieldsCanBeMerged;
use GraphQL\Validator\Rules\PossibleFragmentSpreads;
use GraphQL\Validator\Rules\ProvidedNonNullArguments;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\Rules\ScalarLeafs;
use GraphQL\Validator\Rules\UniqueArgumentNames;
use GraphQL\Validator\Rules\UniqueDirectivesPerLocation;
use GraphQL\Validator\Rules\UniqueFragmentNames;
use GraphQL\Validator\Rules\UniqueInputFieldNames;
use GraphQL\Validator\Rules\UniqueOperationNames;
use GraphQL\Validator\Rules\UniqueVariableNames;
use GraphQL\Validator\Rules\VariablesAreInputTypes;
use GraphQL\Validator\Rules\VariablesInAllowedPosition;

class DocumentValidator
{
    private static $rules = [];

    private static $defaultRules;

    private static $initRules = false;

    public static function allRules()
    {
        if (!self::$initRules) {
            self::$rules = array_merge(static::defaultRules(), self::$rules);
            self::$initRules = true;
        }

        return self::$rules;
    }

    public static function defaultRules()
    {
        if (null === self::$defaultRules) {
            self::$defaultRules = [
                'UniqueOperationNames' => new UniqueOperationNames(),
                'LoneAnonymousOperation' => new LoneAnonymousOperation(),
                'KnownTypeNames' => new KnownTypeNames(),
                'FragmentsOnCompositeTypes' => new FragmentsOnCompositeTypes(),
                'VariablesAreInputTypes' => new VariablesAreInputTypes(),
                'ScalarLeafs' => new ScalarLeafs(),
                'FieldsOnCorrectType' => new FieldsOnCorrectType(),
                'UniqueFragmentNames' => new UniqueFragmentNames(),
                'KnownFragmentNames' => new KnownFragmentNames(),
                'NoUnusedFragments' => new NoUnusedFragments(),
                'PossibleFragmentSpreads' => new PossibleFragmentSpreads(),
                'NoFragmentCycles' => new NoFragmentCycles(),
                'UniqueVariableNames' => new UniqueVariableNames(),
                'NoUndefinedVariables' => new NoUndefinedVariables(),
                'NoUnusedVariables' => new NoUnusedVariables(),
                'KnownDirectives' => new KnownDirectives(),
                'UniqueDirectivesPerLocation' => new UniqueDirectivesPerLocation(),
                'KnownArgumentNames' => new KnownArgumentNames(),
                'UniqueArgumentNames' => new UniqueArgumentNames(),
                'ArgumentsOfCorrectType' => new ArgumentsOfCorrectType(),
                'ProvidedNonNullArguments' => new ProvidedNonNullArguments(),
                'DefaultValuesOfCorrectType' => new DefaultValuesOfCorrectType(),
                'VariablesInAllowedPosition' => new VariablesInAllowedPosition(),
                'OverlappingFieldsCanBeMerged' => new OverlappingFieldsCanBeMerged(),
                'UniqueInputFieldNames' => new UniqueInputFieldNames(),

                // Query Security
                'DisableIntrospection' => new DisableIntrospection(DisableIntrospection::DISABLED), // DEFAULT DISABLED
                'QueryDepth' => new QueryDepth(QueryDepth::DISABLED), // default disabled
                'QueryComplexity' => new QueryComplexity(QueryComplexity::DISABLED), // default disabled
            ];
        }

        return self::$defaultRules;
    }

    public static function getRule($name)
    {
        $rules = static::allRules();

        return isset($rules[$name]) ? $rules[$name] : null ;
    }

    public static function addRule($name, callable $rule)
    {
        self::$rules[$name] = $rule;
    }

    public static function validate(Schema $schema, DocumentNode $ast, array $rules = null)
    {
        $typeInfo = new TypeInfo($schema);
        $errors = static::visitUsingRules($schema, $typeInfo, $ast, $rules ?: static::allRules());
        return $errors;
    }

    public static function isError($value)
    {
        return is_array($value)
            ? count(array_filter($value, function($item) { return $item instanceof \Exception;})) === count($value)
            : $value instanceof \Exception;
    }

    public static function append(&$arr, $items)
    {
        if (is_array($items)) {
            $arr = array_merge($arr, $items);
        } else {
            $arr[] = $items;
        }
        return $arr;
    }

    /**
     * Utility for validators which determines if a value literal AST is valid given
     * an input type.
     *
     * Note that this only validates literal values, variables are assumed to
     * provide values of the correct type.
     *
     * @return array
     */
    public static function isValidLiteralValue(Type $type, $valueNode)
    {
        // A value must be provided if the type is non-null.
        if ($type instanceof NonNull) {
            if (!$valueNode || $valueNode instanceof NullValueNode) {
                return [ 'Expected "' . Utils::printSafe($type) . '", found null.' ];
            }
            return static::isValidLiteralValue($type->getWrappedType(), $valueNode);
        }

        if (!$valueNode || $valueNode instanceof NullValueNode) {
            return [];
        }

        // This function only tests literals, and assumes variables will provide
        // values of the correct type.
        if ($valueNode instanceof VariableNode) {
            return [];
        }

        // Lists accept a non-list value as a list of one.
        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if ($valueNode instanceof ListValueNode) {
                $errors = [];
                foreach($valueNode->values as $index => $itemNode) {
                    $tmp = static::isValidLiteralValue($itemType, $itemNode);

                    if ($tmp) {
                        $errors = array_merge($errors, Utils::map($tmp, function($error) use ($index) {
                            return "In element #$index: $error";
                        }));
                    }
                }
                return $errors;
            } else {
                return static::isValidLiteralValue($itemType, $valueNode);
            }
        }

        // Input objects check each defined field and look for undefined fields.
        if ($type instanceof InputObjectType) {
            if ($valueNode->kind !== NodeKind::OBJECT) {
                return [ "Expected \"{$type->name}\", found not an object." ];
            }

            $fields = $type->getFields();
            $errors = [];

            // Ensure every provided field is defined.
            $fieldNodes = $valueNode->fields;

            foreach ($fieldNodes as $providedFieldNode) {
                if (empty($fields[$providedFieldNode->name->value])) {
                    $errors[] = "In field \"{$providedFieldNode->name->value}\": Unknown field.";
                }
            }

            // Ensure every defined field is valid.
            $fieldNodeMap = Utils::keyMap($fieldNodes, function($fieldNode) {return $fieldNode->name->value;});
            foreach ($fields as $fieldName => $field) {
                $result = static::isValidLiteralValue(
                    $field->getType(),
                    isset($fieldNodeMap[$fieldName]) ? $fieldNodeMap[$fieldName]->value : null
                );
                if ($result) {
                    $errors = array_merge($errors, Utils::map($result, function($error) use ($fieldName) {
                        return "In field \"$fieldName\": $error";
                    }));
                }
            }

            return $errors;
        }

        if ($type instanceof LeafType) {
            // Scalar/Enum input checks to ensure the type can parse the value to
            // a non-null value.
            $parseResult = $type->parseLiteral($valueNode);

            if (null === $parseResult) {
                $printed = Printer::doPrint($valueNode);
                return [ "Expected type \"{$type->name}\", found $printed." ];
            }

            return [];
        }

        throw new InvariantViolation('Must be input type');
    }

    /**
     * This uses a specialized visitor which runs multiple visitors in parallel,
     * while maintaining the visitor skip and break API.
     *
     * @param Schema $schema
     * @param TypeInfo $typeInfo
     * @param DocumentNode $documentNode
     * @param array $rules
     * @return array
     */
    public static function visitUsingRules(Schema $schema, TypeInfo $typeInfo, DocumentNode $documentNode, array $rules)
    {
        $context = new ValidationContext($schema, $documentNode, $typeInfo);
        $visitors = [];
        foreach ($rules as $rule) {
            $visitors[] = $rule($context);
        }
        Visitor::visit($documentNode, Visitor::visitWithTypeInfo($typeInfo, Visitor::visitInParallel($visitors)));
        return $context->getErrors();
    }
}
