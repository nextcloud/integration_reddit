<template>
	<div v-if="state.client_id" id="reddit_prefs" class="section">
		<h2>
			<RedditIcon class="icon" />
			{{ t('integration_reddit', 'Reddit integration') }}
		</h2>
		<div v-if="showOAuth">
			<div v-if="!connected">
				<p v-if="usingCustomApp" class="settings-hint">
					<InformationOutlineIcon :size="20" class="icon" />
					{{ t('integration_reddit', 'If you have trouble authenticating, ask your Nextcloud administrator to check Reddit admin settings.') }}
				</p>
				<div v-else>
					<p class="settings-hint">
						{{ t('integration_reddit', 'Make sure to accept the protocol registration on top of this page to allow authentication to Reddit.') }}
					</p>
					<span v-if="isChromium">
						<p class="settings-hint">
							{{ t('integration_reddit', 'With Chrome/Chromium, you should see a popup on browser top-left to authorize this page to open "web+nextcloudreddit" links.') }}
						</p>
						<p class="settings-hint">
							{{ t('integration_reddit', 'If you don\'t see the popup, you can still click on this icon in the address bar.') }}
						</p>
						<img :src="chromiumImagePath">
						<br><br>
						<p class="settings-hint">
							{{ t('integration_reddit', 'Then authorize this page to open "web+nextcloudreddit" links.') }}
						</p>
						<p class="settings-hint">
							{{ t('integration_reddit', 'If you still don\'t manage to get the protocol registered, check your settings on this page:') }}
						</p>
						<strong>chrome://settings/handlers</strong>
					</span>
					<span v-else-if="isFirefox">
						<p class="settings-hint">
							{{ t('integration_reddit', 'With Firefox, you should see a bar on top of this page to authorize this page to open "web+nextcloudreddit" links.') }}
						</p>
						<img :src="firefoxImagePath">
					</span>
				</div>
				<br>
			</div>
			<div id="reddit-content">
				<NcButton v-if="!connected"
					@click="onOAuthClick">
					<template #icon>
						<OpenInNewIcon :size="20" />
					</template>
					{{ t('integration_reddit', 'Connect to Reddit') }}
				</NcButton>
				<div v-else
					class="line">
					<label>
						<CheckIcon :size="20" class="icon" />
						{{ t('integration_reddit', 'Connected as {user}', { user: state.user_name }) }}
					</label>
					<NcButton @click="onLogoutClick">
						<template #icon>
							<CloseIcon :size="20" />
						</template>
						{{ t('integration_reddit', 'Disconnect from Reddit') }}
					</NcButton>
				</div>
			</div>
		</div>
		<p v-else
			class="settings-hint">
			{{ t('integration_reddit', 'You must access this page with HTTPS to be able to authenticate to Reddit.') }}
		</p>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import RedditIcon from './icons/RedditIcon.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl, imagePath } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay, detectBrowser } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'PersonalSettings',

	components: {
		RedditIcon,
		NcButton,
		CheckIcon,
		OpenInNewIcon,
		CloseIcon,
		InformationOutlineIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_reddit', 'user-config'),
			readonly: true,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_reddit/oauth-redirect'),
			redirect_uri_protocol: 'web+nextcloudreddit://oauth-protocol-redirect',
			chromiumImagePath: imagePath('integration_reddit', 'chromium.png'),
			firefoxImagePath: imagePath('integration_reddit', 'firefox.png'),
			isChromium: detectBrowser() === 'chrome',
			isFirefox: detectBrowser() === 'firefox',
		}
	},

	computed: {
		showOAuth() {
			// 2 cases, no client secret means the default app is used => https required
			// if there is a client secret, redirect URL is probably correctly defined by NC admin in Reddit OAuth app
			return this.state.client_id
				&& (this.state.client_secret || window.location.protocol === 'https:')
		},
		usingCustomApp() {
			return this.state.client_id && this.state.client_secret
		},
		connected() {
			return this.state.user_name && this.state.user_name !== ''
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.slice(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const rdToken = urlParams.get('redditToken')
		if (rdToken === 'success') {
			showSuccess(t('integration_reddit', 'Successfully connected to Reddit!'))
		} else if (rdToken === 'error') {
			showError(t('integration_reddit', 'Reddit OAuth error:') + ' ' + urlParams.get('message'))
		}

		// register protocol handler
		if (window.isSecureContext && window.navigator.registerProtocolHandler) {
			const ncUrl = window.location.protocol
				+ '//' + window.location.hostname
				+ window.location.pathname.replace('settings/user/connected-accounts', '').replace('/index.php/', '')
			window.navigator.registerProtocolHandler(
				'web+nextcloudreddit',
				generateUrl('/apps/integration_reddit/oauth-protocol-redirect') + '?url=%s',
				t('integration_reddit', 'Nextcloud Reddit integration on {ncUrl}', { ncUrl })
			)
		}
	},

	methods: {
		onLogoutClick() {
			this.state.user_name = ''
			this.saveOptions()
		},
		onInput() {
			const that = this
			delay(() => {
				that.saveOptions()
			}, 2000)()
		},
		saveOptions() {
			const req = {
				values: {
					user_name: this.state.user_name,
				},
			}
			const url = generateUrl('/apps/integration_reddit/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_reddit', 'Reddit options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_reddit', 'Failed to save Reddit options')
						+ ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})
		},
		onOAuthClick() {
			const redirectUri = this.state.client_secret
				? this.redirect_uri
				: this.redirect_uri_protocol
			const oauthState = Math.random().toString(36).substring(3)
			const requestUrl = 'https://www.reddit.com/api/v1/authorize'
				+ '?client_id=' + encodeURIComponent(this.state.client_id)
				+ '&redirect_uri=' + encodeURIComponent(redirectUri)
				+ '&state=' + encodeURIComponent(oauthState)
				+ '&response_type=code'
				+ '&duration=permanent'
				+ '&scope=' + encodeURIComponent('identity history mysubreddits privatemessages read wikiread')

			const req = {
				values: {
					oauth_state: oauthState,
					redirect_uri: redirectUri,
				},
			}
			const url = generateUrl('/apps/integration_reddit/config')
			axios.put(url, req)
				.then((response) => {
					window.location.replace(requestUrl)
				})
				.catch((error) => {
					showError(
						t('integration_reddit', 'Failed to save Reddit OAuth state')
						+ ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
#reddit_prefs {
	#reddit-content {
		margin-left: 40px;
	}
	h2,
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	h2 .icon {
		margin-right: 8px;
	}

	.line {
		> label {
			width: 300px;
			display: flex;
			align-items: center;
		}
		> input {
			width: 250px;
		}
	}
}
</style>
