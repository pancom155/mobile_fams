<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Site;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\Asset;
use App\Models\InventoryCount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ApiController extends Controller
{
    public function getSites()
    {
        return Site::all();
    }

    public function getLocations(Request $request)
    {
        $siteId = $request->query('site_id');
        if ($siteId) {
            return Location::where('site_id', $siteId)->get();
        }
        return Location::all();
    }

    public function storeTransaction(Request $request)
    {
        try {
            $validated = $request->validate([
                'site' => 'required',
                'location' => 'required',
                'date' => 'required',
            ]);

            // Save to JSON instead of DB
            $path = storage_path('app/json_data/transactions.json');
            $transactions = file_exists($path) ? json_decode(file_get_contents($path), true) ?? [] : [];
            
            $maxId = 0;
            if (!empty($transactions)) {
                $maxId = max(array_column($transactions, 'id'));
            }

            $newTransaction = [
                'id' => $maxId + 1,
                'site_id' => $validated['site'],
                'location_id' => $validated['location'],
                'date' => $validated['date'],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];

            $transactions[] = $newTransaction;
            file_put_contents($path, json_encode($transactions, JSON_PRETTY_PRINT));

            return response()->json($newTransaction, 201);
        } catch (\Exception $e) {
            Log::error('Transaction store error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getTransactions()
    {
        // Join with locations to get names if needed, or just return raw
        // For UI "TransactionsPage", it shows location name and date.
        // So we might need to load relationships.
        // But for now, let's just return basic data + location name loaded.
        
        $transactions = Transaction::join('locations', 'transactions.location_id', '=', 'locations.id')
            ->select('transactions.*', 'locations.name as location')
            ->orderBy('transactions.created_at', 'desc')
            ->get();
            
        return $transactions;
    }

    public function lookupAsset(Request $request)
    {
        $barcode = $request->query('barcode');
        
        if (!$barcode) {
            return response()->json(['error' => 'Barcode is required'], 400);
        }

        // Search by asset_id or serial_number
        $assets = Asset::where('asset_id', $barcode)
            ->orWhere('serial_number', $barcode)
            ->get();

        // If no assets found, return empty array (or 404 if preferred, but array is safer for UI list)
        // Frontend expects an array based on: "if (Array.isArray(results) && results.length > 0)"
        
        return $assets;
    }

    public function storeInventoryCount(Request $request)
    {
        try {
            $validated = $request->validate([
                'session_id' => 'nullable|integer',
                'asset_id' => 'required|integer',
                'actual_asset_id' => 'nullable|string',
                'actual_serial' => 'nullable|string',
                'status' => 'nullable|string',
                'remarks' => 'nullable|string',
            ]);

            $path = storage_path('app/json_data/inventory_counts.json');
            $counts = file_exists($path) ? json_decode(file_get_contents($path), true) ?? [] : [];

            $maxId = 0;
            if (!empty($counts)) {
                $maxId = max(array_column($counts, 'id'));
            }

            $newCount = [
                'id' => $maxId + 1,
                'session_id' => $validated['session_id'] ?? null,
                'asset_id' => $validated['asset_id'],
                'actual_asset_id' => $validated['actual_asset_id'] ?? null,
                'actual_serial' => $validated['actual_serial'] ?? null,
                'status' => $validated['status'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];

            $counts[] = $newCount;
            file_put_contents($path, json_encode($counts, JSON_PRETTY_PRINT));

            return response()->json($newCount, 201);
        } catch (\Exception $e) {
            Log::error('Inventory count save error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getInventoryCounts(Request $request)
    {
        $sessionId = $request->query('session_id');

        $query = InventoryCount::join('assets', 'inventory_counts.asset_id', '=', 'assets.id')
            ->select(
                'inventory_counts.*',
                'assets.asset_id as asset_tag', // Alias to avoid collision if needed, or just use assets columns
                'assets.short_description',
                'assets.brand',
                'assets.serial_number'
            )
            ->orderBy('inventory_counts.created_at', 'desc');

        if ($sessionId) {
            $query->where('inventory_counts.session_id', $sessionId);
        }

        return $query->get();
    }

    public function deleteInventoryCount($id)
    {
        try {
            $path = storage_path('app/json_data/inventory_counts.json');
            $counts = file_exists($path) ? json_decode(file_get_contents($path), true) ?? [] : [];

            $id = (int) $id;
            $originalCount = count($counts);
            $counts = array_values(array_filter($counts, function ($item) use ($id) {
                return isset($item['id']) ? (int) $item['id'] !== $id : true;
            }));

            if (count($counts) === $originalCount) {
                throw new \Exception('Inventory count not found');
            }

            file_put_contents($path, json_encode($counts, JSON_PRETTY_PRINT));

            return response()->json(['message' => 'Inventory count deleted successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Inventory count delete error: ' . $e->getMessage());
            return response()->json(['error' => 'Item not found or could not be deleted'], 404);
        }
    }

    public function deleteTransaction($id)
    {
        try {
            $transaction = Transaction::findOrFail($id);
            // Optionally delete associated inventory counts
            // InventoryCount::where('session_id', $id)->delete();
            $transaction->delete();
            return response()->json(['message' => 'Transaction deleted successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Transaction delete error: ' . $e->getMessage());
            return response()->json(['error' => 'Transaction not found or could not be deleted'], 404);
        }
    }
}
