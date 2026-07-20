# Security Policy

## Reporting a Vulnerability

Please report security vulnerabilities through [GitHub's private vulnerability reporting](https://github.com/webonyx/graphql-php/security/advisories/new).

Do not open public issues for security vulnerabilities.

## Scope

This library is a low-level GraphQL engine.
It parses, validates, and executes GraphQL documents against a schema you define.
The security boundary is between the library and the GraphQL document it receives — we consider bugs that allow a crafted document to crash the PHP process, exhaust resources disproportionate to document size, or bypass validation to be vulnerabilities.

The following are **intentional design decisions** and are **not** considered vulnerabilities:

### Introspection enabled by default

Introspection is part of the [GraphQL specification](https://spec.graphql.org/October2021/#sec-Introspection).
Disabling it by default would break spec compliance and tooling (GraphiQL, IDE plugins, code generators).
The library provides [`DisableIntrospection`](docs/security.md#disabling-introspection) for applications that choose to restrict it.

### No default query depth or complexity limits

The library provides [`QueryDepth`](docs/security.md#limiting-query-depth) and [`QueryComplexity`](docs/security.md#query-complexity-analysis) validation rules, but does not enable them by default.
Sensible limits depend entirely on your schema shape and use case — a generic default would either break legitimate queries or be too high to matter.
Applying these limits is the responsibility of the application, just as rate limiting and authentication are.

### Error messages containing user input

GraphQL error messages include the invalid values that caused them (e.g. `Int cannot represent non-integer value: "foo"`).
This is standard behavior matching graphql-js and the expectations of GraphQL tooling.
Error messages are returned as JSON — the library does not render HTML.
If your application renders error messages as raw HTML, that is an XSS vulnerability in your application, not in this library.

### General hardening advice

Reports that recommend enabling security features we already provide as opt-in, or that describe theoretical attack scenarios that require the application to be misconfigured, will be closed as informational.
See [docs/security.md](docs/security.md) for guidance on hardening your GraphQL endpoint.
