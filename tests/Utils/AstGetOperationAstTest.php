<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

class AstGetOperationAstTest extends TestCase
{
    // Describe: getOperationAST

    /**
     * @see it('Gets an operation from a simple document')
     */
    public function testGetsAnOperationFromASimpleDocument() : void
    {
        $doc = Parser::parse('{ field }');
        self::assertEquals(AST::getOperationAST($doc), $doc->definitions->offsetGet(0));
    }

    /**
     * @see it('Gets an operation from a document with named op (mutation)')
     */
    public function testGetsAnOperationFromADcoumentWithNamedOpMutation() : void
    {
        $doc = Parser::parse('mutation Test { field }');
        self::assertEquals(AST::getOperationAST($doc), $doc->definitions->offsetGet(0));
    }

    /**
     * @see it('Gets an operation from a document with named op (subscription)')
     */
    public function testGetsAnOperationFromADcoumentWithNamedOpSubscription() : void
    {
        $doc = Parser::parse('subscription Test { field }');
        self::assertEquals(AST::getOperationAST($doc), $doc->definitions->offsetGet(0));
    }

    /**
     * @see it('Does not get missing operation')
     */
    public function testDoesNotGetMissingOperation() : void
    {
        $doc = Parser::parse('type Foo { field: String }');
        self::assertEquals(AST::getOperationAST($doc), null);
    }

    /**
     * @see it('Does not get ambiguous unnamed operation')
     */
    public function testDoesNotGetAmbiguousUnnamedOperation() : void
    {
        $doc = Parser::parse('
          { field }
          mutation Test { field }
          subscription TestSub { field }
        ');
        self::assertEquals(AST::getOperationAST($doc), null);
    }

    /**
     * @see it('Does not get ambiguous named operation')
     */
    public function testDoesNotGetAmbiguousNamedOperation() : void
    {
        $doc = Parser::parse('
          query TestQ { field }
          mutation TestM { field }
          subscription TestS { field }
        ');
        self::assertEquals(AST::getOperationAST($doc), null);
    }

    /**
     * @see it('Does not get misnamed operation')
     */
    public function testDoesNotGetMisnamedOperation() : void
    {
        $doc = Parser::parse('
          { field }
          query TestQ { field }
          mutation TestM { field }
          subscription TestS { field }
        ');
        self::assertEquals(AST::getOperationAST($doc, 'Unknown'), null);
    }

    /**
     * @see it('Gets named operation')
     */
    public function testGetsNamedOperation() : void
    {
        $doc = Parser::parse('
          query TestQ { field }
          mutation TestM { field }
          subscription TestS { field }
        ');
        self::assertEquals(AST::getOperationAST($doc, 'TestQ'), $doc->definitions->offsetGet(0));
        self::assertEquals(AST::getOperationAST($doc, 'TestM'), $doc->definitions->offsetGet(1));
        self::assertEquals(AST::getOperationAST($doc, 'TestS'), $doc->definitions->offsetGet(2));
    }
}
