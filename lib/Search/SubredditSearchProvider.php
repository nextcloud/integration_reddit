<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Search;

use OCA\Reddit\AppInfo\Application;
use OCA\Reddit\Service\RedditAPIService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class SubredditSearchProvider implements IProvider {

	public function __construct(private IAppManager $appManager,
		private IL10N $l10n,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private RedditAPIService $service) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return Application::SUBREDDIT_SEARCH_PROVIDER_ID;
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Subreddits');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer Zammad results
			return -1;
		}

		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = $query->getLimit();
		$term = $query->getTerm();
		$after = $query->getCursor();

		$accessToken = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'token');
		$searchEnabled = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'search_enabled', '1') === '1';
		if ($accessToken === '' || !$searchEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$searchResults = $this->service->searchSubreddits($user->getUID(), $term, $after, $limit);

		if (isset($searchResults['error'])) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$newAfter = $searchResults['data']['after'] ?? null;

		$formattedResults = array_map(function (array $entry): SearchResultEntry {
			return new SearchResultEntry(
				$this->getThumbnailUrl($entry),
				$this->getMainText($entry),
				$this->getSubline($entry),
				$this->getLink($entry),
				'',
				true
			);
		}, $searchResults['data']['children']);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$newAfter
		);
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getMainText(array $entry): string {
		if (isset($entry['data']['title'], $entry['data']['display_name_prefixed'])) {
			return '/' . $entry['data']['display_name_prefixed'] . ' [' . $entry['data']['title'] . ']';
		}
		return '??';
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getSubline(array $entry): string {
		return $entry['data']['public_description'] ?? '??';
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getLink(array $entry): string {
		return Application::REDDIT_BASE_WEB_URL . '/' . $entry['data']['display_name_prefixed'];
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getThumbnailUrl(array $entry): string {
		return $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.redditAPI.getAvatar', ['subreddit' => $entry['data']['display_name']]);
	}
}
