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
    const [sitesRes, locationsRes] = await Promise.all([
      axios.get(`${API_BASE_URL}/api/sync/json/sites.json`),
      axios.get(`${API_BASE_URL}/api/sync/json/locations.json`)
    ])
    
    console.log('API Sites Response:', sitesRes.data)
    if (Array.isArray(sitesRes.data)) {
      siteOptions.value = sitesRes.data
    }
    
    if (Array.isArray(locationsRes.data)) {
      allLocations.value = locationsRes.data
    }
    
  } catch (error) {
    console.error('Error fetching data:', error)
    alert(`[DEBUG v6] Error fetching data: ${error.message}\nURL: ${API_BASE_URL}/api/sync/json/sites.json\nDetails: ${JSON.stringify(error.toJSON ? error.toJSON() : error)}`)
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
    const payload = {
      site: site.value,
      location: location.value,
      date: date.value
    }
    
    // Use standard endpoint which now saves to JSON
    // Force using port 8001 to ensure we hit the new server instance
    const response = await axios.post(`${API_BASE_URL}/api/transactions`, payload)
    
    $q.notify({
      type: 'positive',
      message: 'Transaction saved successfully',
      position: 'top'
    })
    
    // Navigate to asset counting page with the new transaction/session ID
    // Check if response.data.id exists (it should be the new ID)
    if (response.data && response.data.id) {
        router.push(`/asset-count?session_id=${response.data.id}`)
    } else {
        // Fallback or error handling if ID is missing
        console.warn('No ID returned from save transaction')
        router.push('/transactions') 
    }
    
  } catch (error) {
    console.error('Error saving transaction:', error)
    $q.notify({
      type: 'negative',
      message: 'Error saving transaction',
      caption: error.response ? error.response.statusText : error.message,
      position: 'top'
    })
  }
}
</script>
 