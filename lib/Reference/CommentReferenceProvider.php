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
use OCP\IL10N;

use OCP\IURLGenerator;

class CommentReferenceProvider implements IReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_comment';

	public function __construct(private IConfig $config,
								private IL10N $l10n,
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
				$postId = $urlInfo['post_id'];
				$commentId = $urlInfo['comment_id'];
				$commentInfo = $this->redditAPIService->getCommentInfo($this->userId, $commentId);
				$postInfo = $this->redditAPIService->getPostInfo($this->userId, $postId);
				$reference = new Reference($referenceText);
				$title = $this->l10n->t('Comment from %1$s in %2$s', [$commentInfo['author'], 'r/' . $subreddit . '/' . $postInfo['title']]);
				$reference->setTitle($title);
				$description = $commentInfo['body'];
				$reference->setDescription($description);
				$thumbnailUrl = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.redditAPI.getAvatar', ['username' => $commentInfo['author']]);
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
		// https://www.reddit.com/r/rickandmorty/comments/11yul5n/comment/jd9tued/
		preg_match('/^(?:https?:\/\/)?(?:www\.)?reddit\.com\/r\/([^\/\?]+)\/comments\/([0-9a-z]+)\/comment\/([0-9a-z]+)\/?/i', $url, $matches);
		return count($matches) > 3
			? [
				'subreddit' => $matches[1],
				'post_id' => $matches[2],
				'comment_id' => $matches[3],
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
