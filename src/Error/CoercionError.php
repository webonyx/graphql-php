<?php declare(strict_types=1);

namespace GraphQL\Error;

use GraphQL\Utils\Utils;
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
     * @var mixed whatever invalid value was passed
     */
    public $invalidValue;

    /**
     * @param InputPath|null $inputPath
     * @param mixed $invalidValue whatever invalid value was passed
     */
    public function __construct(
        string $message,
        ?array $inputPath,
        $invalidValue,
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

    public function printInvalidValue(): string
    {
        return Utils::printSafeJson($this->invalidValue);
    }
}
