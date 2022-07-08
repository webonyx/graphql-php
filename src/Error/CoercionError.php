<?php declare(strict_types=1);

namespace GraphQL\Error;

use GraphQL\Language\AST\Node;
use Throwable;

/**
 * @phpstan-type InputPath list<string>
 */
class CoercionError extends Error
{
    /**
     * @var InputPath|null
     */
    public ?array $inputPath;

    /**
     * @param iterable<array-key, Node|null>|Node|null $nodes
     * @param InputPath|null $inputPath
     */
    public function __construct(
        string $message,
        $nodes = null,
        ?Throwable $previous = null,
        ?array $inputPath = null
    ) {
        parent::__construct($message, $nodes, null, [], null, $previous);
        $this->inputPath = $inputPath;
    }
}
