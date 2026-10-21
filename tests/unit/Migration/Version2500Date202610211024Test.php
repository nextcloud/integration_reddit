<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Tests;

use OCA\Reddit\AppInfo\Application;
use OCA\Reddit\Migration\Version2500Date202610211024;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class Version2500Date202610211024Test extends TestCase {

	/**
	 * Rows written before the change are still loaded on every request until
	 * they are marked lazy.
	 */
	public function testEveryKeyOfTheAppIsMarkedLazy(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$marked = [];
		$appConfig->method('updateLazy')->willReturnCallback(
			function (string $app, string $key, bool $lazy) use (&$marked): bool {
				$marked[] = [$app, $key, $lazy];
				return true;
			}
		);

		(new Version2500Date202610211024($appConfig))
			->postSchemaChange($this->createMock(IOutput::class), fn () => null, []);

		$this->assertSame([
			[Application::APP_ID, 'client_id', true],
			[Application::APP_ID, 'client_secret', true],
			[Application::APP_ID, 'link_preview_enabled', true],
		], $marked);
	}
}
