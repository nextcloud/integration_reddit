<template>
	<div v-if="state.client_id" id="reddit_prefs" class="section">
		<h2>
			<a class="icon icon-reddit" />
			{{ t('integration_reddit', 'Reddit integration') }}
		</h2>
		<div class="reddit-grid-form">
			<label for="reddit-token">
				<a class="icon icon-category-auth" />
				{{ t('integration_reddit', 'Reddit access token') }}
			</label>
			<input id="reddit-token"
				v-model="state.token"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_reddit', 'Get it with OAuth')"
				@input="onInput"
				@focus="readonly = false">
			<button v-if="showOAuth" id="reddit-oauth" @click="onOAuthClick">
				<span class="icon icon-external" />
				{{ t('integration_reddit', 'Get access with OAuth') }}
			</button>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
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
		}
	},

	computed: {
		showOAuth() {
			return this.state.client_id
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
.reddit-grid-form label {
	line-height: 38px;
}
.reddit-grid-form input {
	width: 100%;
}
.reddit-grid-form {
	max-width: 900px;
	display: grid;
	grid-template: 1fr / 1fr 1fr 1fr;
	margin-left: 30px;
	button .icon {
		margin-bottom: -1px;
	}
}
#reddit_prefs .icon {
	display: inline-block;
	width: 32px;
}
#reddit_prefs .grid-form .icon {
	margin-bottom: -3px;
}
.icon-reddit {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.dark .icon-reddit {
	background-image: url(./../../img/app.svg);
}
</style>
