<?php

require_once __DIR__ . '/../vendor/autoload.php';

$b = new \GraphQL\Benchmarks\HugeSchemaBench();
$b->setUp();
$b->benchSchema();
$b->benchSchemaLazy();
$b->benchSmallQuery();
$b->benchSmallQueryLazy();

$b = new \GraphQL\Benchmarks\LexerBench();
$b->setUp();
$b->benchIntrospectionQuery();

$b = new \GraphQL\Benchmarks\StarWarsBench();
$b->setIntroQuery();
$b->benchSchema();
$b->benchHeroQuery();
$b->benchNestedQuery();
$b->benchQueryWithFragment();
$b->benchStarWarsIntrospectionQuery();
