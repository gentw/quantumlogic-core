/**
 * Optional feature modules.
 *
 * Mirrors `api/config/features.php` — keep the two in sync, since hiding the UI
 * without disabling the API (or the reverse) leaves a half-available module.
 *
 * @type {{ security: boolean }}
 */
export const appFeatures = {
  /**
   * Origin protection: domains, incoming alarms, threat logs. Dormant.
   * See docs/modules/security/README.md before switching it on.
   */
  security: import.meta.env.VITE_FEATURE_SECURITY_MODULE === 'true',
}

/**
 * Route-name prefixes owned by the security module. The pages stay on disk and
 * `unplugin-vue-router` still registers them, so the router guard is what makes
 * them unreachable while the module is off.
 *
 * @type {string[]}
 */
export const SECURITY_ROUTE_PREFIXES = [
  'client-domains',
  'client-alarm-alerts',
  'agent-alarm-alerts',
]

/**
 * Whether a route belongs to a module that is currently switched off.
 *
 * @param {string|symbol|null|undefined} routeName - `to.name` from a navigation.
 * @returns {boolean} true when the navigation should be blocked.
 */
export const isDisabledModuleRoute = routeName => {
  if (appFeatures.security)
    return false

  const name = routeName?.toString() ?? ''

  return SECURITY_ROUTE_PREFIXES.some(prefix => name.startsWith(prefix))
}
