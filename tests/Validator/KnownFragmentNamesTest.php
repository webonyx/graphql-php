<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\KnownFragmentNames;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
final class KnownFragmentNamesTest extends ValidatorTestCase
{
    // Validate: Known fragment names

    /** @see it('known fragment names are valid') */
    public function testKnownFragmentNamesAreValid(): void
    {
        $this->expectPassesRule(
            new KnownFragmentNames(),
            '
      {
        human(id: 4) {
          ...HumanFields1
          ... on Human {
            ...HumanFields2
          }
        }
      }
      fragment HumanFields1 on Human {
        name
        ...HumanFields3
      }
      fragment HumanFields2 on Human {
        name
      }
      fragment HumanFields3 on Human {
        name
      }
        '
        );
    }

    /** @see it('unknown fragment names are invalid') */
    public function testUnknownFragmentNamesAreInvalid(): void
    {
        $this->expectFailsRule(
            new KnownFragmentNames(),
            '
      {
        human(id: 4) {
          ...UnknownFragment1
          ... on Human {
            ...UnknownFragment2
          }
        }
      }
      fragment HumanFields on Human {
        name
        ...UnknownFragment3
      }
        ',
            [
                $this->undefFrag('UnknownFragment1', 4, 14),
                $this->undefFrag('UnknownFragment2', 6, 16),
                $this->undefFrag('UnknownFragment3', 12, 12),
            ]
        );
    }

    /** @phpstan-return ErrorArray */
    private function undefFrag(string $fragName, int $line, int $column): array
    {
        return ErrorHelper::create(
            KnownFragmentNames::unknownFragmentMessage($fragName),
            [new SourceLocation($line, $column)]
        );
    }
}
