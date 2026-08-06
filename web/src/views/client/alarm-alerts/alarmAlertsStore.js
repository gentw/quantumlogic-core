import { defineStore } from 'pinia';

export const alarmAlertsStore = defineStore('ClientAlarms', {
  state: () => ({}),
  getters: {},
  actions: {
    fetchAlarmLogs(alarmId) {
        return new Promise(async(resolve, reject) => {
            await $api('https://api-ds.bitemybytes.com/api/v1/client/alarm/'+alarmId+'/logs', 
            {
                method: 'POST'
            }
            )
            .then(response => resolve(response))
            .catch(error => reject(error));
        });
    },

    fetchAlarms(queryParams) {
        return new Promise((resolve, reject) => {
            useApi(createUrl('https://api-ds.bitemybytes.com/api/v1/fetchAlarms', queryParams))
            .then(response => resolve(response))
            .catch(error => reject(error));
        });
    },

    async respondToAlarm(alarmId, bodyReq) {
        return new Promise(async(resolve, reject) => {
            await $api('https://api-ds.bitemybytes.com/api/v1/alarm/'+alarmId+'/respondToAlarm', 
            {
                method: 'POST',
                body: bodyReq
            }
            )
            .then(response => resolve(response))
            .catch(error => reject(error));
        });
    },


  },
});
