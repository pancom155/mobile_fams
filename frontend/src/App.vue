<template>
  <router-view />
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';
import { DatabaseService } from './services/DatabaseService';
import { Network } from '@capacitor/network';
import { useQuasar } from 'quasar';
import { API_BASE_URL } from './config/api';

const $q = useQuasar();

let networkListener = null;

onMounted(async () => {
  console.log('App started. API Base URL:', API_BASE_URL);
  
  // Initialize SQLite Database
  await DatabaseService.initDb();

  // Listen for network status changes
  networkListener = await Network.addListener('networkStatusChange', async (status) => {
    console.log('Network status changed:', status);
    if (status.connected) {
      $q.notify({
        type: 'positive',
        message: 'Online',
        caption: 'Syncing data with server...',
        position: 'top',
        timeout: 2000
      });
      await DatabaseService.syncAll();
    } else {
      $q.notify({
        type: 'warning',
        message: 'Offline',
        caption: 'Using local storage',
        position: 'top',
        timeout: 2000
      });
    }
  });
});

onUnmounted(() => {
  if (networkListener) {
    networkListener.remove();
  }
});
</script>

