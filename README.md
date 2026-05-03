# graphql-php

**This repository is planned to move to a new home.**
**See the [announcement](https://github.com/webonyx/graphql-php/discussions/1912) for details and to share feedback.**

This is a PHP implementation of the [GraphQL](https://graphql.org) [specification](https://github.com/graphql/graphql-spec)
based on the [reference implementation in JavaScript](https://github.com/graphql/graphql-js).

## Sponsors

If you make money using this project, please consider sponsoring [its maintainer on GitHub Sponsors](https://github.com/sponsors/spawnia) or [the project on OpenCollective](https://opencollective.com/webonyx-graphql-php).

<a href="https://opencollective.com/webonyx-graphql-php/sponsor/0/website" target="_blank"><img src="https://opencollective.com/webonyx-graphql-php/sponsor/0/avatar.svg"></a>
<a href="https://opencollective.com/webonyx-graphql-php/sponsor/1/website" target="_blank"><img src="https://opencollective.com/webonyx-graphql-php/sponsor/1/avatar.svg"></a>
<a href="https://opencollective.com/webonyx-graphql-php/sponsor/2/website" target="_blank"><img src="https://opencollective.com/webonyx-graphql-php/sponsor/2/avatar.svg"></a>
<a href="https://opencollective.com/webonyx-graphql-php/sponsor/3/website" target="_blank"><img src="https://opencollective.com/webonyx-graphql-php/sponsor/3/avatar.svg"></a>
<a href="https://opencollective.com/webonyx-graphql-php/sponsor/4/website" target="_blank"><img src="https://opencollective.com/webonyx-graphql-php/sponsor/4/avatar.svg"></a>
<a href="https://opencollective.com/webonyx-graphql-php/sponsor/5/website" target="_blank"><img src="https://opencollective.com/webonyx-graphql-php/sponsor/5/avatar.svg"></a>
<a href="https://opencollective.com/webonyx-graphql-php/sponsor/6/website" target="_blank"><img src="https://opencollective.com/webonyx-graphql-php/sponsor/6/avatar.svg"></a>
<a href="https://opencollective.com/webonyx-graphql-php/sponsor/7/website" target="_blank"><img src="https://opencollective.com/webonyx-graphql-php/sponsor/7/avatar.svg"></a>
<a href="https://opencollective.com/webonyx-graphql-php/sponsor/8/website" target="_blank"><img src="https://opencollective.com/webonyx-graphql-php/sponsor/8/avatar.svg"></a>
<a href="https://opencollective.com/webonyx-graphql-php/sponsor/9/website" target="_blank"><img src="https://opencollective.com/webonyx-graphql-php/sponsor/9/avatar.svg"></a>

<a href="https://opencollective.com/webonyx-graphql-php#backers" target="_blank"><img src="https://opencollective.com/webonyx-graphql-php/backers.svg?width=890"></a>

## Installation

Via composer:

```sh
composer require webonyx/graphql-php
```

## Documentation

Full documentation is available at [https://webonyx.github.io/graphql-php](https://webonyx.github.io/graphql-php)
or in the [docs](docs) directory.

## Examples

There are several ready examples in the [examples](examples) directory,
with a specific README file per example.

## Versioning

This project follows [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html).

Elements that belong to the public API of this package are marked with the `@api` PHPDoc tag.
Constants included in the [class-reference docs](https://webonyx.github.io/graphql-php/class-reference) are also part of the public API.
Those elements are thus guaranteed to be stable within major versions.
All other elements are not part of this backwards compatibility guarantee and may change between minor or patch versions.

The most recent version is actively developed on [`master`](https://github.com/webonyx/graphql-php/tree/master).
Older versions are generally no longer supported, although exceptions may be made for [sponsors](#sponsors).
