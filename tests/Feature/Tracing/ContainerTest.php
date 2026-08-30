<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\Tracer;

it('resolves the tracer from the container', function (): void {
    expect(app(Tracer::class))
        ->toBeInstanceOf(
            AdilAzhari\LaravelTrace\Tracing\Tracer::class,
        );
});
