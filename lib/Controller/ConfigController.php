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

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;
use OCP\ILogger;

use OCP\IRequest;
use OCP\IDBConnection;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;
use OCP\Http\Client\IClientService;

class ConfigController extends Controller {


    private $userId;
    private $config;
    private $dbconnection;
    private $dbtype;

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IAppManager $appManager,
                                IAppData $appData,
                                IDBConnection $dbconnection,
                                IURLGenerator $urlGenerator,
                                IL10N $l,
                                ILogger $logger,
                                IClientService $clientService,
                                $userId) {
        parent::__construct($AppName, $request);
        $this->l = $l;
        $this->appName = $AppName;
        $this->userId = $userId;
        $this->appData = $appData;
        $this->serverContainer = $serverContainer;
        $this->config = $config;
        $this->dbconnection = $dbconnection;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->clientService = $clientService;
    }

    /**
     * set config values
     * @NoAdminRequired
     */
    public function setConfig($values) {
        foreach ($values as $key => $value) {
            $this->config->setUserValue($this->userId, 'reddit', $key, $value);
        }
        $response = new DataResponse(1);
        return $response;
    }

    /**
     * set admin config values
     */
    public function setAdminConfig($values) {
        foreach ($values as $key => $value) {
            $this->config->setAppValue('reddit', $key, $value);
        }
        $response = new DataResponse(1);
        return $response;
    }

    /**
     * receive oauth code and get oauth access token
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function oauthRedirect($code, $state, $error) {
        $configState = $this->config->getUserValue($this->userId, 'reddit', 'oauth_state', '');
        $clientID = $this->config->getAppValue('reddit', 'client_id', '');
        $clientSecret = $this->config->getAppValue('reddit', 'client_secret', '');

        // anyway, reset state
        $this->config->setUserValue($this->userId, 'reddit', 'oauth_state', '');

        if ($clientID and $clientSecret and $configState !== '' and $configState === $state) {
            $redirect_uri = $this->urlGenerator->linkToRouteAbsolute('reddit.config.oauthRedirect');
            $result = $this->requestOAuthAccessToken($clientID, $clientSecret, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri,
            ], 'POST');
            if (is_array($result) and isset($result['access_token'])) {
                $accessToken = $result['access_token'];
                $this->config->setUserValue($this->userId, 'reddit', 'token', $accessToken);
                return new RedirectResponse(
                    $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
                    '?redditToken=success&scope='.$result['scope'].'&type='.$result['token_type'].'&expires_in='.$result['expires_in']
                );
            }
            $result = $this->l->t('Error getting OAuth access token');
        } else {
            $result = $this->l->t('Error during OAuth exchanges');
        }
        return new RedirectResponse(
            $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
            '?redditToken=error&message=' . urlencode($result)
        );
    }

    private function requestOAuthAccessToken($clientID, $clientSecret, $params = [], $method = 'GET') {
        $client = $this->clientService->newClient();
        try {
            $url = 'https://www.reddit.com/api/v1/access_token';
            $options = [
                'headers' => [
                    'Authorization' => 'Basic '. base64_encode($clientID. ':' . $clientSecret),
                    'User-Agent' => 'Nextcloud Reddit integration'
                ],
            ];

            if (count($params) > 0) {
                if ($method === 'GET') {
                    $paramsContent = http_build_query($params);
                    $url .= '?' . $paramsContent;
                } else {
                    $options['body'] = $params;
                }
            }

            if ($method === 'GET') {
                $response = $client->get($url, $options);
            } else if ($method === 'POST') {
                $response = $client->post($url, $options);
            } else if ($method === 'PUT') {
                $response = $client->put($url, $options);
            } else if ($method === 'DELETE') {
                $response = $client->delete($url, $options);
            }
            $body = $response->getBody();
            $respCode = $response->getStatusCode();

            if ($respCode >= 400) {
                return $this->l->t('OAuth access token refused');
            } else {
                return json_decode($body, true);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Reddit OAuth error : '.$e, array('app' => $this->appName));
            return $e;
        }
    }
}
