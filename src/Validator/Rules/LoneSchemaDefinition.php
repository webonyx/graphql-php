<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Validator\SDLValidationContext;

/**
 * Lone schema definition.
 *
 * A GraphQL document is only valid if it contains only one schema definition.
 */
class LoneSchemaDefinition extends ValidationRule
{
    public static function schemaDefinitionNotAloneMessage(): string
    {
        return 'Must provide only one schema definition.';
    }

    public static function canNotDefineSchemaWithinExtensionMessage(): string
    {
        return 'Cannot define a new schema within a schema extension.';
    }

    public function getSDLVisitor(SDLValidationContext $context): array
    {
        $oldSchema = $context->getSchema();
        $alreadyDefined = $oldSchema === null
            ? false
            : (
                $oldSchema->astNode !== null
                || $oldSchema->getQueryType() !== null
                || $oldSchema->getMutationType() !== null
                || $oldSchema->getSubscriptionType() !== null
            );

        $schemaDefinitionsCount = 0;

        return [
            NodeKind::SCHEMA_DEFINITION => static function (SchemaDefinitionNode $node) use ($alreadyDefined, $context, &$schemaDefinitionsCount): void {
                if ($alreadyDefined) {
                    $context->reportError(new Error(static::canNotDefineSchemaWithinExtensionMessage(), $node));

                    return;
                }

                if ($schemaDefinitionsCount > 0) {
                    $context->reportError(new Error(static::schemaDefinitionNotAloneMessage(), $node));
                }

                ++$schemaDefinitionsCount;
            },
        ];
    }
}
