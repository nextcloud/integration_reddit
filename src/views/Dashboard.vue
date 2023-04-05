<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
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
	</DashboardWidget>
</template>

<script>
import LoginVariantIcon from 'vue-material-design-icons/LoginVariant.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import RedditIcon from '../components/icons/RedditIcon.vue'

import axios from '@nextcloud/axios'
import { generateUrl, imagePath } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { getLocale } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
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
			// lastDate could be computed but we want to keep the value when first notification is removed
			// to avoid getting it again on next request
			lastDate: null,
			locale: getLocale(),
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
			windowVisibility: true,
		}
	},

	computed: {
		items() {
			return this.notifications.map((n) => {
				return {
					id: n.name,
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
		lastId() {
			const nbNotif = this.notifications.length
			return (nbNotif > 0) ? this.notifications[0].name : null
		},
		emptyContentMessage() {
			if (this.state === 'no-token') {
				return t('integration_reddit', 'No Reddit account connected')
			} else if (this.state === 'error') {
				return t('integration_reddit', 'Error connecting to Reddit')
			} else if (this.state === 'ok') {
				return t('integration_reddit', 'No Reddit news!')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.state === 'no-token') {
				return RedditIcon
			} else if (this.state === 'error') {
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

	beforeDestroy() {
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
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 60000)
		},
		fetchNotifications() {
			const req = {}
			// dunnow why 'after' param does not work
			/* if (this.lastId) {
				req.params = {
					after: this.lastId
				}
			} */
			axios.get(generateUrl('/apps/integration_reddit/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(
						t('integration_reddit', 'Failed to get Reddit news') + ' '
						+ error.response.request.responseText
					)
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processNotifications(newNotifications) {
			if (this.lastDate) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.length && this.lastDate < newNotifications[i].created_utc) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filter(newNotifications.slice(0, i))
					this.notifications = toAdd.concat(this.notifications)
				}
			} else {
				// first time we don't check the date
				this.notifications = this.filter(newNotifications)
			}
			// update lastDate manually (explained in data)
			const nbNotif = this.notifications.length
			this.lastDate = (nbNotif > 0) ? this.notifications[0].created_utc : null
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
				return n.thumbnail === 'self' || n.thumbnail === 'spoiler'
					? generateUrl('/apps/integration_reddit/avatar?subreddit={subreddit}', { subreddit: n.subreddit })
					: generateUrl('/apps/integration_reddit/thumbnail?url={url}', { url: n.thumbnail })
			}
		},
		getNotificationTarget(n) {
			return 'https://reddit.com' + n.permalink
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
		getFormattedDate(n) {
			return moment(parseInt(n.created_utc) * 1000).locale(this.locale).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .connect-button {
	margin-top: 10px;
}
</style>
