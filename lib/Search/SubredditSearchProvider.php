<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023, Julien Veyssier
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Reddit\Search;

use DateTime;
use OCA\Reddit\Service\RedditAPIService;
use OCA\Reddit\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class SubredditSearchProvider implements IProvider {
	private IAppManager $appManager;
	private IL10N $l10n;
	private IConfig $config;
	private IURLGenerator $urlGenerator;
	private IDateTimeFormatter $dateTimeFormatter;
	private RedditAPIService $service;

	public function __construct(IAppManager $appManager,
								IL10N $l10n,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IDateTimeFormatter $dateTimeFormatter,
								RedditAPIService $service) {
		$this->appManager = $appManager;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->dateTimeFormatter = $dateTimeFormatter;
		$this->service = $service;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'reddit-subreddit-search';
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
		return -1;
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
		return $entry['data']['title'] ?? '??';
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getSubline(array $entry): string {
		return $entry['data']['subreddit_name_prefixed'] ?? '??';
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getLink(array $entry): string {
		return Application::REDDIT_BASE_WEB_URL . $entry['data']['permalink'];
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getThumbnailUrl(array $entry): string {
		return isset($entry['data']['thumbnail'])
			? (($entry['data']['thumbnail'] === 'self' || $entry['data']['thumbnail'] === 'spoiler')
				? $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.redditAPI.getAvatar', ['subreddit' => $entry['data']['subreddit']])
				: $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.redditAPI.getThumbnail', ['url' => $entry['data']['thumbnail']]))
			: '';
	}
}
