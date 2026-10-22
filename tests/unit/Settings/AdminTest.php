<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Tests;

use OCA\Reddit\Settings\Admin;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use PHPUnit\Framework\TestCase;

class AdminTest extends TestCase {

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
		$state = [];
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects($this->once())
			->method('provideInitialState')
			->willReturnCallback(function (string $key, mixed $data) use (&$state): void {
				$state[$key] = $data;
			});

		(new Admin($appConfig, $initialState))->getForm();

		return $state;
	}

	/**
	 * The client id and secret are lazy rows, which a read that does not ask
	 * for lazy values is handed the default for: the form would show no client
	 * at all.
	 */
	public function testTheFormShowsTheStoredClient(): void {
		$this->assertSame(
			['admin-config' => ['client_id' => 'my-client', 'client_secret' => 'dummySecret']],
			$this->form(['client_id' => 'my-client', 'client_secret' => 'encrypted-secret']),
		);
	}

	/**
	 * The admin's field holds the admin's own application id: an empty one
	 * is what stands for the built-in application, so that is not shown.
	 *
	 * @dataProvider provideInstancesWithoutAClientOfTheirOwn
	 */
	public function testTheFormLeavesTheFieldsEmptyWhenNoClientIsStored(array $lazyValues): void {
		$this->assertSame(
			['admin-config' => ['client_id' => '', 'client_secret' => '']],
			$this->form($lazyValues),
		);
	}

	public static function provideInstancesWithoutAClientOfTheirOwn(): array {
		return [
			'none ever stored' => [[]],
			'cleared by the admin' => [['client_id' => '', 'client_secret' => '']],
		];
	}
}
