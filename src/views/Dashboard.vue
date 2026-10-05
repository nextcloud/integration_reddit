<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDashboardWidget :items="items"
		:showMoreUrl="showMoreUrl"
		:showMoreLabel="title"
		:loading="state === 'loading'">
		<template #empty-content>
			<NcEmptyContent
				v-if="emptyContentMessage"
				:description="emptyContentMessage">
				<template #icon>
					<component :is="emptyContentIcon" />
				</template>
				<template #action>
					<div v-if="state === 'no-token' || state === 'error'" class="connect-button">
						<a :href="settingsUrl">
							<NcButton>
								<template #icon>
									<LoginVariantIcon />
								</template>
								{{ t('integration_reddit', 'Connect to Reddit') }}
							</NcButton>
						</a>
					</div>
				</template>
			</NcEmptyContent>
		</template>
	</NcDashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl, imagePath } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDashboardWidget from '@nextcloud/vue/components/NcDashboardWidget'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import LoginVariantIcon from 'vue-material-design-icons/LoginVariant.vue'
import RedditIcon from '../components/icons/RedditIcon.vue'

// enough to recognise what the next listing repeats, several times the 25 a
// listing holds, and far more than the handful the widget shows
const MAX_HELD = 100

export default {
	name: 'RedditDashboard',

	components: {
		NcDashboardWidget,
		NcEmptyContent,
		RedditIcon,
		NcButton,
		LoginVariantIcon,
		CloseIcon,
		CheckIcon,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			notifications: [],
			showMoreUrl: 'https://reddit.com/new',
			loop: null,
			state: 'loading',
			failedPolls: 0,
			settingsUrl: generateUrl('/settings/user/connected-accounts'),
			windowVisibility: true,
		}
	},

	computed: {
		items() {
			return this.notifications.map((n) => {
				return {
					id: this.keyOf(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getAvatarUrl(n),
					avatarUsername: n.subreddit,
					avatarIsNoUser: true,
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: n.title,
					subText: this.getSubline(n),
				}
			})
		},

		emptyContentMessage() {
			if (this.state === 'no-token') {
				return t('integration_reddit', 'No Reddit account connected')
			} else if (this.state === 'error') {
				return t('integration_reddit', 'Error connecting to Reddit')
			} else if (this.state === 'unreachable') {
				return t('integration_reddit', 'Could not reach Reddit')
			} else if (this.state === 'ok') {
				return t('integration_reddit', 'No Reddit news!')
			}
			return ''
		},

		emptyContentIcon() {
			if (this.state === 'no-token') {
				return RedditIcon
			} else if (this.state === 'error' || this.state === 'unreachable') {
				return CloseIcon
			} else if (this.state === 'ok') {
				return CheckIcon
			}
			return CheckIcon
		},
	},

	watch: {
		windowVisibility(newValue) {
			if (newValue) {
				this.launchLoop()
			} else {
				this.stopLoop()
			}
		},
	},

	beforeUnmount() {
		this.stopLoop()
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)
	},

	beforeMount() {
		this.launchLoop()
		document.addEventListener('visibilitychange', this.changeWindowVisibility)
	},

	mounted() {
	},

	methods: {
		changeWindowVisibility() {
			this.windowVisibility = !document.hidden
		},

		stopLoop() {
			clearInterval(this.loop)
		},

		launchLoop() {
			if (this.state === 'no-token' || this.state === 'error') {
				// the account is the problem, so asking again changes nothing
				// and would raise the same toast on every tab switch
				return
			}
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 60000)
		},

		fetchNotifications() {
			// the 'after' param does not page this listing, so every poll asks
			// for the current one and processNotifications keeps what is new
			const req = {}
			axios.get(generateUrl('/apps/integration_reddit/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.failedPolls = 0
				this.state = 'ok'
			}).catch((error) => {
				if (error.response && error.response.status === 400) {
					this.stopLoop()
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					this.stopLoop()
					showError(t('integration_reddit', 'Failed to get Reddit news') + ' '
						+ error.response.request.responseText)
					this.state = 'error'
				} else {
					// a transient failure: keep polling, but say something once
					// it is clearly not transient any more
					this.failedPolls++
					if (this.failedPolls >= 3) {
						this.state = 'unreachable'
					}
					console.debug(error)
				}
			})
		},

		processNotifications(newNotifications) {
			if (!Array.isArray(newNotifications)) {
				return
			}
			// a post is identified by its fullname, so comparing those keeps
			// what is new whatever order the listing arrives in, where
			// comparing created_utc dropped a post sharing its second
			const seen = new Set(this.notifications.map((n) => this.keyOf(n)))
			const toAdd = []
			for (const n of this.filter(newNotifications)) {
				const key = this.keyOf(n)
				if (key !== undefined && seen.has(key)) {
					continue
				}
				seen.add(key)
				toAdd.push(n)
			}
			if (toAdd.length > 0) {
				// newest first whatever order they arrived in, since two polls
				// can be in flight, and only as many as it takes to recognise
				// what the next listing repeats
				this.notifications = toAdd.concat(this.notifications)
					.sort((a, b) => (Number(b.created_utc) || 0) - (Number(a.created_utc) || 0))
					.slice(0, MAX_HELD)
			}
		},

		keyOf(n) {
			return n.name ?? n.permalink
		},

		filter(notifications) {
			return notifications
		},

		getAvatarUrl(n) {
			if (n.notification_type === 'privatemessage') {
				return (n.author)
					? generateUrl('/apps/integration_reddit/avatar?username={username}', { username: n.author })
					: undefined
			} else if (n.notification_type === 'post') {
				// reddit answers with self, spoiler, default, nsfw, image or
				// nothing at all when a post has no thumbnail of its own
				return typeof n.thumbnail === 'string' && n.thumbnail.startsWith('http')
					? generateUrl('/apps/integration_reddit/thumbnail?url={url}&subreddit={subreddit}', { url: n.thumbnail, subreddit: n.subreddit })
					: generateUrl('/apps/integration_reddit/avatar?subreddit={subreddit}', { subreddit: n.subreddit })
			}
		},

		getNotificationTarget(n) {
			return n.permalink ? 'https://reddit.com' + n.permalink : 'https://reddit.com'
		},

		getSubline(n) {
			return '/r/' + n.subreddit
		},

		getNotificationTypeImage(n) {
			if (n.notification_type === 'privatemessage') {
				return imagePath('integration_reddit', 'message.svg')
			} else if (n.notification_type === 'post') {
				return imagePath('integration_reddit', 'post.svg')
			}
			return ''
		},

	},
}
</script>

<style scoped lang="scss">
:deep(.connect-button) {
	margin-top: 10px;
}
</style>
