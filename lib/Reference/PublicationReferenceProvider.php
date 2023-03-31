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

use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Reddit\AppInfo\Application;
use OCA\Reddit\Service\RedditAPIService;
use OCP\Collaboration\Reference\IReference;
use OCP\IConfig;
use OCP\IL10N;

use OCP\IURLGenerator;

class PublicationReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_publication';

	private ?string $userId;
	private IConfig $config;
	private ReferenceManager $referenceManager;
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;
	private RedditAPIService $redditAPIService;

	public function __construct(IConfig $config,
								IL10N $l10n,
								IURLGenerator $urlGenerator,
								ReferenceManager $referenceManager,
								RedditAPIService $redditAPIService,
								?string $userId) {
		$this->userId = $userId;
		$this->config = $config;
		$this->referenceManager = $referenceManager;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->redditAPIService = $redditAPIService;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string	{
		return 'reddit-publication';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Reddit publications and subreddits');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int	{
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedSearchProviderIds(): array {
		if ($this->userId !== null) {
			$ids = [];
			$searchSubredditsEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_subreddits_enabled', '1') === '1';
			if ($searchSubredditsEnabled) {
				$ids[] = Application::SUBREDDIT_SEARCH_PROVIDER_ID;
			}
			$searchPublicationsEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_publications_enabled', '1') === '1';
			if ($searchPublicationsEnabled) {
				$ids[] = Application::PUBLICATION_SEARCH_PROVIDER_ID;
			}
			return $ids;
		}
		return [
			Application::PUBLICATION_SEARCH_PROVIDER_ID,
			Application::SUBREDDIT_SEARCH_PROVIDER_ID,
		];
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
				$postId = $urlInfo['post_id'];
				$subreddit = $urlInfo['subreddit'];
				$postInfo = $this->redditAPIService->getPostInfo($this->userId, $postId);
				$reference = new Reference($referenceText);
				$reference->setTitle($postInfo['title']);
				// TRANSLATORS By @$author in $subreddit_name_prefixed
				$description = $this->l10n->t('By @%1$s in %2$s', [$postInfo['author'], $postInfo['subreddit_name_prefixed']]);
				$reference->setDescription($description);
				$thumbnailUrl = ($postInfo['thumbnail'] === 'self' || $postInfo['thumbnail'] === 'spoiler')
					? $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.redditAPI.getAvatar', ['subreddit' => $subreddit])
					: $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.redditAPI.getThumbnail', ['url' => $postInfo['thumbnail']]);
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
		// https://www.reddit.com/r/television/comments/11yw1jj/rick_and_morty_blabla/
		preg_match('/^(?:https?:\/\/)?(?:www\.)?reddit\.com\/r\/([^\/\?]+)\/comments\/([0-9a-z]+)\/[^\/\?]+\/$/i', $url, $matches);
		return count($matches) > 2
			? [
				'subreddit' => $matches[1],
				'post_id' => $matches[2],
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
