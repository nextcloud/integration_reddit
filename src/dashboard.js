/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

__webpack_nonce__ = btoa(OC.requestToken) // eslint-disable-line
__webpack_public_path__ = OC.linkTo('integration_reddit', 'js/') // eslint-disable-line

OCA.Dashboard.register('reddit_news', async (el, { widget }) => {
	const { default: Vue } = await import(/* webpackChunkName: "dashboard-lazy" */'vue')
	const { default: Dashboard } = await import(/* webpackChunkName: "dashboard-lazy" */'./views/Dashboard.vue')
	Vue.mixin({ methods: { t, n } })
	const View = Vue.extend(Dashboard)
	new View({
		propsData: { title: widget.title },
	}).$mount(el)
})
