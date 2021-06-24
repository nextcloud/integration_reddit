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

use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Reddit\Service\RedditAPIService;
use OCA\Reddit\AppInfo\Application;

class RedditAPIController extends Controller {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var RedditAPIService
	 */
	private $redditAPIService;
	/**
	 * @var string|null
	 */
	private $userId;
	/**
	 * @var string
	 */
	private $accessToken;
	/**
	 * @var string
	 */
	private $refreshToken;
	/**
	 * @var string
	 */
	private $clientID;
	/**
	 * @var string
	 */
	private $clientSecret;

	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								RedditAPIService $redditAPIService,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->redditAPIService = $redditAPIService;
		$this->userId = $userId;
		$this->accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$this->refreshToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'refresh_token');
		$this->clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', Application::DEFAULT_REDDIT_CLIENT_ID);
		$this->clientID = $this->clientID ?: Application::DEFAULT_REDDIT_CLIENT_ID;
		$this->clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
	}

	/**
	 * get notification list
	 * @NoAdminRequired
	 *
	 * @param ?string $after
	 * @return DataResponse
	 */
	public function getNotifications(?string $after = null): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse(null, 400);
		}
		$result = $this->redditAPIService->getNotifications(
			$this->accessToken, $this->refreshToken, $this->clientID, $this->clientSecret, $after
		);
		if (!isset($result['error'])) {
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
	 *
	 * @param ?string $username
	 * @param ?string $subreddit
	 * @return DataDisplayResponse
	 */
	public function getAvatar(?string $username = null, string $subreddit = null): DataDisplayResponse {
		$avatarContent = $this->redditAPIService->getAvatar(
			$this->accessToken, $this->clientID, $this->clientSecret, $this->refreshToken,
			$username, $subreddit
		);
		if ($avatarContent !== '') {
			$response = new DataDisplayResponse($avatarContent);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} else {
			return new DataDisplayResponse('', 404);
		}
	}
}
