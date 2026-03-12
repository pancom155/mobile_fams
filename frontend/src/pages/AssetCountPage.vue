<template>
  <q-layout view="lHh Lpr lFf" style="min-height: 100vh;">
    <q-header class="bg-primary text-white" elevated>
      <q-toolbar>
        <q-btn flat round dense icon="arrow_back" @click="$router.back()" />
        <q-toolbar-title class="text-weight-bold">
          <div class="text-h6">Asset Count</div>
          <div class="text-caption text-blue-grey-2">{{ locationName }}</div>
        </q-toolbar-title>
        <div class="row items-center q-gutter-xs">
          <q-btn 
            round 
            dense 
            flat 
            icon="help_outline"
            @click="showInstructions = true"
          >
            <q-tooltip>Instructions</q-tooltip>
          </q-btn>
          <q-btn round dense flat icon="refresh" @click="refreshData">
            <q-tooltip>Refresh</q-tooltip>
          </q-btn>
          <q-btn round dense flat icon="save" @click="saveSession" :loading="savingSession">
            <q-tooltip>Save Count</q-tooltip>
          </q-btn>
        </div>
      </q-toolbar>
    </q-header>

    <q-page-container>
      <q-page class="bg-grey-1">
        <div class="q-pa-md">
          <!-- Scan Section -->
          <div class="q-mb-lg">
            <div class="text-subtitle1 text-weight-bold q-mb-sm">Scan Asset</div>
            <q-input 
              outlined 
              v-model="barcode" 
              placeholder="Scan barcode or type Asset ID..."
              dense 
              bg-color="white" 
              class="q-mb-xs shadow-2"
              @keyup.enter="scanAsset"
              @keydown="onScannerKeydown"
              autofocus
              :loading="scanning"
            >
              <template v-slot:prepend>
                <q-icon name="qr_code_scanner" color="primary" />
              </template>
              <template v-slot:append>
                <q-btn 
                  round 
                  dense 
                  flat 
                  icon="search" 
                  color="primary"
                  @click="scanAsset"
                  :loading="scanning"
                >
                  <q-tooltip>Scan</q-tooltip>
                </q-btn>
              </template>
              <template v-slot:hint>
                Press Enter or click the search icon to scan
              </template>
            </q-input>
          </div>

          <!-- Results Card -->
          <div v-if="scannedAsset" class="q-mb-lg">
            <q-card class="shadow-3" :class="scanStatusColorClass">
              <q-card-section class="q-pa-md">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle1 text-weight-bold ellipsis" style="max-width: 70%;">
                    {{ scannedAsset.short_description }}
                  </div>
                  <q-chip 
                    :color="scanStatusColor" 
                    text-color="white" 
                    size="md"
                    class="text-weight-bold"
                  >
                    {{ scanStatus }}
                  </q-chip>
                </div>
                
                <div class="row items-center q-gutter-md">
                  <div class="column">
                    <div class="text-caption text-grey-7">Asset ID</div>
                    <div class="text-weight-medium">{{ scannedAsset.asset_id }}</div>
                  </div>
                  <q-separator vertical />
                  <div class="column">
                    <div class="text-caption text-grey-7">Serial Number</div>
                    <div class="text-weight-medium">{{ scannedAsset.serial_number || 'N/A' }}</div>
                  </div>
                </div>

                <div class="q-mt-md">
                  <q-input
                    outlined
                    v-model="remarks"
                    placeholder="Remarks (optional)"
                    dense
                    bg-color="white"
                  />
                </div>

                <div v-if="scanStatus === 'Found' || scanStatus === 'Missed Location'" class="row justify-end q-mt-md">
                  <q-btn 
                    color="primary" 
                    label="Add to Count" 
                    icon="add" 
                    dense
                    class="q-px-xl"
                    @click="saveScannedAsset"
                    :loading="saving"
                  />
                </div>
              </q-card-section>
            </q-card>
          </div>

          <!-- Summary Cards -->
          <div class="row q-gutter-md q-mb-lg">
            <q-card class="col-grow bg-white shadow-2">
              <q-card-section class="text-center">
                <div class="text-h5 text-weight-bold text-primary">{{ assets.length }}</div>
                <div class="text-caption text-grey-7">Scanned Assets</div>
              </q-card-section>
            </q-card>
            
            <q-card class="col-grow bg-white shadow-2">
              <q-card-section class="text-center">
                <div class="text-h5 text-weight-bold text-blue">{{ totalAssetsInLocation }}</div>
                <div class="text-caption text-grey-7">Total Assets in Location</div>
              </q-card-section>
            </q-card>
            
            <q-card class="col-grow bg-white shadow-2">
              <q-card-section class="text-center">
                <div class="text-h5 text-weight-bold text-orange">{{ notScannedCount }}</div>
                <div class="text-caption text-grey-7">Not Scanned</div>
              </q-card-section>
            </q-card>
            
            <q-card class="col-grow bg-white shadow-2">
              <q-card-section class="text-center">
                <div class="text-h5 text-weight-bold text-orange-9">{{ missedLocationCount }}</div>
                <div class="text-caption text-grey-7">Missed Location</div>
              </q-card-section>
            </q-card>
          </div>

          <!-- Assets Table -->
          <q-card class="shadow-2">
            <q-card-section>
              <div class="row items-center justify-between q-mb-md">
                <div class="text-subtitle1 text-weight-bold">Scanned Assets</div>
                <div class="row q-gutter-sm">
                  <q-chip dense clickable :color="statusFilter === 'all' ? 'primary' : 'grey-3'" :text-color="statusFilter === 'all' ? 'white' : 'grey-9'" @click="statusFilter = 'all'">All</q-chip>
                  <q-chip dense clickable :color="statusFilter === 'match' ? 'green' : 'grey-3'" :text-color="statusFilter === 'match' ? 'white' : 'grey-9'" @click="statusFilter = 'match'">Match</q-chip>
                  <q-chip dense clickable :color="statusFilter === 'missed' ? 'orange' : 'grey-3'" :text-color="statusFilter === 'missed' ? 'white' : 'grey-9'" @click="statusFilter = 'missed'">Missed</q-chip>
                </div>
              </div>
              <q-table
                :rows="filteredAssets"
                :columns="columns"
                row-key="id"
                flat
                bordered
                dense
                hide-pagination
                :rows-per-page-options="[0]"
                class="asset-table"
              >
                <template v-slot:body="props">
                  <q-tr :props="props" class="cursor-pointer" @click="handleRowClick(props.row)">
                    <q-td v-for="col in props.cols" :key="col.name" :props="props">
                      <div v-if="col.name === 'description'" class="ellipsis" style="max-width: 200px;">
                        {{ col.value }}
                      </div>
                      <q-chip v-else-if="col.name === 'status'" :color="props.row.status === 'Missed Location' ? 'orange' : (props.row.status === 'Match' ? 'green' : 'grey-6')" text-color="white" size="sm">
                        {{ col.value }}
                      </q-chip>
                      <span v-else>{{ col.value }}</span>
                    </q-td>
                    <q-td auto-width>
                      <q-btn 
                        round 
                        dense 
                        flat 
                        icon="delete" 
                        color="negative"
                        size="sm"
                        @click.stop="confirmDelete(props.row)"
                      >
                        <q-tooltip>Remove</q-tooltip>
                      </q-btn>
                    </q-td>
                  </q-tr>
                </template>
                
                <template v-slot:no-data>
                  <div class="full-width row flex-center text-grey-6 q-py-xl">
                    <q-icon name="inventory_2" size="48px" class="q-mb-sm" />
                    <div class="text-subtitle1 q-ml-sm">No assets scanned yet</div>
                  </div>
                </template>
              </q-table>
            </q-card-section>
          </q-card>

          <!-- Not Scanned List -->
          <q-card class="shadow-2 q-mt-md">
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-md">Not Scanned Assets</div>
              <q-table
                :rows="notScannedAssets"
                :columns="columns"
                row-key="id"
                flat
                bordered
                dense
                hide-pagination
                :rows-per-page-options="[0]"
              >
                <template v-slot:no-data>
                  <div class="full-width row flex-center text-grey-6 q-py-xl">
                    <q-icon name="check_circle" size="40px" class="q-mb-sm" color="green" />
                    <div class="text-subtitle1 q-ml-sm">All assets scanned</div>
                  </div>
                </template>
              </q-table>
            </q-card-section>
          </q-card>

          <!-- Instructions Dialog -->
          <q-dialog v-model="showInstructions">
            <q-card style="width: 400px; max-width: 90vw;">
              <q-card-section>
                <div class="text-h6">How to Count Assets</div>
              </q-card-section>
              
              <q-card-section class="q-pt-none">
                <div class="q-gutter-y-md">
                  <div class="row items-start">
                    <q-icon name="qr_code_scanner" color="primary" class="q-mr-sm" />
                    <div>
                      <div class="text-weight-medium">Scan Barcodes</div>
                      <div class="text-caption text-grey-7">
                        Use a barcode scanner or type the Asset ID in the search field
                      </div>
                    </div>
                  </div>
                  
                  <div class="row items-start">
                    <q-icon name="add_circle" color="green" class="q-mr-sm" />
                    <div>
                      <div class="text-weight-medium">Add Assets</div>
                      <div class="text-caption text-grey-7">
                        Click "Add to Count" to include the asset in your inventory
                      </div>
                    </div>
                  </div>
                  
                  <div class="row items-start">
                    <q-icon name="delete" color="negative" class="q-mr-sm" />
                    <div>
                      <div class="text-weight-medium">Remove Assets</div>
                      <div class="text-caption text-grey-7">
                        Click the delete icon to remove an asset from the count
                      </div>
                    </div>
                  </div>
                </div>
              </q-card-section>
              
              <q-card-actions align="right">
                <q-btn flat label="Close" color="primary" v-close-popup />
              </q-card-actions>
            </q-card>
          </q-dialog>

          <!-- Delete Confirmation Dialog -->
          <q-dialog v-model="showConfirmDelete">
            <q-card>
              <q-card-section class="row items-center">
                <q-avatar icon="warning" color="negative" text-color="white" />
                <span class="q-ml-sm text-weight-medium">Remove Asset?</span>
              </q-card-section>
              
              <q-card-section class="q-pt-none">
                <div class="q-pa-sm bg-grey-2 rounded-borders">
                  <div class="text-weight-medium">{{ selectedAsset?.short_description }}</div>
                  <div class="text-caption text-grey-7">{{ selectedAsset?.asset_id }}</div>
                </div>
                <div class="text-caption text-negative q-mt-sm">
                  This will remove the asset from the current count session
                </div>
              </q-card-section>

              <q-card-actions align="right">
                <q-btn flat label="Cancel" color="grey-7" v-close-popup />
                <q-btn flat label="Remove" color="negative" @click="deleteAsset" />
              </q-card-actions>
            </q-card>
          </q-dialog>

          <!-- Floating Action Button -->
          <q-page-sticky position="bottom-right" :offset="[18, 18]">
            <q-btn 
              fab 
              icon="qr_code_scanner" 
              color="primary"
              @click="focusScanner"
              size="lg"
            />
          </q-page-sticky>
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { useQuasar } from 'quasar'
import { API_BASE_URL } from '../config/api'
import { DatabaseService } from '../services/DatabaseService'

