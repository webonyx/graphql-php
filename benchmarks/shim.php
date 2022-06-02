<?php declare(strict_types=1);

use GraphQL\Benchmarks\HugeSchemaBench;
use GraphQL\Benchmarks\LexerBench;
use GraphQL\Benchmarks\StarWarsBench;

require_once __DIR__ . '/../vendor/autoload.php';

$b = new HugeSchemaBench();
$b->setUp();
$b->benchSchema();
$b->benchSchemaLazy();
$b->benchSmallQuery();
$b->benchSmallQueryLazy();

$b = new LexerBench();
$b->setUp();
$b->benchIntrospectionQuery();

$b = new StarWarsBench();
$b->setIntroQuery();
$b->benchSchema();
$b->benchHeroQuery();
$b->benchNestedQuery();
$b->benchQueryWithFragment();
$b->benchQueryWithInterfaceFragment();
$b->benchStarWarsIntrospectionQuery();
