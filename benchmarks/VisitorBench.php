<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Language\Visitor;
use GraphQL\Type\Introspection;
use Tally\Attribute\Before;
use Tally\Attribute\Expected;
use Tally\Attribute\Warmup;

#[Warmup(1)]
class VisitorBench
{
    private DocumentNode $introspectionAST;

    private DocumentNode $nestedAST;

    #[Before]
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

    #[Expected(instructions: '2.409M', cycles: '2.486M', memory: '13.74KiB')]
    public function benchVisitIntrospectionWithEnterLeave(): void
    {
        Visitor::visit($this->introspectionAST, [
            'enter' => static function (Node $node): void {},
            'leave' => static function (Node $node): void {},
        ]);
    }

    #[Expected(instructions: '2.347M', cycles: '2.421M', memory: '16.34KiB')]
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

    #[Expected(instructions: '2.200M', cycles: '2.272M', memory: '14.12KiB')]
    public function benchVisitIntrospectionWithKindCallable(): void
    {
        Visitor::visit($this->introspectionAST, [
            NodeKind::FIELD => static function (Node $node): void {},
            NodeKind::NAME => static function (Node $node): void {},
            NodeKind::SELECTION_SET => static function (Node $node): void {},
        ]);
    }

    #[Expected(instructions: '2.370M', cycles: '2.463M', memory: '15.23KiB')]
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

    #[Expected(instructions: '549.4K', cycles: '573.4K', memory: '10.44KiB')]
    public function benchVisitNestedWithEnterLeave(): void
    {
        Visitor::visit($this->nestedAST, [
            'enter' => static function (Node $node): void {},
            'leave' => static function (Node $node): void {},
        ]);
    }
}
