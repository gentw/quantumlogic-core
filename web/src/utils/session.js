/**
 * One definition of what a logged-in session consists of, so ending one cannot
 * drift out of sync with starting one. Auto-imported (`src/utils/` is in the
 * unplugin-auto-import dirs list).
 */

/**
 * Cookies that make up a session. Everything the login and OTP flows set:
 * `pages/login.vue`, `pages/checkpoint.vue`, and the chat widget.
 *
 * `preferredCodeLanguage` is deliberately absent — it is a Vuexy UI preference,
 * not session state.
 *
 * @type {string[]}
 */
const SESSION_COOKIES = [
  'accessToken',
  'userData',
  'userAbilityRules',
  'isOtp',
  'phoneNo',
  'redirect_uri',
  'chatClientId',
  'chatChatId',
]

/**
 * localStorage keys holding data about the signed-in user.
 *
 * `trial_fp` is deliberately **not** here. It is the anti-abuse fingerprint from
 * `useTrialFingerprint`, and it is supposed to outlive the session — clearing it
 * on logout would let anyone reset their trial history by signing out.
 * Theme and layout keys are left alone too: those are device preferences.
 *
 * @type {string[]}
 */
const SESSION_STORAGE_KEYS = ['user', 'subscription']

/**
 * Forget the current session on this device.
 *
 * Cookies are removed by assigning `null` — `@core/composable/useCookie.js` only
 * emits the `maxAge: -1` delete header for `null`/`undefined`. Assigning `false`
 * writes a cookie whose value is the string "false", which reads as logged-out
 * but leaves the cookie in place.
 *
 * Does not call the API and never throws: the server round-trip is best-effort
 * and must not be able to strand a user in a half-signed-in state.
 *
 * @returns {void}
 */
export const clearSession = () => {
  SESSION_COOKIES.forEach(name => {
    useCookie(name).value = null
  })

  SESSION_STORAGE_KEYS.forEach(key => {
    localStorage.removeItem(key)
  })
}

/**
 * Whether the browser currently holds a usable session.
 *
 * @returns {boolean}
 */
export const hasSession = () => Boolean(useCookie('userData').value && useCookie('accessToken').value)
