<?php
namespace GraphQL\Validator;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\LeafType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\Rules\AbstractValidationRule;
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

/**
 * Implements the "Validation" section of the spec.
 *
 * Validation runs synchronously, returning an array of encountered errors, or
 * an empty array if no errors were encountered and the document is valid.
 *
 * A list of specific validation rules may be provided. If not provided, the
 * default list of rules defined by the GraphQL specification will be used.
 *
 * Each validation rule is an instance of GraphQL\Validator\Rules\AbstractValidationRule
 * which returns a visitor (see the [GraphQL\Language\Visitor API](reference.md#graphqllanguagevisitor)).
 *
 * Visitor methods are expected to return an instance of [GraphQL\Error\Error](reference.md#graphqlerrorerror),
 * or array of such instances when invalid.
 *
 * Optionally a custom TypeInfo instance may be provided. If not provided, one
 * will be created from the provided schema.
 */
class DocumentValidator
{
    private static $rules = [];

    private static $defaultRules;

    private static $securityRules;

    private static $initRules = false;

    /**
     * Primary method for query validation. See class description for details.
     *
     * @api
     * @param Schema $schema
     * @param DocumentNode $ast
     * @param AbstractValidationRule[]|null $rules
     * @param TypeInfo|null $typeInfo
     * @return Error[]
     */
    public static function validate(
        Schema $schema,
        DocumentNode $ast,
        array $rules = null,
        TypeInfo $typeInfo = null
    )
    {
        if (null === $rules) {
            $rules = static::allRules();
        }

        if (true === is_array($rules) && 0 === count($rules)) {
            // Skip validation if there are no rules
            return [];
        }

        $typeInfo = $typeInfo ?: new TypeInfo($schema);
        $errors = static::visitUsingRules($schema, $typeInfo, $ast, $rules);
        return $errors;
    }


    /**
     * Returns all global validation rules.
     *
     * @api
     * @return AbstractValidationRule[]
     */
    public static function allRules()
    {
        if (!self::$initRules) {
            static::$rules = array_merge(static::defaultRules(), self::securityRules(), self::$rules);
            static::$initRules = true;
        }

        return self::$rules;
    }

    public static function defaultRules()
    {
        if (null === self::$defaultRules) {
            self::$defaultRules = [
                UniqueOperationNames::class => new UniqueOperationNames(),
                LoneAnonymousOperation::class => new LoneAnonymousOperation(),
                KnownTypeNames::class => new KnownTypeNames(),
                FragmentsOnCompositeTypes::class => new FragmentsOnCompositeTypes(),
                VariablesAreInputTypes::class => new VariablesAreInputTypes(),
                ScalarLeafs::class => new ScalarLeafs(),
                FieldsOnCorrectType::class => new FieldsOnCorrectType(),
                UniqueFragmentNames::class => new UniqueFragmentNames(),
                KnownFragmentNames::class => new KnownFragmentNames(),
                NoUnusedFragments::class => new NoUnusedFragments(),
                PossibleFragmentSpreads::class => new PossibleFragmentSpreads(),
                NoFragmentCycles::class => new NoFragmentCycles(),
                UniqueVariableNames::class => new UniqueVariableNames(),
                NoUndefinedVariables::class => new NoUndefinedVariables(),
                NoUnusedVariables::class => new NoUnusedVariables(),
                KnownDirectives::class => new KnownDirectives(),
                UniqueDirectivesPerLocation::class => new UniqueDirectivesPerLocation(),
                KnownArgumentNames::class => new KnownArgumentNames(),
                UniqueArgumentNames::class => new UniqueArgumentNames(),
                ArgumentsOfCorrectType::class => new ArgumentsOfCorrectType(),
                ProvidedNonNullArguments::class => new ProvidedNonNullArguments(),
                DefaultValuesOfCorrectType::class => new DefaultValuesOfCorrectType(),
                VariablesInAllowedPosition::class => new VariablesInAllowedPosition(),
                OverlappingFieldsCanBeMerged::class => new OverlappingFieldsCanBeMerged(),
                UniqueInputFieldNames::class => new UniqueInputFieldNames(),
            ];
        }

        return self::$defaultRules;
    }

    /**
     * @return array
     */
    public static function securityRules()
    {
        // This way of defining rules is deprecated
        // When custom security rule is required - it should be just added via DocumentValidator::addRule();
        // TODO: deprecate this

        if (null === self::$securityRules) {
            self::$securityRules = [
                DisableIntrospection::class => new DisableIntrospection(DisableIntrospection::DISABLED), // DEFAULT DISABLED
                QueryDepth::class => new QueryDepth(QueryDepth::DISABLED), // default disabled
                QueryComplexity::class => new QueryComplexity(QueryComplexity::DISABLED), // default disabled
            ];
        }
        return self::$securityRules;
    }

    /**
     * Returns global validation rule by name. Standard rules are named by class name, so
     * example usage for such rules:
     *
     * $rule = DocumentValidator::getRule(GraphQL\Validator\Rules\QueryComplexity::class);
     *
     * @api
     * @param string $name
     * @return AbstractValidationRule
     */
    public static function getRule($name)
    {
        $rules = static::allRules();

        if (isset($rules[$name])) {
            return $rules[$name];
        }

        $name = "GraphQL\\Validator\\Rules\\$name";
        return isset($rules[$name]) ? $rules[$name] : null ;
    }

    /**
     * Add rule to list of global validation rules
     *
     * @api
     * @param AbstractValidationRule $rule
     */
    public static function addRule(AbstractValidationRule $rule)
    {
        self::$rules[$rule->getName()] = $rule;
    }

    public static function isError($value)
    {
        return is_array($value)
            ? count(array_filter($value, function($item) { return $item instanceof \Exception || $item instanceof \Throwable;})) === count($value)
            : ($value instanceof \Exception || $value instanceof \Throwable);
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
            // Scalars must parse to a non-null value
            if (!$type->isValidLiteral($valueNode)) {
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
     * @param AbstractValidationRule[] $rules
     * @return array
     */
    public static function visitUsingRules(Schema $schema, TypeInfo $typeInfo, DocumentNode $documentNode, array $rules)
    {
        $context = new ValidationContext($schema, $documentNode, $typeInfo);
        $visitors = [];
        foreach ($rules as $rule) {
            $visitors[] = $rule->getVisitor($context);
        }
        Visitor::visit($documentNode, Visitor::visitWithTypeInfo($typeInfo, Visitor::visitInParallel($visitors)));
        return $context->getErrors();
    }
}
