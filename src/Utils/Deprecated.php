<?php declare(strict_types=1);

namespace GraphQL\Utils;

use Attribute;
use GraphQL\Type\Definition\Directive;

#[Attribute(Attribute::TARGET_ALL)]
class Deprecated
{
    public function __construct(
        public string $reason = Directive::DEFAULT_DEPRECATION_REASON,
    ) {
    }
}
