<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

use s00d\OnlineSimApi\Exception\TransportError;
use s00d\OnlineSimApi\OnlineSim;
use s00d\OnlineSimApi\Transport\CurlTransport;

/** The default transport, offline: a refused local connection must become a TransportError without status. */
final class CurlTransportTest extends TestCase
{
    public function testARefusedConnectionIsATransportErrorWithoutStatus(): void
    {
        // Bind a free local port, then release it: nothing listens there, so the connection is refused at once.
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($server, (string) $errstr);
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($server, false), ':'), 1);
        fclose($server);

        $client = new OnlineSim(apiKey: 'k', baseUrl: "http://127.0.0.1:{$port}/api/", transport: new CurlTransport());
        $error = $this->caught(static fn () => $client->user->balance());
        self::assertInstanceOf(TransportError::class, $error);
        self::assertNull($error->status);
        self::assertSame('', $error->body);
        self::assertStringStartsWith('Network error: ', $error->getMessage());
    }
}
