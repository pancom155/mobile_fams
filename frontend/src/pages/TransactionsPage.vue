<template>
  <q-layout view="lHh Lpr lFf">
    <q-header class="bg-primary text-white" elevated>
      <q-toolbar>
        <q-btn 
          flat 
          round 
          dense 
          icon="arrow_back" 
          @click="handleBack"
          class="q-mr-sm"
        >
          <q-tooltip>Go back</q-tooltip>
        </q-btn>
        <q-toolbar-title class="text-weight-bold">Count Sessions</q-toolbar-title>
        <q-btn 
          flat 
          round 
          dense 
          icon="add" 
          @click="$router.push('/new')"
          color="white"
        >
          <q-tooltip>Create new count</q-tooltip>
        </q-btn>
        <q-btn flat round dense icon="logout" @click="logout" />
      </q-toolbar>
    </q-header>

    <q-page-container>
      <q-page class="bg-grey-1">
        <div class="q-pa-lg">
          <!-- Search Section -->
          <div class="q-mb-lg">
            <q-card class="shadow-3">
              <q-card-section class="q-pa-md">
                <div class="text-subtitle2 text-weight-bold q-mb-sm text-grey-9">
                  <q-icon name="search" color="primary" size="sm" class="q-mr-xs" />
                  Search Transactions
                </div>
                <div class="row q-col-gutter-md items-center">
                  <div class="col-12 col-sm-8">
                    <q-input 
                      outlined 
                      v-model="search" 
                      placeholder="Search by location, site, or date..." 
                      dense 
                      bg-color="white"
                      @keyup.enter="handleSearch"
                      clearable
                      @clear="handleClearSearch"
                    >
                      <template v-slot:prepend>
                        <q-icon name="filter_list" color="grey-6" />
                      </template>
                      <template v-slot:append>
                        <q-icon name="tune" color="grey-6" />
                      </template>
                    </q-input>
                  </div>
                  <div class="col-12 col-sm-4">
                    <q-btn 
                      color="primary" 
                      label="Search" 
                      no-caps 
                      class="full-width"
                      @click="handleSearch"
                      :loading="loading"
                      icon="search"
                    />
                  </div>
                </div>
                <div class="row items-center justify-between q-mt-sm">
                  <div class="text-caption text-grey-6">
                    {{ filteredTransactions.length }} transaction{{ filteredTransactions.length !== 1 ? 's' : '' }} found
                  </div>
                  <div class="row q-gutter-xs">
                    <q-chip 
                      dense 
                      size="sm" 
                      :color="isOnline ? 'green-1' : 'red-1'" 
                      :text-color="isOnline ? 'green-9' : 'red-9'"
                      :icon="isOnline ? 'cloud_done' : 'cloud_off'"
                    >
                      {{ isOnline ? 'Online' : 'Offline' }}
                    </q-chip>
                    <q-btn 
                      flat 
                      dense 
                      no-caps 
                      color="primary" 
                      icon="refresh" 
                      label="Refresh"
                      @click="fetchTransactions"
                      :loading="loading"
                      size="sm"
                    />
                  </div>
                </div>
              </q-card-section>
            </q-card>
          </div>

          <!-- Summary Cards -->
          <div class="row q-gutter-md q-mb-lg">
            <q-card class="col bg-white shadow-2">
              <q-card-section class="text-center">
                <q-icon name="list_alt" color="primary" size="md" class="q-mb-xs" />
                <div class="text-h5 text-weight-bold text-grey-9">{{ totalTransactions }}</div>
                <div class="text-caption text-grey-7">Total Counts</div>
              </q-card-section>
            </q-card>
            
            <q-card class="col bg-white shadow-2">
              <q-card-section class="text-center">
                <q-icon name="today" color="green" size="md" class="q-mb-xs" />
                <div class="text-h5 text-weight-bold text-grey-9">{{ todayTransactions }}</div>
                <div class="text-caption text-grey-7">Today</div>
              </q-card-section>
            </q-card>
            
            <q-card class="col bg-white shadow-2">
              <q-card-section class="text-center">
                <q-icon name="pending_actions" color="orange" size="md" class="q-mb-xs" />
                <div class="text-h5 text-weight-bold text-grey-9">{{ activeTransactions }}</div>
                <div class="text-caption text-grey-7">Active</div>
              </q-card-section>
            </q-card>
          </div>

          <!-- Transactions List -->
          <div class="q-mb-lg">
            <div class="text-subtitle1 text-weight-bold q-mb-md text-grey-9">
              <q-icon name="receipt_long" color="primary" size="sm" class="q-mr-xs" />
              Recent Count Sessions
            </div>
            
            <!-- Empty State -->
            <div v-if="filteredTransactions.length === 0 && !loading" class="text-center q-py-xl">
              <q-icon name="inventory_2" size="64px" color="grey-4" class="q-mb-md" />
              <div class="text-h6 text-grey-6 q-mb-sm">No transactions found</div>
              <div class="text-caption text-grey-6 q-mb-lg">
                {{
                  search 
                    ? 'No transactions match your search criteria' 
                    : 'Start by creating your first inventory count'
                }}
              </div>
              <q-btn 
                v-if="!search"
                color="primary" 
                label="Create First Count" 
                icon="add" 
                no-caps
                @click="$router.push('/new')"
              />
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="text-center q-py-xl">
              <q-spinner-gears color="primary" size="50px" />
              <div class="text-subtitle2 text-grey-7 q-mt-md">Loading transactions...</div>
            </div>

            <!-- Transactions Grid -->
            <div v-if="!loading && filteredTransactions.length > 0" class="row q-col-gutter-md">
              <div 
                v-for="transaction in filteredTransactions" 
                :key="transaction.id" 
                class="col-12"
              >
                <q-card 
                  class="shadow-2 cursor-pointer transaction-card"
                  @click="handleTransactionClick(transaction)"
                  :class="{ 'border-left-warning': isToday(transaction.date) }"
                >
                  <q-card-section class="q-pa-md">
                    <div class="row items-center justify-between">
                      <div class="row items-center q-gutter-md">
                        <q-avatar color="primary" text-color="white" size="md">
                          <q-icon name="location_on" size="sm" />
                        </q-avatar>
                        <div>
                          <div class="text-subtitle2 text-weight-bold ellipsis" style="max-width: 250px;">
                            {{ transaction.location }}
                          </div>
                          <div class="text-caption text-grey-7">
                            <q-icon name="calendar_today" size="xs" class="q-mr-xs" />
                            {{ formatDate(transaction.date) }}
                          </div>
                        </div>
                      </div>
                      
                      <div class="row items-center q-gutter-xs">
                        <q-btn 
                          round 
                          dense 
                          flat 
                          icon="more_vert" 
                          color="grey-6"
                          @click.stop="showOptions(transaction)"
                        >
                          <q-tooltip>Options</q-tooltip>
                        </q-btn>
                      </div>
                    </div>
                    
                    <!-- Additional Info -->
                    <div v-if="transaction.site_name" class="row items-center q-mt-sm q-gutter-sm">
                      <q-chip dense size="sm" color="blue-1" text-color="blue-9">
                        <q-icon name="business" size="xs" class="q-mr-xs" />
                        {{ transaction.site_name }}
                      </q-chip>
                      <q-chip v-if="isToday(transaction.date)" dense size="sm" color="green-1" text-color="green-9">
                        <q-icon name="today" size="xs" class="q-mr-xs" />
                        Today
                      </q-chip>
                      <q-chip dense size="sm" color="grey-2" text-color="grey-7">
                        ID: {{ transaction.id }}
                      </q-chip>
                    </div>
                  </q-card-section>
                </q-card>
              </div>
            </div>
          </div>

          <!-- Transaction Options Menu -->
          <q-menu v-model="showActionDialog" :offset="[0, 10]">
            <q-list style="min-width: 200px">
              <q-item-label header>Transaction Options</q-item-label>
              
              <q-item clickable v-close-popup @click="openTransaction" class="text-primary">
                <q-item-section avatar>
                  <q-icon name="open_in_new" color="primary" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Open Count</q-item-label>
                  <q-item-label caption>View and scan assets</q-item-label>
                </q-item-section>
              </q-item>
              
              <q-item clickable v-close-popup @click="duplicateTransaction">
                <q-item-section avatar>
                  <q-icon name="content_copy" color="blue" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Duplicate</q-item-label>
                  <q-item-label caption>Create a copy</q-item-label>
                </q-item-section>
              </q-item>
              
              <q-separator />
              
              <q-item clickable v-close-popup @click="confirmDelete" class="text-negative">
                <q-item-section avatar>
                  <q-icon name="delete" color="negative" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Delete</q-item-label>
                  <q-item-label caption>Remove permanently</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-menu>

          <!-- Delete Confirmation Dialog -->
          <q-dialog v-model="showConfirmDelete">
            <q-card style="width: 400px; max-width: 90vw;">
              <q-card-section class="row items-center">
                <q-avatar icon="warning" color="negative" text-color="white" />
                <span class="q-ml-sm text-weight-medium">Delete Count Session?</span>
              </q-card-section>
              
              <q-card-section class="q-pt-none">
                <div class="q-pa-sm bg-grey-2 rounded-borders">
                  <div class="text-weight-medium">{{ selectedTransaction?.location }}</div>
                  <div class="text-caption text-grey-7">
                    Created on {{ selectedTransaction?.date ? formatDate(selectedTransaction.date) : '' }}
                  </div>
                </div>
                <div class="text-caption text-negative q-mt-sm">
                  <q-icon name="warning" size="sm" class="q-mr-xs" />
                  This will permanently delete this count session and all scanned assets.
                </div>
              </q-card-section>

              <q-card-actions align="right">
                <q-btn flat label="Cancel" color="grey-7" v-close-popup />
                <q-btn flat label="Delete" color="negative" @click="deleteTransaction" />
              </q-card-actions>
            </q-card>
          </q-dialog>

          <q-dialog v-model="showLocationAssetsDialog">
            <q-card style="width: 700px; max-width: 95vw;">
              <q-card-section>
                <div class="row items-center justify-between">
                  <div class="text-h6">{{ locationDialogTitle }}</div>
                  <div class="row q-gutter-sm">
                    <q-chip dense clickable :color="!showAllAssets ? 'primary' : 'grey-3'" :text-color="!showAllAssets ? 'white' : 'grey-9'" @click="showAllAssets = false">
                      Scanned
                    </q-chip>
                    <q-chip dense clickable :color="showAllAssets ? 'primary' : 'grey-3'" :text-color="showAllAssets ? 'white' : 'grey-9'" @click="showAllAssets = true">
                      All Assets
                    </q-chip>
                  </div>
                </div>
              </q-card-section>
              <q-card-section class="q-pt-none">
                <div v-if="loadingLocationAssets" class="text-center q-py-md">
                  <q-spinner-gears color="primary" size="40px" />
                </div>
                <q-table
                  v-else
                  :rows="showAllAssets ? locationAssetsAll : locationAssets"
                  :columns="locationAssetColumns"
                  row-key="id"
                  flat
                  bordered
                  dense
                  hide-pagination
                  :rows-per-page-options="[0]"
                />
              </q-card-section>
              <q-card-actions align="right">
                <q-btn flat label="Close" color="primary" v-close-popup />
              </q-card-actions>
            </q-card>
          </q-dialog>

          <!-- FAB for New Transaction -->
          <q-page-sticky position="bottom-right" :offset="[18, 18]">
            <q-btn 
              fab 
              icon="add" 
              color="primary"
              @click="$router.push('/new')"
              size="lg"
            >
              <q-tooltip>New count session</q-tooltip>
            </q-btn>
          </q-page-sticky>
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useQuasar } from 'quasar'
import { API_BASE_URL } from '../config/api'
import { DatabaseService } from '../services/DatabaseService'

