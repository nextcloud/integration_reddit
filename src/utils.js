/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

let mytimer = 0
export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

/**
 * Which browser the page is running in, as far as the personal settings need
 * to know: it picks the instructions for registering the protocol handler.
 *
 * This used to probe objects instead of the user agent, and every probe but
 * Opera's had stopped being reachable: chrome.webstore went in Chrome 71 and
 * chrome.runtime is not exposed to a page, InstallTrigger went in Firefox 115,
 * safari.pushNotification in Safari 16, document.documentMode was Internet
 * Explorer and window.StyleMedia the old Edge. Chrome users were shown no
 * instructions at all because of it.
 */
export function detectBrowser() {
	const userAgent = navigator.userAgent

	// Opera carries Chrome/ as well, so it has to be asked about first
	if (/ OPR\//.test(userAgent)) {
		return 'opera'
	}

	if (/Firefox\//.test(userAgent)) {
		return 'firefox'
	}

	// Chrome, Chromium and the Chromium-based Edge, which carries Chrome/ too
	if (/Chrom(e|ium)\//.test(userAgent)) {
		return 'chrome'
	}

	return 'unknown browser'
}
