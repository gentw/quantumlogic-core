import { deepMerge } from '@antfu/utils'
import { useI18n } from 'vue-i18n'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components/VBtn'
import { createVueI18nAdapter } from 'vuetify/locale/adapters/vue-i18n'
import defaults from './defaults'
import { icons } from './icons'
import { staticPrimaryColor, staticPrimaryDarkenColor, themes } from './theme'
import { i18n } from '@/plugins/0.i18n'
import { themeConfig } from '@themeConfig'

// Styles
import { cookieRef } from '@/@layouts/stores/config'
import '@core/scss/template/libs/vuetify/index.scss'
import 'vuetify/styles'

export default function (app) {
  const cookieThemeValues = {
    defaultTheme: resolveVuetifyTheme(themeConfig.app.theme),
    themes: {
      light: {
        colors: {
          'primary': cookieRef('lightThemePrimaryColor', staticPrimaryColor).value,
          'primary-darken-1': cookieRef('lightThemePrimaryDarkenColor', staticPrimaryDarkenColor).value,
        },
      },
      dark: {
        colors: {
          'primary': cookieRef('darkThemePrimaryColor', staticPrimaryColor).value,
          'primary-darken-1': cookieRef('darkThemePrimaryDarkenColor', staticPrimaryDarkenColor).value,
        },
      },
    },
  }

  const optionTheme = deepMerge({ themes }, cookieThemeValues)

  const vuetify = createVuetify({
    aliases: {
      IconBtn: VBtn,
    },
    defaults,
    icons,
    theme: optionTheme,

    // Vuetify keeps its own message catalogue for the strings it renders itself
    // (data-table footers, pagination, pickers). Routing it through the same
    // vue-i18n instance means switching language in the navbar moves both, and
    // there is no second locale to keep in step by hand.
    //
    // The adapter must sit under `adapter`. Passed as the whole `locale` object
    // it is silently ignored — createLocale() only honours it when
    // `options.adapter` is set, and otherwise builds Vuetify's own adapter with
    // `ref({ en, ...options.messages })`. Spreading our messages ComputedRef
    // there copies its `__v_isRef: true` onto a plain object, so ref() hands the
    // object straight back instead of wrapping it, `.value` is undefined, and
    // every $vuetify lookup dies on `messages.value[locale]`.
    locale: {
      adapter: createVueI18nAdapter({ i18n, useI18n }),
    },
  })

  app.use(vuetify)
}
