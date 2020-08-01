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

return [
    'routes' => [
        ['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
        ['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
        ['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
        ['name' => 'redditAPI#getNotifications', 'url' => '/notifications', 'verb' => 'GET'],
        ['name' => 'redditAPI#unsubscribeNotification', 'url' => '/notifications/{id}/unsubscribe', 'verb' => 'PUT'],
        ['name' => 'redditAPI#markNotificationAsRead', 'url' => '/notifications/{id}/mark-read', 'verb' => 'PUT'],
        ['name' => 'redditAPI#getAvatar', 'url' => '/avatar', 'verb' => 'GET'],
    ]
];
