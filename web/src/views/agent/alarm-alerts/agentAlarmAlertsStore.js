import { defineStore } from 'pinia';

export const agentAlarmAlertsStore = defineStore('AgentAlarms', {
  state: () => ({}),
  getters: {},
  actions: {
    fetchAlarms(queryParams) {
      return new Promise((resolve, reject) => {
        useApi(createUrl('https://api-ds.bitemybytes.com/api/v1/fetchAlarmsForAgents', queryParams))
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

    async changeStatusByAgent(alarmId, bodyReq) {
      return new Promise(async(resolve, reject) => {
        await $api('https://api-ds.bitemybytes.com/api/v1/alarm/'+alarmId+'/changeStatusByAgent', 
        {
            method: 'POST',
            body: bodyReq
        }
        )
        .then(response => resolve(response))
        .catch(error => reject(error));
    });
    }


  },
});