const router = useRouter()
const $q = useQuasar()

// State
const search = ref('')
const transactions = ref([])
const showActionDialog = ref(false)
const showConfirmDelete = ref(false)
const selectedTransaction = ref(null)
const loading = ref(false)
const isOnline = ref(true)
const showLocationAssetsDialog = ref(false)
const locationAssets = ref([])
const locationAssetsAll = ref([])
const loadingLocationAssets = ref(false)
const locationDialogTitle = ref('')
const showAllAssets = ref(false)
const locationAssetColumns = [
  { name: 'asset_tag', label: 'Asset ID', field: 'asset_tag', align: 'left', sortable: true },
  { name: 'short_description', label: 'Description', field: 'short_description', align: 'left', sortable: true },
  { name: 'status', label: 'Status', field: 'status', align: 'left', sortable: true }
]

// Computed properties
const filteredTransactions = computed(() => {
  if (!search.value) return transactions.value
  
  const searchTerm = search.value.toLowerCase()
  return transactions.value.filter(t => 
    (t.location && t.location.toLowerCase().includes(searchTerm)) ||
    (t.date && t.date.toLowerCase().includes(searchTerm)) ||
    (t.site_name && t.site_name.toLowerCase().includes(searchTerm))
  )
})

const totalTransactions = computed(() => transactions.value.length)
const todayTransactions = computed(() => {
  const today = new Date().toDateString()
  return transactions.value.filter(t => isToday(t.date)).length
})

