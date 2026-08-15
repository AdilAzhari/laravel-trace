<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Contracts\Tracer;

it('resolves the tracer from the container', function () {
    expect(app(Tracer::class))
        ->toBeInstanceOf(
            LaravelTrace\LaravelTrace\Tracing\Tracer::class,
        );
});
