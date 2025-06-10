<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Reference;

use OC\Collaboration\Reference\LinkReferenceProvider;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Reddit\AppInfo\Application;
use OCA\Reddit\Service\RedditAPIService;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IConfig;

use OCP\IURLGenerator;

class SubredditReferenceProvider implements IReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_subreddit';

	public function __construct(
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private ReferenceManager $referenceManager,
		private RedditAPIService $redditAPIService,
		private LinkReferenceProvider $linkReferenceProvider,
		private ?string $userId,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		if ($this->userId === null) {
			return false;
		}
		$linkPreviewEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'link_preview_enabled', '1') === '1';
		if (!$linkPreviewEnabled) {
			return false;
		}
		$adminLinkPreviewEnabled = $this->config->getAppValue(Application::APP_ID, 'link_preview_enabled', '1') === '1';
		if (!$adminLinkPreviewEnabled) {
			return false;
		}
		return $this->getUrlInfo($referenceText) !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$urlInfo = $this->getUrlInfo($referenceText);
			if ($urlInfo !== null) {
				$subreddit = $urlInfo['subreddit'];
				$subredditInfo = $this->redditAPIService->getSubredditInfo($this->userId, $subreddit);
				if (isset($subredditInfo['title'], $subredditInfo['display_name_prefixed'], $subredditInfo['public_description'])) {
					$reference = new Reference($referenceText);
					$title = '/' . $subredditInfo['display_name_prefixed'] . ' [' . $subredditInfo['title'] . ']';
					$reference->setTitle($title);
					$description = $subredditInfo['public_description'];
					$reference->setDescription($description);
					$thumbnailUrl = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.redditAPI.getAvatar', ['subreddit' => $subreddit]);
					$reference->setImageUrl($thumbnailUrl);
					/*
					$reference->setRichObject(
						self::RICH_OBJECT_TYPE,
						$postInfo
					);
					*/
					return $reference;
				}
				// fallback to opengraph
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}
		}

		return null;
	}

	/**
	 * @param string $url
	 * @return array|null
	 */
	private function getUrlInfo(string $url): ?array {
		// example url
		// https://www.reddit.com/r/television/
		preg_match('/^(?:https?:\/\/)?(?:www\.)?reddit\.com\/r\/([^\/\?]+)\/?$/i', $url, $matches);
		return count($matches) > 1
			? [
				'subreddit' => $matches[1],
			]
			: null;
	}

	/**
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	/**
	 * We don't use the userId here but rather a reference unique id
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
