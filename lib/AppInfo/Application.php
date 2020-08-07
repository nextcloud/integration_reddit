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

use OCA\Reddit\Controller\PageController;
use OCA\Reddit\Dashboard\RedditWidget;

/**
 * Class Application
 *
 * @package OCA\Reddit\AppInfo
 */
class Application extends App implements IBootstrap {

    /**
     * Constructor
     *
     * @param array $urlParams
     */
    public function __construct(array $urlParams = []) {
        parent::__construct('reddit', $urlParams);

        $this->container = $this->getContainer();
    }

    public function register(IRegistrationContext $context): void {
        // enable dashboard widget only if client ID and secret were defined by an admin
        $config = $this->container->query(\OCP\IConfig::class);
        $clientId = $config->getAppValue('reddit', 'client_id', '');
        $clientSecret = $config->getAppValue('reddit', 'client_secret', '');
        if ($clientId !== '' and $clientSecret !== '') {
            $context->registerDashboardWidget(RedditWidget::class);
        }
    }

    public function boot(IBootContext $context): void {
    }
}

