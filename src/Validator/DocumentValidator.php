<?php
namespace GraphQL\Validator;

use GraphQL\Error;
use GraphQL\Language\AST\ListValue;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\Value;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
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
use GraphQL\Validator\Rules\NoFragmentCycles;
use GraphQL\Validator\Rules\NoUndefinedVariables;
use GraphQL\Validator\Rules\NoUnusedFragments;
use GraphQL\Validator\Rules\NoUnusedVariables;
use GraphQL\Validator\Rules\OverlappingFieldsCanBeMerged;
use GraphQL\Validator\Rules\PossibleFragmentSpreads;
use GraphQL\Validator\Rules\ProvidedNonNullArguments;
use GraphQL\Validator\Rules\ScalarLeafs;
use GraphQL\Validator\Rules\VariablesAreInputTypes;
use GraphQL\Validator\Rules\VariablesInAllowedPosition;

class DocumentValidator
{
    private static $allRules;

    static function allRules()
    {
        if (null === self::$allRules) {
            self::$allRules = [
                // new UniqueOperationNames,
                // new LoneAnonymousOperation,
                new KnownTypeNames,
                new FragmentsOnCompositeTypes,
                new VariablesAreInputTypes,
                new ScalarLeafs,
                new FieldsOnCorrectType,
                // new UniqueFragmentNames,
                new KnownFragmentNames,
                new NoUnusedFragments,
                new PossibleFragmentSpreads,
                new NoFragmentCycles,
                new NoUndefinedVariables,
                new NoUnusedVariables,
                new KnownDirectives,
                new KnownArgumentNames,
                // new UniqueArgumentNames,
                new ArgumentsOfCorrectType,
                new ProvidedNonNullArguments,
                new DefaultValuesOfCorrectType,
                new VariablesInAllowedPosition,
                new OverlappingFieldsCanBeMerged,
            ];
        }
        return self::$allRules;
    }

    public static function validate(Schema $schema, Document $ast, array $rules = null)
    {
        $errors = self::visitUsingRules($schema, $ast, $rules ?: self::allRules());
        return $errors;
    }

    static function isError($value)
    {
        return is_array($value)
            ? count(array_filter($value, function($item) { return $item instanceof \Exception;})) === count($value)
            : $value instanceof \Exception;
    }

    static function append(&$arr, $items)
    {
        if (is_array($items)) {
            $arr = array_merge($arr, $items);
        } else {
            $arr[] = $items;
        }
        return $arr;
    }

    static function isValidLiteralValue($valueAST, Type $type)
    {
        // A value can only be not provided if the type is nullable.
        if (!$valueAST) {
            return !($type instanceof NonNull);
        }

        // Unwrap non-null.
        if ($type instanceof NonNull) {
            return self::isValidLiteralValue($valueAST, $type->getWrappedType());
        }

        // This function only tests literals, and assumes variables will provide
        // values of the correct type.
        if ($valueAST instanceof Variable) {
            return true;
        }

        if (!$valueAST instanceof Value) {
            return false;
        }

        // Lists accept a non-list value as a list of one.
        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if ($valueAST instanceof ListValue) {
                foreach($valueAST->values as $itemAST) {
                    if (!self::isValidLiteralValue($itemAST, $itemType)) {
                        return false;
                    }
                }
                return true;
            } else {
                return self::isValidLiteralValue($valueAST, $itemType);
            }
        }

        // Scalar/Enum input checks to ensure the type can serialize the value to
        // a non-null value.
        if ($type instanceof ScalarType || $type instanceof EnumType) {
            return $type->parseLiteral($valueAST) !== null;
        }

        // Input objects check each defined field, ensuring it is of the correct
        // type and provided if non-nullable.
        if ($type instanceof InputObjectType) {
            $fields = $type->getFields();
            if ($valueAST->kind !== Node::OBJECT) {
                return false;
            }
            $fieldASTs = $valueAST->fields;
            $fieldASTMap = Utils::keyMap($fieldASTs, function($field) {return $field->name->value;});

            foreach ($fields as $fieldKey => $field) {
                $fieldName = $field->name ?: $fieldKey;
                if (!isset($fieldASTMap[$fieldName]) && $field->getType() instanceof NonNull) {
                    // Required fields missing
                    return false;
                }
            }
            foreach ($fieldASTs as $fieldAST) {
                if (empty($fields[$fieldAST->name->value]) || !self::isValidLiteralValue($fieldAST->value, $fields[$fieldAST->name->value]->getType())) {
                    return false;
                }
            }
            return true;
        }

        // Any other kind of type is not an input type, and a literal cannot be used.
        return false;
    }

    /**
     * This uses a specialized visitor which runs multiple visitors in parallel,
     * while maintaining the visitor skip and break API.
     *
     * @param Schema $schema
     * @param Document $documentAST
     * @param array $rules
     * @return array
     */
    public static function visitUsingRules(Schema $schema, Document $documentAST, array $rules)
    {
        $typeInfo = new TypeInfo($schema);
        $context = new ValidationContext($schema, $documentAST, $typeInfo);
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
                            $enter = Visitor::getVisitFn($instances[$i], false, $node->kind);
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
                        } else if ($result && self::isError($result)) {
                            self::append($errors, $result);
                            for ($j = $i - 1; $j >= 0; $j--) {
                                $leaveFn = Visitor::getVisitFn($instances[$j], true, $node->kind);
                                if ($leaveFn) {
                                    // $leaveFn = $leaveFn->bindTo($instances[$j])
                                    $result = call_user_func_array($leaveFn, func_get_args());

                                    if ($result instanceof VisitorOperation) {
                                        if ($result->doBreak) {
                                            $instances[$j] = null;
                                        }
                                    } else if (self::isError($result)) {
                                        self::append($errors, $result);
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
                        $leaveFn = Visitor::getVisitFn($instances[$i], true, $node->kind);

                        if ($leaveFn) {
                            // $leaveFn = $leaveFn.bindTo($instances[$i]);
                            $result = call_user_func_array($leaveFn, func_get_args());

                            if ($result instanceof VisitorOperation) {
                                if ($result->doBreak) {
                                    $instances[$i] = null;
                                }
                            } else if (self::isError($result)) {
                                self::append($errors, $result);
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
            $allRuleInstances[] = $rule($context);
        }
        $visitInstances($documentAST, $allRuleInstances);

        return $errors;
    }
}
