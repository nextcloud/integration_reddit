<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Tests;

use OCA\Reddit\Controller\RedditAPIController;
use OCA\Reddit\Service\RedditAPIService;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

class RedditAPIControllerTest extends TestCase {

	private IConfig $config;
	private ICrypto $crypto;
	private RedditAPIService $apiService;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->apiService = $this->createMock(RedditAPIService::class);
	}

	private function controller(string $token = 'encrypted'): RedditAPIController {
		$this->config->method('getUserValue')->willReturn($token);
		$this->crypto->method('decrypt')->willReturn($token);

		return new RedditAPIController(
			'integration_reddit',
			$this->createMock(IRequest::class),
			$this->config,
			$this->createMock(IURLGenerator::class),
			$this->apiService,
			$this->crypto,
			'admin',
		);
	}

	public function testNewsIsAnsweredWithTheListing(): void {
		$this->apiService->method('getNotifications')->willReturn([['name' => 't3_abc']]);

		$response = $this->controller()->getNotifications();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([['name' => 't3_abc']], $response->getData());
	}

	public function testAMissingTokenIsAnsweredWithBadRequest(): void {
		$response = $this->controller('')->getNotifications();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testARefusedRequestIsAnsweredWithUnauthorized(): void {
		$this->apiService->method('getNotifications')->willReturn(['error' => 'invalid_grant']);

		$response = $this->controller()->getNotifications();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * The dashboard widget stops polling and asks the user to connect again
	 * when it is told the request was unauthorised, so a Reddit that cannot be
	 * reached must not be reported that way: nothing is wrong with the account.
	 */
	public function testAnUnreachableRedditIsAnsweredWithServiceUnavailable(): void {
		$this->apiService->method('getNotifications')->willReturn([
			'error' => 'Could not reach Reddit',
			'unreachable' => true,
		]);

		$response = $this->controller()->getNotifications();

		$this->assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
	}
}
