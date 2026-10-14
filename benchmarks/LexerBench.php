<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\Language\Lexer;
use GraphQL\Language\Source;
use GraphQL\Language\Token;
use GraphQL\Type\Introspection;

/**
 * @BeforeMethods({"setUp"})
 *
 * @OutputTimeUnit("milliseconds", precision=3)
 */
class LexerBench
{
    private Source $introspectionQuery;

    private Source $deeplyIndentedQuery;

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

    /**
     * @Warmup(2)
     *
     * @Revs(100)
     *
     * @Iterations(5)
     */
    public function benchIntrospectionQuery(): void
    {
        $lexer = new Lexer($this->introspectionQuery);

        do {
            $token = $lexer->advance();
        } while ($token->kind !== Token::EOF);
    }

    /**
     * @Warmup(2)
     *
     * @Revs(100)
     *
     * @Iterations(5)
     */
    public function benchDeeplyIndentedQuery(): void
    {
        $lexer = new Lexer($this->deeplyIndentedQuery);

        do {
            $token = $lexer->advance();
        } while ($token->kind !== Token::EOF);
    }
}
