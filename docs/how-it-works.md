# Overview
Following reading describes implementation details of query execution process. It may clarify some 
internals of GraphQL runtime but is not required to use it.

# Parsing

TODOC

# Validating
TODOC

# Executing
TODOC

# Errors explained
There are 3 types of errors in GraphQL:

- **Syntax**: query has invalid syntax and could not be parsed;
- **Validation**: query is incompatible with type system (e.g. unknown field is requested);
- **Execution**: occurs when some field resolver throws (or returns unexpected value).

Obviously when **Syntax** or **Validation** error is detected - process is interrupted and query is not 
executed.

GraphQL is forgiving to **Execution** errors which occur in resolvers of nullable fields. 
If such field throws or returns unexpected value the value of the field in response will be simply 
replaced with `null` and error entry will be registered.

If exception is thrown in non-null field - error bubbles up to first nullable field. This nullable field is  
replaced with `null` and error entry is added to response. If all fields up to the root are non-null - 
**data** entry will be removed from response and only **errors** key will be presented.
