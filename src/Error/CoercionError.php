<?php declare(strict_types=1);

namespace GraphQL\Error;

use Throwable;

/**
 * @phpstan-type InputPath list<string|int>
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
        ?array $inputPath,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, null, null, [], null, $previous);
        $this->inputPath = $inputPath;
    }

    public function printInputPath(): ?string
    {
        if ($this->inputPath === null) {
            return null;
        }

        $path = '';
        foreach ($this->inputPath as $segment) {
            $path .= is_int($segment)
                ? "[{$segment}]"
                : ".{$segment}";
        }

        return $path;
    }
}
