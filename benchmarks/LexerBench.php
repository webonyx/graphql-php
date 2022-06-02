<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\Language\Lexer;
use GraphQL\Language\Source;
use GraphQL\Language\Token;
use GraphQL\Type\Introspection;

/**
 * @BeforeMethods({"setUp"})
 * @OutputTimeUnit("milliseconds", precision=3)
 */
class LexerBench
{
    private Source $introQuery;

    public function setUp(): void
    {
        $this->introQuery = new Source(Introspection::getIntrospectionQuery());
    }

    /**
     * @Warmup(2)
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchIntrospectionQuery(): void
    {
        $lexer = new Lexer($this->introQuery);

        do {
            $token = $lexer->advance();
        } while (Token::EOF !== $token->kind);
    }
}
