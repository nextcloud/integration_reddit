/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { login } from '@nextcloud/e2e-test-server/playwright'
import { test as base, expect } from '@playwright/test'

// the test container always has this admin user
const admin = { userId: 'admin', password: 'admin' }

// the dashboard widget learns from this 400 that no Reddit account is connected
const expectedFailures = ['400 GET /index.php/apps/integration_reddit/notifications']

// served as the subreddit avatar of mocked posts
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64')

// a post as the app returns it for a connected account
const post = {
	name: 't3_nc36',
	notification_type: 'post',
	subreddit: 'NextCloud',
	author: 'someone',
	thumbnail: 'self',
	title: 'Nextcloud 36 is out',
	permalink: '/r/NextCloud/comments/nc36/nextcloud_36_is_out/',
	created_utc: 1789725600,
}

// Every test also fails on an uncaught exception, or on an unexpected failing request to one of the app's own routes.
// Errors of other apps on the instance are ignored on purpose.
const test = base.extend<{ appErrors: void }>({
	appErrors: [async ({ page }, use) => {
		const errors: string[] = []
		page.on('pageerror', (error) => errors.push(`uncaught: ${error.message}`))
		page.on('response', (response) => {
			const failure = `${response.status()} ${response.request().method()} ${new URL(response.url()).pathname}`
			if (response.status() >= 400 && response.url().includes('/integration_reddit/') && !expectedFailures.includes(failure)) {
				errors.push(failure)
			}
		})
		await use()
		expect(errors).toEqual([])
	}, { auto: true }],
})

/**
 * Put exactly these widgets on the dashboard of the logged in user.
 *
 * @param page the page whose session is used
 * @param widgets the widget ids
 */
async function showOnlyWidgets(page: Page, ...widgets: string[]) {
	const layout = await page.request.post('../ocs/v2.php/apps/dashboard/api/v3/layout', {
		headers: { 'OCS-APIRequest': 'true' },
		data: { layout: widgets },
	})
	expect(layout.ok()).toBe(true)
}

test.beforeEach(async ({ page }) => {
	await login(page.request, admin)
})

test.describe('Admin settings', () => {
	test('show the Reddit section', async ({ page }) => {
		await page.goto('settings/admin/connected-accounts')
		const section = page.locator('#reddit_prefs')
		await expect(section.getByRole('heading', { name: /Reddit integration/ })).toBeVisible()
		await expect(section.getByLabel('Application ID', { exact: true })).toBeVisible()
		await expect(section.getByLabel('Application secret', { exact: true })).toBeVisible()
	})
})

test.describe('Personal settings', () => {
	test('show the Reddit section', async ({ page }) => {
		await page.goto('settings/user/connected-accounts')
		const section = page.locator('#reddit_prefs')
		await expect(section.getByRole('heading', { name: /Reddit integration/ })).toBeVisible()
		// over plain HTTP, as in the test container, the section only explains why it cannot connect
		await expect(section.getByText('You must access this page with HTTPS to be able to authenticate to Reddit.')
			.or(section.getByRole('button', { name: 'Connect to Reddit' }))).toBeVisible()
	})
})

test.describe('Dashboard widget', () => {
	test('ask to connect a Reddit account', async ({ page }) => {
		await showOnlyWidgets(page, 'reddit_news')
		await page.goto('apps/dashboard/')

		const widget = page.locator('.panel').filter({ hasText: 'Reddit news' })
		await expect(widget.getByText('No Reddit account connected')).toBeVisible()
		await expect(widget.getByRole('button', { name: 'Connect to Reddit' })).toBeVisible()
	})

	test('list the posts of a connected account', async ({ page }) => {
		await showOnlyWidgets(page, 'reddit_news')
		// answer the way the app does for a connected account, the test container cannot reach Reddit
		await page.route('**/apps/integration_reddit/notifications**', (route) => route.fulfill({ json: [post] }))
		await page.route('**/apps/integration_reddit/avatar**', (route) => route.fulfill({ contentType: 'image/png', body: png }))
		await page.goto('apps/dashboard/')

		const widget = page.locator('.panel').filter({ hasText: 'Reddit news' })
		const item = widget.getByRole('link', { name: /Nextcloud 36 is out/ })
		await expect(item).toBeVisible()
		await expect(item).toHaveAttribute('href', 'https://reddit.com/r/NextCloud/comments/nc36/nextcloud_36_is_out/')
		await expect(widget.getByText('/r/NextCloud', { exact: true })).toBeVisible()
	})
})
