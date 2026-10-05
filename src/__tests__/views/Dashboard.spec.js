/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import Dashboard from '../../views/Dashboard.vue'
import { httpError } from '../helpers.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

const NEWS_URL = '/index.php/apps/integration_reddit/notifications'

/**
 * A post as the server hands it to the widget.
 *
 * @param over what to add or override
 */
function post(over = {}) {
	return {
		name: 't3_abc123',
		notification_type: 'post',
		subreddit: 'nextcloud',
		title: 'Nextcloud 36 is out',
		permalink: '/r/nextcloud/comments/abc123/nextcloud_36_is_out/',
		thumbnail: 'https://b.thumbs.redditmedia.com/abc.jpg',
		created_utc: 1758193800,
		...over,
	}
}

/**
 * A private message as the server hands it to the widget.
 *
 * @param over what to add or override
 */
function message(over = {}) {
	return {
		name: 't4_def456',
		notification_type: 'privatemessage',
		subreddit: 'nextcloud',
		title: 'Re: your post',
		permalink: '/message/messages/def456/',
		author: 'jane_doe',
		created_utc: 1758193900,
		...over,
	}
}

/**
 * Mount the widget and let its first request settle.
 *
 * @param news what the server answers with
 * @param options.shallow whether to stub the child components
 * @param options
 */
async function mountWidget(news = [], options = {}) {
	axios.get.mockResolvedValue({ data: news })
	const wrapper = mount(Dashboard, {
		shallow: options.shallow ?? false,
		props: { title: 'Reddit news' },
	})
	await flushPromises()
	return wrapper
}

/**
 * Mount the widget with a request that fails, which is how it reaches every
 * state but 'ok'.
 *
 * @param error what axios rejects with
 */
async function mountFailing(error) {
	axios.get.mockRejectedValue(error)
	const wrapper = mount(Dashboard, { props: { title: 'Reddit news' } })
	await flushPromises()
	return wrapper
}

