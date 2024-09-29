<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon reddit-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			:width="size"
			:height="size"
			enable-background="new 0 0 20 20"
			version="1.1"
			viewBox="0 0 20 20"
			xml:space="preserve"
			xmlns="http://www.w3.org/2000/svg">
			<path d="m10 0a10 10 0 0 0-10 10 10 10 0 0 0 10 10 10 10 0 0 0 10-10 10 10 0 0 0-10-10zm0.75781 3.9922a0.31 0.31 0 0 1 0.0625 0.0078125l2.4492 0.49023a1 1 0 1 1-0.12891 0.60938l-2.1406-0.44922-0.65039 3.1191a7.12 7.12 0 0 1 3.8496 1.2305 1.46 1.46 0 0 1 2.4707 1 1.46 1.46 0 0 1-0.81055 1.3301 2.87 2.87 0 0 1 0 0.43945c0 2.24-2.6101 4.0605-5.8301 4.0605-3.22 0-5.8301-1.8205-5.8301-4.0605a2.87 2.87 0 0 1 0-0.43945 1.46 1.46 0 1 1 1.6113-2.3906 7.14 7.14 0 0 1 3.9004-1.2285l0.73828-3.4707a0.31 0.31 0 0 1 0.30859-0.24805zm-3.0586 6.0078a1 1 0 0 0-1.0293 1 1 1 0 0 0 1 1 1 1 0 0 0 0.029297-2zm4.5586 0.041016a1 1 0 0 0 0.042969 1.998l-0.011719 0.041016a1 1 0 0 0 1.0117-1.041 1 1 0 0 0-1.043-0.99805zm-4.5254 3.2676a0.27 0.27 0 0 0-0.19336 0.44141 3.84 3.84 0 0 0 2.4707 0.76953 3.84 3.84 0 0 0 2.4707-0.76953v0.039062a0.27933 0.27933 0 1 0-0.39062-0.39844 3.28 3.28 0 0 1-2.0898 0.60938 3.27 3.27 0 0 1-2.0801-0.63086 0.27 0.27 0 0 0-0.1875-0.060547z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'RedditIcon',
	props: {
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
