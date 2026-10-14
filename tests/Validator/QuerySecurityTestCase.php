<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Type\Introspection;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QuerySecurityRule;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
abstract class QuerySecurityTestCase extends TestCase
{
    public function testMaxQueryDepthMustBeGreaterOrEqualTo0(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('argument must be greater or equal to 0.');

        $this->getRule(-1);
    }

    abstract protected function getRule(int $max): QuerySecurityRule;

    /**
     * @throws \Exception
     * @throws \JsonException
     * @throws SyntaxError
     */
    protected function assertIntrospectionQuery(int $maxExpected): void
    {
        $query = Introspection::getIntrospectionQuery();

        $this->assertMaxValue($query, $maxExpected);
    }

    /**
     * @throws \Exception
     * @throws \JsonException
     * @throws SyntaxError
     */
    protected function assertMaxValue(string $query, int $maxExpected): void
    {
        $this->assertDocumentValidator($query, $maxExpected);
        $newMax = $maxExpected - 1;
        if ($newMax === QuerySecurityRule::DISABLED) {
            return;
        }

        $this->assertDocumentValidator($query, $newMax, [self::createFormattedError($newMax, $maxExpected)]);
    }

    /**
     * @param array<int, array<string, mixed>> $expectedErrors
     *
     * @throws \Exception
     * @throws \JsonException
     * @throws SyntaxError
     *
     * @return array<int, Error>
     */
    protected function assertDocumentValidator(string $queryString, int $max, array $expectedErrors = []): array
    {
        $errors = DocumentValidator::validate(
            QuerySecuritySchema::buildSchema(),
            Parser::parse($queryString),
            [$this->getRule($max)]
        );

        self::assertEquals($expectedErrors, array_map([FormattedError::class, 'createFromException'], $errors), $queryString);

        return $errors;
    }

    /**
     * @param array<SourceLocation> $locations
     *
     * @phpstan-return ErrorArray
     */
    protected static function createFormattedError(int $max, int $count, array $locations = []): array
    {
        return ErrorHelper::create(
            static::getErrorMessage($max, $count),
            $locations
        );
    }

    abstract protected static function getErrorMessage(int $max, int $count): string;

    /**
     * @throws \Exception
     * @throws \JsonException
     * @throws SyntaxError
     */
    protected function assertIntrospectionTypeMetaFieldQuery(int $maxExpected): void
    {
        $query = '
          {
            __type(name: "Human") {
              name
            }
          }
        ';

        $this->assertMaxValue($query, $maxExpected);
    }

    /**
     * @throws \Exception
     * @throws \JsonException
     * @throws SyntaxError
     */
    protected function assertTypeNameMetaFieldQuery(int $maxExpected): void
    {
        $query = '
          {
            human {
              __typename
              firstName
            }
          }
        ';
        $this->assertMaxValue($query, $maxExpected);
    }
}
