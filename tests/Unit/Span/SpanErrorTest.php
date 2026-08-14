<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Span\SpanError;

it('creates an error from a throwable', function (): void {
    $exception = new RuntimeException(
        'Payment provider timed out',
    );

    $error = SpanError::fromThrowable($exception);

    expect($error->type)
        ->toBe(RuntimeException::class)
        ->and($error->message)
        ->toBe('Payment provider timed out')
        ->and($error->file)
        ->toBe($exception->getFile())
        ->and($error->line)
        ->toBe($exception->getLine());
});
