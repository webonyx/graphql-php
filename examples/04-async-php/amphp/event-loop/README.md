## Event loop with AMPHP

While you can create an own http-server with PHP GraphQL & AMPHP (see "http-server" example)
this example aims to give you an event-loop on traditional request model (apache, fpm, ...)
This variant can be used if you want to use async but also have good separation between requests.