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

use OCA\Reddit\AppInfo\Application;
use OCA\Reddit\Service\RedditAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\PreConditionNotMetException;

class RedditAPIController extends Controller {

	private string $accessToken;

	public function __construct(string                   $appName,
		IRequest                 $request,
		private IConfig          $config,
		private IURLGenerator    $urlGenerator,
		private RedditAPIService $redditAPIService,
		private ?string          $userId) {
		parent::__construct($appName, $request);
		$this->accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
	}

	/**
	 * get notification list
	 * @NoAdminRequired
	 *
	 * @param string|null $after
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	public function getNotifications(?string $after = null): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse(null, 400);
		}
		$result = $this->redditAPIService->getNotifications($this->userId, $after);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}

	/**
	 * get user avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param ?string $username
	 * @param string|null $subreddit
	 * @return DataDisplayResponse|RedirectResponse
	 * @throws PreConditionNotMetException
	 */
	public function getAvatar(?string $username = null, string $subreddit = null) {
		$avatarContent = $this->redditAPIService->getAvatar($this->userId, $username, $subreddit);
		if ($avatarContent !== '') {
			$response = new DataDisplayResponse($avatarContent);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} else {
			$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $username ?? $subreddit, 'size' => 44]);
			return new RedirectResponse($fallbackAvatarUrl);
		}
	}

	/**
	 * get subreddit thumbnail
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string|null $url
	 * @param string $subreddit
	 * @return DataDisplayResponse|RedirectResponse
	 */
	public function getThumbnail(string $url = null, string $subreddit = '??'): DataDisplayResponse|RedirectResponse {
		$thumbnailResponse = $this->redditAPIService->getThumbnail($url);
		if (isset($thumbnailResponse['body'], $thumbnailResponse['headers'])) {
			$response = new DataDisplayResponse(
				$thumbnailResponse['body'],
				Http::STATUS_OK,
				['Content-Type' => $thumbnailResponse['headers']['Content-Type'][0] ?? 'image/jpeg']
			);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} else {
			$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $subreddit, 'size' => 44]);
			return new RedirectResponse($fallbackAvatarUrl);
		}
	}
}
