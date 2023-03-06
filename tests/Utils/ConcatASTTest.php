<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

final class ConcatASTTest extends TestCase
{
    /**
     * @see it('concatenates two ASTs together'
     */
    public function testConcatenatesTwoAstsTogether(): void
    {
        $sourceA = '
          { a, b, ...Frag }
        ';

        $sourceB = '
          fragment Frag on T {
            c
          }
        ';

        $astA = Parser::parse($sourceA);
        $astB = Parser::parse($sourceB);
        $astC = AST::concatAST([$astA, $astB]);

        self::assertSame(
            <<<'GRAPHQL'
              {
                a
                b
                ...Frag
              }

              fragment Frag on T {
                c
              }

              GRAPHQL,
            Printer::doPrint($astC)
        );
    }
}
