/**
 * Optional feature modules.
 *
 * Mirrors `api/config/features.php` — keep the two in sync, since hiding the UI
 * without disabling the API (or the reverse) leaves a half-available module.
 *
 * @type {{ security: boolean, subscriptionPlans: boolean }}
 */
export const appFeatures = {
  /**
   * Origin protection: domains, incoming alarms, threat logs. Dormant.
   * See docs/modules/security/README.md before switching it on.
   */
  security: import.meta.env.VITE_FEATURE_SECURITY_MODULE === 'true',

  /**
   * SaaS plan-tier subscriptions: pricing, plans-billing, change-plan, trials.
   * Retired in favour of Billing & Payments.
   * See docs/modules/subscriptions/README.md before switching it on.
   */
  subscriptionPlans: import.meta.env.VITE_FEATURE_SUBSCRIPTION_PLANS === 'true',
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
 * Route-name prefixes owned by the retired subscription-plans module. Same
 * mechanism as the security prefixes above. Deliberately excludes
 * `client-invoice` itself — invoices are a live Billing & Payments concept;
 * only the plan-change flow under it belongs to the retired module.
 *
 * @type {string[]}
 */
export const SUBSCRIPTION_ROUTE_PREFIXES = [
  'client-pricing',
  'client-plans-billing',
  'client-invoice-change-plan',
]

/**
 * Whether a route belongs to a module that is currently switched off.
 *
 * @param {string|symbol|null|undefined} routeName - `to.name` from a navigation.
 * @returns {boolean} true when the navigation should be blocked.
 */
export const isDisabledModuleRoute = routeName => {
  const name = routeName?.toString() ?? ''

  const disabledPrefixes = [
    ...(appFeatures.security ? [] : SECURITY_ROUTE_PREFIXES),
    ...(appFeatures.subscriptionPlans ? [] : SUBSCRIPTION_ROUTE_PREFIXES),
  ]

  return disabledPrefixes.some(prefix => name.startsWith(prefix))
}
