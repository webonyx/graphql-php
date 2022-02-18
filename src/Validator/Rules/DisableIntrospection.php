<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Validator\QueryValidationContext;

class DisableIntrospection extends QuerySecurityRule
{
    public const ENABLED = 1;

    protected int $isEnabled;

    public function __construct(int $enabled)
    {
        $this->setEnabled($enabled);
    }

    public function setEnabled(int $enabled): void
    {
        $this->isEnabled = $enabled;
    }

    public function getVisitor(QueryValidationContext $context): array
    {
        return $this->invokeIfNeeded(
            $context,
            [
                NodeKind::FIELD => static function (FieldNode $node) use ($context): void {
                    if ($node->name->value !== '__type' && $node->name->value !== '__schema') {
                        return;
                    }

                    $context->reportError(new Error(
                        static::introspectionDisabledMessage(),
                        [$node]
                    ));
                },
            ]
        );
    }

    public static function introspectionDisabledMessage(): string
    {
        return 'GraphQL introspection is not allowed, but the query contained __schema or __type';
    }

    protected function isEnabled(): bool
    {
        return $this->isEnabled !== self::DISABLED;
    }
}
