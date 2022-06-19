<?php
/**
 * Nextcloud - reddit
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Reddit\Service;

use DateTime;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\IConfig;
use OCP\Http\Client\IClientService;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

use OCA\Reddit\AppInfo\Application;

class RedditAPIService {
	/**
	 * @var string
	 */
	private $appName;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var string
	 */
	private $userId;
	/**
	 * @var \OCP\Http\Client\IClient
	 */
	private $client;

	/**
	 * Service to make requests to Reddit API
	 */
	public function __construct (string $appName,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config,
								IClientService $clientService,
								string $userId) {
		$this->appName = $appName;
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->userId = $userId;
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
				$url = $response['data']['icon_img'];
			}
		} else {
			$response = $this->request($userId, 'r/' . urlencode($subreddit) . '/about');
			if (isset($response['data'])) {
				if (isset($response['data']['community_icon']) && $response['data']['community_icon'] !== '') {
					$url = parse_url($response['data']['community_icon']);
					$url = $url['scheme'] . '://' . $url['host'] . $url['path'];
				}
				else if (isset($response['data']['icon_img']) && $response['data']['icon_img'] !== '') {
					$url = $response['data']['icon_img'];
				}
			}
		}
		if ($url !== null) {
			return $this->client->get($url)->getBody();
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
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function request(string $userId, string $endPoint, array $params = [], string $method = 'GET'): array {
		$this->checkTokenExpiration($userId);
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
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
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException | ClientException $e) {
			$this->logger->warning('Reddit API error : '.$e->getMessage(), ['app' => $this->appName]);
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
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		if (!$refreshToken) {
			$this->logger->error('No Reddit refresh token found', ['app' => $this->appName]);
			return false;
		}
		$result = $this->requestOAuthAccessToken($clientID, $clientSecret, [
			'grant_type' => 'refresh_token',
			'refresh_token' => $refreshToken,
		], 'POST');
		if (isset($result['access_token'])) {
			$this->logger->info('Reddit access token successfully refreshed', ['app' => $this->appName]);
			$accessToken = $result['access_token'];
			$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
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
				['app' => $this->appName]
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
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
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
			$this->logger->warning('Reddit OAuth error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}
}
