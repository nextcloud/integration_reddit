<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Tests;

use OCA\Reddit\AppInfo\Application;
use OCA\Reddit\Controller\ConfigController;
use OCA\Reddit\Service\RedditAPIService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigControllerTest extends TestCase {

	private IAppConfig&MockObject $appConfig;
	private IConfig&MockObject $config;
	private RedditAPIService&MockObject $apiService;
	/** @var array<string, array{string, bool}> */
	private array $written = [];

	public function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig->method('setAppValueString')->willReturnCallback(
			function (string $key, string $value, bool $lazy = false, bool $sensitive = false): bool {
				$this->written[$key] = [$value, $lazy];
				return true;
			}
		);
		$this->config = $this->createMock(IConfig::class);
		// what the server hands a read of a lazy row through IConfig
		$this->config->method('getAppValue')->willReturnCallback(fn ($app, $key, $default = '') => $default);
		$this->apiService = $this->createMock(RedditAPIService::class);
	}

	private function controller(): ConfigController {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(fn (string $value): string => 'encrypted:' . $value);
		$crypto->method('decrypt')->willReturnCallback(fn (string $value): string => substr($value, strlen('encrypted:')));

		return new ConfigController(
			'integration_reddit',
			$this->createMock(IRequest::class),
			$this->appConfig,
			$this->config,
			$this->createMock(IURLGenerator::class),
			$l10n,
			$crypto,
			$this->apiService,
			'alice',
		);
	}

	public function testTheClientIsStoredLazily(): void {
		$this->controller()->setSensitiveAdminConfig(['client_id' => 'my-client', 'client_secret' => 's3cret']);

		$this->assertSame([
			'client_id' => ['my-client', true],
			'client_secret' => ['encrypted:s3cret', true],
		], $this->written);
	}

	public function testAnAdminValueIsStoredLazily(): void {
		$this->controller()->setAdminConfig(['link_preview_enabled' => '0']);

		$this->assertSame(['link_preview_enabled' => ['0', true]], $this->written);
	}

	/**
	 * A key that is not the endpoint's own could name one of the server's
	 * rows of the app, such as "enabled", which a lazy write would leave
	 * looking disabled.
	 *
	 * @dataProvider provideKeysTheEndpointDoesNotOwn
	 */
	public function testAKeyTheEndpointDoesNotOwnChangesNothing(string $endpoint, array $values): void {
		$response = $this->controller()->$endpoint($values);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([], $this->written);
	}

	public static function provideKeysTheEndpointDoesNotOwn(): array {
		return [
			'the server\'s own key' => ['setSensitiveAdminConfig', ['enabled' => 'yes']],
			'next to the client' => ['setSensitiveAdminConfig', ['client_id' => 'my-client', 'enabled' => 'yes']],
			'the server\'s own key, unconfirmed' => ['setAdminConfig', ['enabled' => 'yes']],
			'the client, unconfirmed' => ['setAdminConfig', ['client_id' => 'my-client']],
		];
	}

	/**
	 * @dataProvider provideValuesThatAreNotStrings
	 */
	public function testAValueThatIsNotAStringChangesNothing(string $endpoint, array $values): void {
		$response = $this->controller()->$endpoint($values);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([], $this->written);
	}

	public static function provideValuesThatAreNotStrings(): array {
		return [
			'no secret' => ['setSensitiveAdminConfig', ['client_secret' => null]],
			'a number' => ['setAdminConfig', ['link_preview_enabled' => 0]],
			'a boolean' => ['setAdminConfig', ['link_preview_enabled' => false]],
		];
	}

	/**
	 * Answers the OAuth redirect with the app's lazy rows set to $lazyValues
	 * and expects the code to be exchanged with the given client.
	 *
	 * @param array<string, string> $lazyValues
	 */
	private function assertTheCodeIsExchangedWith(array $lazyValues, string $clientId, string $clientSecret): void {
		$this->appConfig->method('getAppValueString')->willReturnCallback(
			fn (string $key, string $default = '', bool $lazy = false): string => $lazy
				? ($lazyValues[$key] ?? $default)
				: $default,
		);
		$this->config->method('getUserValue')->willReturnCallback(
			fn ($userId, $appName, $key, $default = '') => match ($key) {
				'oauth_state' => 'the-state',
				'redirect_uri' => 'https://cloud.example.com/apps/integration_reddit/oauth-redirect',
				default => $default,
			}
		);
		$this->apiService->expects($this->once())
			->method('requestOAuthAccessToken')
			->with($clientId, $clientSecret, $this->anything(), 'POST')
			->willReturn(['error' => 'refused']);

		$this->controller()->oauthRedirect('the-code', 'the-state');
	}

	/**
	 * The client id and secret are lazy rows, which a read that does not ask
	 * for lazy values is handed the default for.
	 */
	public function testTheOAuthCodeIsExchangedWithTheStoredClient(): void {
		$this->assertTheCodeIsExchangedWith(
			['client_id' => 'my-client', 'client_secret' => 'encrypted:s3cret'],
			'my-client',
			's3cret',
		);
	}

	/**
	 * Most instances use the built-in Reddit application, and clearing the
	 * admin's own stores an empty id.
	 *
	 * @dataProvider provideInstancesWithoutAClientOfTheirOwn
	 */
	public function testTheOAuthCodeIsExchangedWithTheBuiltInClientWhenNoneIsStored(array $lazyValues): void {
		$this->assertTheCodeIsExchangedWith($lazyValues, Application::DEFAULT_REDDIT_CLIENT_ID, '');
	}

	public static function provideInstancesWithoutAClientOfTheirOwn(): array {
		return [
			'none ever stored' => [[]],
			'cleared by the admin' => [['client_id' => '', 'client_secret' => '']],
		];
	}
}
