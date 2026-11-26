<?php declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Token;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    public function testReturnTokenOnArray(): void
    {
        $token = new Token('Kind', 1, 10, 3, 5);
        $expected = [
            'kind' => 'Kind',
            'value' => null,
            'line' => 3,
            'column' => 5,
        ];

        self::assertEquals($expected, $token->toArray());
    }

    public function testSerializeAndUnserializeSingleToken(): void
    {
        $token = new Token(Token::NAME, 4, 7, 2, 3, null, 'foo');

        $serialized = serialize($token);
        $unserialized = unserialize($serialized);

        self::assertInstanceOf(Token::class, $unserialized);
        self::assertSame(Token::NAME, $unserialized->kind);
        self::assertSame(4, $unserialized->start);
        self::assertSame(7, $unserialized->end);
        self::assertSame(2, $unserialized->line);
        self::assertSame(3, $unserialized->column);
        self::assertSame('foo', $unserialized->value);
        self::assertNull($unserialized->prev);
        self::assertNull($unserialized->next);
    }

    public function testSerializeAndUnserializeFromStartMiddleAndEndToken(): void
    {
        $t0 = new Token(Token::SOF, 0, 0, 0, 0);
        $t1 = new Token(Token::NAME, 0, 4, 1, 1, $t0, 'type');
        $t0->next = $t1;
        $t2 = new Token(Token::NAME, 5, 10, 1, 6, $t1, 'Query');
        $t1->next = $t2;
        $t3 = new Token(Token::EOF, 10, 10, 1, 11, $t2);
        $t2->next = $t3;

        foreach ([$t0, $t1, $t2, $t3] as $startNode) {
            $unserialized = unserialize(serialize($startNode));
            self::assertInstanceOf(Token::class, $unserialized);

            $head = $unserialized;
            while ($head->prev !== null) {
                $head = $head->prev;
            }

            self::assertSame(Token::SOF, $head->kind);
            self::assertNull($head->prev);

            $u1 = $head->next;
            self::assertInstanceOf(Token::class, $u1);
            self::assertSame('type', $u1->value);
            self::assertSame($head, $u1->prev);

            $u2 = $u1->next;
            self::assertInstanceOf(Token::class, $u2);
            self::assertSame('Query', $u2->value);
            self::assertSame($u1, $u2->prev);

            $u3 = $u2->next;
            self::assertInstanceOf(Token::class, $u3);
            self::assertSame(Token::EOF, $u3->kind);
            self::assertSame($u2, $u3->prev);
            self::assertNull($u3->next);
        }
    }

    public function testSerializeAndUnserializeLargeDocument(): void
    {
        $sdl = "type Query {\n" . str_repeat("  field: String\n", 1500) . '}';
        $document = Parser::parse($sdl);

        $serialized = serialize($document);
        $unserialized = unserialize($serialized);

        self::assertInstanceOf(DocumentNode::class, $unserialized);
        self::assertSame(AST::toArray($document), AST::toArray($unserialized));

        $loc = $unserialized->loc;
        self::assertNotNull($loc);
        $current = $loc->startToken;
        self::assertNotNull($current);

        $count = 1;
        while ($current->next !== null) {
            $next = $current->next;
            self::assertSame($current, $next->prev);
            $current = $next;
            ++$count;
        }

        // 1500 * 3 tokens + header/braces/SOF/EOF exceeds PHP's default unserialize_max_depth (4096)
        self::assertGreaterThan(4500, $count);
        self::assertSame(Token::EOF, $current->kind);
        self::assertSame($loc->endToken, $current);

        $queryType = $unserialized->definitions[0];
        self::assertInstanceOf(ObjectTypeDefinitionNode::class, $queryType);
        $lastField = $queryType->fields[1499];
        self::assertNotNull($lastField->loc);
        self::assertNotNull($lastField->loc->startToken);

        $fieldStart = $current;
        for ($i = 0; $i < 4; ++$i) {
            self::assertNotNull($fieldStart->prev);
            $fieldStart = $fieldStart->prev;
        }
        self::assertSame($lastField->loc->startToken, $fieldStart);
    }

    public function testSerializeRepeatedlyAndMultipleDocuments(): void
    {
        $doc1 = Parser::parse('type Query { a: Int }');
        $doc2 = Parser::parse('type Mutation { b: String }');

        $serialized1 = serialize($doc1);
        $serialized2 = serialize($doc1);
        self::assertSame($serialized1, $serialized2);

        $unserializedPair = unserialize(serialize([$doc1, $doc2]));
        self::assertIsArray($unserializedPair);
        self::assertCount(2, $unserializedPair);
        self::assertInstanceOf(DocumentNode::class, $unserializedPair[0]);
        self::assertInstanceOf(DocumentNode::class, $unserializedPair[1]);
        self::assertSame(AST::toArray($doc1), AST::toArray($unserializedPair[0]));
        self::assertSame(AST::toArray($doc2), AST::toArray($unserializedPair[1]));
    }

    public function testSerializeHandlesCyclicTokensWithoutInfiniteLoop(): void
    {
        $a = new Token(Token::NAME, 0, 1, 1, 1, null, 'a');
        $b = new Token(Token::NAME, 2, 3, 1, 3, $a, 'b');
        $a->next = $b;
        $b->next = $a;
        $a->prev = $b;

        $unserialized = unserialize(serialize($a));
        self::assertInstanceOf(Token::class, $unserialized);
        self::assertSame('a', $unserialized->value);

        // Ensure subsequent normal serialization is unaffected
        $t0 = new Token(Token::SOF, 0, 0, 0, 0);
        $t1 = new Token(Token::EOF, 0, 0, 1, 1, $t0);
        $t0->next = $t1;

        $normal = unserialize(serialize($t1));
        self::assertInstanceOf(Token::class, $normal);
        self::assertInstanceOf(Token::class, $normal->prev);
        self::assertSame(Token::SOF, $normal->prev->kind);
        self::assertSame($normal, $normal->prev->next);
    }
}
