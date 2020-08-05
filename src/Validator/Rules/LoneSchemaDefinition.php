<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Validator\SDLValidationContext;

/**
 * Lone Schema definition
 *
 * A GraphQL document is only valid if it contains only one schema definition.
 */
class LoneSchemaDefinition extends ValidationRule
{
    /** @var string */
    public static $schemaDefinitionNotAloneMessage = 'Must provide only one schema definition.';

    /** @var string */
    public static $canNotDefineSchemaWithinExtensionMessage = 'Cannot define a new schema within a schema extension.';

    public static function schemaDefinitionNotAloneMessage()
    {
        return static::$schemaDefinitionNotAloneMessage;
    }

    public static function canNotDefineSchemaWithinExtensionMessage()
    {
        return static::$canNotDefineSchemaWithinExtensionMessage;
    }

    public function getSDLVisitor(SDLValidationContext $context)
    {
        $oldSchema      = $context->getSchema();
        $alreadyDefined = $oldSchema !== null
            ? (
                $oldSchema->getAstNode() !== null ||
                $oldSchema->getQueryType() !== null ||
                $oldSchema->getMutationType() !== null ||
                $oldSchema->getSubscriptionType() !== null
            )
            : false;

        $schemaDefinitionsCount = 0;

        return [
            NodeKind::SCHEMA_DEFINITION => static function (SchemaDefinitionNode $node) use ($alreadyDefined, $context, &$schemaDefinitionsCount) : void {
                if ($alreadyDefined !== false) {
                    $context->reportError(new Error(self::canNotDefineSchemaWithinExtensionMessage(), $node));

                    return;
                }

                if ($schemaDefinitionsCount > 0) {
                    $context->reportError(new Error(self::schemaDefinitionNotAloneMessage(), $node));
                }

                ++$schemaDefinitionsCount;
            },
        ];
    }
}
