<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

use s00d\OnlineSimApi\Exception\ApiError;
use s00d\OnlineSimApi\Exception\NoNumberError;
use s00d\OnlineSimApi\Exception\TransportError;
use s00d\OnlineSimApi\OnlineSim;

final class ClientTest extends TestCase
{
    public function testSendsTheKeyAsBearerHeaderNeverInTheQueryPlusLang(): void
    {
        $client = $this->client([['json' => ['response' => '1', 'price' => 0.36]]]);
        $client->numbers->price('vkcom', 44);
        $call = $this->last();
        self::assertSame('GET', $call['method']);
        self::assertSame('Bearer test-key', $call['headers']['Authorization']);
        self::assertSame('application/json', $call['headers']['Accept']);
        self::assertArrayNotHasKey('apikey', $call['query']);
        self::assertSame('en', $call['query']['lang']);
        self::assertArrayNotHasKey('dev_id', $call['query']);
        self::assertSame(30.0, $call['timeout']);
    }

    public function testAppliesLangDevIdAndABaseUrlWithoutTrailingSlash(): void
    {
        $client = $this->client([['json' => ['response' => '1', 'price' => 1]]], baseUrl: 'https://api.test/api', lang: 'ru', devId: 42);
        $client->numbers->price('vkcom');
        $call = $this->last();
        self::assertStringStartsWith('https://api.test/api/getPrice.php?', $call['url']);
        self::assertSame('ru', $call['query']['lang']);
        self::assertSame('42', $call['query']['dev_id']);
    }

    public function testSendsNoAuthorizationHeaderWithoutAKey(): void
    {
        $client = $this->client([['json' => ['response' => '1', 'price' => 1]]], apiKey: null);
        $client->numbers->price('vkcom');
        self::assertArrayNotHasKey('Authorization', $this->last()['headers']);
    }

    public function testUsesTheDefaultBaseUrlAndAPerRequestTimeout(): void
    {
        $this->transport = new FakeTransport([['json' => ['response' => '1', 'price' => 1]]]);
        $client = new OnlineSim(apiKey: 'k', timeout: 2.5, transport: $this->transport);
        $client->numbers->price('vkcom');
        self::assertStringStartsWith('https://onlinesim.host/api/getPrice.php?', $this->last()['url']);
        self::assertSame(2.5, $this->last()['timeout']);
    }

    public function testMapsNoNumberToNoNumberError(): void
    {
        $client = $this->client([['json' => ['response' => 'NO_NUMBER']]]);
        $error = $this->caught(static fn () => $client->numbers->get('google', 44));
        self::assertInstanceOf(NoNumberError::class, $error);
        self::assertSame('NO_NUMBER', $error->errorCode);
        self::assertSame('No numbers available for this service right now', $error->getMessage());
    }

    public function testMapsHttp429TryAgainLaterRateLimitToApiErrorNotTransportError(): void
    {
        $client = $this->client([['status' => 429, 'json' => ['response' => 'TRY_AGAIN_LATER']]]);
        $error = $this->caught(static fn () => $client->numbers->state());
        self::assertInstanceOf(ApiError::class, $error); // so not a TransportError
        self::assertSame('TRY_AGAIN_LATER', $error->errorCode);
    }

    public function testMapsAMissingKeyHttp200WrongKeyToApiError(): void
    {
        $client = $this->client([['json' => ['response' => 'ERROR_WRONG_KEY']]], apiKey: null);
        $error = $this->caught(static fn () => $client->user->balance());
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame(['ERROR_WRONG_KEY', 'Wrong or missing API key'], [$error->errorCode, $error->getMessage()]);
    }

    public function testOnlyNoNumberIsNoNumberErrorForwardNotSupportedIsAPlainApiError(): void
    {
        $client = $this->client([['json' => ['response' => 'FORWARD_NOT_SUPPORTED']]]);
        $error = $this->caught(static fn () => $client->numbers->get('google'));
        self::assertInstanceOf(ApiError::class, $error);
        self::assertNotInstanceOf(NoNumberError::class, $error);
        self::assertSame('Number forwarding is no longer available', $error->getMessage());
    }

    public function testMapsHttp456WrongKeyToApiErrorNotTransportError(): void
    {
        $client = $this->client([['status' => 456, 'json' => ['response' => 'ERROR_WRONG_KEY']]]);
        $error = $this->caught(static fn () => $client->user->balance());
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame('ERROR_WRONG_KEY', $error->errorCode);
        self::assertSame('Wrong or missing API key', $error->getMessage());
    }

    public function testPrefersTheServerMessageButNotWhenItOnlyEchoesTheCode(): void
    {
        $client = $this->client([
            ['json' => ['response' => 'TRY_AGAIN_LATER', 'message' => 'Maintenance until 12:00']],
            ['json' => ['response' => 'UNDEFINED_COUNTRY', 'message' => 'UNDEFINED_COUNTRY']],
            ['json' => ['response' => 'SOMETHING_NEW']],
        ]);
        $expected = [
            ['TRY_AGAIN_LATER', 'Maintenance until 12:00'],
            ['UNDEFINED_COUNTRY', 'Unknown country'],
            ['SOMETHING_NEW', 'SOMETHING_NEW'],
        ];
        foreach ($expected as [$code, $message]) {
            $error = $this->caught(static fn () => $client->numbers->get('x'));
            self::assertInstanceOf(ApiError::class, $error);
            self::assertSame([$code, $message], [$error->errorCode, $error->getMessage()]);
        }
    }

    public function testMapsAValidationObjectToValidationErrorWithTheFieldMessages(): void
    {
        $client = $this->client([['json' => ['response' => [
            'webhook_url' => ['The webhook url must be a valid URL.', 'Too long.'],
            'name' => ['Required.'],
        ]]]]);
        $error = $this->caught(static fn () => $client->user->setWebhookUrl('nope'));
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame('VALIDATION_ERROR', $error->errorCode);
        self::assertSame('The webhook url must be a valid URL.; Too long.; Required.', $error->getMessage());
    }

    public function testMapsANonJsonBodyToTransportErrorWithStatusAndExcerpt(): void
    {
        $client = $this->client([['status' => 502, 'raw' => '<html>Bad gateway</html>']]);
        $error = $this->caught(static fn () => $client->user->balance());
        self::assertInstanceOf(TransportError::class, $error);
        self::assertSame(502, $error->status);
        self::assertSame('<html>Bad gateway</html>', $error->body);
        self::assertSame('HTTP 502: response is not JSON', $error->getMessage());
    }

    public function testCutsTheBodyExcerptTo500Characters(): void
    {
        $client = $this->client([['status' => 500, 'raw' => str_repeat('я', 600)]]);
        $error = $this->caught(static fn () => $client->user->balance());
        self::assertInstanceOf(TransportError::class, $error);
        self::assertSame(str_repeat('я', 500), $error->body);
    }

    public function testMapsAJsonErrorStatusWithoutResponseToTransportError(): void
    {
        $client = $this->client([['status' => 500, 'json' => ['error' => 'boom']]]);
        $error = $this->caught(static fn () => $client->user->balance());
        self::assertInstanceOf(TransportError::class, $error);
        self::assertSame(500, $error->status);
    }

    public function testPassesATransportFailureThroughUnchanged(): void
    {
        $failure = new TransportError('Request timed out after 30 s');
        $client = $this->client([$failure]);
        self::assertSame($failure, $this->caught(static fn () => $client->user->balance()));
    }

    public function testReturnsBodiesWithoutResponseAsTheyAre(): void
    {
        $client = $this->client([['json' => []]]);
        self::assertSame([], $client->numbers->state());
    }
}
