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
use Psr\Log\LoggerInterface;

use OCP\IRequest;
use OCP\IDBConnection;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;
use OCP\Http\Client\IClientService;

use OCA\Reddit\Service\RedditAPIService;
use OCA\Reddit\AppInfo\Application;

require_once __DIR__ . '/../constants.php';

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
								LoggerInterface $logger,
								IClientService $clientService,
								RedditAPIService $redditAPIService,
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
		$this->redditAPIService = $redditAPIService;
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		if (isset($values['user_name']) && $values['user_name'] === '') {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'token', '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', '');
		}
		$response = new DataResponse(1);
		return $response;
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		$response = new DataResponse(1);
		return $response;
	}

	/**
	 * receive oauth payload with protocol handler redirect
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $url
	 * @return RedirectResponse
	 */
	public function oauthProtocolRedirect(string $url = ''): RedirectResponse {
		if ($url === '') {
			$message = $this->l->t('Error during OAuth exchanges');
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
				'?redditToken=error&message=' . urlencode($message)
			);
		}
		$parts = parse_url($url);
		parse_str($parts['query'], $params);
		return $this->oauthRedirect($params['code'], $params['state'], $params['error']);
	}

	/**
	 * receive oauth code and get oauth access token
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $code
	 * @param string $state
	 * @param string $error
	 * @return RedirectResponse
	 */
	public function oauthRedirect(?string $code = '', ?string $state = '', ?string $error = ''): RedirectResponse {
		if ($code === '' || $state === '') {
			$message = $this->l->t('Error during OAuth exchanges');
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
				'?redditToken=error&message=' . urlencode($message)
			);
		}
		$configState = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_state', '');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', DEFAULT_REDDIT_CLIENT_ID);
		$clientID = $clientID ? $clientID : DEFAULT_REDDIT_CLIENT_ID;
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret', '');

		// anyway, reset state
		$this->config->setUserValue($this->userId, Application::APP_ID, 'oauth_state', '');

		if ($clientID && $configState !== '' && $configState === $state) {
			// if there is a client secret, then the app should be a 'classic' one redirecting to a web page
			if ($clientSecret) {
				$redirect_uri = $this->urlGenerator->linkToRouteAbsolute('integration_reddit.config.oauthRedirect');
			} else {
				// otherwise it's redirecting to the protocol
				$redirect_uri = 'web+nextcloudreddit://oauth-protocol-redirect';
			}
			$result = $this->redditAPIService->requestOAuthAccessToken($clientID, $clientSecret, [
				'grant_type' => 'authorization_code',
				'code' => $code,
				'redirect_uri' => $redirect_uri,
			], 'POST');
			if (isset($result['access_token'], $result['refresh_token'])) {
				$accessToken = $result['access_token'];
				$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
				$refreshToken = $result['refresh_token'];
				$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', $refreshToken);
				// get user information
				$info = $this->redditAPIService->request($accessToken, $refreshToken, $clientID, $clientSecret, 'api/v1/me');
				if (isset($info['id'], $info['name'])) {
					$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', $info['id']);
					$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $info['name']);
				} else {
					$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', '??');
					$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', '??');
				}
				return new RedirectResponse(
					$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
					'?redditToken=success'
				);
			} else {
				$message = $this->l->t('Error getting OAuth access token') . ' ' . ($result['error'] ?? 'missing token or refresh token');
			}
		} else {
			$message = $this->l->t('Error during OAuth exchanges');
		}
		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
			'?redditToken=error&message=' . urlencode($message)
		);
	}
}
