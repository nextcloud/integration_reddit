<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Tests;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OCA\Reddit\Service\RedditAPIService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Throwable;

class RedditAPIServiceTest extends TestCase {

	private IClient $client;
	private RedditAPIService $apiService;

	public function setUp(): void {
		parent::setUp();

		$this->client = $this->createMock(IClient::class);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($this->client);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('decrypt')->willReturn('a-token');

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn ($userId, $appName, $key, $default = '') => match ($key) {
				'token' => 'encrypted',
				default => $default,
			}
		);

		$this->apiService = new RedditAPIService(
			$clientService,
			$this->createMock(LoggerInterface::class),
			$l10n,
			$crypto,
			$config,
		);
	}

	private function served(int $status, string $body, array $headers = []): IResponse {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn($body);
		$response->method('getHeaders')->willReturn($headers);
		return $response;
	}

	private static function answer(string $class, int $status): Throwable {
		return new $class(
			'Reddit said ' . $status,
			new Request('GET', 'https://oauth.reddit.com/new'),
			new Response($status),
		);
	}

	/**
	 * The dashboard widget stops polling and asks the user to connect again
	 * when it is told the answer was unauthorised, so only an answer about the
	 * account itself may be reported that way.
	 *
	 * @dataProvider provideFailuresThatAreNotTheAccount
	 */
	public function testAFailureOnRedditsSideIsNotBlamedOnTheAccount(Throwable $error): void {
		$this->client->method('get')->willThrowException($error);

		$result = $this->apiService->request('admin', 'new');

		$this->assertArrayHasKey('error', $result);
		$this->assertTrue($result['unreachable'] ?? false, 'was blamed on the account: ' . json_encode($result));
	}

	public static function provideFailuresThatAreNotTheAccount(): array {
		return [
			'Reddit is overloaded' => [self::answer(ServerException::class, 503)],
			'Reddit is rate limiting' => [self::answer(ClientException::class, 429)],
			'Reddit is not answering' => [self::answer(ServerException::class, 502)],
			'the connection was refused' => [
				new ConnectException('Connection refused', new Request('GET', 'https://oauth.reddit.com/new')),
			],
		];
	}

	/**
	 * @dataProvider provideFailuresThatAreTheAccount
	 */
	public function testARefusedTokenIsBlamedOnTheAccount(int $status): void {
		$this->client->method('get')->willThrowException(self::answer(ClientException::class, $status));

		$result = $this->apiService->request('admin', 'new');

		$this->assertArrayHasKey('error', $result);
		$this->assertArrayNotHasKey('unreachable', $result);
	}

	public static function provideFailuresThatAreTheAccount(): array {
		return ['unauthorised' => [401], 'forbidden' => [403]];
	}

	public function testAnAnswerIsHandedBackAsItCame(): void {
		$this->client->method('get')->willReturn(
			$this->served(200, json_encode(['data' => ['children' => []]])),
		);

		$this->assertSame(['data' => ['children' => []]], $this->apiService->request('admin', 'new'));
	}

	/**
	 * @dataProvider provideEmptyAvatarRequests
	 */
	public function testNoAvatarIsAskedForWhenNobodyWasNamed(?string $username, ?string $subreddit): void {
		$this->client->expects($this->never())->method('get');

		$this->assertNull($this->apiService->getAvatar('admin', $username, $subreddit));
	}

	public static function provideEmptyAvatarRequests(): array {
		return [
			'neither' => [null, null],
			'both empty' => ['', ''],
			'an empty subreddit' => [null, ''],
		];
	}

	/**
	 * @dataProvider provideThumbnailUrls
	 */
	public function testOnlyRedditsOwnImageHostsAreFetched(string $url, bool $allowed): void {
		$this->client->method('get')->willReturn(
			$this->served(200, 'bytes', ['Content-Type' => ['image/jpeg']]),
		);

		$this->assertSame($allowed, $this->apiService->getThumbnail($url) !== null, $url);
	}

	public static function provideThumbnailUrls(): array {
		return [
			'Reddit image host' => ['https://i.redd.it/abc.jpg', true],
			'Reddit thumbnail host' => ['https://b.thumbs.redditmedia.com/abc.jpg', true],
			'a host that only starts like it' => ['https://i.redd.it.attacker.example/x', false],
			'a host that only contains it' => ['https://x-i.redd.ixyz.attacker.example/x', false],
			'something that is not a url' => ['default', false],
			'nothing at all' => ['', false],
		];
	}
}
