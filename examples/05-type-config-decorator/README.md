# Using schema definition language and `typeConfigDecorator`

This example shows how to resolve object types in a query using the schema definition language instead of defining the schema in pure PHP code. 

The business logic matches [the Apollo GraphQL course](https://www.apollographql.com/tutorials/lift-off-part2/03-apollo-restdatasource).
There is [an accompanying issue](https://github.com/webonyx/graphql-php/issues/1699) that provides further explanations. 

## How to follow along?

1. Navigate to [examples/05-type-config-decorator](/examples/05-type-config-decorator) using the command line
2. Run `composer install`
3. Run `php -S localhost:8080 graphql.php`
4. You may use `curl` to make a request to the running API:
    
    ```
    curl --data '{"query": "{ tracksForHome { title thumbnail author { id name } } }"}' --header "Content-Type: application/json" http://localhost:8080
    ```

    Alternatively, use your favorite API client and make a request to `localhost:8080` with the following request body: 

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