describe('RedditDashboard', () => {
	beforeEach(() => vi.clearAllMocks())
	afterEach(() => {
		vi.useRealTimers()
		vi.restoreAllMocks()
	})

	describe('the items it hands to the widget', () => {
		it('maps a post to every field the widget renders', async () => {
			const wrapper = await mountWidget([post()])

			expect(wrapper.vm.items).toEqual([{
				id: 't3_abc123',
				targetUrl: 'https://reddit.com/r/nextcloud/comments/abc123/nextcloud_36_is_out/',
				avatarUrl: '/index.php/apps/integration_reddit/thumbnail?url=https%3A%2F%2Fb.thumbs.redditmedia.com%2Fabc.jpg&subreddit=nextcloud',
				avatarUsername: 'nextcloud',
				avatarIsNoUser: true,
				overlayIconUrl: '/apps/integration_reddit/img/post.svg',
				mainText: 'Nextcloud 36 is out',
				subText: '/r/nextcloud',
			}])
		})

		it('maps a private message to the avatar of its author', async () => {
			const wrapper = await mountWidget([message()])

			expect(wrapper.vm.items[0]).toMatchObject({
				id: 't4_def456',
				targetUrl: 'https://reddit.com/message/messages/def456/',
				avatarUrl: '/index.php/apps/integration_reddit/avatar?username=jane_doe',
				overlayIconUrl: '/apps/integration_reddit/img/message.svg',
			})
		})

		it('links a post the backend passed through without a permalink to Reddit', async () => {
			// getNotifications guarantees only the subreddit and the title
			const wrapper = await mountWidget([post({ permalink: undefined })])

			expect(wrapper.vm.items[0].targetUrl).toBe('https://reddit.com')
			expect(wrapper.find('a.item-list__entry').attributes('href')).toBe('https://reddit.com')
		})

		it('renders what it maps', async () => {
			const wrapper = await mountWidget([post()])

			expect(wrapper.find('a.item-list__entry').attributes('href'))
				.toBe('https://reddit.com/r/nextcloud/comments/abc123/nextcloud_36_is_out/')
			expect(wrapper.text()).toContain('Nextcloud 36 is out')
			expect(wrapper.text()).toContain('/r/nextcloud')
			expect(wrapper.find('img.item-icon').attributes('src'))
				.toBe('/apps/integration_reddit/img/post.svg')
		})

		it.each([
			['self', 'self'],
			['spoiler', 'spoiler'],
			['default', 'default'],
			['nsfw', 'nsfw'],
			['image', 'image'],
			['nothing at all', ''],
			['no thumbnail field', undefined],
		])('asks for the subreddit icon when the thumbnail is %s', async (_, thumbnail) => {
			// only a real url can be fetched; every other value reddit sends is
			// a placeholder the thumbnail route would reject
			const wrapper = await mountWidget([post({ thumbnail })], { shallow: true })

			expect(wrapper.vm.items[0].avatarUrl)
				.toBe('/index.php/apps/integration_reddit/avatar?subreddit=nextcloud')
		})

		it.each([
			['a number', 42],
			['a boolean', true],
			['an object', { a: 1 }],
			['a list', ['x']],
		])('falls back to the subreddit icon when the thumbnail is %s', async (_, thumbnail) => {
			// optional chaining guards an absent value, not a wrong type
			const wrapper = await mountWidget([post({ thumbnail })])

			expect(wrapper.vm.items[0].avatarUrl)
				.toBe('/index.php/apps/integration_reddit/avatar?subreddit=nextcloud')
			expect(wrapper.text()).toContain('Nextcloud 36 is out')
		})

		it('asks the thumbnail route for a plain http thumbnail too', async () => {
			const wrapper = await mountWidget([post({ thumbnail: 'http://i.redd.it/x.jpg' })], { shallow: true })

			expect(wrapper.vm.items[0].avatarUrl)
				.toBe('/index.php/apps/integration_reddit/thumbnail?url=http%3A%2F%2Fi.redd.it%2Fx.jpg&subreddit=nextcloud')
		})

		it('keys a post with no fullname by its permalink', async () => {
			const wrapper = await mountWidget([
				post({ name: undefined, permalink: '/r/nextcloud/comments/one/' }),
				post({ name: undefined, permalink: '/r/nextcloud/comments/two/', title: 'The other one' }),
			], { shallow: true })

			expect(wrapper.vm.items.map((item) => item.id))
				.toEqual(['/r/nextcloud/comments/one/', '/r/nextcloud/comments/two/'])
		})

		it('lists a post repeated inside one listing once', async () => {
			const wrapper = await mountWidget([post(), post()], { shallow: true })

			expect(wrapper.vm.items.map((item) => item.id)).toEqual(['t3_abc123'])
		})

		it('escapes what it puts in the thumbnail query', async () => {
			const wrapper = await mountWidget([post({ thumbnail: 'https://x/a b&c=1.jpg', subreddit: 'a b' })], { shallow: true })

			expect(wrapper.vm.items[0].avatarUrl)
				.toBe('/index.php/apps/integration_reddit/thumbnail?url=https%3A%2F%2Fx%2Fa%20b%26c%3D1.jpg&subreddit=a%20b')
		})

		it('escapes an author name that needs it', async () => {
			const wrapper = await mountWidget([message({ author: 'a b&c' })], { shallow: true })

			expect(wrapper.vm.items[0].avatarUrl)
				.toBe('/index.php/apps/integration_reddit/avatar?username=a%20b%26c')
		})

		it('has no avatar for a message without an author', async () => {
			const wrapper = await mountWidget([message({ author: undefined })], { shallow: true })

			expect(wrapper.vm.items[0].avatarUrl).toBeUndefined()
		})

		it('has no overlay icon for a kind it does not know', async () => {
			const wrapper = await mountWidget([post({ notification_type: 'comment' })], { shallow: true })

			expect(wrapper.vm.items[0].overlayIconUrl).toBe('')
		})
	})

	describe('fetching', () => {
		it('asks the server for the news of the dashboard', async () => {
			await mountWidget([])

			expect(axios.get).toHaveBeenCalledTimes(1)
			expect(axios.get).toHaveBeenCalledWith(NEWS_URL, {})
		})

		it('polls once a minute', async () => {
			vi.useFakeTimers()
			await mountWidget([])
			const started = Date.now()

			await vi.advanceTimersByTimeAsync(59999)
			// only the line above may have moved the clock
			expect(Date.now() - started).toBe(59999)
			expect(axios.get).toHaveBeenCalledTimes(1)

			await vi.advanceTimersByTimeAsync(1)
			expect(axios.get).toHaveBeenCalledTimes(2)
		})
	})

	describe('what it adds on a later poll', () => {
		it('adds a post newer than the newest it holds', async () => {
			vi.useFakeTimers()
			const wrapper = await mountWidget([post()])
			axios.get.mockResolvedValue({
				data: [post({ name: 't3_new', title: 'Newer', created_utc: 1758200000 }), post()],
			})

			await vi.advanceTimersByTimeAsync(60000)

			expect(wrapper.vm.notifications.map((n) => n.name)).toEqual(['t3_new', 't3_abc123'])
		})

		it('adds nothing when the answer repeats what it holds', async () => {
			vi.useFakeTimers()
			const wrapper = await mountWidget([post()])

			await vi.advanceTimersByTimeAsync(60000)

			expect(wrapper.vm.notifications.map((n) => n.name)).toEqual(['t3_abc123'])
		})

		it('adds a post that shares its second with one it already holds', async () => {
			vi.useFakeTimers()
			const wrapper = await mountWidget([post({ name: 't3_first', created_utc: 1758193800 })])
			axios.get.mockResolvedValue({
				data: [
					post({ name: 't3_same_second', title: 'Same second', created_utc: 1758193800 }),
					post({ name: 't3_first', created_utc: 1758193800 }),
				],
			})

			await vi.advanceTimersByTimeAsync(60000)

			expect(wrapper.vm.notifications.map((n) => n.name)).toEqual(['t3_same_second', 't3_first'])
			expect(wrapper.text()).toContain('Same second')
		})

		it('adds what a listing out of order carries', async () => {
			vi.useFakeTimers()
			const wrapper = await mountWidget([post({ name: 't3_held', created_utc: 1758193800 })])
			axios.get.mockResolvedValue({
				data: [
					post({ name: 't3_older', created_utc: 1758100000 }),
					post({ name: 't3_newer', created_utc: 1758300000 }),
				],
			})

			await vi.advanceTimersByTimeAsync(60000)

			expect(wrapper.vm.notifications.map((n) => n.name))
				.toEqual(['t3_newer', 't3_held', 't3_older'])
			expect(wrapper.vm.items[0].mainText).toBe('Nextcloud 36 is out')
		})

		it('keeps no more than it needs to recognise a repeat', async () => {
			vi.useFakeTimers()
			const wrapper = await mountWidget(Array.from({ length: 80 }, (_, i) => post({ name: `t3_a${i}` })))
			axios.get.mockResolvedValue({
				data: Array.from({ length: 80 }, (_, i) => post({ name: `t3_b${i}` })),
			})

			await vi.advanceTimersByTimeAsync(60000)

			expect(wrapper.vm.notifications).toHaveLength(100)
			expect(wrapper.vm.notifications[0].name).toBe('t3_b0')
		})

		it('keeps the newest on top when a listing is longer than it holds', async () => {
			vi.useFakeTimers()
			// the same long listing answered over and over used to walk
			// backwards through itself, pushing the newest post out
			const listing = Array.from({ length: 120 }, (_, i) => post({
				name: `t3_p${i}`,
				title: `Post ${i}`,
				created_utc: 1758300000 - i,
			}))
			const wrapper = await mountWidget(listing)
			axios.get.mockResolvedValue({ data: listing })

			await vi.advanceTimersByTimeAsync(180000)

			expect(wrapper.vm.notifications).toHaveLength(100)
			expect(wrapper.vm.notifications[0].name).toBe('t3_p0')
			expect(wrapper.vm.items[0].mainText).toBe('Post 0')
		})

		it('ignores an answer that is not a list at all', async () => {
			vi.useFakeTimers()
			const wrapper = await mountWidget([post()])
			axios.get.mockResolvedValue({ data: { kind: 'Listing', data: { after: null } } })

			await vi.advanceTimersByTimeAsync(60000)

			expect(wrapper.vm.notifications.map((n) => n.name)).toEqual(['t3_abc123'])
			expect(wrapper.text()).toContain('Nextcloud 36 is out')
			// handled, not thrown into the failure path
			expect(wrapper.vm.failedPolls).toBe(0)
		})
	})

	describe('when the request fails', () => {
		it('asks the user to connect when there is no token', async () => {
			const wrapper = await mountFailing(httpError(400))

			expect(wrapper.vm.state).toBe('no-token')
			expect(wrapper.text()).toContain('No Reddit account connected')
			expect(wrapper.find('.connect-button a').attributes('href'))
				.toBe('/index.php/settings/user/connected-accounts')
			expect(showError).not.toHaveBeenCalled()
		})

		it('reports an unauthorised answer to the user', async () => {
			const wrapper = await mountFailing(httpError(401, 'token expired'))

			expect(wrapper.vm.state).toBe('error')
			expect(wrapper.text()).toContain('Error connecting to Reddit')
			expect(showError).toHaveBeenCalledWith('Failed to get Reddit news token expired')
		})

		it.each([
			['no token', 400],
			['an unauthorised answer', 401],
		])('stays stopped after %s, however often the tab is left and come back to', async (_, status) => {
			vi.useFakeTimers()
			await mountFailing(httpError(status))
			const wrapper = await mountFailing(httpError(status))
			const before = axios.get.mock.calls.length
			const toastsBefore = showError.mock.calls.length

			for (let i = 0; i < 2; i++) {
				wrapper.vm.windowVisibility = false
				await flushPromises()
				wrapper.vm.windowVisibility = true
				await flushPromises()
			}
			await vi.advanceTimersByTimeAsync(180000)

			// coming back to the tab used to restart a loop a failure had
			// stopped, and raise the same toast again every time
			expect(axios.get.mock.calls.length).toBe(before)
			expect(showError.mock.calls.length).toBe(toastsBefore)
		})

		it.each([
			['no token', 400],
			['an unauthorised answer', 401],
		])('stops polling after %s', async (_, status) => {
			vi.useFakeTimers()
			await mountFailing(httpError(status))

			await vi.advanceTimersByTimeAsync(180000)

			expect(axios.get).toHaveBeenCalledTimes(1)
		})

		it.each([
			['a server error', 500],
			['an unreachable Reddit', 503],
		])('keeps quiet about a single %s', async (_, status) => {
			const wrapper = await mountFailing(httpError(status))

			expect(wrapper.vm.state).toBe('loading')
			expect(showError).not.toHaveBeenCalled()
		})

		it('retries the status the server answers when Reddit cannot be reached', async () => {
			// the server answers 503 for that, which must not be read as a
			// problem with the account the way 401 is
			vi.useFakeTimers()
			axios.get.mockRejectedValue(httpError(503, 'Could not reach Reddit'))
			const wrapper = mount(Dashboard, { props: { title: 'Reddit news' } })
			await flushPromises()

			await vi.advanceTimersByTimeAsync(120000)

			expect(axios.get).toHaveBeenCalledTimes(3)
			expect(wrapper.vm.state).toBe('unreachable')
			expect(wrapper.find('.connect-button').exists()).toBe(false)
			expect(showError).not.toHaveBeenCalled()
		})

		it('says so once the failures stop looking transient', async () => {
			vi.useFakeTimers()
			axios.get.mockRejectedValue(httpError(500))
			const wrapper = mount(Dashboard, { props: { title: 'Reddit news' } })
			await flushPromises()

			await vi.advanceTimersByTimeAsync(60000)
			expect(wrapper.vm.state).toBe('loading')

			await vi.advanceTimersByTimeAsync(60000)
			expect(wrapper.vm.state).toBe('unreachable')
			expect(wrapper.text()).toContain('Could not reach Reddit')
			// the account is fine, so there is nothing to connect
			expect(wrapper.find('.connect-button').exists()).toBe(false)
			expect(showError).not.toHaveBeenCalled()
		})

		it('forgets the failures once a poll succeeds', async () => {
			vi.useFakeTimers()
			axios.get.mockRejectedValue(httpError(500))
			const wrapper = mount(Dashboard, { props: { title: 'Reddit news' } })
			await flushPromises()
			await vi.advanceTimersByTimeAsync(60000)

			axios.get.mockResolvedValue({ data: [] })
			await vi.advanceTimersByTimeAsync(60000)
			expect(wrapper.vm.state).toBe('ok')

			axios.get.mockRejectedValue(httpError(500))
			await vi.advanceTimersByTimeAsync(120000)

			expect(wrapper.vm.state).toBe('ok')
		})

		it('keeps polling and recovers', async () => {
			vi.useFakeTimers()
			axios.get.mockRejectedValueOnce(httpError(500))
			const wrapper = mount(Dashboard, { props: { title: 'Reddit news' } })
			await flushPromises()

			axios.get.mockResolvedValue({ data: [post()] })
			await vi.advanceTimersByTimeAsync(60000)

			expect(wrapper.vm.state).toBe('ok')
			expect(wrapper.vm.items).toHaveLength(1)
		})
	})

	describe('what it says when it has nothing to show', () => {
		it('says nothing while it is still loading', async () => {
			axios.get.mockReturnValue(new Promise(() => {}))
			const wrapper = mount(Dashboard, { props: { title: 'Reddit news' } })

			// what the user looked at forever after one failed request
			expect(wrapper.findAll('.item-list__entry')).toHaveLength(7)
			expect(wrapper.vm.state).toBe('loading')
			expect(wrapper.vm.emptyContentMessage).toBe('')
			expect(wrapper.vm.emptyContentIcon).toBe(CheckIcon)
			expect(wrapper.text()).not.toContain('No Reddit news!')
		})

		it('tells the user there is nothing when the server sent nothing', async () => {
			const wrapper = await mountWidget([])

			expect(wrapper.text()).toContain('No Reddit news!')
		})
	})

	describe('the link to Reddit', () => {
		it('points at the Reddit front page once the widget is full', async () => {
			const full = Array.from({ length: 7 }, (_, i) => post({ name: `t3_${i}` }))
			const wrapper = await mountWidget(full)

			expect(wrapper.vm.showMoreUrl).toBe('https://reddit.com/new')
			expect(wrapper.find('a.more').attributes('href')).toBe('https://reddit.com/new')
			expect(wrapper.find('a.more').text()).toBe('Reddit news')
		})
	})

	describe('the polling loop', () => {
		it('stops when the widget goes away', async () => {
			vi.useFakeTimers()
			const wrapper = await mountWidget([])

			wrapper.unmount()
			await vi.advanceTimersByTimeAsync(180000)

			expect(axios.get).toHaveBeenCalledTimes(1)
		})

		it('stops while the browser tab is hidden and picks up again', async () => {
			vi.useFakeTimers()
			const wrapper = await mountWidget([])

			wrapper.vm.windowVisibility = false
			await flushPromises()
			await vi.advanceTimersByTimeAsync(180000)
			expect(axios.get).toHaveBeenCalledTimes(1)

			wrapper.vm.windowVisibility = true
			await flushPromises()
			expect(axios.get).toHaveBeenCalledTimes(2)
		})

		it('follows the visibility of the browser tab', async () => {
			const wrapper = await mountWidget([])

			vi.spyOn(document, 'hidden', 'get').mockReturnValue(true)
			document.dispatchEvent(new Event('visibilitychange'))
			expect(wrapper.vm.windowVisibility).toBe(false)

			vi.spyOn(document, 'hidden', 'get').mockReturnValue(false)
			document.dispatchEvent(new Event('visibilitychange'))
			expect(wrapper.vm.windowVisibility).toBe(true)
		})

		it('stops listening for visibility when the widget goes away', async () => {
			const wrapper = await mountWidget([])
			const handler = wrapper.vm.changeWindowVisibility
			const remove = vi.spyOn(document, 'removeEventListener')

			wrapper.unmount()

			expect(remove).toHaveBeenCalledWith('visibilitychange', handler)
		})
	})
})
