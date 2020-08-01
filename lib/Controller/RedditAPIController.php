<?php
/**
 * Nextcloud - reddit
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Reddit\Controller;

use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\AppFramework\Http\DataDisplayResponse;

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\ILogger;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Reddit\Service\RedditAPIService;

class RedditAPIController extends Controller {


    private $userId;
    private $config;
    private $dbconnection;
    private $dbtype;

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IL10N $l10n,
                                IAppManager $appManager,
                                IAppData $appData,
                                ILogger $logger,
                                RedditAPIService $redditAPIService,
                                $userId) {
        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->appData = $appData;
        $this->serverContainer = $serverContainer;
        $this->config = $config;
        $this->logger = $logger;
        $this->redditAPIService = $redditAPIService;
        $this->accessToken = $this->config->getUserValue($this->userId, 'reddit', 'token', '');
    }

    /**
     * get notification list
     * @NoAdminRequired
     */
    public function getNotifications($since = null) {
        if ($this->accessToken === '') {
            return new DataResponse($result, 400);
        }
        $result = $this->redditAPIService->getNotifications($this->accessToken, $since);
        if (is_array($result)) {
            $response = new DataResponse($result);
        } else {
            $response = new DataResponse($result, 401);
        }
        return $response;
    }

    /**
     * get repository avatar
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getAvatar($username) {
        $response = new DataDisplayResponse($this->redditAPIService->getAvatar($username, $this->accessToken));
        $response->cacheFor(60*60*24);
        return $response;
    }

}
