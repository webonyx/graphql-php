<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use PHPUnit\Framework\TestCase;
use Throwable;
use function file_get_contents;

class SchemaPrinterTest extends TestCase
{
    /**
     * @see it('prints minimal ast')
     */
    public function testPrintsMinimalAst() : void
    {
        $ast = new ScalarTypeDefinitionNode([
            'name' => new NameNode(['value' => 'foo']),
        ]);
        self::assertEquals('scalar foo', Printer::doPrint($ast));
    }

    /**
     * @see it('produces helpful error messages')
     */
    public function testProducesHelpfulErrorMessages() : void
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Invalid AST Node: {"random":"Data"}');

        // $badAst1 = { random: 'Data' };
        $badAst = (object) ['random' => 'Data'];
        Printer::doPrint($badAst);
    }

    /**
     * @see it('does not alter ast')
     */
    public function testDoesNotAlterAst() : void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/schema-kitchen-sink.graphql');

        $ast     = Parser::parse($kitchenSink);
        $astCopy = $ast->cloneDeep();
        Printer::doPrint($ast);

        self::assertEquals($astCopy, $ast);
    }

    /**
     * @see it('prints kitchen sink')
     */
    public function testPrintsKitchenSink() : void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/schema-kitchen-sink.graphql');

        $ast     = Parser::parse($kitchenSink);
        $printed = Printer::doPrint($ast);

        $expected = 'schema {
  query: QueryType
  mutation: MutationType
}

type AnnotatedObject @onObject(arg: "value") {
  annotatedField(arg: Type = "default" @onArg): Type @onField
}
';
        self::assertEquals($expected, $printed);
    }
}
