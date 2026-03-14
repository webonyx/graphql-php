window.BENCHMARK_DATA = {
  "lastUpdate": 1773519036807,
  "repoUrl": "https://github.com/webonyx/graphql-php",
  "entries": {
    "Benchmark": [
      {
        "commit": {
          "author": {
            "email": "benedikt@franke.tech",
            "name": "Benedikt Franke",
            "username": "spawnia"
          },
          "committer": {
            "email": "noreply@github.com",
            "name": "GitHub",
            "username": "web-flow"
          },
          "distinct": true,
          "id": "5fabdcf2a80032e7163ede6e3603e625de5d4b24",
          "message": "Add benchmark history tracking via github-action-benchmark (#1870)",
          "timestamp": "2026-03-14T21:03:31+01:00",
          "tree_id": "e1dac45bcb13dda2066192da2c6e6069ef6e81e4",
          "url": "https://github.com/webonyx/graphql-php/commit/5fabdcf2a80032e7163ede6e3603e625de5d4b24"
        },
        "date": 1773519036468,
        "tool": "customSmallerIsBetter",
        "benches": [
          {
            "name": "LexerBench::benchIntrospectionQuery",
            "value": 0.469,
            "unit": "ms"
          },
          {
            "name": "ScalarOverrideBench::benchGetTypeWithoutOverride",
            "value": 0,
            "unit": "ms"
          },
          {
            "name": "ScalarOverrideBench::benchGetTypeWithTypeLoaderOverride",
            "value": 0,
            "unit": "ms"
          },
          {
            "name": "ScalarOverrideBench::benchGetTypeWithTypesOverride",
            "value": 0,
            "unit": "ms"
          },
          {
            "name": "ScalarOverrideBench::benchExecuteWithoutOverride",
            "value": 0.165,
            "unit": "ms"
          },
          {
            "name": "ScalarOverrideBench::benchExecuteWithTypeLoaderOverride",
            "value": 0.165,
            "unit": "ms"
          },
          {
            "name": "ScalarOverrideBench::benchExecuteWithTypesOverride",
            "value": 0.163,
            "unit": "ms"
          },
          {
            "name": "DeferredBench::benchSingleDeferred",
            "value": 0.001,
            "unit": "ms"
          },
          {
            "name": "DeferredBench::benchNestedDeferred",
            "value": 0.002,
            "unit": "ms"
          },
          {
            "name": "DeferredBench::benchChain5",
            "value": 0.005,
            "unit": "ms"
          },
          {
            "name": "DeferredBench::benchChain100",
            "value": 0.08,
            "unit": "ms"
          },
          {
            "name": "DeferredBench::benchManyDeferreds",
            "value": 0.451,
            "unit": "ms"
          },
          {
            "name": "DeferredBench::benchManyNestedDeferreds",
            "value": 12.84,
            "unit": "ms"
          },
          {
            "name": "DeferredBench::bench1000Chains",
            "value": 3.427,
            "unit": "ms"
          },
          {
            "name": "StarWarsBench::benchSchema",
            "value": 0.005,
            "unit": "ms"
          },
          {
            "name": "StarWarsBench::benchHeroQuery",
            "value": 0.343,
            "unit": "ms"
          },
          {
            "name": "StarWarsBench::benchNestedQuery",
            "value": 0.767,
            "unit": "ms"
          },
          {
            "name": "StarWarsBench::benchQueryWithFragment",
            "value": 0.822,
            "unit": "ms"
          },
          {
            "name": "StarWarsBench::benchQueryWithInterfaceFragment",
            "value": 0.77,
            "unit": "ms"
          },
          {
            "name": "StarWarsBench::benchStarWarsIntrospectionQuery",
            "value": 7.289,
            "unit": "ms"
          },
          {
            "name": "BuildSchemaBench::benchBuildSchema",
            "value": 26.289,
            "unit": "ms"
          },
          {
            "name": "HugeSchemaBench::benchSchema",
            "value": 12.762,
            "unit": "ms"
          },
          {
            "name": "HugeSchemaBench::benchSchemaLazy",
            "value": 0.001,
            "unit": "ms"
          },
          {
            "name": "HugeSchemaBench::benchSmallQuery",
            "value": 16.99,
            "unit": "ms"
          },
          {
            "name": "HugeSchemaBench::benchSmallQueryLazy",
            "value": 17.985,
            "unit": "ms"
          }
        ]
      }
    ]
  }
}