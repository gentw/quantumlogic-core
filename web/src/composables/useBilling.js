/**
 * Shared helpers for the Billing & Payments pages: formatting, status
 * presentation, and the client billing API calls. Auto-imported (composables
 * are in the unplugin-auto-import dirs list).
 */

/** @param {number|string|null|undefined} value @returns {string} e.g. "1.234,56 €" */
export const formatMoney = value => {
  const number = Number(value ?? 0)

  return new Intl.NumberFormat('de-AT', { style: 'currency', currency: 'EUR' }).format(number)
}

/** @param {string|null|undefined} value ISO date @returns {string} dd.mm.yyyy or em dash */
export const formatDate = value => {
  if (!value) return '—'

  return new Date(value).toLocaleDateString('de-AT')
}

/**
 * Vuetify color + label per invoice status. `is_overdue` is derived
 * server-side and overrides the plain "sent" presentation.
 *
 * @param {{ status: string, is_overdue?: boolean }} invoice
 * @returns {{ color: string, label: string }}
 */
export const invoiceStatusChip = invoice => {
  if (invoice.is_overdue)
    return { color: 'error', label: 'Overdue' }

  const map = {
    draft: { color: 'secondary', label: 'Draft' },
    sent: { color: 'info', label: 'Open' },
    awaiting_confirmation: { color: 'warning', label: 'In review' },
    paid: { color: 'success', label: 'Paid' },
    cancelled: { color: 'secondary', label: 'Cancelled' },
    unpaid: { color: 'info', label: 'Open' },
    failed: { color: 'error', label: 'Failed' },
  }

  return map[invoice.status] ?? { color: 'secondary', label: invoice.status }
}

/** @param {string|null|undefined} type @returns {string} human label for an invoice type */
export const invoiceTypeLabel = type => {
  const map = {
    deposit: 'Deposit',
    milestone: 'Milestone',
    balance: 'Balance',
    one_off: 'Invoice',
    recurring: 'Recurring',
    credit_note: 'Credit note',
  }

  return map[type] ?? 'Invoice'
}

/** Client billing API surface — keep the endpoints in one place. */
export const useBillingApi = () => ({
  summary: () => $api('/v1/client/billing/summary'),

  /** @param {{ page?: number, perPage?: number, status?: string, search?: string }} params */
  invoices: ({ page = 1, perPage = 10, status = '', search = '' } = {}) =>
    $api('/v1/client/billing/invoices', { query: { page, per_page: perPage, status: status || undefined, search: search || undefined } }),

  /**
   * A single invoice. The API returns an InvoiceResource, and resource wrapping
   * is on, so the payload arrives as { data: {...} } — unwrapped here so callers
   * get the invoice itself. (The paginated list above keeps its envelope: its
   * meta/links drive server-side pagination.)
   *
   * @param {number|string} id @returns {Promise<object>}
   */
  invoice: async id => (await $api(`/v1/client/billing/invoices/${id}`)).data,

  bankDetails: id => $api(`/v1/client/invoices/${id}/bank-details`),

  /** @param {number} id @param {File} file @param {string} note */
  uploadProof: (id, file, note) => {
    const body = new FormData()

    body.append('file', file)
    if (note) body.append('note', note)

    return $api(`/v1/client/invoices/${id}/payment-proof`, { method: 'POST', body })
  },

  stripeIntent: (id, amount) => $api(`/v1/client/invoices/${id}/stripe/intent`, { method: 'POST', body: amount ? { amount } : {} }),

  paypalCreate: (id, amount) => $api(`/v1/client/invoices/${id}/paypal/create`, { method: 'POST', body: amount ? { amount } : {} }),

  paymentStatus: id => $api(`/v1/client/payments/${id}/status`),

  /**
   * The client's orders and recurring plans — "My Services", and the services
   * card on the dashboard.
   *
   * @returns {Promise<{ orders: object[], recurring_plans: object[] }>}
   */
  services: () => $api('/v1/client/services'),
})

/**
 * Whole days from today until an ISO date. Negative once the date has passed.
 * Both sides are floored to midnight so "today" is 0 rather than a fraction.
 *
 * @param {string|null|undefined} value ISO date
 * @returns {number|null} null when there is no date to measure against
 */
export const daysUntil = value => {
  if (!value) return null

  const target = new Date(value)
  if (Number.isNaN(target.getTime())) return null

  target.setHours(0, 0, 0, 0)

  const today = new Date()

  today.setHours(0, 0, 0, 0)

  return Math.round((target - today) / 86400000)
}

/**
 * Due-date wording for the next invoice: "Due in 5 days", "Due today",
 * "12 days overdue".
 *
 * @param {string|null|undefined} dueAt ISO date
 * @returns {string}
 */
export const dueLabel = dueAt => {
  const days = daysUntil(dueAt)

  if (days === null) return 'No due date'
  if (days === 0) return 'Due today'
  if (days === 1) return 'Due tomorrow'
  if (days > 1) return `Due in ${days} days`
  if (days === -1) return '1 day overdue'

  return `${Math.abs(days)} days overdue`
}
