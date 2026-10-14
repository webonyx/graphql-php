<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Language\Visitor;
use GraphQL\Type\Introspection;

/**
 * @BeforeMethods({"setUp"})
 *
 * @OutputTimeUnit("milliseconds", precision=3)
 *
 * @Warmup(2)
 *
 * @Revs(100)
 *
 * @Iterations(5)
 */
class VisitorBench
{
    private DocumentNode $introspectionAST;

    private DocumentNode $nestedAST;

    public function setUp(): void
    {
        $this->introspectionAST = Parser::parse(Introspection::getIntrospectionQuery());

        $this->nestedAST = Parser::parse('
            query NestedQuery {
              hero {
                name
                friends {
                  name
                  appearsIn
                  friends {
                    name
                    friends {
                      name
                      appearsIn
                    }
                  }
                }
              }
            }

            fragment HumanFields on Human {
              name
              homePlanet
              friends {
                name
              }
            }
        ');
    }

    public function benchVisitIntrospectionWithEnterLeave(): void
    {
        Visitor::visit($this->introspectionAST, [
            'enter' => static function (Node $node): void {},
            'leave' => static function (Node $node): void {},
        ]);
    }

    public function benchVisitIntrospectionWithKindMap(): void
    {
        Visitor::visit($this->introspectionAST, [
            NodeKind::FIELD => [
                'enter' => static function (Node $node): void {},
                'leave' => static function (Node $node): void {},
            ],
            NodeKind::NAME => [
                'enter' => static function (Node $node): void {},
                'leave' => static function (Node $node): void {},
            ],
            NodeKind::SELECTION_SET => [
                'enter' => static function (Node $node): void {},
                'leave' => static function (Node $node): void {},
            ],
        ]);
    }

    public function benchVisitIntrospectionWithKindCallable(): void
    {
        Visitor::visit($this->introspectionAST, [
            NodeKind::FIELD => static function (Node $node): void {},
            NodeKind::NAME => static function (Node $node): void {},
            NodeKind::SELECTION_SET => static function (Node $node): void {},
        ]);
    }

    public function benchVisitIntrospectionWithEnterLeaveMap(): void
    {
        Visitor::visit($this->introspectionAST, [
            'enter' => [
                NodeKind::FIELD => static function (Node $node): void {},
                NodeKind::NAME => static function (Node $node): void {},
            ],
            'leave' => [
                NodeKind::FIELD => static function (Node $node): void {},
                NodeKind::NAME => static function (Node $node): void {},
            ],
        ]);
    }

    public function benchVisitNestedWithEnterLeave(): void
    {
        Visitor::visit($this->nestedAST, [
            'enter' => static function (Node $node): void {},
            'leave' => static function (Node $node): void {},
        ]);
    }
}