const route = useRoute()
const $q = useQuasar()
const barcode = ref('')
const scannedAsset = ref(null)
const scanStatus = ref('')
const scanStatusColor = ref('grey')
const remarks = ref('')
const showConfirmDelete = ref(false)
const showInstructions = ref(false)
const selectedAsset = ref(null)
const scanning = ref(false)
const saving = ref(false)
const savingSession = ref(false)
const assets = ref([])
const localCountsCache = ref([])
let scanTimer = null
const shouldClearOnNextInput = ref(false)
const locationAssetsAll = ref([])
const statusFilter = ref('all')

const sessionId = computed(() => route.query.session_id)
const currentLocationId = ref(route.query.location_id || null)
const locationName = computed(() => route.query.location_name || 'Count Session')

const columns = [
  { 
    name: 'assetID', 
    label: 'Asset ID', 
    field: 'asset_id', 
    align: 'left', 
    sortable: true,
    headerClasses: 'text-weight-bold'
  },
  { 
    name: 'description', 
    label: 'Description', 
    field: 'short_description', 
    align: 'left',
    sortable: true
  },
  {
    name: 'status',
    label: 'Status',
    field: 'status',
    align: 'left',
    sortable: true
  },
  {
    name: 'remarks',
    label: 'Remarks',
    field: 'remarks',
    align: 'left',
    sortable: false
  }
]

