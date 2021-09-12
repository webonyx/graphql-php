<?php

declare(strict_types=1);

namespace GraphQL\Tests\TestUtils;

use PHPUnit\Framework\TestCase;

use function implode;

class StrTest extends TestCase
{
    // describe('dedent')

    /**
     * @see it('removes indentation in typical usage')
     */
    public function testRemovesIndentationInTypicalUsage(): void
    {
        $output = Str::dedent('
          type Query {
            me: User
          }

          type User {
            id: ID
            name: String
          }
        ');
        self::assertEquals(
            implode(
                "\n",
                [
                    'type Query {',
                    '  me: User',
                    '}',
                    '',
                    'type User {',
                    '  id: ID',
                    '  name: String',
                    '}',
                    '',
                ]
            ),
            $output
        );
    }

    /**
     * @see it('removes only the first level of indentation')
     */
    public function testRemovesOnlyTheFirstLevelOfIndentation(): void
    {
        $output = Str::dedent('
            first
              second
                third
                  fourth
        ');
        self::assertEquals(
            implode("\n", ['first', '  second', '    third', '      fourth', '']),
            $output
        );
    }

    /**
     * @see it('does not escape special characters')
     */
    public function testDoesNotEscapeSpecialCharacters(): void
    {
        $output = Str::dedent("
          type Root {
            field(arg: String = \"wi\th de\fault\"): String
          }
        ");
        self::assertEquals(
            implode(
                "\n",
                [
                    'type Root {',
                    "  field(arg: String = \"wi\th de\fault\"): String",
                    '}',
                    '',
                ]
            ),
            $output
        );
    }

    /**
     * @see it('also removes indentation using tabs')
     */
    public function testAlsoRemovesIndentationUsingTabs(): void
    {
        $output = Str::dedent("
            \t\t    type Query {
            \t\t      me: User
            \t\t    }
        ");
        self::assertEquals(implode("\n", ['type Query {', '  me: User', '}', '']), $output);
    }

    /**
     * @see it('removes leading newlines')
     */
    public function testRemovesLeadingNewlines(): void
    {
        $output = Str::dedent('


          type Query {
            me: User
          }');
        self::assertEquals(implode("\n", ['type Query {', '  me: User', '}']), $output);
    }

    /**
     * @see it('does not remove trailing newlines')
     */
    public function testDoesNotRemoveTrailingNewlines(): void
    {
        $output = Str::dedent('
          type Query {
            me: User
          }

         ');
        self::assertEquals(implode("\n", ['type Query {', '  me: User', '}', '', '']), $output);
    }

    /**
     * @see it('removes all trailing spaces and tabs')
     */
    public function testRemovesAllTrailingSpacesAndTabs(): void
    {
        $output = Str::dedent("
          type Query {
            me: User
          }
              \t\t  \t ");
        self::assertEquals(implode("\n", ['type Query {', '  me: User', '}', '']), $output);
    }

    /**
     * @see it('works on text without leading newline')
     */
    public function testWorksOnTextWithoutLeadingNewline(): void
    {
        $output = Str::dedent('          type Query {
            me: User
          }');
        self::assertEquals(implode("\n", ['type Query {', '  me: User', '}']), $output);
    }
}
