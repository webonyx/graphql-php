<?php
namespace GraphQL\Tests\Utils;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

class AssertValidNameTest extends TestCase
{
    // Describe: assertValidName()

    /**
     * @it throws for use of leading double underscores
     */
    public function testThrowsForUseOfLeadingDoubleUnderscores()
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('"__bad" must not begin with "__", which is reserved by GraphQL introspection.');
        Utils::assertValidName('__bad');
    }

    /**
     * @it throws for non-strings
     */
    public function testThrowsForNonStrings()
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Expected string');
        Utils::assertValidName([]);
    }

    /**
     * @it throws for names with invalid characters
     */
    public function testThrowsForNamesWithInvalidCharacters()
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Names must match');
        Utils::assertValidName('>--()-->');
    }
}
