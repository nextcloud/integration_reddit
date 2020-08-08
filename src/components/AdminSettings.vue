<template>
	<div id="reddit_prefs" class="section">
		<h2>
			<a class="icon icon-reddit" />
			{{ t('reddit', 'Reddit') }}
		</h2>
		<p class="settings-hint">
			{{ t('reddit', 'If you want to allow your Nextcloud users to use OAuth to authenticate to https://reddit.com, create a Reddit application in your Reddit preferences (https://www.reddit.com/prefs/apps) and set the ID and secret here.') }}
			<br>
			{{ t('reddit', 'Make sure you set the "redirect_uri" to') }}
			<br><b> {{ redirect_uri }} </b>
		</p>
		<div class="grid-form">
			<label for="reddit-client-id">
				<a class="icon icon-category-auth" />
				{{ t('reddit', 'Reddit application client ID') }}
			</label>
			<input id="reddit-client-id"
				v-model="state.client_id"
				type="password"
				:readonly="readonly"
				:placeholder="t('reddit', 'Client ID or your Reddit application')"
				@input="onInput"
				@focus="readonly = false">
			<label for="reddit-client-secret">
				<a class="icon icon-category-auth" />
				{{ t('reddit', 'Reddit application client secret') }}
			</label>
			<input id="reddit-client-secret"
				v-model="state.client_secret"
				type="password"
				:readonly="readonly"
				:placeholder="t('reddit', 'Client secret or your Reddit application')"
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
			state: loadState('reddit', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			redirect_uri: OC.getProtocol() + '://' + OC.getHostName() + generateUrl('/apps/reddit/oauth-redirect'),
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
			const url = generateUrl('/apps/reddit/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('reddit', 'Reddit admin options saved.'))
				})
				.catch((error) => {
					showError(
						t('reddit', 'Failed to save Reddit admin options')
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
body.dark .icon-reddit {
	background-image: url(./../../img/app.svg);
}
</style>
