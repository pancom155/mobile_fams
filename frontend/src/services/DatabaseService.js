import { CapacitorSQLite, SQLiteConnection } from '@capacitor-community/sqlite';
import { Network } from '@capacitor/network';
import axios from 'axios';
import { API_BASE_URL } from '../config/api';

// Add axios interceptor for auth token
axios.interceptors.request.use(config => {
  const token = localStorage.getItem('fams_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

const sqlite = new SQLiteConnection(CapacitorSQLite);
let db = null;
let isSyncing = false;
let lastSyncTime = 0;
const SYNC_COOLDOWN = 30000; // 30 seconds cooldown between full syncs

export const DatabaseService = {
  async initDb() {
    try {
      db = await sqlite.createConnection('inventory_db', false, 'no-encryption', 1, false);
      await db.open();

      const schema = `
        CREATE TABLE IF NOT EXISTS sites (
          id INTEGER PRIMARY KEY NOT NULL,
          name TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS locations (
          id INTEGER PRIMARY KEY NOT NULL,
          site_id INTEGER NOT NULL,
          name TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS assets (
          id INTEGER PRIMARY KEY NOT NULL,
          asset_id TEXT NOT NULL,
          short_description TEXT,
          brand TEXT,
          serial_number TEXT,
          location_id INTEGER
        );
        CREATE TABLE IF NOT EXISTS transactions (
          local_id INTEGER PRIMARY KEY AUTOINCREMENT,
          id INTEGER NULL, 
          site_id INTEGER NOT NULL,
          location_id INTEGER NOT NULL,
          date TEXT NOT NULL,
          sync_status TEXT DEFAULT 'pending'
        );
        CREATE TABLE IF NOT EXISTS inventory_counts (
          local_id INTEGER PRIMARY KEY AUTOINCREMENT,
          id INTEGER NULL,
          session_id TEXT NOT NULL,
          asset_id INTEGER NOT NULL,
          actual_asset_id TEXT,
          actual_serial TEXT,
          status TEXT,
          remarks TEXT,
          sync_status TEXT DEFAULT 'pending'
        );
      `;
      await db.execute(schema);
      console.log('Local SQLite Database initialized successfully!');
      try {
        await db.run('ALTER TABLE assets ADD COLUMN location_id INTEGER');
      } catch (e) {}
      try {
        await db.run('CREATE UNIQUE INDEX IF NOT EXISTS idx_transactions_id ON transactions(id) WHERE id IS NOT NULL');
      } catch (e) {}
      try {
        await db.run('CREATE UNIQUE INDEX IF NOT EXISTS idx_inventory_counts_id ON inventory_counts(id) WHERE id IS NOT NULL');
      } catch (e) {}
      
      // Initial sync if online
      const status = await Network.getStatus();
      if (status.connected) {
        // Run sync in background without awaiting to speed up app startup
        setTimeout(() => this.syncAll(), 2000); 
      }
    } catch (error) {
      console.error('SQLite Init Error:', error);
    }
  },

  async isOnline() {
    try {
      const status = await Network.getStatus();
      return status.connected;
    } catch (e) {
      return false;
    }
  },

  // --- SYNCHRONIZATION ---
  async syncAll() {
    if (isSyncing) return;
    
    const now = Date.now();
    if (now - lastSyncTime < SYNC_COOLDOWN) {
      console.log('Sync skipped: cooldown period active');
      return;
    }

    // 1. Check basic network status
    if (!(await this.isOnline())) {
      console.log('Sync skipped: device is offline');
      return;
    }
    
    // 2. Check if API is actually reachable
    try {
      console.log(`Checking API health at: ${API_BASE_URL}/api/test-log`);
      await axios.get(`${API_BASE_URL}/api/test-log`, { timeout: 5000 });
      console.log('API Health Check Passed!');
    } catch (e) {
      console.error(`API Health Check Failed at ${API_BASE_URL}/api/test-log: ${e.message}`);
      console.error('Likely causes: 1. Server not running, 2. Wrong IP/Port in api.js, 3. Firewall blocking port 8001.');
      return;
    }
    
    // 3. Require authentication for server sync
    const token = localStorage.getItem('fams_token');
    if (!token) {
      console.warn('Skip full sync: not authenticated');
      return;
    }

    isSyncing = true;
    console.log('Starting full synchronization...');
    try {
      await this.syncFromServer();
      await this.syncToServer();
      lastSyncTime = Date.now();
      console.log('Full synchronization completed!');
    } catch (error) {
      console.error('Sync error:', error);
    } finally {
      isSyncing = false;
    }
  },

  async syncFromServer() {
    const url = `${API_BASE_URL}/api/sync/download`;
    console.log(`Syncing from server (MySQL -> SQLite) via: ${url}`);
    try {
      const res = await axios.get(url, {
        timeout: 60000 // 60 second timeout for large data/slow network
      });
      const data = res.data;

      if (data.sites) await this.syncSitesToLocal(data.sites);
      if (data.locations) await this.syncLocationsToLocal(data.locations);
      if (data.assets) await this.syncAssetsToLocal(data.assets);
      if (data.transactions) await this.syncTransactionsToLocal(data.transactions);
      if (data.inventory_count_items) await this.syncInventoryCountsToLocal(data.inventory_count_items);
      
      console.log('Successfully synced data from server!');
    } catch (error) {
      console.error('Error syncing from server:', error.message);
      if (error.code === 'ECONNABORTED') {
        console.error('Sync Timeout: The server took too long to respond.');
      } else if (error.response) {
        console.error('Server responded with:', error.response.status, error.response.data);
      } else if (error.request) {
        console.error('No response received. Check if your server is running at:', API_BASE_URL);
        console.error('Reminder: Run "php artisan serve --host=0.0.0.0 --port=8001"');
      }
    }
  },

  async syncToServer() {
    console.log('Syncing to server (SQLite -> MySQL)...');
    try {
      const token = localStorage.getItem('fams_token');
      if (!token) {
        console.warn('Skip sync to server: not authenticated');
        return;
      }
      // 1. Sync pending transactions
      const pendingTransactions = await this.getPendingTransactions();
      for (const t of pendingTransactions) {
        try {
          const res = await axios.post(`${API_BASE_URL}/api/transactions`, {
            site: t.site_id,
            location: t.location_id,
            date: t.date
          }, { timeout: 30000 });
          
          if (res.data && res.data.id) {
            await this.markTransactionSynced(t.local_id, res.data.id);
            await this.updateInventorySessionIds(`local_${t.local_id}`, res.data.id);
          }
        } catch (e) {
          console.error(`Failed to sync transaction ${t.local_id}:`, e.message);
        }
      }

      // 2. Sync pending inventory counts
      const pendingCounts = await this.getPendingInventoryCounts();
      for (const c of pendingCounts) {
        if (c.session_id.toString().startsWith('local_')) {
          continue;
        }

        try {
          const res = await axios.post(`${API_BASE_URL}/api/inventory/count`, {
            session_id: c.session_id,
            asset_id: c.asset_id,
            actual_asset_id: c.actual_asset_id,
            actual_serial: c.actual_serial,
            status: c.status,
            remarks: c.remarks
          }, { timeout: 30000 });
          
          if (res.data && res.data.id) {
            await this.markInventoryCountSynced(c.local_id, res.data.id);
          }
        } catch (e) {
          console.error(`Failed to sync count ${c.local_id}:`, e.message);
        }
      }
    } catch (error) {
      console.error('Error syncing to server:', error);
    }
  },

  // --- SITES & LOCATIONS ---
  async syncSitesToLocal(sitesArray) {
    if (!db || !sitesArray.length) return;
    try {
      const statements = [
        { statement: 'DELETE FROM sites', values: [] },
        ...sitesArray.map(site => ({
          statement: 'INSERT INTO sites (id, name) VALUES (?, ?)',
          values: [site.id, site.name]
        }))
      ];
      await db.executeSet(statements);
    } catch (e) {
      console.error('Error syncing sites to local:', e);
    }
  },
  async getLocalSites() {
    if (!db) return [];
    try {
      const res = await db.query('SELECT * FROM sites');
      return res.values || [];
    } catch (e) {
      return [];
    }
  },
  async syncLocationsToLocal(locationsArray) {
    if (!db || !locationsArray.length) return;
    try {
      const statements = [
        { statement: 'DELETE FROM locations', values: [] },
        ...locationsArray.map(loc => ({
          statement: 'INSERT INTO locations (id, site_id, name) VALUES (?, ?, ?)',
          values: [loc.id, loc.site_id, loc.name]
        }))
      ];
      await db.executeSet(statements);
    } catch (e) {
      console.error('Error syncing locations to local:', e);
    }
  },
  async getLocalLocations() {
    if (!db) return [];
    try {
      const res = await db.query('SELECT * FROM locations');
      return res.values || [];
    } catch (e) {
      return [];
    }
  },

  // --- ASSETS ---
  async syncAssetsToLocal(assetsArray) {
    if (!db || !assetsArray.length) return;
    try {
      console.log(`Syncing ${assetsArray.length} assets to local...`);
      const statements = [
        { statement: 'DELETE FROM assets', values: [] }
      ];
      
      // Use smaller batch size for better memory management on low-end devices
      const batchSize = 200; 
      for (let i = 0; i < assetsArray.length; i += batchSize) {
        const batch = assetsArray.slice(i, i + batchSize).map(asset => ({
          statement: 'INSERT INTO assets (id, asset_id, short_description, brand, serial_number, location_id) VALUES (?, ?, ?, ?, ?, ?)',
          values: [asset.id, asset.asset_id, asset.short_description, asset.brand, asset.serial_number, asset.location_id ?? null]
        }));
        await db.executeSet(i === 0 ? [...statements, ...batch] : batch);
        
        // Brief pause every 5 batches to let the CPU breathe
        if (i % (batchSize * 5) === 0) {
          await new Promise(resolve => setTimeout(resolve, 50));
        }
      }
      console.log('Assets sync completed');
    } catch (e) {
      console.error('Error syncing assets to local:', e);
    }
  },
  async getTransactionBySessionId(sessionId) {
    if (!db) return null;
    try {
      if (sessionId.toString().startsWith('local_')) {
        const localId = parseInt(sessionId.replace('local_', ''), 10);
        const res = await db.query('SELECT * FROM transactions WHERE local_id = ?', [localId]);
        const rows = res.values || [];
        return rows.length > 0 ? rows[0] : null;
      } else {
        const res = await db.query('SELECT * FROM transactions WHERE id = ?', [sessionId]);
        const rows = res.values || [];
        return rows.length > 0 ? rows[0] : null;
      }
    } catch (e) {
      return null;
    }
  },
  async getLocalTransactionsByLocation(locationId) {
    if (!db) return [];
    try {
      const res = await db.query('SELECT * FROM transactions WHERE location_id = ?', [locationId]);
      return res.values || [];
    } catch (e) {
      return [];
    }
  },
  async getLocalInventoryCountsWithAssetsBySession(sessionId) {
    if (!db) return [];
    try {
      const res = await db.query(
        'SELECT inventory_counts.*, assets.asset_id as asset_tag, assets.short_description, assets.brand, assets.serial_number FROM inventory_counts JOIN assets ON assets.id = inventory_counts.asset_id WHERE inventory_counts.session_id = ?',
        [sessionId]
      );
      return res.values || [];
    } catch (e) {
      return [];
    }
  },
  async getLocalInventoryCountsByLocation(locationId) {
    if (!db) return [];
    try {
      const txs = await this.getLocalTransactionsByLocation(locationId);
      const rows = [];
      for (const t of txs) {
        if (t.id) {
          const part = await this.getLocalInventoryCountsWithAssetsBySession(t.id.toString());
          rows.push(...part);
        }
        const localSessionId = `local_${t.local_id}`;
        const partLocal = await this.getLocalInventoryCountsWithAssetsBySession(localSessionId);
        rows.push(...partLocal);
      }
      return rows;
    } catch (e) {
      return [];
    }
  },
  async getLocalAssetsByLocation(locationId) {
    if (!db) return [];
    try {
      const res = await db.query('SELECT * FROM assets WHERE location_id = ?', [locationId]);
      return res.values || [];
    } catch (e) {
      return [];
    }
  },
  async lookupLocalAsset(barcode) {
    if (!db) return [];
    try {
      const res = await db.query('SELECT * FROM assets WHERE asset_id = ? OR serial_number = ?', [barcode, barcode]);
      return res.values || [];
    } catch (e) {
      return [];
    }
  },

  // --- TRANSACTIONS ---
  async syncTransactionsToLocal(transactionsArray) {
    if (!db || !transactionsArray.length) return;
    try {
      const statements = [];
      for (const t of transactionsArray) {
        statements.push({
          statement: 'INSERT OR REPLACE INTO transactions (id, site_id, location_id, date, sync_status) VALUES (?, ?, ?, ?, ?)',
          values: [t.id, t.site_id, t.location_id, t.date, 'synced']
        });
      }
      await db.executeSet(statements);
    } catch (e) {
      console.error('Error syncing transactions to local:', e);
    }
  },
  async saveTransactionLocally(siteId, locationId, date, mysqlId = null) {
    if (!db) return null;
    try {
      const syncStatus = mysqlId ? 'synced' : 'pending';
      const res = await db.run(
        'INSERT INTO transactions (id, site_id, location_id, date, sync_status) VALUES (?, ?, ?, ?, ?)',
        [mysqlId, siteId, locationId, date, syncStatus]
      );
      return res.changes.lastId;
    } catch (e) {
      console.error('Error saving transaction locally:', e);
      return null;
    }
  },
  async getLocalTransactions() {
    if (!db) return [];
    try {
      const res = await db.query('SELECT * FROM transactions');
      return res.values || [];
    } catch (e) {
      return [];
    }
  },
  async getPendingTransactions() {
    if (!db) return [];
    try {
      const res = await db.query("SELECT * FROM transactions WHERE sync_status = 'pending'");
      return res.values || [];
    } catch (e) {
      return [];
    }
  },
  async markTransactionSynced(localId, mysqlId) {
    if (!db) return;
    try {
      await db.run("UPDATE transactions SET id = ?, sync_status = 'synced' WHERE local_id = ?", [mysqlId, localId]);
    } catch (e) {
      console.error('Error marking transaction synced:', e);
    }
  },

  // --- INVENTORY COUNTS ---
  async syncInventoryCountsToLocal(countsArray) {
    if (!db || !countsArray.length) return;
    try {
      const statements = [];
      for (const c of countsArray) {
        statements.push({
          statement: 'INSERT OR REPLACE INTO inventory_counts (id, session_id, asset_id, actual_asset_id, actual_serial, status, remarks, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
          values: [c.id, c.session_id, c.asset_id, c.actual_asset_id, c.actual_serial, c.status, c.remarks, 'synced']
        });
      }
      await db.executeSet(statements);
    } catch (e) {
      console.error('Error syncing inventory counts to local:', e);
    }
  },
  async saveInventoryCountLocally(countData, mysqlId = null) {
    if (!db) return null;
    try {
      const syncStatus = mysqlId ? 'synced' : 'pending';
      const res = await db.run(
        `INSERT INTO inventory_counts (id, session_id, asset_id, actual_asset_id, actual_serial, status, remarks, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [mysqlId, countData.session_id, countData.asset_id, countData.actual_asset_id || null, countData.actual_serial || null, countData.status || null, countData.remarks || null, syncStatus]
      );
      return res.changes.lastId;
    } catch (e) {
      console.error('Error saving inventory count locally:', e);
      return null;
    }
  },
  async getLocalInventoryCounts(sessionId) {
    if (!db) return [];
    try {
      const res = await db.query('SELECT * FROM inventory_counts WHERE session_id = ?', [sessionId]);
      return res.values || [];
    } catch (e) {
      return [];
    }
  },
  async getPendingInventoryCounts() {
    if (!db) return [];
    try {
      const res = await db.query("SELECT * FROM inventory_counts WHERE sync_status = 'pending'");
      return res.values || [];
    } catch (e) {
      return [];
    }
  },
  async updateInventorySessionIds(oldLocalSessionId, newMysqlSessionId) {
    if (!db) return;
    try {
      await db.run("UPDATE inventory_counts SET session_id = ? WHERE session_id = ?", [newMysqlSessionId, oldLocalSessionId]);
    } catch (e) {
      console.error('Error updating inventory session ids:', e);
    }
  },
  async markInventoryCountSynced(localId, mysqlId) {
    if (!db) return;
    try {
      await db.run("UPDATE inventory_counts SET id = ?, sync_status = 'synced' WHERE local_id = ?", [mysqlId, localId]);
    } catch (e) {
      console.error('Error marking inventory count synced:', e);
    }
  },
  async deleteLocalInventoryCount(localId) {
    if (!db) return;
    try {
      await db.run('DELETE FROM inventory_counts WHERE local_id = ?', [localId]);
    } catch (e) {
      console.error('Error deleting local inventory count:', e);
    }
  },
  async deleteLocalInventoryCountsBySession(sessionId) {
    if (!db) return;
    try {
      await db.run('DELETE FROM inventory_counts WHERE session_id = ?', [sessionId]);
    } catch (e) {
      console.error('Error deleting local inventory counts by session:', e);
    }
  },
  async deleteLocalTransactionBySessionId(sessionId) {
    if (!db) return;
    try {
      if (sessionId.toString().startsWith('local_')) {
        const localId = parseInt(sessionId.replace('local_', ''), 10);
        await db.run('DELETE FROM transactions WHERE local_id = ?', [localId]);
      } else {
        await db.run('DELETE FROM transactions WHERE id = ?', [sessionId]);
      }
    } catch (e) {
      console.error('Error deleting local transaction:', e);
    }
  }
};
