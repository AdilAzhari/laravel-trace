<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Config\ConfigBoolean;

it('passes native booleans through unchanged', function (): void {
    expect(ConfigBoolean::resolve(true, false))->toBeTrue()
        ->and(ConfigBoolean::resolve(false, true))->toBeFalse();
});

it('parses the string "true" and "false" correctly, unlike a raw (bool) cast', function (): void {
    // (bool) 'false' === true in plain PHP - this is the exact bug this
    // helper exists to avoid, since `env()` (or a host application) can
    // hand back the literal strings "true"/"false".
    expect(ConfigBoolean::resolve('true', false))->toBeTrue()
        ->and(ConfigBoolean::resolve('false', true))->toBeFalse();
});

it('is case-insensitive for "TRUE"/"FALSE"', function (): void {
    expect(ConfigBoolean::resolve('TRUE', false))->toBeTrue()
        ->and(ConfigBoolean::resolve('FALSE', true))->toBeFalse();
});

it('parses "1"/"0" and integer 1/0', function (): void {
    expect(ConfigBoolean::resolve('1', false))->toBeTrue()
        ->and(ConfigBoolean::resolve('0', true))->toBeFalse()
        ->and(ConfigBoolean::resolve(1, false))->toBeTrue()
        ->and(ConfigBoolean::resolve(0, true))->toBeFalse();
});

it('falls back to the default for null or an empty string', function (): void {
    expect(ConfigBoolean::resolve(null, true))->toBeTrue()
        ->and(ConfigBoolean::resolve(null, false))->toBeFalse()
        ->and(ConfigBoolean::resolve('', true))->toBeTrue()
        ->and(ConfigBoolean::resolve('', false))->toBeFalse();
});

it('falls back to the default for an unrecognised value', function (): void {
    expect(ConfigBoolean::resolve('yesish', true))->toBeTrue()
        ->and(ConfigBoolean::resolve('yesish', false))->toBeFalse()
        ->and(ConfigBoolean::resolve([], true))->toBeTrue();
});
