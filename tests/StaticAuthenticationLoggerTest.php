<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use YdbPlatform\Ydb\Auth\Implement\StaticAuthentication;
use YdbPlatform\Ydb\Ydb;

// setYdbConnectionConfig() built its nested Ydb from $config (still holding
// 'logger') *and* passed $this->logger as the second constructor argument -
// Ydb's own "set in 2 places" guard then rejected StaticAuthentication with
// any logger at all, even one with nothing unserializable in it.
class StaticAuthenticationLoggerTest extends TestCase
{
    public function testStaticAuthenticationWithALoggerDoesNotTriggerSetInTwoPlaces(): void
    {
        $config = [
            'database' => '/local',
            'endpoint' => 'localhost:2136',
            'discovery' => false,
            'iam_config' => ['insecure' => true],
            'credentials' => new StaticAuthentication('testuser', 'testpassword'),
            'logger' => new NullLogger(),
        ];

        $ydb = new Ydb($config);

        self::assertInstanceOf(Ydb::class, $ydb);
    }
}
