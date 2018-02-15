<?php
namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Language\Printer;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
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
     * Accepts options as a second argument:
     *
     *    - commentDescriptions:
     *        Provide true to use preceding comments as the description.
     * @api
     * @param Schema $schema
     * @return string
     */
    public static function doPrint(Schema $schema, array $options = [])
    {
        return self::printFilteredSchema(
            $schema,
            function($type) {
                return !Directive::isSpecifiedDirective($type);
            },
            function ($type) {
                return !Type::isBuiltInType($type);
            },
            $options
        );
    }

    /**
     * @api
     * @param Schema $schema
     * @return string
     */
    public static function printIntrosepctionSchema(Schema $schema, array $options = [])
    {
        return self::printFilteredSchema(
            $schema,
            [Directive::class, 'isSpecifiedDirective'],
            [Introspection::class, 'isIntrospectionType'],
            $options
        );
    }

    private static function printFilteredSchema(Schema $schema, $directiveFilter, $typeFilter, $options)
    {
        $directives = array_filter($schema->getDirectives(), function($directive) use ($directiveFilter) {
            return $directiveFilter($directive);
        });
        $types = $schema->getTypeMap();
        ksort($types);
        $types = array_filter($types, $typeFilter);

        return implode("\n\n", array_filter(array_merge(
            [self::printSchemaDefinition($schema)],
            array_map(function($directive) use ($options) { return self::printDirective($directive, $options); }, $directives),
            array_map(function($type) use ($options) { return self::printType($type, $options); }, $types)
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

    public static function printType(Type $type, array $options = [])
    {
        if ($type instanceof ScalarType) {
            return self::printScalar($type, $options);
        } else if ($type instanceof ObjectType) {
            return self::printObject($type, $options);
        } else if ($type instanceof InterfaceType) {
            return self::printInterface($type, $options);
        } else if ($type instanceof UnionType) {
            return self::printUnion($type, $options);
        } else if ($type instanceof EnumType) {
            return self::printEnum($type, $options);
        } else if ($type instanceof InputObjectType) {
            return self::printInputObject($type, $options);
        }

        throw new Error('Unknown type: ' . Utils::printSafe($type) . '.');
    }

    private static function printScalar(ScalarType $type, array $options)
    {
        return self::printDescription($options, $type) . "scalar {$type->name}";
    }

    private static function printObject(ObjectType $type, array $options)
    {
        $interfaces = $type->getInterfaces();
        $implementedInterfaces = !empty($interfaces) ?
            ' implements ' . implode(', ', array_map(function($i) {
                return $i->name;
            }, $interfaces)) : '';
        return self::printDescription($options, $type) .
            "type {$type->name}$implementedInterfaces {\n" .
                self::printFields($options, $type) . "\n" .
            "}";
    }

    private static function printInterface(InterfaceType $type, array $options)
    {
        return self::printDescription($options, $type) .
            "interface {$type->name} {\n" .
                self::printFields($options, $type) . "\n" .
            "}";
    }

    private static function printUnion(UnionType $type, array $options)
    {
        return self::printDescription($options, $type) .
            "union {$type->name} = " . implode(" | ", $type->getTypes());
    }

    private static function printEnum(EnumType $type, array $options)
    {
        return self::printDescription($options, $type) .
            "enum {$type->name} {\n" .
                self::printEnumValues($type->getValues(), $options) . "\n" .
            "}";
    }

    private static function printEnumValues($values, $options)
    {
        return implode("\n", array_map(function($value, $i) use ($options) {
            return self::printDescription($options, $value, '  ', !$i) . '  ' .
                $value->name . self::printDeprecated($value);
        }, $values, array_keys($values)));
    }

    private static function printInputObject(InputObjectType $type, array $options)
    {
        $fields = array_values($type->getFields());
        return self::printDescription($options, $type) .
            "input {$type->name} {\n" .
                implode("\n", array_map(function($f, $i) use ($options) {
                    return self::printDescription($options, $f, '  ', !$i) . '  ' . self::printInputValue($f);
                }, $fields, array_keys($fields))) . "\n" .
            "}";
    }

    private static function printFields($options, $type)
    {
        $fields = array_values($type->getFields());
        return implode("\n", array_map(function($f, $i) use ($options) {
                return self::printDescription($options, $f, '  ', !$i) . '  ' .
                    $f->name . self::printArgs($options, $f->args, '  ') . ': ' .
                    (string) $f->getType() . self::printDeprecated($f);
            }, $fields, array_keys($fields)));
    }

    private static function printArgs($options, $args, $indentation = '')
    {
        if (!$args) {
            return '';
        }

        // If every arg does not have a description, print them on one line.
        if (Utils::every($args, function($arg) { return empty($arg->description); })) {
            return '(' . implode(', ', array_map('self::printInputValue', $args)) . ')';
        }

        return "(\n" . implode("\n", array_map(function($arg, $i) use ($indentation, $options) {
            return self::printDescription($options, $arg, '  ' . $indentation, !$i) . '  ' . $indentation .
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

    private static function printDirective($directive, $options)
    {
        return self::printDescription($options, $directive) .
            'directive @' . $directive->name . self::printArgs($options, $directive->args) .
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

    private static function printDescription($options, $def, $indentation = '', $firstInBlock = true)
    {
        if (!$def->description) {
            return '';
        }
        $lines = self::descriptionLines($def->description, 120 - strlen($indentation));
        if (isset($options['commentDescriptions'])) {
            return self::printDescriptionWithComments($lines, $indentation, $firstInBlock);
        }

        $description = ($indentation && !$firstInBlock) ? "\n" : '';
        if (count($lines) === 1 && mb_strlen($lines[0]) < 70) {
            $description .= $indentation . '"""' . self::escapeQuote($lines[0]) . "\"\"\"\n";
            return $description;
        }

        $description .= $indentation . "\"\"\"\n";
        foreach ($lines as $line) {
            $description .= $indentation . self::escapeQuote($line) . "\n";
        }
        $description .= $indentation . "\"\"\"\n";

        return $description;
    }

    private static function escapeQuote($line)
    {
        return str_replace('"""', '\\"""', $line);
    }

    private static function printDescriptionWithComments($lines, $indentation, $firstInBlock)
    {
        $description = $indentation && !$firstInBlock ? "\n" : '';
        foreach ($lines as $line) {
            if ($line === '') {
                $description .= $indentation . "#\n";
            } else {
                $description .= $indentation . '# ' . $line . "\n";
            }
        }

        return $description;
    }

    private static function descriptionLines($description, $maxLen) {
        $lines = [];
        $rawLines = explode("\n", $description);
        foreach($rawLines as $line) {
            if ($line === '') {
                $lines[] = $line;
            } else {
                // For > 120 character long lines, cut at space boundaries into sublines
                // of ~80 chars.
                $sublines = self::breakLine($line, $maxLen);
                foreach ($sublines as $subline) {
                    $lines[] = $subline;
                }
            }
        }
        return $lines;
    }

    private static function breakLine($line, $maxLen)
    {
        if (strlen($line) < $maxLen + 5) {
            return [$line];
        }
        preg_match_all("/((?: |^).{15," . ($maxLen - 40) . "}(?= |$))/", $line, $parts);
        $parts = $parts[0];
        return array_map(function($part) {
            return trim($part);
        }, $parts);
    }
}
