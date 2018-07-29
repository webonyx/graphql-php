<?php
namespace GraphQL\Tests\Error;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use PHPUnit\Framework\TestCase;

class PrintErrorTest extends TestCase
{
    // Describe printError

    /**
     * @it prints an error with nodes from different sources
     */
    public function testPrintsAnErrorWithNodesFromDifferentSources()
    {
        $sourceA = Parser::parse(new Source('type Foo {
  field: String
}',
        'SourceA'
        ));

        $fieldTypeA = $sourceA->definitions[0]->fields[0]->type;

        $sourceB = Parser::parse(new Source('type Foo {
  field: Int
}',
            'SourceB'
        ));

        $fieldTypeB = $sourceB->definitions[0]->fields[0]->type;


        $error = new Error(
            'Example error with two nodes',
            [
                $fieldTypeA,
                $fieldTypeB,
            ]
        );

        $this->assertEquals(
            'Example error with two nodes

SourceA (2:10)
1: type Foo {
2:   field: String
            ^
3: }

SourceB (2:10)
1: type Foo {
2:   field: Int
            ^
3: }
',
            FormattedError::printError($error)
        );
    }
}
