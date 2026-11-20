<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use s00d\OnlineSimApi\Exception\ApiError;
use s00d\OnlineSimApi\Exception\TimeoutError;
use s00d\OnlineSimApi\Exception\TransportError;
use s00d\OnlineSimApi\OnlineSim;

/**
 * waitCode on the fake transport and a fake clock: `sleep` only moves `now`, nothing really waits.
 *
 * @phpstan-import-type Reply from FakeTransport
 */
final class WaitCodeTest extends TestCase
{
    private float $clock = 0.0;

    /** @var list<string> */
    private array $log = [];

    /**
     * Replies to getState for `$tzid` 7: each step is extra operation fields, or a Throwable.
     *
     * @param list<array<string, mixed>|\Throwable> $steps
     * @param list<Reply>                          $tail  replies after the steps (close / next)
     */
    private function operations(array $steps, array $tail = []): OnlineSim
    {
        $replies = [];
        foreach ($steps as $step) {
            $replies[] = $step instanceof \Throwable ? $step : ['json' => [['tzid' => 7, 'response' => 'TZ_NUM_WAIT', ...$step]]];
        }

        return $this->client([...$replies, ...$tail]);
    }

    private function sleep(float $seconds): void
    {
        $this->log[] = "sleep {$seconds}";
        $this->clock += $seconds;
    }

    private function now(): float
    {
        return $this->clock;
    }

    /**
     * Requests made, as `endpoint code=<message_to_code>` or `endpoint tzid=<tzid>` lines.
     *
     * @return list<string>
     */
    private function requests(): array
    {
        return array_map(
            static fn (array $c): string => trim(basename($c['path']) . ' ' . (isset($c['query']['message_to_code']) ? "code={$c['query']['message_to_code']}" : "tzid={$c['query']['tzid']}")),
            $this->transport->calls,
        );
    }

    public function testReturnsTheCodeOnceItArrivesAndClosesTheOperation(): void
    {
        $client = $this->operations([[], [], ['response' => 'TZ_NUM_ANSWER', 'msg' => '195895']], [['json' => ['response' => 1]]]);
        self::assertSame('195895', $client->numbers->waitCode(7, sleep: $this->sleep(...), now: $this->now(...)));
        self::assertSame(['getState.php code=1', 'getState.php code=1', 'getState.php code=1', 'setOperationOk.php tzid=7'], $this->requests());
        self::assertSame(['sleep 5', 'sleep 5'], $this->log);
        self::assertSame('7', $this->transport->calls[0]['query']['tzid']);
    }

    public function testKeepOpenCallsNextSinceSkipsThePreviousCodeFullMessageAsksForText(): void
    {
        $client = $this->operations([['msg' => 'old'], ['msg' => 'Your code is 2991']], [['json' => ['response' => '1']]]);
        $code = $client->numbers->waitCode(7, interval: 1.0, keepOpen: true, fullMessage: true, since: 'old', sleep: $this->sleep(...), now: $this->now(...));
        self::assertSame('Your code is 2991', $code);
        self::assertSame(['getState.php code=0', 'getState.php code=0', 'setOperationRevise.php tzid=7'], $this->requests());
        self::assertSame(['sleep 1'], $this->log);
    }

    /** @return iterable<string, array{string}> */
    public static function finished(): iterable
    {
        yield 'TZ_OVER_OK' => ['TZ_OVER_OK'];
        yield 'ERROR_NO_ITEMS' => ['ERROR_NO_ITEMS'];
    }

    #[DataProvider('finished')]
    public function testRaisesApiErrorAtOnceForAFinishedOperation(string $status): void
    {
        $client = $this->operations([['response' => $status]]);
        $error = $this->caught(fn () => $client->numbers->waitCode(7, sleep: $this->sleep(...), now: $this->now(...)));
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame($status, $error->errorCode);
        self::assertCount(1, $this->transport->calls);
        self::assertSame([], $this->log);
    }

    public function testAFinishedOperationThatStillCarriesANewCodeReturnsIt(): void
    {
        $client = $this->operations([['response' => 'TZ_OVER_OK', 'msg' => '1234']], [['json' => ['response' => 1]]]);
        self::assertSame('1234', $client->numbers->waitCode(7, sleep: $this->sleep(...), now: $this->now(...)));
    }

