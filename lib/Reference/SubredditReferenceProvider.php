<?php
/**
 * @copyright Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Reddit\Reference;

use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Reddit\AppInfo\Application;
use OCA\Reddit\Service\RedditAPIService;
use OCP\Collaboration\Reference\IReference;
use OCP\IConfig;

use OCP\IURLGenerator;

class SubredditReferenceProvider implements IReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_subreddit';

	public function __construct(private IConfig $config,
								private IURLGenerator $urlGenerator,
								private ReferenceManager $referenceManager,
								private RedditAPIService $redditAPIService,
								private ?string $userId) {
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
