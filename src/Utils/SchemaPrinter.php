<?php
namespace GraphQL\Utils;

use GraphQL\Language\Printer;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\Directive;

/**
 * Given an instance of Schema, prints it in GraphQL type language.
 */
class SchemaPrinter
{
    /**
     * @api
     * @param Schema $schema
     * @return string
     */
    public static function doPrint(Schema $schema)
    {
        return self::printFilteredSchema($schema, function($n) {
            return !self::isSpecDirective($n);
        }, 'self::isDefinedType');
    }

    /**
     * @api
     * @param Schema $schema
     * @return string
     */
    public static function printIntrosepctionSchema(Schema $schema)
    {
        return self::printFilteredSchema($schema, [__CLASS__, 'isSpecDirective'], [__CLASS__, 'isIntrospectionType']);
    }

    private static function isSpecDirective($directiveName)
    {
        return (
            $directiveName === 'skip' ||
            $directiveName === 'include' ||
            $directiveName === 'deprecated'
        );
    }

    private static function isDefinedType($typename)
    {
        return !self::isIntrospectionType($typename) && !self::isBuiltInScalar($typename);
    }

    private static function isIntrospectionType($typename)
    {
        return strpos($typename, '__') === 0;
    }

    private static function isBuiltInScalar($typename)
    {
        return (
            $typename === Type::STRING ||
            $typename === Type::BOOLEAN ||
            $typename === Type::INT ||
            $typename === Type::FLOAT ||
            $typename === Type::ID
        );
    }

    private static function printFilteredSchema(Schema $schema, $directiveFilter, $typeFilter)
    {
        $directives = array_filter($schema->getDirectives(), function($directive) use ($directiveFilter) {
            return $directiveFilter($directive->name);
        });
        $typeMap = $schema->getTypeMap();
        $types = array_filter(array_keys($typeMap), $typeFilter);
        sort($types);
        $types = array_map(function($typeName) use ($typeMap) { return $typeMap[$typeName]; }, $types);

        return implode("\n\n", array_filter(array_merge(
            [self::printSchemaDefinition($schema)],
            array_map('self::printDirective', $directives),
            array_map('self::printType', $types)
        ))) . "\n";
    }

    private static function printSchemaDefinition(Schema $schema)
    {
        if (self::isSchemaOfCommonNames($schema)) {
            return;
        }

        $operationTypes = [];

        $queryType = $schema->getQueryType();
        if ($queryType) {
            $operationTypes[] = "  query: {$queryType->name}";
        }

        $mutationType = $schema->getMutationType();
        if ($mutationType) {
            $operationTypes[] = "  mutation: {$mutationType->name}";
        }

        $subscriptionType = $schema->getSubscriptionType();
        if ($subscriptionType) {
            $operationTypes[] = "  subscription: {$subscriptionType->name}";
        }

        return "schema {\n" . implode("\n", $operationTypes) . "\n}";
    }
    
    /**
     * GraphQL schema define root types for each type of operation. These types are
     * the same as any other type and can be named in any manner, however there is
     * a common naming convention:
     *
     *   schema {
     *     query: Query
     *     mutation: Mutation
     *   }
     *
     * When using this naming convention, the schema description can be omitted.
     */
    private static function isSchemaOfCommonNames(Schema $schema)
    {
        $queryType = $schema->getQueryType();
        if ($queryType && $queryType->name !== 'Query') {
            return false;
        }

        $mutationType = $schema->getMutationType();
        if ($mutationType && $mutationType->name !== 'Mutation') {
            return false;
        }

        $subscriptionType = $schema->getSubscriptionType();
        if ($subscriptionType && $subscriptionType->name !== 'Subscription') {
            return false;
        }

        return true;
    }

    public static function printType(Type $type)
    {
        if ($type instanceof ScalarType) {
            return self::printScalar($type);
        } else if ($type instanceof ObjectType) {
            return self::printObject($type);
        } else if ($type instanceof InterfaceType) {
            return self::printInterface($type);
        } else if ($type instanceof UnionType) {
            return self::printUnion($type);
        } else if ($type instanceof EnumType) {
            return self::printEnum($type);
        }
        Utils::invariant($type instanceof InputObjectType);
        return self::printInputObject($type);
    }

    private static function printScalar(ScalarType $type)
    {
        return self::printDescription($type) . "scalar {$type->name}";
    }

