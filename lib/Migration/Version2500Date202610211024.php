<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Reddit\Migration;

use Closure;
use OCA\Reddit\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2500Date202610211024 extends SimpleMigrationStep {

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return null|ISchemaWrapper
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		// only the settings, the OAuth flow, the requests to Reddit and the
		// link previews read these, so no other request has to load them
		$this->appConfig->updateLazy(Application::APP_ID, 'client_id', true);
		$this->appConfig->updateLazy(Application::APP_ID, 'client_secret', true);
		$this->appConfig->updateLazy(Application::APP_ID, 'link_preview_enabled', true);

		return null;
	}
}
