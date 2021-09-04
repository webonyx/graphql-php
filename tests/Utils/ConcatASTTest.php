<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

use function preg_match;
use function preg_replace;
use function trim;

class ConcatASTTest extends TestCase
{
    private function dedent(string $str): string
    {
        $trimmedStr = trim($str, "\n");
        $trimmedStr = preg_replace('/[ \t]*$/', '', $trimmedStr);

        preg_match('/^[ \t]*/', $trimmedStr, $indentMatch);
        $indent = $indentMatch[0];

        return preg_replace('/^' . $indent . '/m', '', $trimmedStr);
    }

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

        self::assertEquals(
            $this->dedent('
              {
                a
                b
                ...Frag
              }

              fragment Frag on T {
                c
              }
            '),
            Printer::doPrint($astC)
        );
    }
}
