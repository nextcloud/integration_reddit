<?php
/**
 * Nextcloud - Reddit
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Reddit\AppInfo;

use OCP\IContainer;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Reddit\Dashboard\RedditWidget;

/**
 * Class Application
 *
 * @package OCA\Reddit\AppInfo
 */
class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_reddit';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$this->container = $this->getContainer();
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(RedditWidget::class);
	}

	public function boot(IBootContext $context): void {
	}
}

