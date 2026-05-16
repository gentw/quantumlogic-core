/**
 * Persistent trial-abuse fingerprint stored in localStorage.
 * Shared by `pricing.vue`, `pay-now.vue`, and `change-plan.vue` so the same
 * value is sent in `X-Trial-Fingerprint` on every trial-related call.
 *
 * @returns {{ get: () => string }} reader for the fingerprint
 */
export function useTrialFingerprint() {
  const KEY = 'trial_fp'

  const generate = () => {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
      return crypto.randomUUID()
    }

    return `fp-${Math.random().toString(36).slice(2)}-${Date.now()}`
  }

  const get = () => {
    let fp = localStorage.getItem(KEY)
    if (!fp) {
      fp = generate()
      localStorage.setItem(KEY, fp)
    }
    return fp
  }

  return { get }
}