const totalAssetsInLocation = computed(() => locationAssetsAll.value.length)
const notScannedAssets = computed(() => {
  const scannedSet = new Set(assets.value.map(a => a.asset_id))
  return locationAssetsAll.value.filter(a => !scannedSet.has(a.asset_id))
})
const notScannedCount = computed(() => notScannedAssets.value.length)
const missedLocationCount = computed(() => assets.value.filter(a => a.status === 'Missed Location').length)
const filteredAssets = computed(() => {
  if (statusFilter.value === 'match') {
    return assets.value.filter(a => a.status === 'Match')
  }
  if (statusFilter.value === 'missed') {
    return assets.value.filter(a => a.status === 'Missed Location')
  }
  return assets.value
})

const scanStatusColorClass = computed(() => {
  if (scanStatus.value === 'Found') return 'bg-green-1 border-green'
  if (scanStatus.value === 'Not Found') return 'bg-red-1 border-red'
  return ''
})

onMounted(async () => {
  if (!currentLocationId.value) {
    const tx = await DatabaseService.getTransactionBySessionId(sessionId.value);
    currentLocationId.value = tx?.location_id || null;
  }
  await fetchAssets()
})

const fetchAssets = async () => {
  try {
    // 1. Load local counts for this session first
    const localCounts = await DatabaseService.getLocalInventoryCounts(sessionId.value);
    localCountsCache.value = localCounts;
    
    // We need asset details for the UI, let's look them up
    const enrichedAssets = [];
    for (const count of localCounts) {
      let assetDetails = await DatabaseService.lookupLocalAsset(count.actual_asset_id);
      if (assetDetails.length === 0 && (await DatabaseService.isOnline())) {
        try {
          const res = await axios.get(`${API_BASE_URL}/api/assets/lookup?barcode=${count.actual_asset_id}`);
          const fromServer = Array.isArray(res.data) ? res.data : (res.data ? [res.data] : []);
          if (fromServer.length > 0) {
            assetDetails = fromServer;
          }
        } catch (e) {}
      }
      if (assetDetails.length > 0) {
        enrichedAssets.push({
          ...assetDetails[0],
          local_count_id: count.local_id,
          sync_status: count.sync_status,
          inventory_count_id: count.id || null,
          status: count.status,
          remarks: count.remarks || ''
        });
      }
    }
    assets.value = enrichedAssets;

    // 2. Load all assets for the current location (expected list)
    if (currentLocationId.value) {
      const localAll = await DatabaseService.getLocalAssetsByLocation(Number(currentLocationId.value));
      if (localAll.length > 0) {
        locationAssetsAll.value = localAll;
      } else if (await DatabaseService.isOnline()) {
        const res = await axios.get(`${API_BASE_URL}/api/assets/by-location?location_id=${currentLocationId.value}`);
        locationAssetsAll.value = Array.isArray(res.data) ? res.data : [];
      }
    }

    // 2. Background sync handles server merge
  } catch (err) {
    console.error('Error fetching assets:', err);
  }
}

