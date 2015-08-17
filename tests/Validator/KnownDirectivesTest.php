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
        dog @include(if: true) {
          name
        }
        human @skip(if: true) {
          name
        }
      }
        ');
    }

    public function testWithUnknownDirective()
    {
        $this->expectFailsRule(new KnownDirectives, '
      {
        dog @unknown(directive: "value") {
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
        dog @unknown(directive: "value") {
          name
        }
        human @unknown(directive: "value") {
          name
          pets @unknown(directive: "value") {
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

    public function testWithWellPlacedDirectives()
    {
        $this->expectPassesRule(new KnownDirectives, '
      query Foo {
        name @include(if: true)
        ...Frag @include(if: true)
        skippedField @skip(if: true)
        ...SkippedFrag @skip(if: true)
      }
        ');
    }

    public function testWithMisplacedDirectives()
    {
        $this->expectFailsRule(new KnownDirectives, '
      query Foo @include(if: true) {
        name
        ...Frag
      }
        ', [
            $this->misplacedDirective('include', 'operation', 2, 17)
        ]);
    }

    private function unknownDirective($directiveName, $line, $column)
    {
        return FormattedError::create(
            KnownDirectives::unknownDirectiveMessage($directiveName),
            [ new SourceLocation($line, $column) ]
        );
    }

    function misplacedDirective($directiveName, $placement, $line, $column)
    {
        return FormattedError::create(
            KnownDirectives::misplacedDirectiveMessage($directiveName, $placement),
            [new SourceLocation($line, $column)]
        );
    }
}
