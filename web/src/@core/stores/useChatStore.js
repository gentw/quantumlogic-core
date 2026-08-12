import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useChatStore = defineStore('chat', () => {
  // const participants = ref([]);
  // const titleImageUrl = "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTIIk7VV8QuFU22XYyQHgDknNwdtqkMnxiS1Q&s";
  const messageList = ref([]);
  const participants = ref([]);
  // const newMessagesCount = ref(0);
  const isChatOpen = ref(false);
 
    const openChat = () => {
      isChatOpen.value = true;
      // newMessagesCount.value = 0;
    };
  
    const closeChat = () => {
      isChatOpen.value = false;
    };
  
    const openClientChat = () => {
      isChatOpen.value = true;
      fetchClientMessages();
    }

    const addToMessageList = (message) => {
      // console.log();
      messageList.value = [...messageList.value, message];
    };

    const fetchClientMessages = async () => {

      try {
        const response = await $api('/v1/chat/fetchMessagesByClient', {
          method: 'POST',
          body: {},
          headers: {
            'Content-Type': 'application/json',
          },
        });

        if (response.messages.length > 0) {
          messageList.value = []
          response.messages.forEach(message => {
            if(message.user_id != useCookie("userData").value.id) {
              addToMessageList({
                  author: message.user_id,
                  type: "text",
                  data: {
                      text: message.message,
                      status: 'received'
                  },
              });
            } else {
              addToMessageList({
                author: 'me',
                type: "text",
                data: { 
                  text: message.message,
                  status: 'sent'
                },
              });
            }
          });
          // newMessagesCount.value = isChatOpen.value
          //   ? newMessagesCount.value
          //   : newMessagesCount.value + 1;
        } 
      } catch (error) {
        console.error("Error fetching messages:", error);
      }
    }
    // // Subscribe to messages when the component is mounted
    // onMounted(() => {
    //   subscribeMessages();
    // });
  
    return {
      openChat,
      isChatOpen,
      closeChat,      
      messageList,
      participants,
      openClientChat
    };
  });
  