    public function testRaisesTimeoutErrorWhenNoCodeArrivesTheLastSleepIsCutToTheDeadline(): void
    {
        $client = $this->operations([[], [], [], []]);
        $error = $this->caught(fn () => $client->numbers->waitCode(7, interval: 4.0, timeout: 10.0, sleep: $this->sleep(...), now: $this->now(...)));
        self::assertInstanceOf(TimeoutError::class, $error);
        self::assertSame(['sleep 4', 'sleep 4', 'sleep 2'], $this->log);
        self::assertCount(4, $this->transport->calls);
        self::assertSame(10.0, $this->clock);
    }

    public function testRetriesATransientTransportError(): void
    {
        $client = $this->operations([new TransportError('Network error: reset'), ['msg' => '42']], [['json' => ['response' => 1]]]);
        self::assertSame('42', $client->numbers->waitCode(7, sleep: $this->sleep(...), now: $this->now(...)));
        self::assertSame(['getState.php code=1', 'getState.php code=1', 'setOperationOk.php tzid=7'], $this->requests());
        self::assertSame(['sleep 5'], $this->log);
    }

    /** @return iterable<string, array{string}> */
    public static function retryable(): iterable
    {
        yield 'TRY_AGAIN_LATER' => ['TRY_AGAIN_LATER'];
        yield 'ERROR_UNDEFINED' => ['ERROR_UNDEFINED'];
        yield 'INTERVAL_CONCURRENT_REQUESTS_ERROR' => ['INTERVAL_CONCURRENT_REQUESTS_ERROR'];
    }

    #[DataProvider('retryable')]
    public function testRetriesATransientApiErrorUntilTheCodeArrives(string $code): void
    {
        $client = $this->operations([new ApiError($code), ['msg' => '42']], [['json' => ['response' => 1]]]);
        self::assertSame('42', $client->numbers->waitCode(7, sleep: $this->sleep(...), now: $this->now(...)));
        self::assertSame(['getState.php code=1', 'getState.php code=1', 'setOperationOk.php tzid=7'], $this->requests());
        self::assertSame(['sleep 5'], $this->log);
    }

    public function testRetriesHttp429TryAgainLaterFromTheWire(): void
    {
        $client = $this->operations([], [
            ['status' => 429, 'json' => ['response' => 'TRY_AGAIN_LATER']],
            ['json' => [['tzid' => 7, 'response' => 'TZ_NUM_ANSWER', 'msg' => '42']]],
            ['json' => ['response' => 1]],
        ]);
        self::assertSame('42', $client->numbers->waitCode(7, sleep: $this->sleep(...), now: $this->now(...)));
    }

    public function testARetryableApiErrorStillEndsInTimeoutErrorAtTheDeadline(): void
    {
        $client = $this->operations(array_fill(0, 3, new ApiError('TRY_AGAIN_LATER')));
        $error = $this->caught(fn () => $client->numbers->waitCode(7, interval: 5.0, timeout: 10.0, sleep: $this->sleep(...), now: $this->now(...)));
        self::assertInstanceOf(TimeoutError::class, $error);
        self::assertCount(3, $this->transport->calls);
    }

    public function testAnOperationWithoutResponseKeepsPolling(): void
    {
        $client = $this->client([
            ['json' => [['tzid' => 7, 'service' => 'google']]],
            ['json' => [['tzid' => 7, 'response' => 'TZ_NUM_ANSWER', 'msg' => '77']]],
            ['json' => ['response' => 1]],
        ]);
        self::assertSame('77', $client->numbers->waitCode(7, sleep: $this->sleep(...), now: $this->now(...)));
    }

    public function testRaisesAnyOtherApiErrorAtOnce(): void
    {
        $client = $this->client([['json' => ['response' => 'ERROR_NO_OPERATIONS']]]);
        $error = $this->caught(fn () => $client->numbers->waitCode(7, sleep: $this->sleep(...), now: $this->now(...)));
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame('ERROR_NO_OPERATIONS', $error->errorCode);
        self::assertCount(1, $this->transport->calls);
    }
}
