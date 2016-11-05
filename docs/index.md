# About GraphQL

GraphQL is a modern way to build HTTP APIs consumed by web and mobile clients.
It is intended to be a replacement for REST and SOAP APIs (even for **existing applications**).

GraphQL itself is a [specification](https://github.com/facebook/graphql) designed by Facebook
engineers. Various implementations of this specification were written 
[for different languages and environments](http://graphql.org/code/).

Great overview of GraphQL features and benefits is presented on [official website](http://graphql.org/). 
All of them equally apply to this PHP implementation. 


# About graphql-php

**graphql-php** is a feature-complete implementation of GraphQL specification in PHP (5.4+, 7.0+). 
It was originally inspired by [reference JavaScript implementation](https://github.com/graphql/graphql-js) 
published by Facebook.

This library is a thin wrapper around your existing data layer and business logic. 
It doesn't dictate how these layers are implemented or which storage engines 
are used. Instead it provides tools for creating rich API for your existing app. 

These tools include:

 - Primitives to express your app as a Type System
 - Tools for validation and introspection of this Type System (for compatibility with tools like [GraphiQL](complementary-tools/#graphiql))
 - Tools for parsing, validating and executing GraphQL queries against this Type System
 - Rich error reporting, including query validation and execution errors
 - Optional tools for parsing GraphQL Schema Definition language

Also several [complementary tools](complementary-tools/) are available which provide integrations with 
existing PHP frameworks, add support for Relay, etc.

## Current Status
Current version supports all features described by GraphQL specification 
(including April 2016 add-ons) as well as some experimental features like 
Schema Language parser.

Ready for real-world usage.