const saveSession = async () => {
  savingSession.value = true
  try {
    const online = await DatabaseService.isOnline()
    if (online) {
      await DatabaseService.syncAll()
      $q.notify({ type: 'positive', message: 'Count saved and synced', position: 'top' })
    } else {
      $q.notify({ type: 'warning', message: 'Saved locally (offline)', position: 'top' })
    }
    await fetchAssets()
  } catch (e) {
    console.error('Save session error:', e)
    $q.notify({ type: 'negative', message: 'Failed to save count', position: 'top' })
  } finally {
    savingSession.value = false
  }
}

const scanAsset = async () => {
  if (!barcode.value) return
  
  scanning.value = true
  scannedAsset.value = null
  scanStatus.value = ''
  
  try {
    const isDuplicate = assets.value.some(a => a.asset_id === barcode.value || a.serial_number === barcode.value)
    if (isDuplicate) {
      const existing = assets.value.find(a => a.asset_id === barcode.value || a.serial_number === barcode.value)
      scannedAsset.value = existing || null
      scanStatus.value = 'Already Scanned'
      scanStatusColor.value = 'blue'
      return
    }
    // 1. Try local lookup first
    const localResults = await DatabaseService.lookupLocalAsset(barcode.value);
    
    if (localResults.length > 0) {
      scannedAsset.value = localResults[0];
      if (currentLocationId.value && scannedAsset.value.location_id && Number(scannedAsset.value.location_id) !== Number(currentLocationId.value)) {
        scanStatus.value = 'Missed Location';
        scanStatusColor.value = 'orange';
      } else {
        scanStatus.value = 'Found';
        scanStatusColor.value = 'green';
      }
    } else if (await DatabaseService.isOnline()) {
      // 2. If not found locally and online, try server
      const res = await axios.get(`${API_BASE_URL}/api/assets/lookup?barcode=${barcode.value}`);
      if (res.data) {
        scannedAsset.value = Array.isArray(res.data) ? res.data[0] : res.data;
        if (currentLocationId.value && scannedAsset.value.location_id && Number(scannedAsset.value.location_id) !== Number(currentLocationId.value)) {
          scanStatus.value = 'Missed Location';
          scanStatusColor.value = 'orange';
        } else {
          scanStatus.value = 'Found';
          scanStatusColor.value = 'green';
        }
      } else {
        scanStatus.value = 'Not Found';
        scanStatusColor.value = 'red';
      }
    } else {
      scanStatus.value = 'Not Found';
      scanStatusColor.value = 'red';
    }
  } catch (error) {
    console.error('Scan error:', error)
    scanStatus.value = 'Error'
    scanStatusColor.value = 'orange'
  } finally {
    scanning.value = false
    focusScanner()
    shouldClearOnNextInput.value = true
  }
}

