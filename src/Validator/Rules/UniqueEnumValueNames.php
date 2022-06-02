<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Validator\SDLValidationContext;

class UniqueEnumValueNames extends ValidationRule
{
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        /** @var array<string, array<string, EnumValueNode>> $knownValueNames */
        $knownValueNames = [];

        /**
         * @param EnumTypeDefinitionNode|EnumTypeExtensionNode $enum
         */
        $checkValueUniqueness = static function ($enum) use ($context, &$knownValueNames): VisitorOperation {
            $typeName = $enum->name->value;

            $schema = $context->getSchema();
            $existingType = $schema !== null
                ? $schema->getType($typeName)
                : null;

            $valueNodes = $enum->values;

            if (! isset($knownValueNames[$typeName])) {
                $knownValueNames[$typeName] = [];
            }

            $valueNames = &$knownValueNames[$typeName];

            foreach ($valueNodes as $valueDef) {
                $valueNameNode = $valueDef->name;
                $valueName = $valueNameNode->value;

                if ($existingType instanceof EnumType && $existingType->getValue($valueName) !== null) {
                    $context->reportError(new Error(
                        "Enum value \"{$typeName}.{$valueName}\" already exists in the schema. It cannot also be defined in this type extension.",
                        $valueNameNode
                    ));
                } elseif (isset($valueNames[$valueName])) {
                    $context->reportError(new Error(
                        "Enum value \"{$typeName}.{$valueName}\" can only be defined once.",
                        [$valueNames[$valueName], $valueNameNode]
                    ));
                } else {
                    $valueNames[$valueName] = $valueNameNode;
                }
            }

            return Visitor::skipNode();
        };

        return [
            NodeKind::ENUM_TYPE_DEFINITION => $checkValueUniqueness,
            NodeKind::ENUM_TYPE_EXTENSION => $checkValueUniqueness,
        ];
    }
}
