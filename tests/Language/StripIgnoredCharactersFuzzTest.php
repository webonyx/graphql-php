<?php declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\Lexer;
use GraphQL\Language\Printer;
use GraphQL\Language\Source;
use GraphQL\Language\Token;
use PHPUnit\Framework\TestCase;

/**
 * Generated input combinations, split out like stripIgnoredCharacters-fuzz.ts in graphql-js.
 *
 * @see describe('stripIgnoredCharacters', () => {
 */
final class StripIgnoredCharactersFuzzTest extends TestCase
{
    private const IGNORED_TOKENS = [
        "\u{FEFF}",
        "\t",
        ' ',
        "\n",
        "\r",
        "\r\n",
        "# \"Comment\" string\n",
        ',',
    ];

    private const PUNCTUATOR_TOKENS = [
        '!',
        '$',
        '(',
        ')',
        '...',
        ':',
        '=',
        '@',
        '[',
        ']',
        '{',
        '|',
        '}',
    ];

    private const NON_PUNCTUATOR_TOKENS = [
        'name_token',
        '1',
        '3.14',
        '"some string value"',
        "\"\"\"block\nstring\nvalue\"\"\"",
    ];

    /**
     * @throws \JsonException
     * @throws SyntaxError
     */
    private static function lexValue(string $str): ?string
    {
        $lexer = new Lexer(new Source($str));
        $value = $lexer->advance()->value;

        self::assertSame(Token::EOF, $lexer->advance()->kind, 'Expected EOF');

        return $value;
    }

