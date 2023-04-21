<?php
/**
 * Nextcloud - reddit
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Reddit\Controller;

use DateTime;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Reddit\Service\RedditAPIService;
use OCA\Reddit\AppInfo\Application;
use OCP\PreConditionNotMetException;

class ConfigController extends Controller {

	public function __construct(string $appName,
								IRequest $request,
								private IConfig $config,
								private IURLGenerator $urlGenerator,
								private IL10N $l,
								private RedditAPIService $redditAPIService,
								private ?string $userId) {
		parent::__construct($appName, $request);
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
	 * @throws PreConditionNotMetException
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
		return new DataResponse(1);
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
		return new DataResponse(1);
	}

	/**
	 * receive oauth payload with protocol handler redirect
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $url
	 * @return RedirectResponse
	 * @throws PreConditionNotMetException
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
		return $this->oauthRedirect(
			$params['code'] ?? '',
			$params['state'] ?? '',
			$params['error'] ?? 'No error returned in OAuth response'
		);
	}

	/**
	 * receive oauth code and get oauth access token
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string|null $code
	 * @param string|null $state
	 * @param string|null $error
	 * @return RedirectResponse
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function oauthRedirect(?string $code = '', ?string $state = '', ?string $error = ''): RedirectResponse {
		if ($code === '' || $state === '') {
			$message = $this->l->t('Error during OAuth exchanges');
			$message .= ': ' . $error ?? '';
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
				'?redditToken=error&message=' . urlencode($message)
			);
		}
		$configState = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_state');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', Application::DEFAULT_REDDIT_CLIENT_ID) ?: Application::DEFAULT_REDDIT_CLIENT_ID;
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');

		// anyway, reset state
		$this->config->setUserValue($this->userId, Application::APP_ID, 'oauth_state', '');

		if ($clientID && $configState !== '' && $configState === $state) {
			// if there is a client secret, then the app should be a 'classic' one redirecting to a web page
			if ($clientSecret) {
				$redirect_uri = $this->config->getUserValue($this->userId, Application::APP_ID, 'redirect_uri');
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
				if (isset($result['expires_in'])) {
					$nowTs = (new Datetime())->getTimestamp();
					$expiresAt = $nowTs + (int) $result['expires_in'];
					$this->config->setUserValue($this->userId, Application::APP_ID, 'token_expires_at', $expiresAt);
				}
				// get user information
				$info = $this->redditAPIService->request($this->userId, 'api/v1/me');
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
