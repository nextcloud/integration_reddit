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
use OCP\ILogger;
use OCP\Http\Client\IClientService;

class RedditAPIService {

    private $l10n;
    private $logger;

    /**
     * Service to make requests to Reddit v3 (JSON) API
     */
    public function __construct (
        string $appName,
        ILogger $logger,
        IL10N $l10n,
        IClientService $clientService
    ) {
        $this->appName = $appName;
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->clientService = $clientService;
        $this->client = $clientService->newClient();
    }

    public function getAvatar($username, $accessToken) {
        $response = $this->request($accessToken, 'user/' . urlencode($username) . '/about');
        if (is_array($response) and isset($response['data']) and isset($response['data']['icon_img'])) {
            $url = $response['data']['icon_img'];
            return $this->client->get($url)->getBody();
        }
        return '';
    }

    public function getNotifications($accessToken, $since = null) {
        $params = [];
        //if (!is_null($since)) {
        //    $params['since'] = $since;
        //}

        $messages = [];
        $result = $this->request($accessToken, 'message/inbox', $params);
        if (is_array($result) and isset($result['data']) and isset($result['data']['children']) and is_array($result['data']['children'])) {
            foreach ($result['data']['children'] as $m) {
                if (is_array($m) and isset($m['data']) and isset($m['data']['author']) and isset($m['data']['subject'])) {
                    $theMessage = $m['data'];
                    $theMessage['notification_type'] = 'privatemessage';
                    array_push($messages, $theMessage);
                }
            }
        }
        return $messages;
    }

    public function request($accessToken, $endPoint, $params = [], $method = 'GET') {
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
                return $this->l10n->t('Bad credentials');
            } else {
                return json_decode($body, true);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Reddit API error : '.$e, array('app' => $this->appName));
            return $e;
        }
    }

}
