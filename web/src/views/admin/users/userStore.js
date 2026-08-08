import { defineStore } from 'pinia';

export const userStore = defineStore('UserStore', {
  state: () => ({}),
  getters: {},
  actions: {
    fetchClients(queryParams) {
        return new Promise((resolve, reject) => {
            useApi(createUrl('https://api.quantumlogic.at/api/v1/client/fetchClients', queryParams))
            .then(response => resolve(response))
            .catch(error => reject(error));
        });
    },
    registerClientFromAdmin(formData) {
      return new Promise((resolve, reject) => {
        $api('https://api.quantumlogic.at/api/v1/admin/registerNewClient', {
          method: 'POST',
          body: formData,
        }).then(response => resolve(response))
        .catch(error => reject(error));
      });
    },

    
    
    // AGENTS
    fetchAgents(queryParams) {
      return new Promise((resolve, reject) => {
          useApi(createUrl('https://api.quantumlogic.at/api/v1/admin/fetchAgents', queryParams))
          .then(response => resolve(response))
          .catch(error => reject(error));
      });
    },
    registerAgentFromAdmin(formData) {
      return new Promise((resolve, reject) => {
        $api('https://api.quantumlogic.at/api/v1/admin/registerNewAgent', {
          method: 'POST',
          body: formData,
        }).then(response => resolve(response))
        .catch(error => reject(error));
      });
    },
    
    deleteAgentFromAdmin(agentId) {
      return new Promise((resolve, reject) => {
        $api('https://api.quantumlogic.at/api/v1/admin/deleteAgent/'+agentId, {
          method: 'GET'
        }).then(response => resolve(response))
        .catch(error => reject(error));
      });
    },


    // SUBADMINS
    fetchAdmins(queryParams) {
      return new Promise((resolve, reject) => {
          useApi(createUrl('https://api.quantumlogic.at/api/v1/admin/fetchAdmins', queryParams))
          .then(response => resolve(response))
          .catch(error => reject(error));
      });
    },
    registerAdminFromAdmin(formData) {
      return new Promise((resolve, reject) => {
        $api('https://api.quantumlogic.at/api/v1/admin/registerNewAdmin', {
          method: 'POST',
          body: formData,
        }).then(response => resolve(response))
        .catch(error => reject(error));
      });
    },
    
    deleteAdminFromAdmin(agentId) {
      return new Promise((resolve, reject) => {
        $api('https://api.quantumlogic.at/api/v1/admin/deleteAdmin/'+agentId, {
          method: 'GET'
        }).then(response => resolve(response))
        .catch(error => reject(error));
      });
    },



    // GENERAL
    blockUnblockUser(formData) {
      return new Promise((resolve, reject) => {
        $api('https://api.quantumlogic.at/api/v1/admin/blockUnblockUser', {
          method: 'POST',
          body: formData,
        }).then(response => resolve(response))
        .catch(error => reject(error));
      });
    },

    // /admin/deactivateUser
    deactivateUser(formData) {
      return new Promise((resolve, reject) => {
        $api('https://api.quantumlogic.at/api/v1/admin/deactivateUser', {
          method: 'POST',
          body: formData,
        }).then(response => resolve(response))
        .catch(error => reject(error));
      });
    },
  },
});
