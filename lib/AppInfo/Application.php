<?php
/**
 * Nextcloud - Reddit
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Reddit\AppInfo;

use OCA\Reddit\Reference\SubredditReferenceProvider;
use OCA\Reddit\Search\SubredditSearchProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Reddit\Dashboard\RedditWidget;

class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_reddit';
	public const DEFAULT_REDDIT_CLIENT_ID = 'Wvd050kRx2lDwg';
	public const REDDIT_BASE_WEB_URL = 'https://www.reddit.com';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(RedditWidget::class);
		$context->registerSearchProvider(SubredditSearchProvider::class);
		$context->registerReferenceProvider(SubredditReferenceProvider::class);
	}

	public function boot(IBootContext $context): void {
	}
}
