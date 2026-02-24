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

                <div v-if="scanStatus === 'Found'" class="row justify-end q-mt-md">
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
                <div class="text-h5 text-weight-bold text-green">{{ uniqueAssetsCount }}</div>
                <div class="text-caption text-grey-7">Unique Assets</div>
              </q-card-section>
            </q-card>
          </div>

          <!-- Assets Table -->
          <q-card class="shadow-2">
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-md">Scanned Assets</div>
              <q-table
                :rows="assets"
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
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { useQuasar } from 'quasar'
import { API_BASE_URL } from '../config/api'

const route = useRoute()
const $q = useQuasar()
const barcode = ref('')
const scannedAsset = ref(null)
const scanStatus = ref('')
const scanStatusColor = ref('grey')
const showConfirmDelete = ref(false)
const showInstructions = ref(false)
const selectedAsset = ref(null)
const scanning = ref(false)
const saving = ref(false)

const sessionId = computed(() => route.query.transaction_id)
const locationName = computed(() => route.query.location_name || 'Unknown Location')

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
    headerClasses: 'text-weight-bold'
  },
  { 
    name: 'brand', 
    label: 'Brand', 
    field: 'brand', 
    align: 'left',
    headerClasses: 'text-weight-bold'
  },
  { 
    name: 'serial', 
    label: 'Serial', 
    field: 'serial_number', 
    align: 'left',
    headerClasses: 'text-weight-bold'
  },
  {
    name: 'actions',
    label: '',
    field: '',
    align: 'right',
    sortable: false
  }
]

const assets = ref([])
const allAssets = ref([]) // To store full asset list for lookup

const uniqueAssetsCount = computed(() => {
  const unique = new Set(assets.value.map(a => a.asset_id))
  return unique.size
})

const scanStatusColorClass = computed(() => {
  return scanStatus.value === 'Found' ? 'border-left-green' : 'border-left-red'
})

onMounted(async () => {
  console.log('AssetCountPage mounted', sessionId.value)
  if (!sessionId.value) {
    $q.notify({ 
      type: 'negative', 
      message: 'No Transaction Selected',
      position: 'top'
    })
    return
  }
  
  await refreshData()
})

const refreshData = async () => {
  try {
    // Fetch both inventory counts and full asset list
    const [countsRes, assetsRes] = await Promise.all([
        axios.get(`${API_BASE_URL}/api/sync/json/inventory_counts.json`),
        axios.get(`${API_BASE_URL}/api/sync/json/assets.json`)
    ])
    
    // Store all assets for lookup
    allAssets.value = assetsRes.data

    // Filter counts for current session
    // Note: sessionId.value comes from route query, check if type matches (string vs number)
    const currentCounts = countsRes.data.filter(item => item.session_id == sessionId.value)
    
    assets.value = currentCounts.map(item => {
        // Find asset details from allAssets
        const assetDetail = allAssets.value.find(a => a.id === item.asset_id)
        return {
            id: item.asset_id,
            inventory_count_id: item.id,
            asset_id: assetDetail ? assetDetail.asset_id : 'Unknown', // The tag/barcode
            short_description: assetDetail ? assetDetail.short_description : 'Unknown',
            brand: assetDetail ? assetDetail.brand : '',
            serial_number: assetDetail ? assetDetail.serial_number : ''
        }
    })
  } catch (error) {
    console.error('Error fetching inventory counts:', error)
    $q.notify({ 
      type: 'negative', 
      message: 'Failed to load inventory data',
      position: 'top'
    })
  }
}

const scanAsset = async () => {
  if (!barcode.value.trim()) {
    $q.notify({ 
      type: 'warning', 
      message: 'Please enter a barcode',
      position: 'top'
    })
    return
  }
  
  scanning.value = true
  try {
    // Local lookup using allAssets
    const searchTerm = barcode.value.trim()
    const foundAssets = allAssets.value.filter(a => 
        a.asset_id === searchTerm || 
        a.serial_number === searchTerm
    )
    
    if (foundAssets.length > 0) {
      const asset = foundAssets[0]
      scannedAsset.value = asset
      scanStatus.value = 'Found'
      scanStatusColor.value = 'positive'
      
      // Check if already scanned
      if (assets.value.some(a => a.id === asset.id)) { // Check by ID (FK)
        $q.notify({ 
          type: 'info', 
          message: 'Asset already in count list',
          icon: 'info',
          position: 'top'
        })
      }
    } else {
      scannedAsset.value = null
      scanStatus.value = 'Not Found'
      scanStatusColor.value = 'negative'
      $q.notify({ 
        type: 'warning', 
        message: 'Asset not found in database',
        position: 'top'
      })
    }
  } catch (error) {
    console.error('Scan Error:', error)
    $q.notify({ 
      type: 'negative', 
      message: 'Error scanning asset',
      position: 'top'
    })
  } finally {
    scanning.value = false
    barcode.value = ''
  }
}

const saveScannedAsset = async () => {
  if (!scannedAsset.value) return
  
  saving.value = true
  const asset = scannedAsset.value
  
  try {
    const response = await axios.post(`${API_BASE_URL}/api/inventory/count`, {
      session_id: sessionId.value, 
      asset_id: asset.id,
      actual_asset_id: asset.asset_id,
      actual_serial: asset.serial_number,
      status: 'Match', 
      remarks: 'Scanned via Mobile App'
    })

    assets.value.unshift({
      ...asset,
      inventory_count_id: response.data.id
    })
    
    $q.notify({ 
      type: 'positive', 
      message: 'Asset added to count',
      icon: 'check_circle',
      position: 'top'
    })
    
    // Clear scanned asset
    scannedAsset.value = null
    scanStatus.value = ''
    
  } catch (error) {
    console.error('Save Error:', error)
    $q.notify({ 
      type: 'negative', 
      message: 'Error saving asset',
      position: 'top'
    })
  } finally {
    saving.value = false
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
  if (!selectedAsset.value || !selectedAsset.value.inventory_count_id) return

  try {
    await axios.delete(`${API_BASE_URL}/api/inventory/count/${selectedAsset.value.inventory_count_id}`)
    
    assets.value = assets.value.filter(a => a.inventory_count_id !== selectedAsset.value.inventory_count_id)
    
    $q.notify({ 
      type: 'positive', 
      message: 'Asset removed from count',
      icon: 'delete',
      position: 'top'
    })
  } catch (error) {
    console.error('Delete Error:', error)
    $q.notify({ 
      type: 'negative', 
      message: 'Error deleting asset',
      position: 'top'
    })
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