const activeTransactions = computed(() => {
  return transactions.value.filter(t => {
    try {
      const transactionDate = new Date(t.date.replace(/(\d{2})-(\d{2})-(\d{4})/, '$2/$1/$3'))
      const diffDays = Math.floor((new Date() - transactionDate) / (1000 * 60 * 60 * 24))
      return diffDays <= 7
    } catch (e) {
      return false
    }
  }).length
})

onMounted(async () => {
  fetchTransactions()
})

const fetchTransactions = async () => {
  loading.value = true
  try {
    // 1. Load from local database first
    const [localTransactions, localSites, localLocations] = await Promise.all([
      DatabaseService.getLocalTransactions(),
      DatabaseService.getLocalSites(),
      DatabaseService.getLocalLocations()
    ]);

    // Create maps for easy lookup
    const sitesMap = new Map(localSites.map(s => [s.id, s.name]))
    const locationsMap = new Map(localLocations.map(l => [l.id, l.name]))

    // Map transactions with site and location names
    const mapped = localTransactions.map(t => ({
      ...t,
      site_name: sitesMap.get(t.site_id) || 'Unknown Site',
      location: locationsMap.get(t.location_id) || 'Unknown Location'
    }))
    const seen = new Set()
    transactions.value = mapped.filter(t => {
      const key = t.id ? `id-${t.id}` : `local-${t.local_id}`
      if (seen.has(key)) return false
      seen.add(key)
      return true
    })

    // Sort by date descending
    transactions.value.sort((a, b) => new Date(b.date) - new Date(a.date))

    // 2. Trigger a background sync if online
    if (await DatabaseService.isOnline()) {
      DatabaseService.syncAll().then(() => {
        // Refresh UI after sync if we are still on this page
        return Promise.all([
          DatabaseService.getLocalTransactions(),
          DatabaseService.getLocalSites(),
          DatabaseService.getLocalLocations()
        ]);
      }).then(([freshTransactions, freshSites, freshLocations]) => {
        const fSitesMap = new Map(freshSites.map(s => [s.id, s.name]))
        const fLocationsMap = new Map(freshLocations.map(l => [l.id, l.name]))
        
        transactions.value = freshTransactions.map(t => ({
          ...t,
          site_name: fSitesMap.get(t.site_id) || 'Unknown Site',
          location: fLocationsMap.get(t.location_id) || 'Unknown Location'
        }))
        transactions.value.sort((a, b) => new Date(b.date) - new Date(a.date))
      }).catch(err => {
        console.warn('Background sync failed:', err);
      });
    }

  } catch (error) {
    console.error('Error fetching transactions:', error)
    $q.notify({
      type: 'negative',
      message: 'Failed to load data',
      caption: 'Check local database or connection',
      timeout: 5000,
      position: 'top'
    })
    transactions.value = []
  } finally {
    loading.value = false
  }
}



