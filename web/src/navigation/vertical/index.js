import { appFeatures } from '@/utils/features'

export default [
  {
    title: 'nav.dashboard',
    to: { name: 'client' },
    icon: 'zap',
    role: 'client',
    class: '',
  },

  // Tickets (not built) — no model, migration or API behind these yet. The client
  // entry points at the `second-page` placeholder; the agent and admin entries below
  // have no destination at all. Flagged off so the nav only offers what works.
  ...(appFeatures.tickets
    ? [
      {
        title: 'nav.myTickets',
        to: { name: 'second-page' },
        icon: 'check-square',
        role: 'client',
        class: '',
      },
    ]
    : []),

  {
    title: 'nav.myServices',
    to: { name: 'client-services' },
    icon: 'briefcase',
    role: 'client',
    class: '',
  },
  {
    title: 'nav.billing',
    to: { name: 'client-billing' },
    icon: 'credit-card',
    role: 'client',
    class: '',
  },

  // Security module (dormant) — definitions stay here so re-enabling is one flag.
  // See docs/modules/security/README.md
  ...(appFeatures.security
    ? [
      {
        title: 'nav.myWebsites',
        to: { name: 'client-domains' },
        icon: 'credit-card',
        role: 'client',
        class: '',
      },
      {
        title: 'nav.threatActivity',
        to: { name: 'client-alarm-alerts' },
        icon: 'alert-octagon',
        role: 'client',
        class: '',
      },
    ]
    : []),

  {
    title: 'nav.account',
    to: { name: 'client-account' },
    icon: 'user',
    role: 'client',
    class: ''
  },

  {
    title: 'nav.preferences',
    to: { name: 'client-preferences' },
    icon: 'settings',
    role: 'client',
    class: 'last-item'
  },


  // Agent

  {
    title: 'nav.dashboard',
    to: { name: 'agent' },
    icon: 'zap',
    role: 'agent',
    class: '',
  },

  // Security module (dormant)
  ...(appFeatures.security
    ? [
      {
        title: 'nav.threatActivity',
        to: { name: 'agent-alarm-alerts' },
        icon: 'alert-octagon',
        role: 'agent',
        class: '',
      },
    ]
    : []),

  // Tickets (not built) — see the client entry above.
  ...(appFeatures.tickets
    ? [
      {
        title: 'nav.myTickets',
        to: { name: 'agent-tickets' },
        icon: 'check-square',
        role: 'agent',
        class: '',
      },
    ]
    : []),

  {
    title: 'nav.notifications',
    // to: { name: 'agent-notifications' },
    icon: 'bell',
    role: 'agent',
    class: '',
  },

  {
    title: 'nav.account',
    to: { name: 'agent-account' },
    icon: 'user',
    role: 'agent',
    class: '',
  },
  {
    title: 'nav.settings',
    to: { name: 'agent-preferences' },
    icon: 'settings',
    role: 'agent',
    class: 'last-item'
  },



  // Admin


  {
    title: 'nav.dashboard',
    to: { name: 'admin' },
    icon: 'zap',
    role: 'admin',
    class: '',
  },
  {
    title: 'nav.clients',
    to: { name: 'admin-clients' },
    icon: 'users',
    role: 'admin',
    class: '',
  },
  {
    title: 'nav.agents',
    to: { name: 'admin-agents' },
    icon: 'clipboard',
    role: 'admin',
    class: '',
  },

  {
    title: 'nav.admins',
    to: { name: 'admin-admins' },
    icon: 'hard-drive',
    role: 'admin',
    class: '',
  },

  // Tickets (not built) — see the client entry above.
  ...(appFeatures.tickets
    ? [
      {
        title: 'nav.tickets',
        to: { name: 'admin-tickets' },
        icon: 'check-square',
        role: 'admin',
        class: '',
      },
    ]
    : []),
  {
    title: 'nav.invoices',
    to: { name: 'admin-invoices' },
    icon: 'file',
    role: 'admin',
    class: '',
  },
  {
    title: 'nav.payments',
    to: { name: 'admin-payments' },
    icon: 'credit-card',
    role: 'admin',
    class: '',
  },
  {
    title: 'nav.reconciliation',
    to: { name: 'admin-payments-reconciliation' },
    icon: 'building-bank',
    role: 'admin',
    class: '',
  },
  {
    title: 'nav.services',
    to: { name: 'admin-services' },
    icon: 'briefcase',
    role: 'admin',
    class: '',
  },
  {
    title: 'nav.orders',
    to: { name: 'admin-orders' },
    icon: 'shopping-cart',
    role: 'admin',
    class: '',
  },
  {
    title: 'nav.notificationsReminders',
    to: { name: 'admin-notifications-reminders' },
    icon: 'bell',
    role: 'admin',
    class: '',
  },
  {
    title: 'nav.reports',
    to: { name: 'admin-reports-tab', params: { tab: 'users' } },
    icon: 'pie-chart',
    role: 'admin',
    class: '',
  },
  {
    title: 'nav.content',
    // to: { name: 'admin-content' },
    icon: 'edit',
    role: 'admin',
    class: '',
  },
  {
    title: 'nav.settings',
    to: { name: 'admin-preferences' },
    icon: 'settings',
    role: 'admin',
    class: 'last-item'
  },
]
