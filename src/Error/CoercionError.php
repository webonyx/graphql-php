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
     * @param InputPath|null $inputPath
     */
    public function __construct(
        string $message,
        ?Throwable $previous = null,
        ?array $inputPath = null
    ) {
        parent::__construct($message, null, null, [], null, $previous);
        $this->inputPath = $inputPath;
    }
}
