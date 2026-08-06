import App from '@/App.vue';
import { registerPlugins } from '@core/utils/plugins';
import { createApp } from 'vue';
import ToastPlugin from 'vue-toast-notification';
import Chat from 'vue3-beautiful-chat';
// Import one of the available themes
//import 'vue-toast-notification/dist/theme-default.css';
import 'vue-toast-notification/dist/theme-bootstrap.css';

// Styles
import '@core/scss/template/index.scss';
import '@styles/styles.scss';

// Create vue app
const app = createApp(App)

app.use(Chat);
app.use(ToastPlugin);
// Register plugins
registerPlugins(app)

// Mount vue app
app.mount('#app')
