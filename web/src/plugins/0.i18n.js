import { createI18n } from 'vue-i18n'
import { de as vuetifyDe, en as vuetifyEn } from 'vuetify/locale'
import de from './i18n/locales/de.json'
import en from './i18n/locales/en.json'
import sq from './i18n/locales/sq.json'

/**
 * Numbered 0 so it registers before the router: guards and layouts read the
 * active locale, and `@core/initCore.js` calls `useI18n` during setup.
 */

const FALLBACK = 'en'

/**
 * Vuetify renders its own strings — data-table footers, pagination, pickers —
 * from a `$vuetify` namespace, which the locale adapter in plugins/vuetify
 * reads out of this same catalogue.
 *
 * Vuetify ships no Albanian pack, so `sq` borrows the English one. Those are
 * component chrome ("Items per page"), not application copy; the alternative is
 * raw keys on every table footer.
 */
const messages = {
  en: { ...en, $vuetify: vuetifyEn },
  de: { ...de, $vuetify: vuetifyDe },
  sq: { ...sq, $vuetify: vuetifyEn },
}

/**
 * Vuetify's own components (data-table footers, pickers) carry a separate
 * locale, and `de-AT` formats money as "1.234,56 €" where `en` does not — so
 * number and date formats are declared per locale rather than left to defaults.
 */
const numberFormats = {
  en: { currency: { style: 'currency', currency: 'EUR' } },
  de: { currency: { style: 'currency', currency: 'EUR' } },
  sq: { currency: { style: 'currency', currency: 'EUR' } },
}

const datetimeFormats = Object.fromEntries(
  Object.keys(messages).map(locale => [
    locale,
    {
      short: { year: 'numeric', month: '2-digit', day: '2-digit' },
      long: { year: 'numeric', month: 'long', day: 'numeric' },
    },
  ]),
)

/**
 * The locale cached from the last visit. `@core/initCore.js` writes this cookie
 * whenever the navbar switcher changes language, so it survives a reload and
 * avoids a flash of the wrong language before the server answers.
 *
 * @returns {string|null}
 */
const cachedLocale = () => {
  const match = document.cookie.match(/(?:^|;\s*)[^=]*-language=([^;]*)/)
  const value = match ? decodeURIComponent(match[1]).replace(/^"|"$/g, '') : null

  return Object.keys(messages).includes(value) ? value : null
}

export const i18n = createI18n({
  // Composition API mode. `@core/components/I18n.vue` calls useI18n(), which
  // needs legacy off.
  legacy: false,
  globalInjection: true,
  locale: cachedLocale() ?? FALLBACK,
  fallbackLocale: FALLBACK,
  messages,
  numberFormats,
  datetimeFormats,
})

/**
 * Ask the API which language this visitor should see and switch to it.
 *
 * The server is authoritative for signed-in users because it knows their saved
 * `users.locale`; for anonymous visitors it returns a guess from their IP. An
 * anonymous visitor who has already picked a language by hand keeps their
 * choice — there is nothing server-side to remember it for them.
 *
 * Failures are silent: the cached or fallback locale is already in place, and a
 * language lookup is not worth interrupting page load for.
 *
 * @returns {Promise<void>}
 */
let serverLocale = null

const syncFromServer = async () => {
  const signedIn = Boolean(useCookie('accessToken').value)

  if (!signedIn && cachedLocale()) return

  try {
    const { locale } = await $api('/v1/locale')

    if (locale && Object.keys(messages).includes(locale)) {
      serverLocale = locale
      i18n.global.locale.value = locale
    }
  } catch {
    // Keep whatever is already active.
  }
}

/**
 * Send a hand-picked language back to the account, so it follows the user to
 * their next device and reaches the mail the scheduler sends them.
 *
 * Skipped for anonymous visitors, who have nowhere to store it, and for the
 * value the server just handed us, which would be an echo.
 *
 * @returns {void}
 */
const persistOnChange = () => {
  watch(i18n.global.locale, async locale => {
    if (locale === serverLocale) return
    if (!useCookie('accessToken').value) return

    try {
      await $api('/v1/user/locale', { method: 'POST', body: { locale } })
      serverLocale = locale
    } catch {
      // The choice still applies for this session; it just is not remembered.
    }
  })
}

export default function (app) {
  app.use(i18n)
  syncFromServer()
  persistOnChange()
}
