<template>
	<div v-if="state.client_id" id="reddit_prefs" class="section">
		<h2>
			<a class="icon icon-reddit" />
			{{ t('integration_reddit', 'Reddit integration') }}
		</h2>
		<div v-if="showOAuth" class="reddit-content">
			<div v-if="!state.token">
				<p class="settings-hint">
					<span class="icon icon-details" />
					<span v-if="usingCustomApp">
						{{ t('integration_reddit', 'If you have trouble authenticating, ask your Nextcloud administrator to check Reddit admin settings.') }}
					</span>
					<span v-else>
						{{ t('integration_reddit', 'Make sure to accept the protocol registration on top of this page to allow authentication to Reddit.') }}
						<span v-if="isChromium">
							<br>
							{{ t('integration_reddit', 'With Chrome/Chromium, you should see a popup on browser top-left to authorize this page to open "web+nextcloudreddit" links.') }}
							<br>
							{{ t('integration_reddit', 'If you don\'t see the popup, you can still click on this icon in the address bar.') }}
							<br>
							<img :src="chromiumImagePath">
							<br>
							{{ t('integration_reddit', 'Then authorize this page to open "web+nextcloudreddit" links.') }}
						</span>
						<span v-else-if="isFirefox">
							<br>
							{{ t('integration_reddit', 'With Firefox, you should see a bar on top of this page to authorize this page to open "web+nextcloudreddit" links.') }}
							<br><br>
							<img :src="firefoxImagePath">
						</span>
					</span>
				</p>
				<button v-if="!state.token" id="reddit-oauth" @click="onOAuthClick">
					<span class="icon icon-external" />
					{{ t('integration_reddit', 'Connect to Reddit') }}
				</button>
			</div>
			<div v-else class="reddit-grid-form">
				<label>
					<a class="icon icon-checkmark-color" />
					{{ t('integration_reddit', 'Connected as {user}', { user: userName }) }}
				</label>
				<button id="reddit-rm-cred" @click="onLogoutClick">
					<span class="icon icon-close" />
					{{ t('integration_reddit', 'Disconnect from Reddit') }}
				</button>
			</div>
		</div>
		<p v-else class="settings-hint">
			{{ t('integration_reddit', 'You must access this page with HTTPS to be able to authenticate to Reddit.') }}
		</p>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl, imagePath } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay, detectBrowser } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_reddit', 'user-config'),
			readonly: true,
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
		userName() {
			return this.state.user_name
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const rdToken = urlParams.get('redditToken')
		if (rdToken === 'success') {
			showSuccess(t('integration_reddit', 'Reddit OAuth access token successfully retrieved!'))
		} else if (rdToken === 'error') {
			showError(t('integration_reddit', 'Reddit OAuth error:') + ' ' + urlParams.get('message'))
		}

		// register protocol handler
		if (window.isSecureContext && window.navigator.registerProtocolHandler) {
			window.navigator.registerProtocolHandler('web+nextcloudreddit', generateUrl('/apps/integration_reddit/oauth-protocol-redirect') + '?url=%s', 'Nextcloud Reddit integration')
		}
	},

	methods: {
		onLogoutClick() {
			this.state.token = ''
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
					token: this.state.token,
				},
			}
			const url = generateUrl('/apps/integration_reddit/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_reddit', 'Reddit options saved.'))
				})
				.catch((error) => {
					showError(
						t('integration_reddit', 'Failed to save Reddit options')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		onOAuthClick() {
			let redirectUri
			if (this.state.client_secret) {
				const redirectEndpoint = generateUrl('/apps/integration_reddit/oauth-redirect')
				redirectUri = window.location.protocol + '//' + window.location.protocol + redirectEndpoint
			} else {
				redirectUri = 'web+nextcloudreddit://oauth-protocol-redirect'
			}
			const oauthState = Math.random().toString(36).substring(3)
			const requestUrl = 'https://www.reddit.com/api/v1/authorize?client_id=' + encodeURIComponent(this.state.client_id)
				+ '&redirect_uri=' + encodeURIComponent(redirectUri)
				+ '&state=' + encodeURIComponent(oauthState)
				+ '&response_type=code'
				+ '&duration=permanent'
				+ '&scope=' + encodeURIComponent('identity history mysubreddits privatemessages read wikiread')

			const req = {
				values: {
					oauth_state: oauthState,
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
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
#reddit_prefs .icon {
	display: inline-block;
	width: 32px;
}
.icon-reddit {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.theme--dark .icon-reddit {
	background-image: url(./../../img/app.svg);
}
.reddit-content {
	margin-left: 40px;
}
.reddit-grid-form {
	max-width: 600px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	button .icon {
		margin-bottom: -1px;
	}
}
.reddit-grid-form label {
	line-height: 38px;
}
</style>
