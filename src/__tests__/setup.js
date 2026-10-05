/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { config, enableAutoUnmount } from '@vue/test-utils'
import { afterEach } from 'vitest'

// a mounted dashboard widget keeps a 60 second interval and a document
// listener; unmounting after each test keeps them out of the next one
enableAutoUnmount(afterEach)

// The entry points assign the free variables webpack declares for them
globalThis.__webpack_nonce__ = ''
globalThis.__webpack_public_path__ = ''

// What a Nextcloud page provides and @nextcloud/router reads: without the app's
// web root, imagePath() resolves app images as if they were core ones.
window._oc_webroot = ''
window._oc_appswebroots = { integration_reddit: '/apps/integration_reddit' }

/**
 * Return the source string with its placeholders filled, so an assertion can
 * read what a user would.
 *
 * @param app the app id, ignored here
 * @param text the source string
 * @param vars the placeholder values
 */
function t(app, text, vars) {
	return vars
		? text.replace(/\{(\w+)\}/g, (match, name) => (name in vars ? vars[name] : match))
		: text
}

/**
 * The plural form of the source string, with %n replaced by the count.
 *
 * @param app the app id, ignored here
 * @param singular the singular source string
 * @param plural the plural source string
 * @param count how many
 * @param vars the placeholder values
 */
function n(app, singular, plural, count, vars) {
	return t(app, count === 1 ? singular : plural, vars).replaceAll('%n', count)
}

// The script blocks call the page globals; the templates go through the
// instance proxy, which the app fills with app.mixin({ methods: { t, n } }).
window.t = t
window.n = n
config.global.mocks = { t, n }
