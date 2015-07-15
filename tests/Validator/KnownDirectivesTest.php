<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\KnownDirectives;

class KnownDirectivesTest extends TestCase
{
    // Validate: Known directives
    public function testWithNoDirectives()
    {
        $this->expectPassesRule(new KnownDirectives, '
      query Foo {
        name
        ...Frag
      }

      fragment Frag on Dog {
        name
      }
        ');
    }

    public function testWithKnownDirectives()
    {
        $this->expectPassesRule(new KnownDirectives, '
      {
        dog @if: true {
          name
        }
        human @unless: false {
          name
        }
      }
        ');
    }

    public function testWithUnknownDirective()
    {
        $this->expectFailsRule(new KnownDirectives, '
      {
        dog @unknown: "directive" {
          name
        }
      }
        ', [
            $this->unknownDirective('unknown', 3, 13)
        ]);
    }

    public function testWithManyUnknownDirectives()
    {
        $this->expectFailsRule(new KnownDirectives, '
      {
        dog @unknown: "directive" {
          name
        }
        human @unknown: "directive" {
          name
          pets @unknown: "directive" {
            name
          }
        }
      }
        ', [
            $this->unknownDirective('unknown', 3, 13),
            $this->unknownDirective('unknown', 6, 15),
            $this->unknownDirective('unknown', 8, 16)
        ]);
    }

    public function testWithMisplacedDirectives()
    {
        $this->expectFailsRule(new KnownDirectives, '
      query Foo @if: true {
        name
        ...Frag
      }
        ', [
            $this->misplacedDirective('if', 'operation', 2, 17)
        ]);
    }

    private function unknownDirective($directiveName, $line, $column)
    {
        return new FormattedError(
            Messages::unknownDirectiveMessage($directiveName),
            [ new SourceLocation($line, $column) ]
        );
    }

    function misplacedDirective($directiveName, $placement, $line, $column)
    {
        return new FormattedError(
            Messages::misplacedDirectiveMessage($directiveName, $placement),
            [new SourceLocation($line, $column)]
        );
    }
}
