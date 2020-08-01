<template>
    <DashboardWidget :items="items"
        :showMore="true"
        @moreClicked="onMoreClick"
        :loading="state === 'loading'">
        <template v-slot:empty-content>
            <div v-if="state === 'no-token'">
                <a :href="settingsUrl">
                    {{ t('reddit', 'Click here to configure the access to your Reddit account.')}}
                </a>
            </div>
            <div v-else-if="state === 'error'">
                <a :href="settingsUrl">
                    {{ t('reddit', 'Incorrect access token.') }}
                    {{ t('reddit', 'Click here to configure the access to your Reddit account.')}}
                </a>
            </div>
            <div v-else-if="state === 'ok'">
                {{ t('reddit', 'Nothing to show') }}
            </div>
        </template>
    </DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { getLocale } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { DashboardWidget } from '@nextcloud/vue-dashboard'

export default {
    name: 'Dashboard',

    props: [],
    components: {
        DashboardWidget,
    },

    beforeMount() {
        this.fetchNotifications()
        this.loop = setInterval(() => this.fetchNotifications(), 45000)
    },

    mounted() {
    },

    data() {
        return {
            notifications: [],
            // lastDate could be computed but we want to keep the value when first notification is removed
            // to avoid getting it again on next request
            lastDate: null,
            locale: getLocale(),
            loop: null,
            state: 'loading',
            settingsUrl: generateUrl('/settings/user/linked-accounts'),
            darkThemeColor: OCA.Accessibility.theme === 'dark' ? '181818' : 'ffffff',
            themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
        }
    },

    computed: {
        items() {
            return this.notifications.map((n) => {
                return {
                    id: n.id,
                    targetUrl: this.getNotificationTarget(n),
                    avatarUrl: this.getAvatarUrl(n),
                    //avatarUsername: '',
                    overlayIconUrl: this.getNotificationTypeImage(n),
                    mainText: n.subject,
                    subText: this.getSubline(n),
                }
            })
        },
    },

    methods: {
        fetchNotifications() {
            const req = {}
            if (this.lastDate) {
                req.params = {
                    since: this.lastDate
                }
            }
            axios.get(generateUrl('/apps/reddit/notifications'), req).then((response) => {
                this.processNotifications(response.data)
                this.state = 'ok'
            }).catch((error) => {
                clearInterval(this.loop)
                if (error.response && error.response.status === 400) {
                    this.state = 'no-token'
                } else if (error.response && error.response.status === 401) {
                    showError(t('reddit', 'Failed to get Reddit notifications.'))
                    this.state = 'error'
                } else {
                    // there was an error in notif processing
                    console.log(error)
                }
            })
        },
        processNotifications(newNotifications) {
            if (this.lastDate) {
                // just add those which are more recent than our most recent one
                let i = 0;
                while (i < newNotifications.length && this.lastDate < newNotifications[i].created_utc) {
                    i++
                }
                if (i > 0) {
                    const toAdd = this.filter(newNotifications.slice(0, i))
                    this.notifications = toAdd.concat(this.notifications)
                }
            } else {
                // first time we don't check the date
                this.notifications = this.filter(newNotifications)
            }
            // update lastDate manually (explained in data)
            const nbNotif = this.notifications.length
            this.lastDate = (nbNotif > 0) ? this.notifications[0].created_utc : null
        },
        filter(notifications) {
            return notifications
            // only keep the unread ones with specific reasons
            return notifications.filter((n) => {
                return (n.unread && ['assign', 'mention', 'review_requested'].includes(n.reason))
            })
        },
        onMoreClick() {
            const win = window.open('https://reddit.com', '_blank')
            win.focus()
        },
        getAvatarUrl(n) {
            return (n.author) ?
                    generateUrl('/apps/reddit/avatar?') + encodeURIComponent('username') + '=' + encodeURIComponent(n.author) :
                    ''
        },
        getNotificationTarget(n) {
            return 'https://www.reddit.com/message/messages/' + n.id
        },
        getSubline(n) {
            return '@' + n.author
        },
        getNotificationTypeImage(n) {
            if (n.notification_type === 'privatemessage') {
                return generateUrl('/svg/reddit/message?color=ffffff')
            } else if (n.type === 'post') {
                return generateUrl('/svg/reddit/post?color=ffffff')
            }
            return ''
        },
        getFormattedDate(n) {
            return moment(parseInt(n.created_utc) * 1000).locale(this.locale).format('LLL')
        },
    },
}
</script>

<style scoped lang="scss">
</style>
