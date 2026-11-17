<?php

declare(strict_types=1);

namespace App\Tests\Functional\Demonstration;

use App\Demonstration\Trace\TraceStore;
use App\Tests\Functional\BrowserTestCase;
use Psr\Cache\CacheItemPoolInterface;

abstract class DemonstrationTestCase extends BrowserTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::service('test.app.demonstration_trace_pool', CacheItemPoolInterface::class)->clear();
    }

    protected static function traces(): TraceStore
    {
        return self::service('test.app.demonstration_traces', TraceStore::class);
    }
}
