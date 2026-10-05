/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { setInitialState } from './helpers.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/password-confirmation', () => ({ confirmPassword: vi.fn() }))

/**
 * A div for an entry point to mount into.
 *
 * @param id the element id the entry point looks for
 */
function target(id) {
	document.getElementById(id)?.remove()
	const el = document.createElement('div')
	el.id = id
	document.body.appendChild(el)
	return el
}

describe('the dashboard entry point', () => {
	// the module registers on DOMContentLoaded and nothing removes that listener
	// again, so it is imported once and every test reads the same registration
	let registrations

	beforeAll(async () => {
		window.OCA = { Dashboard: { register: vi.fn() } }
		await import('../dashboard.js')
		document.dispatchEvent(new Event('DOMContentLoaded'))
		registrations = [...window.OCA.Dashboard.register.mock.calls]
	})

	beforeEach(() => {
		vi.clearAllMocks()
		axios.get.mockResolvedValue({ data: [] })
	})

	it('registers the widget the app declares', () => {
		expect(registrations.map(([id]) => id)).toEqual(['reddit_news'])
	})

	it('mounts a widget whose template can translate', async () => {
		// the empty state message comes from the page global, the button label
		// from the instance, which is what the entry point's mixin provides
		axios.get.mockRejectedValue({ response: { status: 400, request: { responseText: '' } } })
		const [, mountWidget] = registrations[0]

		const el = target('widget')
		await mountWidget(el, { widget: { title: 'Reddit news' } })
		await flushPromises()

		expect(el.textContent).toContain('No Reddit account connected')
		expect(el.textContent).toContain('Connect to Reddit')
	})

	it('asks the server for the news once mounted', async () => {
		const [, mountWidget] = registrations[0]

		await mountWidget(target('widget-2'), { widget: { title: 'Reddit news' } })
		await flushPromises()

		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/integration_reddit/notifications', {})
	})
})

describe('the settings entry points', () => {
	beforeEach(() => {
		vi.resetModules()
		vi.clearAllMocks()
	})

	it('mounts the admin settings where the server put the section', async () => {
		setInitialState('admin-config', { client_id: '', client_secret: '' })
		const el = target('reddit_prefs')

		await import('../adminSettings.js')

		expect(el.textContent).toContain('Reddit integration')
		expect(el.textContent).toContain('Application ID')
	})

	it('mounts the personal settings where the server put the section', async () => {
		setInitialState('user-config', { user_name: 'jane', client_id: 'the-client', client_secret: 'the-secret' })
		const el = target('reddit_prefs')

		await import('../personalSettings.js')
		await flushPromises()

		expect(el.textContent).toContain('Connected as jane')
	})
})
