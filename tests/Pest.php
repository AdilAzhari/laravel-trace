<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Tests\TestCase;

require_once __DIR__.'/Support/readerHelpers.php';
require_once __DIR__.'/Support/envHelpers.php';

uses(TestCase::class)->in(__DIR__);

/*
 * Runs a test body once per storage driver. The read-side behaviour must be
 * identical whichever driver is active, so the cross-driver reader suites
 * share this dataset.
 */
dataset('drivers', ['memory', 'database']);