    /**
     * @throws \JsonException
     * @throws SyntaxError
     */
    private static function assertStripped(string $expected, string $docString): void
    {
        $stripped = Printer::stripIgnoredCharacters($docString);
        self::assertSame($expected, $stripped, 'Stripping ' . json_encode($docString, JSON_THROW_ON_ERROR));

        $strippedTwice = Printer::stripIgnoredCharacters($stripped);
        self::assertSame($stripped, $strippedTwice, 'Stripping twice ' . json_encode($stripped, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws \JsonException
     * @throws SyntaxError
     */
    private static function assertStaysTheSame(string $docString): void
    {
        self::assertStripped($docString, $docString);
    }

    /** @throws \JsonException */
    private static function isSingleToken(string $str): bool
    {
        $lexer = new Lexer(new Source($str));
        try {
            $lexer->advance();

            return $lexer->advance()->kind === Token::EOF;
        } catch (SyntaxError $invalidToken) {
            return false;
        }
    }

    /**
     * @param list<string> $allowedChars
     *
     * @return \Generator<string>
     */
    private static function genFuzzStrings(array $allowedChars, int $maxLength): \Generator
    {
        $numAllowedChars = count($allowedChars);

        $numCombinations = 0;
        for ($length = 1; $length <= $maxLength; ++$length) {
            $numCombinations += $numAllowedChars ** $length;
        }

        yield '';
        for ($combination = 0; $combination < $numCombinations; ++$combination) {
            $permutation = '';

            $leftOver = $combination;
            while ($leftOver >= 0) {
                $remainder = $leftOver % $numAllowedChars;
                $permutation = $allowedChars[$remainder] . $permutation;
                $leftOver = intdiv($leftOver - $remainder, $numAllowedChars) - 1;
            }

            yield $permutation;
        }
    }

    /** @see it('strips documents with random combination of ignored characters', () => { */
    public function testStripsDocumentsWithRandomCombinationOfIgnoredCharacters(): void
    {
        foreach (self::IGNORED_TOKENS as $ignored) {
            self::assertStripped('', $ignored);

            foreach (self::IGNORED_TOKENS as $anotherIgnored) {
                self::assertStripped('', $ignored . $anotherIgnored);
            }
        }
        self::assertStripped('', implode('', self::IGNORED_TOKENS));
    }

    /** @see it('strips random leading and trailing ignored tokens', () => { */
    public function testStripsRandomLeadingAndTrailingIgnoredTokens(): void
    {
        foreach ([...self::PUNCTUATOR_TOKENS, ...self::NON_PUNCTUATOR_TOKENS] as $token) {
            foreach (self::IGNORED_TOKENS as $ignored) {
                self::assertStripped($token, $ignored . $token);
                self::assertStripped($token, $token . $ignored);

                foreach (self::IGNORED_TOKENS as $anotherIgnored) {
                    self::assertStripped($token, $token . $ignored . $anotherIgnored);
                    self::assertStripped($token, $ignored . $anotherIgnored . $token);
                }
            }

            self::assertStripped($token, implode('', self::IGNORED_TOKENS) . $token);
            self::assertStripped($token, $token . implode('', self::IGNORED_TOKENS));
        }
    }

    /** @see it('strips random ignored tokens between punctuator tokens', () => { */
    public function testStripsRandomIgnoredTokensBetweenPunctuatorTokens(): void
    {
        foreach (self::PUNCTUATOR_TOKENS as $left) {
            foreach (self::PUNCTUATOR_TOKENS as $right) {
                foreach (self::IGNORED_TOKENS as $ignored) {
                    self::assertStripped($left . $right, $left . $ignored . $right);

                    foreach (self::IGNORED_TOKENS as $anotherIgnored) {
                        self::assertStripped($left . $right, $left . $ignored . $anotherIgnored . $right);
                    }
                }

                self::assertStripped($left . $right, $left . implode('', self::IGNORED_TOKENS) . $right);
            }
        }
    }

    /** @see it('strips random ignored tokens between punctuator and non-punctuator tokens', () => { */
    public function testStripsRandomIgnoredTokensBetweenPunctuatorAndNonPunctuatorTokens(): void
    {
        foreach (self::NON_PUNCTUATOR_TOKENS as $nonPunctuator) {
            foreach (self::PUNCTUATOR_TOKENS as $punctuator) {
                foreach (self::IGNORED_TOKENS as $ignored) {
                    self::assertStripped($punctuator . $nonPunctuator, $punctuator . $ignored . $nonPunctuator);

                    foreach (self::IGNORED_TOKENS as $anotherIgnored) {
                        self::assertStripped($punctuator . $nonPunctuator, $punctuator . $ignored . $anotherIgnored . $nonPunctuator);
                    }
                }

                self::assertStripped($punctuator . $nonPunctuator, $punctuator . implode('', self::IGNORED_TOKENS) . $nonPunctuator);
            }
        }
    }

    /** @see it('strips random ignored tokens between non-punctuator and punctuator tokens', () => { */
    public function testStripsRandomIgnoredTokensBetweenNonPunctuatorAndPunctuatorTokens(): void
    {
        foreach (self::NON_PUNCTUATOR_TOKENS as $nonPunctuator) {
            foreach (self::PUNCTUATOR_TOKENS as $punctuator) {
                // Covered by testReplaceRandomIgnoredTokensBetweenNonPunctuatorTokensAndSpreadWithSpace
                if ($punctuator === '...') {
                    continue;
                }

                foreach (self::IGNORED_TOKENS as $ignored) {
                    self::assertStripped($nonPunctuator . $punctuator, $nonPunctuator . $ignored . $punctuator);

                    foreach (self::IGNORED_TOKENS as $anotherIgnored) {
                        self::assertStripped($nonPunctuator . $punctuator, $nonPunctuator . $ignored . $anotherIgnored . $punctuator);
                    }
                }

                self::assertStripped($nonPunctuator . $punctuator, $nonPunctuator . implode('', self::IGNORED_TOKENS) . $punctuator);
            }
        }
    }

    /** @see it('replace random ignored tokens between non-punctuator tokens and spread with space', () => { */
    public function testReplaceRandomIgnoredTokensBetweenNonPunctuatorTokensAndSpreadWithSpace(): void
    {
        foreach (self::NON_PUNCTUATOR_TOKENS as $nonPunctuator) {
            foreach (self::IGNORED_TOKENS as $ignored) {
                self::assertStripped($nonPunctuator . ' ...', $nonPunctuator . $ignored . '...');

                foreach (self::IGNORED_TOKENS as $anotherIgnored) {
                    self::assertStripped($nonPunctuator . ' ...', $nonPunctuator . $ignored . $anotherIgnored . ' ...');
                }
            }

            self::assertStripped($nonPunctuator . ' ...', $nonPunctuator . implode('', self::IGNORED_TOKENS) . '...');
        }
    }

    /** @see it('replace random ignored tokens between non-punctuator tokens with space', () => { */
    public function testReplaceRandomIgnoredTokensBetweenNonPunctuatorTokensWithSpace(): void
    {
        foreach (self::NON_PUNCTUATOR_TOKENS as $left) {
            foreach (self::NON_PUNCTUATOR_TOKENS as $right) {
                foreach (self::IGNORED_TOKENS as $ignored) {
                    self::assertStripped($left . ' ' . $right, $left . $ignored . $right);

                    foreach (self::IGNORED_TOKENS as $anotherIgnored) {
                        self::assertStripped($left . ' ' . $right, $left . $ignored . $anotherIgnored . $right);
                    }
                }

                self::assertStripped($left . ' ' . $right, $left . implode('', self::IGNORED_TOKENS) . $right);
            }
        }
    }

    /** @see it('does not strip random ignored tokens embedded in the string', () => { */
    public function testDoesNotStripRandomIgnoredTokensEmbeddedInTheString(): void
    {
        foreach (self::IGNORED_TOKENS as $ignored) {
            self::assertStaysTheSame(json_encode($ignored, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            foreach (self::IGNORED_TOKENS as $anotherIgnored) {
                self::assertStaysTheSame(json_encode($ignored . $anotherIgnored, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            }
        }

        self::assertStaysTheSame(json_encode(implode('', self::IGNORED_TOKENS), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @see it('does not strip random ignored tokens embedded in the block string', () => { */
    public function testDoesNotStripRandomIgnoredTokensEmbeddedInTheBlockString(): void
    {
        $ignoredTokensWithoutFormatting = array_diff(self::IGNORED_TOKENS, ["\n", "\r", "\r\n", "\t", ' ']);
        foreach ($ignoredTokensWithoutFormatting as $ignored) {
            self::assertStaysTheSame('"""|' . $ignored . '|"""');

            foreach ($ignoredTokensWithoutFormatting as $anotherIgnored) {
                self::assertStaysTheSame('"""|' . $ignored . $anotherIgnored . '|"""');
            }
        }

        self::assertStaysTheSame('"""|' . implode('', $ignoredTokensWithoutFormatting) . '|"""');
    }

    /** @see it('strips ignored characters inside random block strings', () => { */
    public function testStripsIgnoredCharactersInsideRandomBlockStrings(): void
    {
        // Increase when changing the implementation, lengths above 7 are exponentially slower
        foreach (self::genFuzzStrings(["\n", "\t", ' ', '"', 'a', '\\'], 7) as $fuzzStr) {
            $testStr = '"""' . $fuzzStr . '"""';

            if (! self::isSingleToken($testStr)) {
                continue;
            }

            $testValue = self::lexValue($testStr);

            $strippedValue = self::lexValue(Printer::stripIgnoredCharacters($testStr));
            self::assertSame($testValue, $strippedValue, 'Stripping ' . json_encode($testStr, JSON_THROW_ON_ERROR));
        }
    }
}
