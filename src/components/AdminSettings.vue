<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="reddit_prefs" class="section">
		<h2>
			<RedditIcon class="icon" />
			{{ t('integration_reddit', 'Reddit integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_reddit', 'There are 3 ways to allow your Nextcloud users to use OAuth to authenticate to Reddit:') }}
		</p>
		<p class="settings-hint">
			1. {{ t('integration_reddit', 'Leave all fields empty to use default Nextcloud Reddit OAuth app.') }}
		</p>
		<p class="settings-hint">
			2. {{ t('integration_reddit', 'Create your own Reddit "web application" in Reddit preferences and put the application ID and secret below.') }}
		</p>
		<a href="https://www.reddit.com/prefs/apps" target="_blank" class="external">{{ t('integration_reddit', 'Reddit app settings') }}</a>
		<br><br>
		<p class="settings-hint">
			<InformationOutlineIcon :size="20" class="icon" />
			{{ t('integration_reddit', 'Make sure you set the "Redirection URI" to') }}
		</p>
		<strong>{{ redirect_uri }}</strong>
		<br><br>
		<p class="settings-hint">
			3. {{ t('integration_reddit', 'Create your own Reddit "mobile application" in Reddit preferences and put the application ID below. Leave the "Application secret" field empty.') }}
		</p>
		<a href="https://www.reddit.com/prefs/apps" target="_blank" class="external">{{ t('integration_reddit', 'Reddit app settings') }}</a>
		<br><br>
		<p class="settings-hint">
			<InformationOutlineIcon :size="20" class="icon" />
			{{ t('integration_reddit', 'Make sure you set the "Redirection URI" to') }}
		</p>
		<strong>{{ redirect_uri_protocol }}</strong>
		<br><br>
		<div id="reddit-content">
			<div class="line">
				<label for="reddit-client-id">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_reddit', 'Application ID') }}
				</label>
				<input id="reddit-client-id"
					v-model="state.client_id"
					type="password"
					:readonly="readonly"
					:placeholder="t('integration_reddit', 'Client ID of your Reddit application')"
					@input="onInput"
					@focus="readonly = false">
			</div>
			<div class="line">
				<label for="reddit-client-secret">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_reddit', 'Application secret') }}
				</label>
				<input id="reddit-client-secret"
					v-model="state.client_secret"
					type="password"
					:readonly="readonly"
					:placeholder="t('integration_reddit', 'Client secret of your Reddit application')"
					@input="onInput"
					@focus="readonly = false">
			</div>
		</div>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'

import RedditIcon from './icons/RedditIcon.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
		RedditIcon,
		KeyIcon,
		InformationOutlineIcon,
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
					showSuccess(t('integration_reddit', 'Reddit admin options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_reddit', 'Failed to save Reddit admin options')
						+ ': ' + error.response?.request?.responseText
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
#reddit_prefs {
	#reddit-content{
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
			width: 300px;
		}
	}
}
</style>