const handleBack = () => {
  // You can implement specific back logic or use router back
  router.back()
}

const handleSearch = () => {
  // Search is handled by computed property
  console.log('Searching for:', search.value)
}

const handleClearSearch = () => {
  search.value = ''
}

const formatDate = (dateString) => {
  if (!dateString) return 'Unknown date'
  try {
    const [datePart, timePart] = dateString.split(' ')
    const [month, day, year] = datePart.split('-')
    const [hour, minute] = timePart.split(':')
    return `${month}/${day}/${year} ${hour}:${minute}`
  } catch (e) {
    return dateString
  }
}

const isToday = (dateString) => {
  if (!dateString) return false
  try {
    const transactionDate = new Date(dateString.replace(/(\d{2})-(\d{2})-(\d{4})/, '$2/$1/$3'))
    const today = new Date()
    return transactionDate.toDateString() === today.toDateString()
  } catch (e) {
    return false
  }
}

const showOptions = (transaction) => {
  selectedTransaction.value = transaction
  showActionDialog.value = true
}

const handleTransactionClick = (transaction) => {
  goToAssets(transaction)
}

const logout = () => {
  localStorage.removeItem('fams_token')
  localStorage.removeItem('fams_user')
  router.push('/login')
}

const openTransaction = () => {
  if (selectedTransaction.value) {
    goToAssets(selectedTransaction.value)
  }
}

