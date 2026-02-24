<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Site;
use App\Models\Transaction;
use App\Models\InventoryCount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncController extends Controller
{
    private $jsonStoragePath = 'json_data';

    /**
     * List all JSON files available for sync.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function listFiles()
    {
        try {
            if (!Storage::exists($this->jsonStoragePath)) {
                return response()->json([], 200);
            }

            $files = Storage::files($this->jsonStoragePath);
            $fileList = [];

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                    $filename = basename($file);
                    $lastModified = Storage::lastModified($file);
                    
                    $fileList[] = [
                        'name' => $filename,
                        'updated_at' => date('Y-m-d H:i:s', $lastModified),
                        'url' => url('/api/sync/json/' . $filename)
                    ];
                }
            }

            return response()->json($fileList);
        } catch (\Exception $e) {
            Log::error('List files error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to list files'], 500);
        }
    }

    /**
     * Get specific JSON file content.
     * 
     * @param string $filename
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function getFile($filename)
    {
        try {
            $path = $this->jsonStoragePath . '/' . $filename;
            
            if (!Storage::exists($path)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            $content = Storage::get($path);
            $data = json_decode($content, true);

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Get file error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get file'], 500);
        }
    }

    /**
     * Store a new transaction to the JSON file.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeTransactionJson(Request $request)
    {
        try {
            $validated = $request->validate([
                'site' => 'required',
                'location' => 'required',
                'date' => 'required',
            ]);

            $path = $this->jsonStoragePath . '/transactions.json';
            
            if (!Storage::exists($path)) {
                // If file doesn't exist, start with empty array
                $transactions = [];
            } else {
                $content = Storage::get($path);
                $transactions = json_decode($content, true) ?? [];
            }

            // Generate new ID
            $maxId = 0;
            foreach ($transactions as $t) {
                if (isset($t['id']) && $t['id'] > $maxId) {
                    $maxId = $t['id'];
                }
            }
            $newId = $maxId + 1;

            $newTransaction = [
                'id' => $newId,
                'site_id' => $validated['site'],
                'location_id' => $validated['location'],
                'date' => $validated['date'],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];

            $transactions[] = $newTransaction;

            Storage::put($path, json_encode($transactions, JSON_PRETTY_PRINT));

            return response()->json($newTransaction, 201);

        } catch (\Exception $e) {
            Log::error('Transaction store JSON error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a new inventory count to the JSON file.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeInventoryCountJson(Request $request)
    {
        try {
            $validated = $request->validate([
                'session_id' => 'required',
                'asset_id' => 'required', // This is the Asset ID (PK) from assets.json
                'actual_asset_id' => 'nullable|string', // The barcode/tag
                'actual_serial' => 'nullable|string',
                'status' => 'nullable|string',
                'remarks' => 'nullable|string',
            ]);

            $path = $this->jsonStoragePath . '/inventory_counts.json';
            
            if (!Storage::exists($path)) {
                $counts = [];
            } else {
                $content = Storage::get($path);
                $counts = json_decode($content, true) ?? [];
            }

            // Generate new ID
            $maxId = 0;
            foreach ($counts as $c) {
                if (isset($c['id']) && $c['id'] > $maxId) {
                    $maxId = $c['id'];
                }
            }
            $newId = $maxId + 1;

            $newCount = [
                'id' => $newId,
                'session_id' => $validated['session_id'],
                'asset_id' => $validated['asset_id'],
                'actual_asset_id' => $validated['actual_asset_id'] ?? null,
                'actual_serial' => $validated['actual_serial'] ?? null,
                'status' => $validated['status'] ?? 'Match',
                'remarks' => $validated['remarks'] ?? null,
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];

            $counts[] = $newCount;

            Storage::put($path, json_encode($counts, JSON_PRETTY_PRINT));

            return response()->json($newCount, 201);

        } catch (\Exception $e) {
            Log::error('Inventory count store JSON error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Download all necessary data for offline use.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function download()
    {
        try {
            // Fetch all data from the tables
            $assets = Asset::all();
            $locations = Location::all();
            $sites = Site::all();
            
            // Assuming 'transactions' table serves as 'inventory_count_sessions'
            $transactions = Transaction::all(); 
            
            // 'inventory_counts' table serves as 'inventory_count_items'
            $inventoryCountItems = InventoryCount::all();

            return response()->json([
                'assets' => $assets,
                'locations' => $locations,
                'sites' => $sites,
                'transactions' => $transactions,
                'inventory_count_items' => $inventoryCountItems,
                // If the frontend explicitly expects 'inventory_count_sessions', we can map transactions to it
                'inventory_count_sessions' => $transactions, 
            ]);
        } catch (\Exception $e) {
            Log::error('Sync download error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to download data'], 500);
        }
    }

    /**
     * Upload new data created offline.
     * Expects a JSON payload with 'transactions' and their related items.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        // We use a transaction to ensure data integrity
        DB::beginTransaction();

        try {
            $data = $request->all();
            $syncedTransactions = [];
            
            // Handle new transactions (sessions)
            if (isset($data['transactions']) && is_array($data['transactions'])) {
                foreach ($data['transactions'] as $transData) {
                    // Create the transaction
                    // We assume the client sends the data required for creation
                    // We ignore 'id' if sent, letting DB auto-increment
                    
                    $transaction = new Transaction();
                    $transaction->site_id = $transData['site_id'] ?? null;
                    $transaction->location_id = $transData['location_id'] ?? null;
                    $transaction->date = $transData['date'] ?? now();
                    $transaction->save();

                    // If the transaction has items (nested), save them
                    if (isset($transData['items']) && is_array($transData['items'])) {
                        foreach ($transData['items'] as $itemData) {
                            $this->createInventoryCount($itemData, $transaction->id);
                        }
                    }
                    
                    // If items are sent separately but linked via a temporary ID, logic would be more complex.
                    // For now, we assume nested structure or that the client handles the mapping logic 
                    // (e.g. sending one request per transaction).
                    
                    $syncedTransactions[] = $transaction;
                }
            }

            // Handle standalone inventory items if sent separately (optional)
            if (isset($data['inventory_count_items']) && is_array($data['inventory_count_items'])) {
                foreach ($data['inventory_count_items'] as $itemData) {
                     // Note: If these items belong to a NEW transaction, they should be nested in 'transactions'.
                     // If they belong to an EXISTING transaction (created previously online), 
                     // 'session_id' must be valid.
                     $this->createInventoryCount($itemData, $itemData['session_id'] ?? null);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Sync successful', 'synced_transactions' => $syncedTransactions], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sync upload error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to sync data: ' . $e->getMessage()], 500);
        }
    }

    private function createInventoryCount($data, $sessionId)
    {
        $count = new InventoryCount();
        $count->session_id = $sessionId;
        $count->asset_id = $data['asset_id'];
        $count->actual_asset_id = $data['actual_asset_id'] ?? null;
        $count->actual_serial = $data['actual_serial'] ?? null;
        $count->status = $data['status'] ?? 'Match';
        $count->remarks = $data['remarks'] ?? null;
        $count->save();
        return $count;
    }
}
