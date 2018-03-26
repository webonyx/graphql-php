<?php
namespace GraphQL\Tests\Utils;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;

class AssertValidNameTest extends \PHPUnit_Framework_TestCase
{
    // Describe: assertValidName()

    /**
     * @it throws for use of leading double underscores
     */
    public function testThrowsForUseOfLeadingDoubleUnderscores()
    {
        $this->setExpectedException(
            Error::class,
            '"__bad" must not begin with "__", which is reserved by GraphQL introspection.'
        );
        Utils::assertValidName('__bad');
    }

    /**
     * @it throws for non-strings
     */
    public function testThrowsForNonStrings()
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'Expected string'
        );
        Utils::assertValidName([]);
    }

    /**
     * @it throws for names with invalid characters
     */
    public function testThrowsForNamesWithInvalidCharacters()
    {
        $this->setExpectedException(
            Error::class,
            'Names must match'
        );
        Utils::assertValidName('>--()-->');
    }
}
