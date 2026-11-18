<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\Language\Lexer;
use GraphQL\Language\Source;
use GraphQL\Language\Token;
use GraphQL\Type\Introspection;
use Tally\Attribute\Before;
use Tally\Attribute\Expected;
use Tally\Attribute\Warmup;

#[Warmup(1)]
class LexerBench
{
    private Source $introspectionQuery;

    private Source $deeplyIndentedQuery;

    #[Before]
    public function setUp(): void
    {
        $this->introspectionQuery = new Source(Introspection::getIntrospectionQuery());
        $this->deeplyIndentedQuery = new Source($this->buildDeeplyIndentedQuery());
    }

    /** Query dominated by long runs of leading-tab indentation, to weigh the whitespace-skipping path. */
    private function buildDeeplyIndentedQuery(): string
    {
        $indent = str_repeat("\t", 16);
        $fields = [];
        for ($i = 0; $i < 200; ++$i) {
            $fields[] = "{$indent}field{$i}(arg: \"{$i}\")";
        }

        return "query DeepIndent {\n" . implode("\n", $fields) . "\n}\n";
    }

    #[Expected(instructions: '2.318M', cycles: '2.487M', memory: '35.79KiB')]
    public function benchIntrospectionQuery(): void
    {
        $lexer = new Lexer($this->introspectionQuery);

        do {
            $token = $lexer->advance();
        } while ($token->kind !== Token::EOF);
    }

    #[Expected(instructions: '15.42M', cycles: '17.35M', memory: '265.5KiB')]
    public function benchDeeplyIndentedQuery(): void
    {
        $lexer = new Lexer($this->deeplyIndentedQuery);

        do {
            $token = $lexer->advance();
        } while ($token->kind !== Token::EOF);
    }
}
