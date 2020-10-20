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

use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\IConfig;
use OCP\Http\Client\IClientService;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

use OCA\Reddit\AppInfo\Application;

class RedditAPIService {

	private $l10n;
	private $logger;

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
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
		$this->userId = $userId;
		$this->clientService = $clientService;
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $accessToken
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param ?string $username
	 * @param ?string $subreddit
	 * @return ?string
	 */
	public function getAvatar(string $accessToken, string $refreshToken, string $clientID, string $clientSecret,
								?string $username, ?string $subreddit): ?string {
		$url = null;
		if (!is_null($username)) {
			$response = $this->request($accessToken, $refreshToken, $clientID, $clientSecret, 'user/' . urlencode($username) . '/about');
			if (is_array($response) && isset($response['data'], $response['data']['icon_img']) && $response['data']['icon_img'] !== '') {
				$url = $response['data']['icon_img'];
			}
		} else {
			$response = $this->request($accessToken, $refreshToken, $clientID, $clientSecret, 'r/' . urlencode($subreddit) . '/about');
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
	 * @param string $accessToken
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param ?string $after
	 * @return array
	 */
	public function getNotifications(string $accessToken, string $refreshToken, string $clientID, string $clientSecret,
									?string $after = null): array {
		$params = [];
		if (!is_null($after)) {
			$params['after'] = $after;
		}

		// get new stuff
		$posts = [];
		$result = $this->request($accessToken, $refreshToken, $clientID, $clientSecret, 'new', $params);
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
	 * @param string $accessToken
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function request(string $accessToken, string $refreshToken, string $clientID, string $clientSecret,
							string $endPoint, array $params = [], string $method = 'GET'): array {
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
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException | ClientException $e) {
			$response = $e->getResponse();
			if ($response->getStatusCode() === 401) {
				$this->logger->info('Trying to REFRESH the access token', ['app' => $this->appName]);
				// try to refresh the token
				$result = $this->requestOAuthAccessToken($clientID, $clientSecret, [
					'grant_type' => 'refresh_token',
					'refresh_token' => $refreshToken,
				], 'POST');
				if (isset($result['access_token'])) {
					$this->logger->info('Reddit access token successfully refreshed', ['app' => $this->appName]);
					$accessToken = $result['access_token'];
					$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
					// retry the request with new access token
					return $this->request($accessToken, $refreshToken, $clientID, $clientSecret, $endPoint, $params, $method);
				} else {
					// impossible to refresh the token
					return ['error' => $this->l10n->t('Token is not valid anymore. Impossible to refresh it.') . ' ' . $result['error']];
				}
			}
			$this->logger->warning('Reddit API error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
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
