<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
		['name' => 'config#oauthProtocolRedirect', 'url' => '/oauth-protocol-redirect', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'redditAPI#getNotifications', 'url' => '/notifications', 'verb' => 'GET'],
		['name' => 'redditAPI#getAvatar', 'url' => '/avatar', 'verb' => 'GET'],
		['name' => 'redditAPI#getThumbnail', 'url' => '/thumbnail', 'verb' => 'GET'],
	]
];
