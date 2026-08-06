/**
 * Stripe.js loader. Stripe requires loading from js.stripe.com at runtime
 * (bundling is not permitted — @stripe/stripe-js is only a loader shim), so
 * a script tag is injected once and the Stripe instance memoised.
 */

let stripePromise = null

/** @returns {Promise<object|null>} the Stripe instance, or null without a key */
export const loadStripe = () => {
  if (stripePromise) return stripePromise

  const key = import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY
  if (!key) {
    console.error('VITE_STRIPE_PUBLISHABLE_KEY is not set — card payments are unavailable.')

    return Promise.resolve(null)
  }

  stripePromise = new Promise((resolve, reject) => {
    if (window.Stripe) {
      resolve(window.Stripe(key))

      return
    }

    const script = document.createElement('script')

    script.src = 'https://js.stripe.com/v3/'
    script.onload = () => resolve(window.Stripe(key))
    script.onerror = () => reject(new Error('Failed to load Stripe.js'))
    document.head.appendChild(script)
  })

  return stripePromise
}
