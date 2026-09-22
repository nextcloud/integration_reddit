/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { login } from '@nextcloud/e2e-test-server/playwright'
import { test as base, expect } from '@playwright/test'

// the test container always has this admin user
const admin = { userId: 'admin', password: 'admin' }
const ocs = { 'OCS-APIRequest': 'true', Accept: 'application/json' }

// Every test also fails on an uncaught exception, or on an unexpected failing request to one of the app's own routes.
// Errors of other apps on the instance are ignored on purpose.
const test = base.extend<{ appErrors: void }>({
	appErrors: [async ({ page }, use) => {
		const errors: string[] = []
		page.on('pageerror', (error) => errors.push(`uncaught: ${error.message}`))
		page.on('response', (response) => {
			if (response.status() >= 400 && response.url().includes('/integration_reddit/')) {
				errors.push(`${response.status()} ${response.request().method()} ${new URL(response.url()).pathname}`)
			}
		})
		await use()
		expect(errors).toEqual([])
	}, { auto: true }],
})

test.beforeEach(async ({ page }) => {
	await login(page.request, admin)
})

test.describe('Link previews', () => {
	test('offer the provider to the smart picker', async ({ page }) => {
		const response = await page.request.get('../ocs/v2.php/references/providers', { headers: ocs })
		expect(response.ok()).toBe(true)
		const providers = (await response.json()).ocs.data as Array<{ id: string, title: string, icon_url: string }>
		const provider = providers.find((candidate) => candidate.id === 'reddit-publication')
		expect(provider).toBeDefined()
		expect(provider?.title).toBe('Reddit publications and subreddits')
		// the provider icon is served by the app
		expect((await page.request.get(provider!.icon_url)).ok()).toBe(true)
	})
})

test.describe('Search providers', () => {
	test('offer the providers to unified search', async ({ page }) => {
		const response = await page.request.get('../ocs/v2.php/search/providers', { headers: ocs })
		expect(response.ok()).toBe(true)
		const providers = (await response.json()).ocs.data as Array<{ id: string, appId: string, name: string }>
		const expected = {
			'reddit-publication-search': 'Reddit posts',
			'reddit-subreddit-search': 'Subreddits',
		}
		for (const [id, name] of Object.entries(expected)) {
			expect(providers.find((candidate) => candidate.id === id), `provider ${id}`).toMatchObject({
				appId: 'integration_reddit',
				name,
			})
		}
	})

	test('answer an empty result for a user without a Reddit account', async ({ page }) => {
		for (const id of ['reddit-publication-search', 'reddit-subreddit-search']) {
			const response = await page.request.get(`../ocs/v2.php/search/providers/${id}/search?term=nextcloud`, { headers: ocs })
			expect(response.ok(), id).toBe(true)
			expect((await response.json()).ocs.data.entries, id).toEqual([])
		}
	})
})
