<template>
	<div id="reddit_prefs" class="section">
		<h2>
			<a class="icon icon-reddit" />
			{{ t('integration_reddit', 'Reddit integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_reddit', 'There are 3 ways to allow your Nextcloud users to use OAuth to authenticate to Reddit:') }}
			<br><br>
			<ul>
				<li>
					<b>1. </b>{{ t('integration_reddit', 'Leave all fields empty to use default Nextcloud Reddit OAuth app.') }}
					<br><br>
				</li>
				<li>
					<b>2. </b>{{ t('integration_reddit', 'Create your own Reddit "web application" in Reddit preferences and put the application ID and secret below.') }}
					<a href="https://www.reddit.com/prefs/apps" target="_blank" class="external">{{ t('integration_reddit', 'Reddit app settings') }}</a>
					<br><br>
					<span class="icon icon-details" />
					{{ t('integration_reddit', 'Make sure you set the "Redirection URI" to') }}
					<b> {{ redirect_uri }} </b>
					<br><br>
				</li>
				<li>
					<b>3. </b>{{ t('integration_reddit', 'Create your own Reddit "mobile application" in Reddit preferences and put the application ID below. Leave the "Application secret" field empty.') }}
					<a href="https://www.reddit.com/prefs/apps" target="_blank" class="external">{{ t('integration_reddit', 'Reddit app settings') }}</a>
					<br><br>
					<span class="icon icon-details" />
					{{ t('integration_reddit', 'Make sure you set the "Redirection URI" to') }}
					<b> {{ redirect_uri_protocol }} </b>
					<br><br>
				</li>
			</ul>
		</p>
		<div class="grid-form">
			<label for="reddit-client-id">
				<a class="icon icon-category-auth" />
				{{ t('integration_reddit', 'Application ID') }}
			</label>
			<input id="reddit-client-id"
				v-model="state.client_id"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_reddit', 'Client ID or your Reddit application')"
				@input="onInput"
				@focus="readonly = false">
			<label for="reddit-client-secret">
				<a class="icon icon-category-auth" />
				{{ t('integration_reddit', 'Application secret') }}
			</label>
			<input id="reddit-client-secret"
				v-model="state.client_secret"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_reddit', 'Client secret or your Reddit application')"
				@input="onInput"
				@focus="readonly = false">
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
	name: 'AdminSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_reddit', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_reddit/oauth-redirect'),
			redirect_uri_protocol: 'web+nextcloudreddit://oauth-protocol-redirect',
		}
	},

	watch: {
	},

	mounted() {
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
					client_id: this.state.client_id,
					client_secret: this.state.client_secret,
				},
			}
			const url = generateUrl('/apps/integration_reddit/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_reddit', 'Reddit admin options saved.'))
				})
				.catch((error) => {
					showError(
						t('integration_reddit', 'Failed to save Reddit admin options')
						+ ': ' + error.response.request.responseText
					)
					console.debug(error)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
.grid-form label {
	line-height: 38px;
}
.grid-form input {
	width: 100%;
}
.grid-form {
	max-width: 500px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	margin-left: 30px;
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
body.theme--dark .icon-reddit {
	background-image: url(./../../img/app.svg);
}
</style>
