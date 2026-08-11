import { appFeatures } from '@/utils/features'

export default [
  {
    title: 'Dashboard',
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
        title: 'My Tickets',
        to: { name: 'second-page' },
        icon: 'check-square',
        role: 'client',
        class: '',
      },
    ]
    : []),

  {
    title: 'My Services',
    to: { name: 'client-services' },
    icon: 'briefcase',
    role: 'client',
    class: '',
  },
  {
    title: 'Billing',
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
        title: 'My Websites',
        to: { name: 'client-domains' },
        icon: 'credit-card',
        role: 'client',
        class: '',
      },
      {
        title: 'Threat Activity',
        to: { name: 'client-alarm-alerts' },
        icon: 'alert-octagon',
        role: 'client',
        class: '',
      },
    ]
    : []),

  {
    title: 'Account',
    to: { name: 'client-account' },
    icon: 'user',
    role: 'client',
    class: ''
  },

  {
    title: 'Preferences',
    to: { name: 'client-preferences' },
    icon: 'settings',
    role: 'client',
    class: 'last-item'
  },


  // Agent

  {
    title: 'Dashboard',
    to: { name: 'agent' },
    icon: 'zap',
    role: 'agent',
    class: '',
  },

  // Security module (dormant)
  ...(appFeatures.security
    ? [
      {
        title: 'Alarm Alerts',
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
        title: 'My Tickets',
        to: { name: 'agent-tickets' },
        icon: 'check-square',
        role: 'agent',
        class: '',
      },
    ]
    : []),

  {
    title: 'Notifications',
    // to: { name: 'agent-notifications' },
    icon: 'bell',
    role: 'agent',
    class: '',
  },

  {
    title: 'Account',
    to: { name: 'agent-account' },
    icon: 'user',
    role: 'agent',
    class: '',
  },
  {
    title: 'Settings',
    to: { name: 'agent-preferences' },
    icon: 'settings',
    role: 'agent',
    class: 'last-item'
  },



  // Admin


  {
    title: 'Dashboard',
    to: { name: 'admin' },
    icon: 'zap',
    role: 'admin',
    class: '',
  },
  {
    title: 'Clients',
    to: { name: 'admin-clients' },
    icon: 'users',
    role: 'admin',
    class: '',
  },
  {
    title: 'Agents',
    to: { name: 'admin-agents' },
    icon: 'clipboard',
    role: 'admin',
    class: '',
  },

  {
    title: 'Admins',
    to: { name: 'admin-admins' },
    icon: 'hard-drive',
    role: 'admin',
    class: '',
  },

  // Tickets (not built) — see the client entry above.
  ...(appFeatures.tickets
    ? [
      {
        title: 'Tickets',
        to: { name: 'admin-tickets' },
        icon: 'check-square',
        role: 'admin',
        class: '',
      },
    ]
    : []),
  {
    title: 'Invoices',
    to: { name: 'admin-invoices' },
    icon: 'file',
    role: 'admin',
    class: '',
  },
  {
    title: 'Payments',
    to: { name: 'admin-payments' },
    icon: 'credit-card',
    role: 'admin',
    class: '',
  },
  {
    title: 'Reconciliation',
    to: { name: 'admin-payments-reconciliation' },
    icon: 'building-bank',
    role: 'admin',
    class: '',
  },
  {
    title: 'Services',
    to: { name: 'admin-services' },
    icon: 'briefcase',
    role: 'admin',
    class: '',
  },
  {
    title: 'Orders',
    to: { name: 'admin-orders' },
    icon: 'shopping-cart',
    role: 'admin',
    class: '',
  },
  {
    title: 'Notifications & Reminders',
    to: { name: 'admin-notifications-reminders' },
    icon: 'bell',
    role: 'admin',
    class: '',
  },
  {
    title: 'Reports',
    to: { name: 'admin-reports-tab', params: { tab: 'users' } },
    icon: 'pie-chart',
    role: 'admin',
    class: '',
  },
  {
    title: 'Content',
    // to: { name: 'admin-content' },
    icon: 'edit',
    role: 'admin',
    class: '',
  },
  {
    title: 'Settings',
    to: { name: 'admin-preferences' },
    icon: 'settings',
    role: 'admin',
    class: 'last-item'
  },
]
