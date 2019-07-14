<?php

interface Resolver {
    public function resolve($rootValue, $args, $context);
}

class Addition implements Resolver
{
    public function resolve($rootValue, $args, $context)
    {
        return $args['x'] + $args['y'];
    }
}

class Echoer implements Resolver
{
    public function resolve($rootValue, $args, $context)
    {
        return $rootValue['prefix'].$args['message'];
    }
}

return [
    'sum' => function($rootValue, $args, $context) {
        $sum = new Addition();

        return $sum->resolve($rootValue, $args, $context);
    },
    'echo' => function($rootValue, $args, $context) {
        $echo = new Echoer();

        return $echo->resolve($rootValue, $args, $context);
    },
    'prefix' => 'You said: ',
];
