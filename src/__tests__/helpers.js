/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/**
 * Put an initial state on the page the way the server does, so the components
 * can read it through the real loadState().
 *
 * @param key the state key, e.g. user-config
 * @param value what the server would have serialised
 */
export function setInitialState(key, value) {
	const id = `initial-state-integration_reddit-${key}`
	document.getElementById(id)?.remove()
	// loadState caches by selector, and every test sets its own state
	delete window._nc_initial_state

	const input = document.createElement('input')
	input.type = 'hidden'
	input.id = id
	input.value = btoa(JSON.stringify(value))
	document.body.appendChild(input)
}

/**
 * A rejection shaped like the ones axios hands the components: they read
 * error.response.status and error.response.request.responseText.
 *
 * @param status the HTTP status
 * @param responseText what the server wrote
 */
export function httpError(status, responseText = 'server said no') {
	return { response: { status, request: { responseText } } }
}
