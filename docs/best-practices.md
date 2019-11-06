# Type Registry
**graphql-php** expects that each type in Schema is presented by single instance. Therefore
if you define your types as separate PHP classes you need to ensure that each type is referenced only once.
 
Technically you can create several instances of your type (for example for tests), but `GraphQL\Type\Schema` 
will throw on attempt to add different instances with the same name.

There are several ways to achieve this depending on your preferences. We provide reference 
implementation below that introduces TypeRegistry class:
