/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import PersonalSettings from '../../components/PersonalSettings.vue'
import { httpError, setInitialState } from '../helpers.js'

vi.mock('@nextcloud/axios', () => ({ default: { put: vi.fn() } }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

const CONFIG_URL = '/index.php/apps/integration_reddit/config'
const REDIRECT_URI = 'http://nextcloud.local/index.php/apps/integration_reddit/oauth-redirect'
const PROTOCOL_URI = 'web+nextcloudreddit://oauth-protocol-redirect'

/**
 * Mount the personal settings with a user config of the test's choosing.
 *
 * @param config what the server put on the page
 */
function mountSettings(config = {}) {
	setInitialState('user-config', {
		user_name: 'jane',
		client_id: 'the-client',
		client_secret: 'the-secret',
		...config,
	})
	return mount(PersonalSettings)
}

/**
 * Press the button with the given label, the way a user would: a test that
 * calls the method instead passes even when the template binds nothing.
 *
 * @param wrapper the mounted settings
 * @param label the label the user reads on it
 */
async function press(wrapper, label) {
	const button = wrapper.findAll('button').find((candidate) => candidate.text().includes(label))
	expect(button, `no button labelled ${label}`).toBeDefined()
	await button.trigger('click')
}

/**
 * jsdom's userAgent is a prototype getter, so it is replaced rather than spied
 * on, and afterEach puts the real one back whether the test passed or not.
 *
 * @param userAgent what the browser should claim to be
 */
function stubUserAgent(userAgent) {
	Object.defineProperty(window.navigator, 'userAgent', { value: userAgent, configurable: true })
}

/**
 * jsdom does not define isSecureContext at all, so it cannot be spied on.
 *
 * @param secure what the page should look like
 */
function stubSecureContext(secure) {
	Object.defineProperty(window, 'isSecureContext', { value: secure, configurable: true })
}

/**
 * Replace window.location, which jsdom will not let a test navigate.
 *
 * @param over what to override on it
 */
function stubLocation(over = {}) {
	const replace = vi.fn()
	vi.spyOn(window, 'location', 'get').mockReturnValue({
		protocol: 'http:',
		host: 'nextcloud.local',
		hostname: 'nextcloud.local',
		pathname: '/index.php/settings/user/connected-accounts',
		search: '',
		replace,
		...over,
	})
	return replace
}

describe('PersonalSettings', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		axios.put.mockResolvedValue({ data: {} })
		window.history.replaceState({}, '', '/settings/user/connected-accounts')
	})

	const realUserAgent = window.navigator.userAgent

	afterEach(() => {
		vi.useRealTimers()
		vi.restoreAllMocks()
		delete window.isSecureContext
		delete globalThis.InstallTrigger
		stubUserAgent(realUserAgent)
	})

	describe('whether it shows a connection', () => {
		it('names the connected account', () => {
			expect(mountSettings({ user_name: 'jane_doe' }).text()).toContain('Connected as jane_doe')
		})

		it.each([
			['an empty user name', ''],
			['no user name at all', undefined],
		])('counts %s as not connected', (_, userName) => {
			const wrapper = mountSettings({ user_name: userName })

			expect(wrapper.vm.connected).toBeFalsy()
			expect(wrapper.text()).not.toContain('Connected as')
		})

		it('shows nothing at all until an admin has configured the app', () => {
			const wrapper = mountSettings({ client_id: '' })

			expect(wrapper.find('#reddit_prefs').exists()).toBe(false)
		})
	})

	describe('whether it offers OAuth', () => {
		it('offers it for a custom app over plain http', () => {
			const wrapper = mountSettings({ client_secret: 'the-secret' })

			expect(wrapper.vm.showOAuth).toBeTruthy()
			expect(wrapper.vm.usingCustomApp).toBeTruthy()
		})

		it('refuses the default app over plain http, which Reddit would reject', () => {
			stubLocation({ protocol: 'http:' })
			const wrapper = mountSettings({ client_secret: '' })

			expect(wrapper.vm.showOAuth).toBeFalsy()
			expect(wrapper.vm.usingCustomApp).toBeFalsy()
		})

		it('offers the default app over https', () => {
			stubLocation({ protocol: 'https:' })
			const wrapper = mountSettings({ client_secret: '' })

			expect(wrapper.vm.showOAuth).toBeTruthy()
		})
	})

	describe('the instructions it shows before connecting', () => {
		it('sends the user to their administrator for a custom app', () => {
			const wrapper = mountSettings({ user_name: '', client_secret: 'the-secret' })

			expect(wrapper.text()).toContain('ask your Nextcloud administrator')
			expect(wrapper.text()).not.toContain('Make sure to accept the protocol registration')
		})

		it('explains the protocol registration for the default app', () => {
			stubLocation({ protocol: 'https:' })
			const wrapper = mountSettings({ user_name: '', client_secret: '' })

			expect(wrapper.text()).toContain('Make sure to accept the protocol registration')
			expect(wrapper.text()).not.toContain('ask your Nextcloud administrator')
		})

		it('shows the Chromium hint to a Chrome user', () => {
			stubLocation({ protocol: 'https:' })
			stubUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36')

			const wrapper = mountSettings({ user_name: '', client_secret: '' })

			expect(wrapper.vm.isChromium).toBe(true)
			expect(wrapper.text()).toContain('popup on browser top-left')
			expect(wrapper.find('img[src="/apps/integration_reddit/img/chromium.png"]').exists()).toBe(true)
		})

		it('shows the Firefox hint to a Firefox user', () => {
			stubLocation({ protocol: 'https:' })
			stubUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:155.0) Gecko/20100101 Firefox/155.0')

			const wrapper = mountSettings({ user_name: '', client_secret: '' })

			expect(wrapper.vm.isFirefox).toBe(true)
			expect(wrapper.find('img[src="/apps/integration_reddit/img/firefox.png"]').exists()).toBe(true)
		})
	})

	describe('connecting', () => {
		it('sends the custom app to the redirect uri of this instance', async () => {
			const replace = stubLocation()
			const wrapper = mountSettings({ user_name: '', client_secret: 'the-secret' })

			await press(wrapper, 'Connect to Reddit')
			await flushPromises()

			const [url, body] = axios.put.mock.calls[0]
			expect(url).toBe(CONFIG_URL)
			expect(body.values.redirect_uri).toBe(REDIRECT_URI)
			expect(body.values.oauth_state).toMatch(/^\w+$/)

			const target = new URL(replace.mock.calls[0][0])
			expect(target.origin + target.pathname).toBe('https://www.reddit.com/api/v1/authorize')
			expect(target.searchParams.get('client_id')).toBe('the-client')
			expect(target.searchParams.get('redirect_uri')).toBe(REDIRECT_URI)
			expect(target.searchParams.get('state')).toBe(body.values.oauth_state)
			expect(target.searchParams.get('response_type')).toBe('code')
			expect(target.searchParams.get('duration')).toBe('permanent')
			expect(target.searchParams.get('scope').split(' ')).toContain('privatemessages')
		})

		it('sends the default app through the protocol handler', async () => {
			const replace = stubLocation({ protocol: 'https:' })
			const wrapper = mountSettings({ user_name: '', client_secret: '' })

			await press(wrapper, 'Connect to Reddit')
			await flushPromises()

			expect(axios.put.mock.calls[0][1].values.redirect_uri).toBe(PROTOCOL_URI)
			expect(new URL(replace.mock.calls[0][0]).searchParams.get('redirect_uri')).toBe(PROTOCOL_URI)
		})

		it('escapes what it puts in the query', async () => {
			const replace = stubLocation()
			const wrapper = mountSettings({ user_name: '', client_id: 'a b&c', client_secret: 'the-secret' })

			await press(wrapper, 'Connect to Reddit')
			await flushPromises()

			expect(replace.mock.calls[0][0]).toContain('client_id=a%20b%26c')
			expect(replace.mock.calls[0][0])
				.toContain('redirect_uri=http%3A%2F%2Fnextcloud.local%2Findex.php%2Fapps%2Fintegration_reddit%2Foauth-redirect')
		})

		it('does not send the user away when the state could not be saved', async () => {
			const replace = stubLocation()
			axios.put.mockRejectedValue(httpError(500, 'no space left'))
			const wrapper = mountSettings({ user_name: '', client_secret: 'the-secret' })

			await press(wrapper, 'Connect to Reddit')
			await flushPromises()

			expect(showError).toHaveBeenCalledWith('Failed to save Reddit OAuth state: no space left')
			expect(replace).not.toHaveBeenCalled()
		})
	})

	describe('disconnecting', () => {
		it('forgets the account and tells the server to do the same', async () => {
			const wrapper = mountSettings()

			await press(wrapper, 'Disconnect from Reddit')
			await flushPromises()

			expect(axios.put).toHaveBeenCalledWith(CONFIG_URL, { values: { user_name: '' } })
			expect(wrapper.text()).not.toContain('Connected as')
			expect(wrapper.text()).toContain('Connect to Reddit')
		})

		it('keeps showing the account when the server refused to forget it', async () => {
			axios.put.mockRejectedValue(httpError(500, 'read-only config'))
			const wrapper = mountSettings({ user_name: 'jane' })

			await press(wrapper, 'Disconnect from Reddit')
			await flushPromises()

			// the server still holds the token, so the page must not claim otherwise
			expect(showError).toHaveBeenCalledWith('Failed to save Reddit options: read-only config')
			expect(wrapper.text()).toContain('Connected as jane')
		})
	})

	describe('saving', () => {
		it('confirms a saved option to the user', async () => {
			const wrapper = mountSettings()

			await press(wrapper, 'Disconnect from Reddit')
			await flushPromises()

			expect(showSuccess).toHaveBeenCalledWith('Reddit options saved')
		})

		it('reports what the server said when saving fails', async () => {
			axios.put.mockRejectedValue(httpError(500, 'disk full'))
			const wrapper = mountSettings()

			await press(wrapper, 'Disconnect from Reddit')
			await flushPromises()

			expect(showError).toHaveBeenCalledWith('Failed to save Reddit options: disk full')
		})
	})

	describe('coming back from Reddit', () => {
		it('congratulates the user on a successful round trip', () => {
			window.history.replaceState({}, '', '/settings/user?redditToken=success')

			mountSettings()

			expect(showSuccess).toHaveBeenCalledWith('Successfully connected to Reddit!')
		})

		it('passes on the message of a failed one', () => {
			window.history.replaceState({}, '', '/settings/user?redditToken=error&message=state+mismatch')

			mountSettings()

			expect(showError).toHaveBeenCalledWith('Reddit OAuth error: state mismatch')
		})

		it('says nothing when the user just opened the page', () => {
			mountSettings()

			expect(showSuccess).not.toHaveBeenCalled()
			expect(showError).not.toHaveBeenCalled()
		})
	})

	describe('the protocol handler for the default app', () => {
		it('registers one in a secure context', () => {
			const registerProtocolHandler = vi.fn()
			stubSecureContext(true)
			// detectBrowser() reads the user agent while the component builds its data
			vi.spyOn(window, 'navigator', 'get')
				.mockReturnValue({ userAgent: window.navigator.userAgent, registerProtocolHandler })

			mountSettings()

			expect(registerProtocolHandler).toHaveBeenCalledWith(
				'web+nextcloudreddit',
				'/index.php/apps/integration_reddit/oauth-protocol-redirect?url=%s',
				expect.stringContaining('Nextcloud Reddit integration on'),
			)
		})

		it('registers none over plain http, where the browser would refuse', () => {
			const registerProtocolHandler = vi.fn()
			stubSecureContext(false)
			vi.spyOn(window, 'navigator', 'get')
				.mockReturnValue({ userAgent: window.navigator.userAgent, registerProtocolHandler })

			mountSettings()

			expect(registerProtocolHandler).not.toHaveBeenCalled()
		})
	})
})