const saveScannedAsset = async () => {
  if (!scannedAsset.value) return
  if (assets.value.some(a => a.asset_id === scannedAsset.value.asset_id || a.serial_number === scannedAsset.value.serial_number)) {
    $q.notify({ type: 'warning', message: 'Already scanned', position: 'top' })
    return
  }
  
  saving.value = true
  try {
    const isOnline = await DatabaseService.isOnline();
    const isLocalSession = sessionId.value.startsWith('local_');
    let mysqlId = null;

    const countData = {
      session_id: sessionId.value,
      asset_id: scannedAsset.value.id,
      actual_asset_id: scannedAsset.value.asset_id,
      actual_serial: scannedAsset.value.serial_number,
      status: scanStatus.value === 'Missed Location' ? 'Missed Location' : 'Match',
      remarks: remarks.value || ''
    };

    if (isOnline && !isLocalSession) {
      try {
        const res = await axios.post(`${API_BASE_URL}/api/inventory/count`, countData);
        if (res.data && res.data.id) {
          mysqlId = res.data.id;
        }
      } catch (err) {
        console.warn('Online count save failed, using local only.', err);
      }
    }

    // Save locally
    await DatabaseService.saveInventoryCountLocally(countData, mysqlId);
    
    $q.notify({
      type: 'positive',
      message: mysqlId ? 'Asset added and synced' : 'Asset added locally',
      position: 'top',
      timeout: 1000
    })
    
    await fetchAssets();
    barcode.value = '';
    scannedAsset.value = null;
    scanStatus.value = '';
    remarks.value = '';
    
  } catch (error) {
    console.error('Save error:', error)
    $q.notify({
      type: 'negative',
      message: 'Failed to save asset',
      position: 'top'
    })
  } finally {
    saving.value = false
  }
}

watch(barcode, (val) => {
  if (!val) return
  if (scanTimer) {
    clearTimeout(scanTimer)
  }
  scanTimer = setTimeout(() => {
    if (!scanning.value) {
      scanAsset()
    }
  }, 200)
})

const onScannerKeydown = () => {
  if (shouldClearOnNextInput.value) {
    barcode.value = ''
    shouldClearOnNextInput.value = false
  }
}

const handleRowClick = (row) => {
  // Optionally show details in a dialog
  console.log('Row clicked:', row)
}

const confirmDelete = (row) => {
  selectedAsset.value = row
  showConfirmDelete.value = true
}

const deleteAsset = async () => {
  if (!selectedAsset.value) return

  try {
    const isOnline = await DatabaseService.isOnline();
    const isLocalSession = sessionId.value.startsWith('local_');
    const mysqlId = selectedAsset.value.inventory_count_id;

    if (isOnline && !isLocalSession && mysqlId) {
      await axios.delete(`${API_BASE_URL}/api/inventory/count/${mysqlId}`)
    }

    // Local deletion
    if (selectedAsset.value.local_count_id) {
      await DatabaseService.deleteLocalInventoryCount(selectedAsset.value.local_count_id);
    }
    
    assets.value = assets.value.filter(a => a.local_count_id !== selectedAsset.value.local_count_id)
    
    $q.notify({ 
      type: 'positive', 
      message: 'Asset removed from count',
      position: 'top'
    })
  } catch (error) {
    console.error('Delete Error:', error)
    $q.notify({ 
      type: 'negative', 
      message: 'Error deleting asset',
      position: 'top'
    })
  } finally {
    showConfirmDelete.value = false
  }
}

const focusScanner = () => {
  // Focus the barcode input field
  const input = document.querySelector('input[autofocus]')
  if (input) {
    input.focus()
    input.select()
  }
}
</script>

<style scoped>
.border-left-green {
  border-left: 4px solid #21ba45;
}

.border-left-red {
  border-left: 4px solid #c10015;
}

.asset-table :deep(.q-table__top) {
  padding: 0;
}

.asset-table :deep(.q-table__middle) {
  min-height: 300px;
}

.asset-table :deep(.q-td) {
  padding: 12px 16px;
}

.asset-table :deep(.q-tr:hover) {
  background-color: #f5f5f5;
}

.q-page-container {
  background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
}
</style>
