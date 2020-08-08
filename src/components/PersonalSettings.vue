<template>
    <div id="reddit_prefs" class="section" v-if="state.client_id && state.client_secret">
            <h2>
                <a class="icon icon-reddit"></a>
                {{ t('reddit', 'Reddit') }}
            </h2>
            <div class="reddit-grid-form">
                <label for="reddit-token">
                    <a class="icon icon-category-auth"></a>
                    {{ t('reddit', 'Reddit access token') }}
                </label>
                <input id="reddit-token" type="password" v-model="state.token" @input="onInput"
                    :readonly="readonly"
                    @focus="readonly = false"
                    :placeholder="t('reddit', 'Get it with OAuth')" />
                <button id="reddit-oauth" v-if="showOAuth" @click="onOAuthClick">
                    <span class="icon icon-external"/>
                    {{ t('reddit', 'Get access with OAuth') }}
                </button>
            </div>
    </div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl, imagePath } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
    name: 'PersonalSettings',

    props: [],
    components: {
    },

    mounted() {
        const paramString = window.location.search.substr(1)
        const urlParams = new URLSearchParams(paramString)
        const rdToken = urlParams.get('redditToken')
        if (rdToken === 'success') {
            showSuccess(t('reddit', 'Reddit OAuth access token successfully retrieved!'))
        } else if (rdToken === 'error') {
            showError(t('reddit', 'Reddit OAuth error:') + ' ' + urlParams.get('message'))
        }
    },

    data() {
        return {
            state: loadState('reddit', 'user-config'),
            readonly: true,
        }
    },

    watch: {
    },

    computed: {
        showOAuth() {
            return this.state.client_id && this.state.client_secret
        },
    },

    methods: {
        onInput() {
            const that = this
            delay(function() {
                that.saveOptions()
            }, 2000)()
        },
        saveOptions() {
            const req = {
                values: {
                    token: this.state.token
                }
            }
            const url = generateUrl('/apps/reddit/config')
            axios.put(url, req)
                .then(function (response) {
                    showSuccess(t('reddit', 'Reddit options saved.'))
                })
                .catch(function (error) {
                    showError(t('reddit', 'Failed to save Reddit options') +
                        ': ' + error.response.request.responseText
                    )
                })
                .then(function () {
                })
        },
        onOAuthClick() {
            const redirect_endpoint = generateUrl('/apps/reddit/oauth-redirect')
            const redirect_uri = OC.getProtocol() + '://' + OC.getHostName() + redirect_endpoint
            const oauth_state = Math.random().toString(36).substring(3)
            const request_url = 'https://www.reddit.com/api/v1/authorize?client_id=' + encodeURIComponent(this.state.client_id) +
                '&redirect_uri=' + encodeURIComponent(redirect_uri) +
                '&state=' + encodeURIComponent(oauth_state) +
                '&response_type=code' +
                '&duration=permanent' +
                '&scope=' + encodeURIComponent('identity history mysubreddits privatemessages read wikiread')

            const req = {
                values: {
                    oauth_state: oauth_state,
                }
            }
            const url = generateUrl('/apps/reddit/config')
            axios.put(url, req)
                .then(function (response) {
                    window.location.replace(request_url)
                })
                .catch(function (error) {
                    showError(t('reddit', 'Failed to save Reddit OAuth state') +
                        ': ' + error.response.request.responseText
                    )
                })
                .then(function () {
                })
        }
    }
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