    private static function printObject(ObjectType $type)
    {
        $interfaces = $type->getInterfaces();
        $implementedInterfaces = !empty($interfaces) ?
            ' implements ' . implode(', ', array_map(function($i) {
                return $i->name;
            }, $interfaces)) : '';
        return self::printDescription($type) .
            "type {$type->name}$implementedInterfaces {\n" .
                self::printFields($type) . "\n" .
            "}";
    }

    private static function printInterface(InterfaceType $type)
    {
        return self::printDescription($type) . 
            "interface {$type->name} {\n" .
                self::printFields($type) . "\n" .
            "}";
    }

    private static function printUnion(UnionType $type)
    {
        return self::printDescription($type) .
            "union {$type->name} = " . implode(" | ", $type->getTypes());
    }

    private static function printEnum(EnumType $type)
    {
        return self::printDescription($type) .
            "enum {$type->name} {\n" .
                self::printEnumValues($type->getValues()) . "\n" .
            "}";
    }

    private static function printEnumValues($values)
    {
        return implode("\n", array_map(function($value, $i) {
            return self::printDescription($value, '  ', !$i) . '  ' .
                $value->name . self::printDeprecated($value);
        }, $values, array_keys($values)));
    }

    private static function printInputObject(InputObjectType $type)
    {
        $fields = array_values($type->getFields());
        return self::printDescription($type) . 
            "input {$type->name} {\n" .
                implode("\n", array_map(function($f, $i) {
                    return self::printDescription($f, '  ', !$i) . '  ' . self::printInputValue($f);
                }, $fields, array_keys($fields))) . "\n" .
            "}";
    }

    private static function printFields($type)
    {
        $fields = array_values($type->getFields());
        return implode("\n", array_map(function($f, $i) {
                return self::printDescription($f, '  ', !$i) . '  ' .
                    $f->name . self::printArgs($f->args, '  ') . ': ' .
                    (string) $f->getType() . self::printDeprecated($f);
            }, $fields, array_keys($fields)));
    }

    private static function printArgs($args, $indentation = '')
    {
        if (count($args) === 0) {
            return '';
        }

        // If every arg does not have a description, print them on one line.
        if (Utils::every($args, function($arg) { return empty($arg->description); })) {
            return '(' . implode(', ', array_map('self::printInputValue', $args)) . ')';
        }

        return "(\n" . implode("\n", array_map(function($arg, $i) use ($indentation) {
            return self::printDescription($arg, '  ' . $indentation, !$i) . '  ' . $indentation .
                self::printInputValue($arg);
        }, $args, array_keys($args))) . "\n" . $indentation . ')';
    }

    private static function printInputValue($arg)
    {
        $argDecl = $arg->name . ': ' . (string) $arg->getType();
        if ($arg->defaultValueExists()) {
            $argDecl .= ' = ' . Printer::doPrint(AST::astFromValue($arg->defaultValue, $arg->getType()));
        }
        return $argDecl;
    }

    private static function printDirective($directive)
    {
        return self::printDescription($directive) .
            'directive @' . $directive->name . self::printArgs($directive->args) .
            ' on ' . implode(' | ', $directive->locations);
    }

    private static function printDeprecated($fieldOrEnumVal)
    {
        $reason = $fieldOrEnumVal->deprecationReason;
        if (empty($reason)) {
            return '';
        }
        if ($reason === '' || $reason === Directive::DEFAULT_DEPRECATION_REASON) {
            return ' @deprecated';
        }
        return ' @deprecated(reason: ' .
            Printer::doPrint(AST::astFromValue($reason, Type::string())) . ')';
    }

    private static function printDescription($def, $indentation = '', $firstInBlock = true)
    {
        if (!$def->description) {
            return '';
        }
        $lines = explode("\n", $def->description);
        $description = $indentation && !$firstInBlock ? "\n" : '';
        foreach ($lines as $line) {
            if ($line === '') {
                $description .= $indentation . "#\n";
            } else {
                // For > 120 character long lines, cut at space boundaries into sublines
                // of ~80 chars.
                $sublines = self::breakLine($line, 120 - strlen($indentation));
                foreach ($sublines as $subline) {
                    $description .= $indentation . '# ' . $subline . "\n";
                }
            }
        }
        return $description;
    }

    private static function breakLine($line, $len)
    {
        if (strlen($line) < $len + 5) {
            return [$line];
        }
        preg_match_all("/((?: |^).{15," . ($len - 40) . "}(?= |$))/", $line, $parts);
        $parts = $parts[0];
        return array_map(function($part) {
            return trim($part);
        }, $parts);
    }
}