const duplicateTransaction = () => {
  $q.notify({
    type: 'info',
    message: 'Duplicate feature coming soon',
    position: 'top'
  })
}

const confirmDelete = () => {
  showConfirmDelete.value = true
  showActionDialog.value = false
}

const deleteTransaction = async () => {
  if (!selectedTransaction.value) return

  const sessionId = selectedTransaction.value.id || `local_${selectedTransaction.value.local_id}`
  if (selectedTransaction.value.id) {
    try {
      await axios.delete(`${API_BASE_URL}/api/transactions/${selectedTransaction.value.id}`)
    } catch (error) {
      console.warn('Remote delete failed, proceeding with local cleanup.', error?.message || error)
    }
  }

  try {
    await DatabaseService.deleteLocalInventoryCountsBySession(sessionId)
    await DatabaseService.deleteLocalTransactionBySessionId(sessionId)
  } catch (e) {
    console.error('Local delete error:', e)
  }

  $q.notify({
    type: 'positive',
    message: 'Transaction deleted',
    caption: 'Count session removed successfully',
    position: 'top'
  })
  
  fetchTransactions()
}

const openLocationAssets = async (locationId, locationName) => {
  loadingLocationAssets.value = true
  locationAssets.value = []
  locationAssetsAll.value = []
  showAllAssets.value = false
  locationDialogTitle.value = locationName || 'Location Assets'
  try {
    const localRows = await DatabaseService.getLocalInventoryCountsByLocation(locationId)
    if (localRows.length > 0) {
      locationAssets.value = localRows
    } else if (await DatabaseService.isOnline()) {
      const res = await axios.get(`${API_BASE_URL}/api/inventory/counts-by-location?location_id=${locationId}`)
      locationAssets.value = Array.isArray(res.data) ? res.data : []
    }
    const localAssets = await DatabaseService.getLocalAssetsByLocation(locationId)
    if (localAssets.length > 0) {
      locationAssetsAll.value = localAssets
    } else if (await DatabaseService.isOnline()) {
      const res2 = await axios.get(`${API_BASE_URL}/api/assets/by-location?location_id=${locationId}`)
      locationAssetsAll.value = Array.isArray(res2.data) ? res2.data : []
    }
    showLocationAssetsDialog.value = true
  } catch (e) {
    $q.notify({ type: 'negative', message: 'Failed to load assets for location', position: 'top' })
  } finally {
    loadingLocationAssets.value = false
  }
}

const goToAssets = (transaction) => {
  router.push({ 
    path: '/asset-count', 
    query: { 
      session_id: transaction.id || `local_${transaction.local_id}`,
      location_id: transaction.location_id,
      location_name: transaction.location,
      date: transaction.date
    } 
  })
}
</script>

<style scoped>
.transaction-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.transaction-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0,0,0,0.12);
}

.border-left-warning {
  border-left: 4px solid #ff9800;
}

.q-page-container {
  background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
}

/* Custom scrollbar for better UX */
.q-page {
  scrollbar-width: thin;
  scrollbar-color: #c1c1c1 #f1f1f1;
}

.q-page::-webkit-scrollbar {
  width: 8px;
}

.q-page::-webkit-scrollbar-track {
  background: #f1f1f1;
}

.q-page::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 4px;
}

.q-page::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
</style>
