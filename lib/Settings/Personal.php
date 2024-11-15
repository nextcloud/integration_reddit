<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Reddit\Settings;

use OCA\Reddit\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;

use OCP\Settings\ISettings;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ?string $userId,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');

		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', Application::DEFAULT_REDDIT_CLIENT_ID) ?: Application::DEFAULT_REDDIT_CLIENT_ID;
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');

		$userConfig = [
			'client_id' => $clientID,
			// Do not expose the saved client secret to the user
			'client_secret' => $clientSecret !== '' ? 'dummySecret' : '',
			'user_name' => $userName,
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
