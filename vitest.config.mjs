/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

export default defineConfig({
	plugins: [vue()],
	test: {
		environment: 'jsdom',
		environmentOptions: {
			jsdom: {
				url: 'http://nextcloud.local',
			},
		},
		setupFiles: ['src/__tests__/setup.js'],
		coverage: {
			// count the whole frontend, not only the files a spec imports
			include: ['src/**/*.{js,vue}'],
			exclude: ['src/__tests__/**'],
		},
		include: ['src/**/*.{test,spec}.?(c|m)[jt]s?(x)'],
		server: {
			deps: {
				inline: [/@nextcloud\//],
			},
		},
	},
})
