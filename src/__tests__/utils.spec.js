/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { delay, detectBrowser } from '../utils.js'

describe('delay', () => {
	beforeEach(() => vi.useFakeTimers())
	afterEach(() => vi.useRealTimers())

	it('calls back only once the time has passed', () => {
		const cb = vi.fn()
		delay(cb, 200)()
		vi.advanceTimersByTime(199)
		expect(cb).not.toHaveBeenCalled()
		vi.advanceTimersByTime(1)
		expect(cb).toHaveBeenCalledOnce()
	})

	it('keeps only the last call of a burst, which is what the settings page relies on', () => {
		const cb = vi.fn()
		const delayed = delay(cb, 100)
		delayed('first')
		vi.advanceTimersByTime(50)
		delayed('second')
		vi.advanceTimersByTime(100)
		expect(cb).toHaveBeenCalledOnce()
		expect(cb).toHaveBeenCalledWith('second')
	})
})

describe('detectBrowser', () => {
	// The personal settings page uses this to decide which browser's
	// instructions to show for the protocol handler.
	const realUserAgent = window.navigator.userAgent

	/**
	 * jsdom's userAgent is a prototype getter, so it is replaced rather than spied on.
	 *
	 * @param userAgent what the browser should claim to be
	 */
	function stubUserAgent(userAgent) {
		Object.defineProperty(window.navigator, 'userAgent', { value: userAgent, configurable: true })
	}

	afterEach(() => stubUserAgent(realUserAgent))

	it.each([
		['Chrome', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'chrome'],
		['Chromium', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chromium/153.0.0.0 Safari/537.36', 'chrome'],
		['Edge', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'chrome'],
		['Firefox', 'Mozilla/5.0 (X11; Linux x86_64; rv:155.0) Gecko/20100101 Firefox/155.0', 'firefox'],
		['Firefox ESR', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0', 'firefox'],
		['Opera', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 OPR/139.0.0.0', 'opera'],
		['Safari', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Safari/605.1.15', 'unknown browser'],
		['a crawler', 'Mozilla/5.0 (compatible; SomeBot/1.0)', 'unknown browser'],
	])('recognises %s', (_, userAgent, expected) => {
		stubUserAgent(userAgent)

		expect(detectBrowser()).toBe(expected)
	})
})
