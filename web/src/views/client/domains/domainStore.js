import { defineStore } from 'pinia';

export const domainStore = defineStore('DomainStore', {
  state: () => ({}),
  getters: {},
  actions: {
    fetchDomains(queryParams) {
        return new Promise((resolve, reject) => {
            useApi(createUrl('https://api-ds.bitemybytes.com/api/v1/client/fetchDomains', queryParams))
            .then(response => resolve(response))
            .catch(error => reject(error));
        });
    },

    addDomain(formData) {
      return new Promise((resolve, reject) => {
        $api('https://api-ds.bitemybytes.com/api/v1/client/addDomain', {
          method: 'POST',
          body: formData,
        }).then(response => resolve(response))
        .catch(error => reject(error));
      });
    },

    
   
  },
});
