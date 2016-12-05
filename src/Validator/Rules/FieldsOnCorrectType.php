<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Schema;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Utils;
use GraphQL\Validator\ValidationContext;

class FieldsOnCorrectType
{
    static function undefinedFieldMessage($field, $type, array $suggestedTypes = [])
    {
        $message = 'Cannot query field "' . $field . '" on type "' . $type.'".';

        $maxLength = 5;
        $count = count($suggestedTypes);
        if ($count > 0) {
            $suggestions = array_slice($suggestedTypes, 0, $maxLength);
            $suggestions = Utils::map($suggestions, function($t) { return "\"$t\""; });
            $suggestions = implode(', ', $suggestions);

            if ($count > $maxLength) {
                $suggestions .= ', and ' . ($count - $maxLength) . ' other types';
            }
            $message .= " However, this field exists on $suggestions.";
            $message .= ' Perhaps you meant to use an inline fragment?';
        }
        return $message;
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            NodeKind::FIELD => function(FieldNode $node) use ($context) {
                $type = $context->getParentType();
                if ($type) {
                    $fieldDef = $context->getFieldDef();
                    if (!$fieldDef) {
                        // This isn't valid. Let's find suggestions, if any.
                        $suggestedTypes = [];
                        if ($type instanceof AbstractType) {
                            $schema = $context->getSchema();
                            $suggestedTypes = self::getSiblingInterfacesIncludingField(
                                $schema,
                                $type,
                                $node->name->value
                            );
                            $suggestedTypes = array_merge($suggestedTypes,
                                self::getImplementationsIncludingField($schema, $type, $node->name->value)
                            );
                        }
                        $context->reportError(new Error(
                            static::undefinedFieldMessage($node->name->value, $type->name, $suggestedTypes),
                            [$node]
                        ));
                    }
                }
            }
        ];
    }

    /**
     * Return implementations of `type` that include `fieldName` as a valid field.
     *
     * @param Schema $schema
     * @param AbstractType $type
     * @param $fieldName
     * @return array
     */
    static function getImplementationsIncludingField(Schema $schema, AbstractType $type, $fieldName)
    {
        $types = $schema->getPossibleTypes($type);
        $types = Utils::filter($types, function($t) use ($fieldName) {return isset($t->getFields()[$fieldName]);});
        $types = Utils::map($types, function($t) {return $t->name;});
        sort($types);
        return $types;
    }

    /**
     * Go through all of the implementations of type, and find other interaces
     * that they implement. If those interfaces include `field` as a valid field,
     * return them, sorted by how often the implementations include the other
     * interface.
     */
    static function getSiblingInterfacesIncludingField(Schema $schema, AbstractType $type, $fieldName)
    {
        $types = $schema->getPossibleTypes($type);
        $suggestedInterfaces = array_reduce($types, function ($acc, $t) use ($fieldName) {
            foreach ($t->getInterfaces() as $i) {
                if (empty($i->getFields()[$fieldName])) {
                    continue;
                }
                if (!isset($acc[$i->name])) {
                    $acc[$i->name] = 0;
                }
                $acc[$i->name] += 1;
            }
            return $acc;
        }, []);
        $suggestedInterfaceNames = array_keys($suggestedInterfaces);
        usort($suggestedInterfaceNames, function($a, $b) use ($suggestedInterfaces) {
            return $suggestedInterfaces[$b] - $suggestedInterfaces[$a];
        });
        return $suggestedInterfaceNames;
    }
}
