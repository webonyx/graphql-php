<?php
namespace GraphQL\Validator;

use GraphQL\Error;
use GraphQL\Language\AST\ListValue;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\Value;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\Rules\ArgumentsOfCorrectType;
use GraphQL\Validator\Rules\DefaultValuesOfCorrectType;
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
                // 'UniqueOperationNames' => new UniqueOperationNames(),
                'LoneAnonymousOperation' => new LoneAnonymousOperation(),
                'KnownTypeNames' => new KnownTypeNames(),
                'FragmentsOnCompositeTypes' => new FragmentsOnCompositeTypes(),
                'VariablesAreInputTypes' => new VariablesAreInputTypes(),
                'ScalarLeafs' => new ScalarLeafs(),
                'FieldsOnCorrectType' => new FieldsOnCorrectType(),
                // 'UniqueFragmentNames' => new UniqueFragmentNames(),
                'KnownFragmentNames' => new KnownFragmentNames(),
                'NoUnusedFragments' => new NoUnusedFragments(),
                'PossibleFragmentSpreads' => new PossibleFragmentSpreads(),
                'NoFragmentCycles' => new NoFragmentCycles(),
                // 'UniqueVariableNames' => new UniqueVariableNames(),
                'NoUndefinedVariables' => new NoUndefinedVariables(),
                'NoUnusedVariables' => new NoUnusedVariables(),
                'KnownDirectives' => new KnownDirectives(),
                'KnownArgumentNames' => new KnownArgumentNames(),
                // 'UniqueArgumentNames' => new UniqueArgumentNames(),
                'ArgumentsOfCorrectType' => new ArgumentsOfCorrectType(),
                'ProvidedNonNullArguments' => new ProvidedNonNullArguments(),
                'DefaultValuesOfCorrectType' => new DefaultValuesOfCorrectType(),
                'VariablesInAllowedPosition' => new VariablesInAllowedPosition(),
                'OverlappingFieldsCanBeMerged' => new OverlappingFieldsCanBeMerged(),
                // 'UniqueInputFieldNames' => new UniqueInputFieldNames(),

                // Query Security
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

    public static function validate(Schema $schema, Document $ast, array $rules = null)
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
    public static function isValidLiteralValue(Type $type, $valueAST)
    {
        // A value must be provided if the type is non-null.
        if ($type instanceof NonNull) {
            $wrappedType = $type->getWrappedType();
            if (!$valueAST) {
                if ($wrappedType->name) {
                    return [ "Expected \"{$wrappedType->name}!\", found null." ];
                }
                return ['Expected non-null value, found null.'];
            }
            return static::isValidLiteralValue($wrappedType, $valueAST);
        }

        if (!$valueAST) {
            return [];
        }

        // This function only tests literals, and assumes variables will provide
        // values of the correct type.
        if ($valueAST instanceof Variable) {
            return [];
        }

        // Lists accept a non-list value as a list of one.
        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if ($valueAST instanceof ListValue) {
                $errors = [];
                foreach($valueAST->values as $index => $itemAST) {
                    $tmp = static::isValidLiteralValue($itemType, $itemAST);

                    if ($tmp) {
                        $errors = array_merge($errors, Utils::map($tmp, function($error) use ($index) {
                            return "In element #$index: $error";
                        }));
                    }
                }
                return $errors;
            } else {
                return static::isValidLiteralValue($itemType, $valueAST);
            }
        }

        // Input objects check each defined field and look for undefined fields.
        if ($type instanceof InputObjectType) {
            if ($valueAST->kind !== Node::OBJECT) {
                return [ "Expected \"{$type->name}\", found not an object." ];
            }

            $fields = $type->getFields();
            $errors = [];

            // Ensure every provided field is defined.
            $fieldASTs = $valueAST->fields;

            foreach ($fieldASTs as $providedFieldAST) {
                if (empty($fields[$providedFieldAST->name->value])) {
                    $errors[] = "In field \"{$providedFieldAST->name->value}\": Unknown field.";
                }
            }

            // Ensure every defined field is valid.
            $fieldASTMap = Utils::keyMap($fieldASTs, function($fieldAST) {return $fieldAST->name->value;});
            foreach ($fields as $fieldName => $field) {
                $result = static::isValidLiteralValue(
                    $field->getType(),
                    isset($fieldASTMap[$fieldName]) ? $fieldASTMap[$fieldName]->value : null
                );
                if ($result) {
                    $errors = array_merge($errors, Utils::map($result, function($error) use ($fieldName) {
                        return "In field \"$fieldName\": $error";
                    }));
                }
            }

            return $errors;
        }

        Utils::invariant(
            $type instanceof ScalarType || $type instanceof EnumType,
            'Must be input type'
        );

        // Scalar/Enum input checks to ensure the type can parse the value to
        // a non-null value.
        $parseResult = $type->parseLiteral($valueAST);

        if (null === $parseResult) {
            $printed = Printer::doPrint($valueAST);
            return [ "Expected type \"{$type->name}\", found $printed." ];
        }

        return [];
    }

    /**
     * This uses a specialized visitor which runs multiple visitors in parallel,
     * while maintaining the visitor skip and break API.
     *
     * @param Schema $schema
     * @param TypeInfo $typeInfo
     * @param Document $documentAST
     * @param array $rules
     * @return array
     */
    public static function visitUsingRules(Schema $schema, TypeInfo $typeInfo, Document $documentAST, array $rules)
    {
        $context = new ValidationContext($schema, $documentAST, $typeInfo);
        $visitors = [];
        foreach ($rules as $rule) {
            $visitors[] = $rule($context);
        }
        Visitor::visit($documentAST, Visitor::visitWithTypeInfo($typeInfo, Visitor::visitInParallel($visitors)));
        return $context->getErrors();



        $errors = [];

        // TODO: convert to class
        $visitInstances = function($ast, $instances) use ($typeInfo, $context, &$errors, &$visitInstances) {
            $skipUntil = new \SplFixedArray(count($instances));
            $skipCount = 0;

            Visitor::visit($ast, [
                'enter' => function ($node, $key) use ($typeInfo, $instances, $skipUntil, &$skipCount, &$errors, $context, $visitInstances) {
                    $typeInfo->enter($node);
                    for ($i = 0; $i < count($instances); $i++) {
                        // Do not visit this instance if it returned false for a previous node
                        if ($skipUntil[$i]) {
                            continue;
                        }

                        $result = null;

                        // Do not visit top level fragment definitions if this instance will
                        // visit those fragments inline because it
                        // provided `visitSpreadFragments`.
                        if ($node->kind === Node::FRAGMENT_DEFINITION && $key !== null && !empty($instances[$i]['visitSpreadFragments'])) {
                            $result = Visitor::skipNode();
                        } else {
                            $enter = Visitor::getVisitFn($instances[$i], $node->kind, false);
                            if ($enter instanceof \Closure) {
                                // $enter = $enter->bindTo($instances[$i]);
                                $result = call_user_func_array($enter, func_get_args());
                            } else {
                                $result = null;
                            }
                        }

                        if ($result instanceof VisitorOperation) {
                            if ($result->doContinue) {
                                $skipUntil[$i] = $node;
                                $skipCount++;
                                // If all instances are being skipped over, skip deeper traversal
                                if ($skipCount === count($instances)) {
                                    for ($k = 0; $k < count($instances); $k++) {
                                        if ($skipUntil[$k] === $node) {
                                            $skipUntil[$k] = null;
                                            $skipCount--;
                                        }
                                    }
                                    return Visitor::skipNode();
                                }
                            } else if ($result->doBreak) {
                                $instances[$i] = null;
                            }
                        } else if ($result && static::isError($result)) {
                            static::append($errors, $result);
                            for ($j = $i - 1; $j >= 0; $j--) {
                                $leaveFn = Visitor::getVisitFn($instances[$j], $node->kind, true);
                                if ($leaveFn) {
                                    // $leaveFn = $leaveFn->bindTo($instances[$j])
                                    $result = call_user_func_array($leaveFn, func_get_args());

                                    if ($result instanceof VisitorOperation) {
                                        if ($result->doBreak) {
                                            $instances[$j] = null;
                                        }
                                    } else if (static::isError($result)) {
                                        static::append($errors, $result);
                                    } else if ($result !== null) {
                                        throw new \Exception("Config cannot edit document.");
                                    }
                                }
                            }
                            $typeInfo->leave($node);
                            return Visitor::skipNode();
                        } else if ($result !== null) {
                            throw new \Exception("Config cannot edit document.");
                        }
                    }

                    // If any validation instances provide the flag `visitSpreadFragments`
                    // and this node is a fragment spread, validate the fragment from
                    // this point.
                    if ($node instanceof FragmentSpread) {
                        $fragment = $context->getFragment($node->name->value);
                        if ($fragment) {
                            $fragVisitingInstances = [];
                            foreach ($instances as $idx => $inst) {
                                if (!empty($inst['visitSpreadFragments']) && !$skipUntil[$idx]) {
                                    $fragVisitingInstances[] = $inst;
                                }
                            }
                            if (!empty($fragVisitingInstances)) {
                                $visitInstances($fragment, $fragVisitingInstances);
                            }
                        }
                    }
                },
                'leave' => function ($node) use ($instances, $typeInfo, $skipUntil, &$skipCount, &$errors) {
                    for ($i = count($instances) - 1; $i >= 0; $i--) {
                        if ($skipUntil[$i]) {
                            if ($skipUntil[$i] === $node) {
                                $skipUntil[$i] = null;
                                $skipCount--;
                            }
                            continue;
                        }
                        $leaveFn = Visitor::getVisitFn($instances[$i], $node->kind, true);

                        if ($leaveFn) {
                            // $leaveFn = $leaveFn.bindTo($instances[$i]);
                            $result = call_user_func_array($leaveFn, func_get_args());

                            if ($result instanceof VisitorOperation) {
                                if ($result->doBreak) {
                                    $instances[$i] = null;
                                }
                            } else if (static::isError($result)) {
                                static::append($errors, $result);
                            } else if ($result !== null) {
                                throw new \Exception("Config cannot edit document.");
                            }
                        }
                    }
                    $typeInfo->leave($node);
                }
            ]);
        };

        // Visit the whole document with instances of all provided rules.
        $allRuleInstances = [];
        foreach ($rules as $rule) {
            $allRuleInstances[] = call_user_func_array($rule, [$context]);
        }
        $visitInstances($documentAST, $allRuleInstances);

        return $errors;
    }
}
