<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Tests;

use OC\Collaboration\Reference\LinkReferenceProvider;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Reddit\Reference\CommentReferenceProvider;
use OCA\Reddit\Reference\PublicationReferenceProvider;
use OCA\Reddit\Reference\SubredditReferenceProvider;
use OCA\Reddit\Service\RedditAPIService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class ReferenceProviderTest extends TestCase {

	/**
	 * @param class-string<IReferenceProvider> $class
	 * @param array<string, string> $lazyValues
	 */
	private function provider(string $class, array $lazyValues): IReferenceProvider {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueString')->willReturnCallback(
			fn (string $key, string $default = '', bool $lazy = false): string => $lazy
				? ($lazyValues[$key] ?? $default)
				: $default,
		);
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn ($userId, $appName, $key, $default = '') => $default,
		);
		// what the server hands a read of a lazy row through IConfig
		$config->method('getAppValue')->willReturnCallback(fn ($app, $key, $default = '') => $default);
		$arguments = [
			'appConfig' => $appConfig,
			'config' => $config,
			'urlGenerator' => $this->createMock(IURLGenerator::class),
			'referenceManager' => $this->createMock(ReferenceManager::class),
			'redditAPIService' => $this->createMock(RedditAPIService::class),
			'linkReferenceProvider' => $this->createMock(LinkReferenceProvider::class),
			'userId' => 'alice',
		];
		if ($class !== SubredditReferenceProvider::class) {
			$arguments['l10n'] = $this->createMock(IL10N::class);
		}
		return new $class(...$arguments);
	}

	/**
	 * @dataProvider provideLinks
	 */
	public function testALinkIsPreviewedByDefault(string $class, string $url): void {
		$this->assertTrue($this->provider($class, [])->matchReference($url));
	}

	/**
	 * The admin switch is a lazy row, which a read that does not ask for lazy
	 * values is handed the default for: links would be previewed although the
	 * admin turned previews off.
	 *
	 * @dataProvider provideLinks
	 */
	public function testNoLinkIsPreviewedOnceTheAdminTurnedPreviewsOff(string $class, string $url): void {
		$this->assertFalse($this->provider($class, ['link_preview_enabled' => '0'])->matchReference($url));
	}

	public static function provideLinks(): array {
		return [
			'a comment' => [CommentReferenceProvider::class, 'https://www.reddit.com/r/rickandmorty/comments/11yul5n/comment/jd9tued/'],
			'a post' => [PublicationReferenceProvider::class, 'https://www.reddit.com/r/television/comments/11yw1jj/rick_and_morty/'],
			'a subreddit' => [SubredditReferenceProvider::class, 'https://www.reddit.com/r/television/'],
		];
	}
}
