# Using typeConfigDecoration with SDL

This example is intended to show you the boilerplate that you would need to resolve object types in a query using the SDL instead of defining the schema using the Object Types. 

**Code Context:** The business logic is from [Apollo graphql course. ](https://www.apollographql.com/tutorials/lift-off-part2/03-apollo-restdatasource) Full source code is also available in the accompanying repo for this course . 

**Further explanation** : There is [an accompanying issue](https://github.com/webonyx/graphql-php/issues/1699) where I explained some of the boilerplate code . 

## How to follow along ?
1. Navigate to `examples/05-using-typeConfigDecorator-with-SDL` using the command line
2. Run `composer install`
3. Run `php -S localhost:8080 graphql.php`
4. Use your favorite API client and make a request to `localhost:8080` . Provide the following as the request body : 

```graphql
  {
    tracksForHome {
      title
      thumbnail
      author {
        id
        name
      }
    }
  }
```

5. You can also use `curl` : 
```
curl --data '{"query": "{ tracksForHome { title thumbnail author { id name } } }"}' --header "Content-Type: application/json" http://localhost:8080
```

