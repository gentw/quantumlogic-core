export default [
  {
    title: 'Dashboard',
    to: { name: 'client' },
    icon: 'zap',
    role: 'client',
    class: '',
  },
  {
    title: 'My Tickets',
    to: { name: 'second-page' },
    icon: 'check-square',
    role: 'client',
    class: '',
  },

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
    title: 'Paneli',
    to: { name: 'agent' },
    icon: 'zap',
    role: 'agent',
    class: '',
  },
  {
    title: 'Alertet e Alarmit',
    to: { name: 'agent-alarm-alerts' },
    icon: 'alert-octagon',
    role: 'agent',
    class: '',
  },
  {
    title: 'Tiketat e mia',
    // to: { name: 'agent-tickets' },
    icon: 'check-square',
    role: 'agent',
    class: '',
  },

  {
    title: 'Njoftimet',
    // to: { name: 'agent-notifications' },
    icon: 'bell',
    role: 'agent',
    class: '',
  },

  {
    title: 'Llogaria',
    to: { name: 'agent-account' },
    icon: 'user',
    role: 'agent',
    class: '',
  },
  {
    title: 'Cilesimet',
    to: { name: 'agent-preferences' },
    icon: 'settings',
    role: 'agent',
    class: 'last-item'
  },



  // Admin


  {
    title: 'Paneli',
    to: { name: 'admin' },
    icon: 'zap',
    role: 'admin',
    class: '',
  },
  {
    title: 'Klientët',
    to: { name: 'admin-clients' },
    icon: 'users',
    role: 'admin',
    class: '',
  },
  {
    title: 'Agjentët',
    to: { name: 'admin-agents' },
    icon: 'clipboard',
    role: 'admin',
    class: '',
  },

  {
    title: 'Administratorët',
    to: { name: 'admin-admins' },
    icon: 'hard-drive',
    role: 'admin',
    class: '',
  },

  {
    title: 'Tiketat',
    // to: { name: 'agent-account' },
    icon: 'check-square',
    role: 'admin',
    class: '',
  },
  {
    title: 'Faturat',
    to: { name: 'admin-invoices' },
    icon: 'file',
    role: 'admin',
    class: '',
  },
  {
    title: 'Transaksionet',
    // to: { name: 'agent-account' },
    icon: 'credit-card',
    role: 'admin',
    class: '',
  },
  {
    title: 'Njoftimet & Rikujtimet',
    to: { name: 'admin-notifications-reminders' },
    icon: 'bell',
    role: 'admin',
    class: '',
  },
  {
    title: 'Raportet',
    to: { name: 'admin-reports-tab', params: { tab: 'users' } },
    icon: 'pie-chart',
    role: 'admin',
    class: '',
  },
  {
    title: 'Përmbajtja',
    // to: { name: 'agent-account' },
    icon: 'edit',
    role: 'admin',
    class: '',
  },
  {
    title: 'Cilesimet',
    to: { name: 'admin-preferences' },
    icon: 'settings',
    role: 'admin',
    class: 'last-item'
  },
]
