## Blog Example

Simple but full-featured example of GraphQL API. Models simple blog with Stories and Users. 

Note that graphql-php doesn't dictate you how to structure your application or data layer. 
You may choose the way of using the library as you prefer.

Best practices in GraphQL world still emerge, so feel free to post your proposals or own 
examples as PRs.

### Running locally
```
php -S localhost:8080 ./index.php
```

### Browsing API
The most convenient way to browse GraphQL API is by using [GraphiQL](https://github.com/graphql/graphiql)
But setting it up from scratch may be inconvenient. The great and easy alternative is to use one of 
existing Google Chrome extensions:
- [ChromeiQL](https://chrome.google.com/webstore/detail/chromeiql/fkkiamalmpiidkljmicmjfbieiclmeij)
- [GraphiQL Feen](https://chrome.google.com/webstore/detail/graphiql-feen/mcbfdonlkfpbfdpimkjilhdneikhfklp)

Note that these extensions may be out of date, but most of the time they Just Work(TM)

Set up `http://localhost:8080?debug=0` as your GraphQL endpoint/server in these extensions and 
execute following query:

```
{
  viewer {
    id
    email
  }
  lastStoryPosted {
    id
    isLiked
    author {
      id
      photo(size: ICON) {
        id
        url
        type
        size
        width
        height
        # Uncomment to see error reporting for failed resolvers
        # error
      }
      lastStoryPosted {
        id
      }
    }
  }
}
```

### Debugging
By default this example runs in production mode without additional debugging tools enabled.

In order to enable debugging mode with additional validation of type definition configs, 
PHP errors handling and reporting - change your endpoint to `http://localhost:8080?debug=1`
