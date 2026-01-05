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
		<div id="reddit-content">
			<NcNoteCard type="info">
				{{ t('integration_reddit', 'There are 3 ways to allow your Nextcloud users to use OAuth to authenticate to Reddit:') }}
				<br><br>
				1. {{ t('integration_reddit', 'Leave all fields empty to use default Nextcloud Reddit OAuth app.') }}
				<br><br>
				2. {{ t('integration_reddit', 'Create your own Reddit "web application" in Reddit preferences and put the application ID and secret below.') }}
				<a href="https://www.reddit.com/prefs/apps" target="_blank" class="external">{{ t('integration_reddit', 'Reddit app settings') }}</a>
				<br>
				{{ t('integration_reddit', 'Make sure you set the "Redirection URI" to') }}
				<strong>{{ redirect_uri }}</strong>
				<br><br>
				3. {{ t('integration_reddit', 'Create your own Reddit "mobile application" in Reddit preferences and put the application ID below. Leave the "Application secret" field empty.') }}
				<a href="https://www.reddit.com/prefs/apps" target="_blank" class="external">{{ t('integration_reddit', 'Reddit app settings') }}</a>
				<br>
				{{ t('integration_reddit', 'Make sure you set the "Redirection URI" to') }}
				<strong>{{ redirect_uri_protocol }}</strong>
			</NcNoteCard>
			<NcTextField
				v-model="state.client_id"
				type="password"
				:label="t('integration_reddit', 'Application ID')"
				:placeholder="t('integration_reddit', 'Client ID of your Reddit application')"
				:readonly="readonly"
				:show-trailing-button="!!state.client_id"
				@trailing-button-click="state.client_id = ''; onInput()"
				@focus="readonly = false"
				@update:model-value="onInput">
				<template #icon>
					<KeyOutlineIcon :size="20" />
				</template>
			</NcTextField>
			<NcTextField
				v-model="state.client_secret"
				type="password"
				:label="t('integration_reddit', 'Application secret')"
				:placeholder="t('integration_reddit', 'Client secret of your Reddit application')"
				:readonly="readonly"
				:show-trailing-button="!!state.client_secret"
				@trailing-button-click="state.client_secret = ''; onInput()"
				@focus="readonly = false"
				@update:model-value="onInput">
				<template #icon>
					<KeyOutlineIcon :size="20" />
				</template>
			</NcTextField>
		</div>
	</div>
</template>

<script>
import KeyOutlineIcon from 'vue-material-design-icons/KeyOutline.vue'

import RedditIcon from './icons/RedditIcon.vue'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { confirmPassword } from '@nextcloud/password-confirmation'

export default {
	name: 'AdminSettings',

	components: {
		RedditIcon,
		KeyOutlineIcon,
		NcNoteCard,
		NcTextField,
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
			delay(() => {
				const values = {
					client_id: this.state.client_id,
				}
				if (this.state.client_secret !== 'dummySecret') {
					values.client_secret = this.state.client_secret
				}
				this.saveOptions(values, true)
			}, 2000)()
		},
		async saveOptions(values, sensitive = false) {
			if (sensitive) {
				await confirmPassword()
			}
			const req = {
				values,
			}
			const url = sensitive
				? generateUrl('/apps/integration_reddit/sensitive-admin-config')
				: generateUrl('/apps/integration_reddit/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_reddit', 'Reddit admin options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_reddit', 'Failed to save Reddit admin options')
						+ ': ' + error.response?.request?.responseText,
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
		max-width: 800px;
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	h2 {
		display: flex;
		align-items: center;
		justify-content: start;
		gap: 8px;
	}
}
</style>
