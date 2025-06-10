<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\AppInfo;

use OCA\Reddit\Dashboard\RedditWidget;
use OCA\Reddit\Reference\CommentReferenceProvider;
use OCA\Reddit\Reference\PublicationReferenceProvider;
use OCA\Reddit\Reference\SubredditReferenceProvider;
use OCA\Reddit\Search\PublicationSearchProvider;
use OCA\Reddit\Search\SubredditSearchProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_reddit';
	public const DEFAULT_REDDIT_CLIENT_ID = 'Wvd050kRx2lDwg';
	public const REDDIT_BASE_WEB_URL = 'https://www.reddit.com';
	public const PUBLICATION_SEARCH_PROVIDER_ID = 'reddit-publication-search';
	public const SUBREDDIT_SEARCH_PROVIDER_ID = 'reddit-subreddit-search';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(RedditWidget::class);

		$context->registerSearchProvider(PublicationSearchProvider::class);
		$context->registerSearchProvider(SubredditSearchProvider::class);

		$context->registerReferenceProvider(PublicationReferenceProvider::class);
		$context->registerReferenceProvider(SubredditReferenceProvider::class);
		$context->registerReferenceProvider(CommentReferenceProvider::class);
	}

	public function boot(IBootContext $context): void {
	}
}
