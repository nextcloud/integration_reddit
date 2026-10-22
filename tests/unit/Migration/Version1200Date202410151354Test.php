<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Tests;

use OCA\Reddit\AppInfo\Application;
use OCA\Reddit\Migration\Version1200Date202410151354;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

class Version1200Date202410151354Test extends TestCase {

	private function connectionWithoutUserTokens(): IDBConnection {
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturn(false);
		$queryBuilder = $this->createMock(IQueryBuilder::class);
		foreach (['select', 'from', 'update', 'set', 'where', 'andWhere', 'setParameter'] as $method) {
			$queryBuilder->method($method)->willReturnSelf();
		}
		$queryBuilder->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
		$queryBuilder->method('executeQuery')->willReturn($result);
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('getQueryBuilder')->willReturn($queryBuilder);
		return $connection;
	}

	/**
	 * The secret is a lazy row, which a read that does not ask for lazy values
	 * is handed the default for, and which a write that does not ask for one
	 * turns back into a row loaded on every request.
	 */
	public function testTheClientSecretIsEncryptedAndStaysLazy(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = '', bool $lazy = false): string => $lazy && $key === 'client_secret'
				? 's3cret'
				: $default,
		);
		$written = [];
		$appConfig->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value, bool $lazy = false, bool $sensitive = false) use (&$written): bool {
				$written[] = [$app, $key, $value, $lazy];
				return true;
			}
		);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(fn (string $value): string => 'encrypted:' . $value);

		(new Version1200Date202410151354($this->connectionWithoutUserTokens(), $crypto, $appConfig))
			->postSchemaChange($this->createMock(IOutput::class), fn () => null, []);

		$this->assertSame([[Application::APP_ID, 'client_secret', 'encrypted:s3cret', true]], $written);
	}
}
