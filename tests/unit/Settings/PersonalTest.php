<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Tests;

use OCA\Reddit\AppInfo\Application;
use OCA\Reddit\Settings\Personal;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class PersonalTest extends TestCase {

	/**
	 * @param array<string, string> $lazyValues the app's lazy rows
	 * @return array<string, mixed> the initial state the form was given
	 */
	private function form(array $lazyValues): array {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueString')->willReturnCallback(
			fn (string $key, string $default = '', bool $lazy = false): string => $lazy
				? ($lazyValues[$key] ?? $default)
				: $default,
		);
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn ($userId, $appName, $key, $default = '') => $key === 'user_name' ? 'reddit-alice' : $default,
		);
		// what the server hands a read of a lazy row through IConfig
		$config->method('getAppValue')->willReturnCallback(fn ($app, $key, $default = '') => $default);
		$state = [];
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects($this->once())
			->method('provideInitialState')
			->willReturnCallback(function (string $key, mixed $data) use (&$state): void {
				$state[$key] = $data;
			});

		(new Personal($appConfig, $config, $initialState, 'alice'))->getForm();

		return $state;
	}

	/**
	 * The client id and secret are lazy rows, which a read that does not ask
	 * for lazy values is handed the default for: the form would offer the
	 * built-in client and no secret.
	 */
	public function testTheFormShowsTheStoredClient(): void {
		$this->assertSame(
			['user-config' => [
				'client_id' => 'my-client',
				'client_secret' => 'dummySecret',
				'user_name' => 'reddit-alice',
			]],
			$this->form(['client_id' => 'my-client', 'client_secret' => 'encrypted-secret']),
		);
	}

	/**
	 * Most instances use the built-in Reddit application, and clearing the
	 * admin's own stores an empty id.
	 *
	 * @dataProvider provideInstancesWithoutAClientOfTheirOwn
	 */
	public function testTheFormOffersTheBuiltInClientWhenNoneIsStored(array $lazyValues): void {
		$form = $this->form($lazyValues)['user-config'];

		$this->assertSame(Application::DEFAULT_REDDIT_CLIENT_ID, $form['client_id']);
		$this->assertSame('', $form['client_secret']);
	}

	public static function provideInstancesWithoutAClientOfTheirOwn(): array {
		return [
			'none ever stored' => [[]],
			'cleared by the admin' => [['client_id' => '', 'client_secret' => '']],
		];
	}
}
