<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

#[\Attribute(\Attribute::TARGET_ALL)]
class Deprecated
{
    public function __construct(
        public string $reason = Directive::DEFAULT_DEPRECATION_REASON,
    ) {}
}
