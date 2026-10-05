/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AdminSettings from '../../components/AdminSettings.vue'
import { httpError, setInitialState } from '../helpers.js'

vi.mock('@nextcloud/axios', () => ({ default: { put: vi.fn() } }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/password-confirmation', () => ({ confirmPassword: vi.fn() }))

const ADMIN_URL = '/index.php/apps/integration_reddit/admin-config'
const SENSITIVE_URL = '/index.php/apps/integration_reddit/sensitive-admin-config'

/**
 * Mount the admin settings with an admin config of the test's choosing.
 *
 * @param config what the server put on the page
 */
function mountSettings(config = {}) {
	// Admin.php replaces a stored secret with this placeholder and sends an
	// empty string when none is stored: a real secret never reaches the page
	const secret = config.client_secret ?? 'dummySecret'
	expect(['dummySecret', ''], 'the server never sends a real secret').toContain(secret)
	setInitialState('admin-config', {
		client_id: 'the-client',
		...config,
		client_secret: secret,
	})
	return mount(AdminSettings)
}

/**
 * One of the text fields, by the placeholder the admin sees in it.
 *
 * @param wrapper the mounted settings
 * @param placeholder the placeholder text
 */
function field(wrapper, placeholder) {
	return wrapper.find(`input[placeholder="${placeholder}"]`)
}

describe('AdminSettings', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		axios.put.mockResolvedValue({ data: {} })
		confirmPassword.mockResolvedValue()
	})

	afterEach(() => {
		vi.useRealTimers()
		vi.restoreAllMocks()
	})

	it('shows both credential fields', () => {
		const wrapper = mountSettings()

		expect(field(wrapper, 'Client ID of your Reddit application').exists()).toBe(true)
		expect(field(wrapper, 'Client secret of your Reddit application').exists()).toBe(true)
	})

	it('keeps the credential fields readonly until they are focused', async () => {
		const wrapper = mountSettings()
		const clientId = field(wrapper, 'Client ID of your Reddit application')
		expect(clientId.attributes('readonly')).toBe('')

		await clientId.trigger('focus')

		expect(clientId.attributes('readonly')).toBeUndefined()
		expect(field(wrapper, 'Client secret of your Reddit application').attributes('readonly'))
			.toBeUndefined()
	})

	it('clears the stored secret along with the application id', async () => {
		// a secret left behind is paired with the built-in application id,
		// which authenticates for nobody and says nothing about it
		vi.useFakeTimers()
		const wrapper = mountSettings()

		await wrapper.findAll('.input-field__trailing-button')[0].trigger('click')
		await vi.advanceTimersByTimeAsync(2000)

		expect(axios.put).toHaveBeenCalledWith(SENSITIVE_URL, {
			values: { client_id: '', client_secret: '' },
		})
	})

	it('clears the secret on its own without touching the application id', async () => {
		vi.useFakeTimers()
		const wrapper = mountSettings()

		await wrapper.findAll('.input-field__trailing-button')[1].trigger('click')
		await vi.advanceTimersByTimeAsync(2000)

		expect(axios.put).toHaveBeenCalledWith(SENSITIVE_URL, {
			values: { client_id: 'the-client', client_secret: '' },
		})
	})

	describe('typing in a field', () => {
		it('waits for the admin to stop typing before saving', async () => {
			vi.useFakeTimers()
			const wrapper = mountSettings()

			await field(wrapper, 'Client ID of your Reddit application').setValue('typed-id')
			await vi.advanceTimersByTimeAsync(1999)
			expect(axios.put).not.toHaveBeenCalled()

			await vi.advanceTimersByTimeAsync(1)
			expect(axios.put).toHaveBeenCalledTimes(1)
		})

		it('saves one burst of typing once', async () => {
			vi.useFakeTimers()
			const wrapper = mountSettings()
			const clientId = field(wrapper, 'Client ID of your Reddit application')

			await clientId.setValue('typed')
			await vi.advanceTimersByTimeAsync(1000)
			await clientId.setValue('typed-id')
			await vi.advanceTimersByTimeAsync(2000)

			expect(axios.put).toHaveBeenCalledTimes(1)
			expect(axios.put.mock.calls[0][1].values.client_id).toBe('typed-id')
		})

		it('saves through the endpoint that asks for the password', async () => {
			vi.useFakeTimers()
			const wrapper = mountSettings()

			await field(wrapper, 'Client ID of your Reddit application').setValue('typed-id')
			await vi.advanceTimersByTimeAsync(2000)

			expect(confirmPassword).toHaveBeenCalled()
			expect(axios.put).toHaveBeenCalledWith(SENSITIVE_URL, {
				values: { client_id: 'typed-id' },
			})
		})

		it('leaves the stored secret alone while the field still shows the placeholder', async () => {
			vi.useFakeTimers()
			const wrapper = mountSettings()

			await field(wrapper, 'Client ID of your Reddit application').setValue('typed-id')
			await vi.advanceTimersByTimeAsync(2000)

			expect(axios.put.mock.calls[0][1].values).not.toHaveProperty('client_secret')
		})

		it('sends a secret the admin actually typed', async () => {
			vi.useFakeTimers()
			const wrapper = mountSettings({ client_secret: '' })

			await field(wrapper, 'Client secret of your Reddit application').setValue('a-new-secret')
			await vi.advanceTimersByTimeAsync(2000)

			expect(axios.put.mock.calls[0][1].values.client_secret).toBe('a-new-secret')
		})
	})

	describe('saving', () => {
		it('sends nothing until the password dialog is answered', async () => {
			let answerDialog
			confirmPassword.mockReturnValue(new Promise((resolve) => {
				answerDialog = resolve
			}))
			const wrapper = mountSettings()

			const saving = wrapper.vm.saveOptions({ client_secret: 'secret' }, true)
			await flushPromises()
			expect(axios.put).not.toHaveBeenCalled()

			answerDialog()
			await saving
			await flushPromises()

			expect(axios.put).toHaveBeenCalledWith(SENSITIVE_URL, { values: { client_secret: 'secret' } })
		})

		it('says so and saves nothing when the admin dismisses the password dialog', async () => {
			confirmPassword.mockRejectedValue(new Error('Dialog closed'))
			const wrapper = mountSettings()

			await wrapper.vm.saveOptions({ client_secret: 'secret' }, true)
			await flushPromises()

			expect(axios.put).not.toHaveBeenCalled()
			expect(showSuccess).not.toHaveBeenCalled()
			expect(showError)
				.toHaveBeenCalledWith('Password confirmation is required to save these options')
		})

		it('always saves through the endpoint that asks for the password', async () => {
			// the page has no way to reach the plain endpoint: every field
			// carries a credential, so onInput always saves sensitively
			vi.useFakeTimers()
			const wrapper = mountSettings()

			await field(wrapper, 'Client ID of your Reddit application').setValue('typed-id')
			await vi.advanceTimersByTimeAsync(2000)
			await field(wrapper, 'Client secret of your Reddit application').setValue('typed-secret')
			await vi.advanceTimersByTimeAsync(2000)

			expect(axios.put.mock.calls.map(([url]) => url)).toEqual([SENSITIVE_URL, SENSITIVE_URL])
			expect(axios.put).not.toHaveBeenCalledWith(ADMIN_URL, expect.anything())
		})

		it('confirms a saved option to the admin', async () => {
			const wrapper = mountSettings()

			await wrapper.vm.saveOptions({ client_id: 'x' })
			await flushPromises()

			expect(showSuccess).toHaveBeenCalledWith('Reddit admin options saved')
		})

		it('reports what the server said when saving fails', async () => {
			axios.put.mockRejectedValue(httpError(500, 'read-only config'))
			const wrapper = mountSettings()

			await wrapper.vm.saveOptions({ client_id: 'x' })
			await flushPromises()

			expect(showError).toHaveBeenCalledWith('Failed to save Reddit admin options: read-only config')
		})
	})
})
