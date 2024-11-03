<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Service;

use DateTime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\Reddit\AppInfo\Application;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\PreConditionNotMetException;

use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service to make requests to Reddit API
 */
class RedditAPIService {

	private IClient $client;

	public function __construct(
		IClientService          $clientService,
		private LoggerInterface $logger,
		private IL10N           $l10n,
		private ICrypto			$crypto,
		private IConfig         $config,
	) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $userId
	 * @param ?string $username
	 * @param ?string $subreddit
	 * @return ?string
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function getAvatar(string $userId, ?string $username, ?string $subreddit): ?string {
		$url = null;
		if (!is_null($username)) {
			$response = $this->request($userId, 'user/' . urlencode($username) . '/about');
			if (is_array($response) && isset($response['data'], $response['data']['icon_img']) && $response['data']['icon_img'] !== '') {
				$url = str_replace('&amp;', '&', $response['data']['icon_img']);
			}
		} else {
			$response = $this->request($userId, 'r/' . urlencode($subreddit) . '/about');
			if (isset($response['data'])) {
				if (isset($response['data']['community_icon']) && $response['data']['community_icon'] !== '') {
					$url = parse_url($response['data']['community_icon']);
					$url = $url['scheme'] . '://' . $url['host'] . $url['path'];
				} elseif (isset($response['data']['icon_img']) && $response['data']['icon_img'] !== '') {
					$url = $response['data']['icon_img'];
				}
			}
		}
		if ($url !== null) {
			try {
				return $this->client->get($url)->getBody();
			} catch (Exception | Throwable $e) {
				$this->logger->warning('Reddit avatar request error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			}
		}
		return '';
	}

	/**
	 * @param string $userId
	 * @param ?string $after
	 * @return array
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function getNotifications(string $userId, ?string $after = null): array {
		$params = [];
		if (!is_null($after)) {
			$params['after'] = $after;
		}

		// get new stuff
		$result = $this->request($userId, 'new', $params);
		if (isset($result['data'], $result['data']['children']) && is_array($result['data']['children'])) {
			$posts = [];
			foreach ($result['data']['children'] as $m) {
				if (is_array($m) && isset($m['data'], $m['data']['subreddit'], $m['data']['title'])) {
					$post = $m['data'];
					$post['notification_type'] = 'post';
					$posts[] = $post;
				}
			}
		} else {
			return $result;
		}

		return $posts;

		//// private messages
		//$result = $this->request($accessToken, $refreshToken, $clientID, $clientSecret, 'message/inbox', $params);
		//if (isset($result['data'], $result['data']['children']) && is_array($result['data']['children'])) {
		//    $messages = [];
		//    foreach ($result['data']['children'] as $m) {
		//        if (is_array($m) && isset($m['data'], $m['data']['author'], $m['data']['subject'])) {
		//            $theMessage = $m['data'];
		//            $theMessage['notification_type'] = 'privatemessage';
		//            $messages[] = $theMessage;
		//        }
		//    }
		//    return $messages;
		//} else {
		//    return $result;
		//}
	}

	/**
	 * @param string $userId
	 * @param string $query
	 * @param string|null $after
	 * @param int $limit
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function searchPublications(string $userId, string $query, ?string $after = null, int $limit = 5): array {
		$params = [
			'q' => $query,
			'sort' => 'relevance',
			'limit' => $limit,
		];
		if ($after !== null && $after !== '') {
			$params['after'] = $after;
		}
		return $this->request($userId, 'search', $params);
	}

	/**
	 * @param string $userId
	 * @param string $query
	 * @param string|null $after
	 * @param int $limit
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function searchSubreddits(string $userId, string $query, ?string $after = null, int $limit = 5): array {
		$params = [
			'q' => $query,
			'sort' => 'relevance',
			'limit' => $limit,
		];
		if ($after !== null && $after !== '') {
			$params['after'] = $after;
		}
		return $this->request($userId, 'subreddits/search', $params);
	}

	/**
	 * Request a thumbnail image
	 * @param string $url
	 * @return array|null Avatar image data
	 */
	public function getThumbnail(string $url): ?array {
		try {
			$domain = parse_url($url, PHP_URL_HOST);
			if ((preg_match('/^[a-z]\.thumbs\.redditmedia\.com$/i', $domain) === 1) ||
				(preg_match('/i\.redd\..*/i', $domain) === 1)) {
				$thumbnailResponse = $this->client->get($url);
				return [
					'body' => $thumbnailResponse->getBody(),
					'headers' => $thumbnailResponse->getHeaders(),
				];
			}
		} catch (Exception | Throwable $e) {
			$this->logger->debug('Reddit thumbnail request error : '.$e->getMessage(), ['app' => Application::APP_ID]);
		}
		return null;
	}

	/**
	 * @param string $userId
	 * @param string $postId
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function getPostInfo(string $userId, string $postId): array {
		$params = [
			'id' => 't3_' . $postId,
		];
		$redditResponse = $this->request($userId, 'api/info', $params);
		if (isset($redditResponse['data'], $redditResponse['data']['children'])
			&& is_array($redditResponse['data']['children'])
			&& count($redditResponse['data']['children']) > 0
			&& isset($redditResponse['data']['children'][0]['data'])) {
			return $redditResponse['data']['children'][0]['data'];
		}
		return $redditResponse;
	}

	/**
	 * @param string $userId
	 * @param string $postId
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function getCommentInfo(string $userId, string $commentId): array {
		$params = [
			'id' => 't1_' . $commentId,
		];
		$redditResponse = $this->request($userId, 'api/info', $params);
		if (isset($redditResponse['data'], $redditResponse['data']['children'])
			&& is_array($redditResponse['data']['children'])
			&& count($redditResponse['data']['children']) > 0
			&& isset($redditResponse['data']['children'][0]['data'])) {
			return $redditResponse['data']['children'][0]['data'];
		}
		return $redditResponse;
	}

	/**
	 * @param string $userId
	 * @param string $subreddit
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function getSubredditInfo(string $userId, string $subreddit): array {
		$redditResponse = $this->request($userId, 'r/' . urlencode($subreddit) . '/about');
		if (isset($redditResponse['data']) && is_array($redditResponse['data'])) {
			return $redditResponse['data'];
		}
		return $redditResponse;
	}

	/**
	 * @param string $userId
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function request(string $userId, string $endPoint, array $params = [], string $method = 'GET'): array {
		$this->checkTokenExpiration($userId);
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		if ($accessToken !== '') {
			$accessToken = $this->crypto->decrypt($accessToken);
		}
		try {
			$url = 'https://oauth.reddit.com/' . $endPoint;
			$options = [
				'headers' => [
					'Authorization' => 'bearer ' . $accessToken,
					'User-Agent' => 'Nextcloud Reddit integration'
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = json_encode($params);
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			}
			$result = json_decode($body, true);
			$json_decode_code = json_last_error();
			if ($json_decode_code === JSON_ERROR_NONE) {
				return $result;
			}
			$this->logger->warning('Reddit API error: js_decode='.$json_decode_code.' , url='.$url, ['app' => Application::APP_ID]);
			return ['error' => $this->l10n->t('Failed to get Reddit news')];
		} catch (ServerException | ClientException $e) {
			$this->logger->warning('Reddit API error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $userId
	 * @return void
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function checkTokenExpiration(string $userId): void {
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$refreshToken = $refreshToken === '' ? '' : $this->crypto->decrypt($refreshToken);
		$expireAt = $this->config->getUserValue($userId, Application::APP_ID, 'token_expires_at');
		if ($refreshToken !== '' && $expireAt !== '') {
			$nowTs = (new Datetime())->getTimestamp();
			$expireAt = (int) $expireAt;
			// if token expires in less than a minute or is already expired
			if ($nowTs > $expireAt - 60) {
				$this->refreshToken($userId);
			}
		}
	}

	/**
	 * @param string $userId
	 * @return bool
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function refreshToken(string $userId): bool {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', Application::DEFAULT_REDDIT_CLIENT_ID) ?: Application::DEFAULT_REDDIT_CLIENT_ID;
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$clientSecret = $clientSecret === '' ? '' : $this->crypto->decrypt($clientSecret);
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$refreshToken = $refreshToken === '' ? '' : $this->crypto->decrypt($refreshToken);
		if (!$refreshToken) {
			$this->logger->error('No Reddit refresh token found', ['app' => Application::APP_ID]);
			return false;
		}
		$result = $this->requestOAuthAccessToken($clientID, $clientSecret, [
			'grant_type' => 'refresh_token',
			'refresh_token' => $refreshToken,
		], 'POST');
		if (isset($result['access_token'])) {
			$this->logger->info('Reddit access token successfully refreshed', ['app' => Application::APP_ID]);
			$accessToken = $result['access_token'];
			$encryptedToken = $accessToken === '' ? '' : $this->crypto->encrypt($accessToken);
			$this->config->setUserValue($userId, Application::APP_ID, 'token', $encryptedToken);
			if (isset($result['expires_in'])) {
				$nowTs = (new Datetime())->getTimestamp();
				$expiresAt = $nowTs + (int) $result['expires_in'];
				$this->config->setUserValue($userId, Application::APP_ID, 'token_expires_at', $expiresAt);
			}
			return true;
		} else {
			// impossible to refresh the token
			$this->logger->error(
				'Token is not valid anymore. Impossible to refresh it. '
					. $result['error'] . ' '
					. $result['error_description'] ?? '[no error description]',
				['app' => Application::APP_ID]
			);
			return false;
		}
	}

	/**
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function requestOAuthAccessToken(string $clientID, string $clientSecret, array $params = [], string $method = 'GET'): array {
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
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('OAuth access token refused')];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException | ClientException  $e) {
			$this->logger->warning('Reddit OAuth error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
