<template>
  <q-layout view="lHh Lpr lFf">
    <q-header class="bg-white text-black" bordered>
      <q-toolbar>
        <q-btn flat round dense icon="arrow_back" @click="$router.push('/transactions')" />
        <q-toolbar-title class="text-weight-bold">New Transaction (v5)</q-toolbar-title>
      </q-toolbar>
    </q-header>

    <q-page-container>
      <q-page class="bg-grey-2">
        <div class="q-pa-md">
      <div class="q-mb-md">
        <label class="text-grey-8 q-mb-xs block">Date:</label>
        <q-input outlined v-model="date" readonly dense bg-color="grey-3" />
      </div>

      <div class="q-mb-md">
        <label class="text-grey-8 q-mb-xs block">Site:</label>
        <q-select 
          outlined 
          v-model="site" 
          :options="siteOptions" 
          option-label="name" 
          option-value="id" 
          emit-value 
          map-options 
          dense 
          bg-color="white" 
          dropdown-icon="arrow_drop_down" 
          placeholder="Select Site" 
        />
      </div>

      <div class="q-mb-lg">
        <label class="text-grey-8 q-mb-xs block">Location:</label>
        <q-select 
          outlined 
          v-model="location" 
          :options="locationOptions" 
          option-label="name" 
          option-value="id" 
          emit-value 
          map-options 
          dense 
          bg-color="white" 
          dropdown-icon="arrow_drop_down" 
          :disable="!site" 
        />
      </div>

      <div class="row q-gutter-md justify-center" style="margin-top: 50px;">
        <div class="col-5">
          <q-btn class="full-width" color="grey-7" label="Cancel" @click="$router.push('/transactions')" no-caps />
        </div>
        <div class="col-5">
          <q-btn class="full-width" color="black" label="Save" @click="save" no-caps />
        </div>
      </div>
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import axios from 'axios'
import { API_BASE_URL } from '../config/api'
import { DatabaseService } from '../services/DatabaseService'

const router = useRouter()
const $q = useQuasar()

// Helper to get current date formatted
const getCurrentDate = () => {
  const now = new Date()
  const pad = (n) => (n < 10 ? '0' + n : n)
  const mm = pad(now.getMonth() + 1)
  const dd = pad(now.getDate())
  const yyyy = now.getFullYear()
  const hh = pad(now.getHours())
  const min = pad(now.getMinutes())
  const ss = pad(now.getSeconds())
  return `${mm}-${dd}-${yyyy} ${hh}:${min}:${ss}`
}

const date = ref(getCurrentDate()) 
const site = ref(null)
const siteOptions = ref([])

const location = ref(null)
const locationOptions = ref([])
const allLocations = ref([])

onMounted(async () => {
  try {
    // 1. Get local data first
    const [localSites, localLocations] = await Promise.all([
      DatabaseService.getLocalSites(),
      DatabaseService.getLocalLocations()
    ]);

    if (localSites.length > 0) {
      siteOptions.value = localSites;
      allLocations.value = localLocations;
    }

    // Background sync will update local data if online
  } catch (error) {
    console.error('Error fetching data from local storage:', error)
    siteOptions.value = []
  }
})

watch(site, async (newSiteId) => {
  location.value = null // Reset location when site changes
  if (!newSiteId) {
    locationOptions.value = []
    return
  }

  // Filter locations from the loaded JSON data
  locationOptions.value = allLocations.value.filter(l => l.site_id === newSiteId)
  
  if (locationOptions.value.length === 0) {
      // Ensure options are empty if no match found in JSON
      locationOptions.value = []
  }
})

const save = async () => {
  if (!site.value || !location.value || !date.value) {
    $q.notify({
      type: 'warning',
      message: 'Please fill all fields',
      position: 'top'
    })
    return
  }

  try {
    const isOnline = await DatabaseService.isOnline();
    let mysqlId = null;

    if (isOnline) {
      try {
        const payload = {
          site: site.value,
          location: location.value,
          date: date.value
        }
        const response = await axios.post(`${API_BASE_URL}/api/transactions`, payload)
        if (response.data && response.data.id) {
          mysqlId = response.data.id;
        }
      } catch (err) {
        console.warn('Online save failed, falling back to local only.', err);
      }
    }

    // Save to local SQLite
    const localId = await DatabaseService.saveTransactionLocally(
      site.value, 
      location.value, 
      date.value, 
      mysqlId
    );
    
    $q.notify({
      type: 'positive',
      message: mysqlId ? 'Saved and synced' : 'Saved locally (Offline)',
      position: 'top'
    })
    
    // Navigate to asset counting page
    // If online, we use mysqlId, if offline, we use a special local ID format
    const sessionId = mysqlId || `local_${localId}`;
    router.push(`/asset-count?session_id=${sessionId}&location_id=${location.value}`)
    
  } catch (error) {
    console.error('Error saving transaction:', error)
    $q.notify({
      type: 'negative',
      message: 'Error saving transaction',
      position: 'top'
    })
  }
}

</script>
 
