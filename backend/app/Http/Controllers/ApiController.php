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
use Illuminate\Support\Facades\Auth;
use App\Models\SystemUser;
use Illuminate\Support\Facades\Hash;

class ApiController extends Controller
{
    public function submitScan(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|integer',
            'date' => 'nullable|string',
            'scanned' => 'required|array',
            'scanned.*' => 'string',
            'expected' => 'nullable|array',
            'expected.*' => 'string',
        ]);

        $transaction = Transaction::create([
            'site_id' => null,
            'location_id' => $validated['location_id'],
            'date' => $validated['date'] ?? now()->toDateString(),
        ]);

        $scannedTags = collect($validated['scanned'])->map(fn ($t) => trim($t))->filter()->values();
        $expectedTags = collect($validated['expected'] ?? [])->map(fn ($t) => trim($t))->filter()->values();

        $tagToAsset = Asset::whereIn('asset_id', $scannedTags->merge($expectedTags)->unique()->all())
            ->orWhereIn('serial_number', $scannedTags->merge($expectedTags)->unique()->all())
            ->get()
            ->mapWithKeys(function ($a) {
                $keys = [];
                if ($a->asset_id) $keys[$a->asset_id] = $a;
                if ($a->serial_number) $keys[$a->serial_number] = $a;
                return $keys;
            });

        $saved = [];
        foreach ($scannedTags as $tag) {
            $asset = $tagToAsset->get($tag);
            if (!$asset) {
                continue;
            }
            $count = InventoryCount::create([
                'session_id' => $transaction->id,
                'asset_id' => $asset->id,
                'actual_asset_id' => $asset->asset_id,
                'actual_serial' => $asset->serial_number,
                'status' => 'Match',
                'remarks' => '',
                'counted_by' => auth()->id(),
            ]);
            $saved[] = $count;
        }

        $missing = [];
        if ($expectedTags->isNotEmpty()) {
            $missingTags = $expectedTags->diff($scannedTags)->values();
            foreach ($missingTags as $tag) {
                $asset = $tagToAsset->get($tag);
                if (!$asset) {
                    continue;
                }
                $count = InventoryCount::create([
                    'session_id' => $transaction->id,
                    'asset_id' => $asset->id,
                    'actual_asset_id' => $asset->asset_id,
                    'actual_serial' => $asset->serial_number,
                    'status' => 'Missing',
                    'remarks' => 'Not scanned',
                    'counted_by' => auth()->id(),
                ]);
                $missing[] = $count;
            }
        }

        return response()->json([
            'session_id' => $transaction->id,
            'location_id' => $transaction->location_id,
            'saved_count' => count($saved),
            'missing_count' => count($missing),
            'items' => InventoryCount::where('session_id', $transaction->id)->get(),
        ], 201);
    }
    public function login(Request $request)
    {
        Log::info('Login attempt for email: ' . $request->email);

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = SystemUser::where('email', $credentials['email'])->first();

        if (!$user) {
            Log::warning('Login failed: User not found for email ' . $credentials['email']);
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!Hash::check($credentials['password'], $user->password_hash)) {
            Log::warning('Login failed: Password mismatch for user ' . $credentials['email']);
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        Log::info('Login successful for user: ' . $user->email);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
            'message' => 'Login successful'
        ]);
    }

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

            // Save to Database
            $transaction = Transaction::create([
                'site_id' => $validated['site'],
                'location_id' => $validated['location'],
                'date' => $validated['date'],
            ]);

            return response()->json($transaction, 201);
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

            // Save to Database
            $count = InventoryCount::create([
                'session_id' => $validated['session_id'],
                'asset_id' => $validated['asset_id'],
                'actual_asset_id' => $validated['actual_asset_id'],
                'actual_serial' => $validated['actual_serial'],
                'status' => $validated['status'] ?? 'Match',
                'remarks' => $validated['remarks'] ?? '',
                'counted_by' => auth()->id(),
            ]);

            return response()->json($count, 201);
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
                'assets.asset_id as asset_tag',
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

    public function getInventoryCountsByLocation(Request $request)
    {
        $locationId = $request->query('location_id');
        if (!$locationId) {
            return response()->json(['error' => 'location_id is required'], 400);
        }
        $query = InventoryCount::join('assets', 'inventory_counts.asset_id', '=', 'assets.id')
            ->join('transactions', 'inventory_counts.session_id', '=', 'transactions.id')
            ->where('transactions.location_id', $locationId)
            ->select(
                'inventory_counts.*',
                'assets.asset_id as asset_tag',
                'assets.short_description',
                'assets.brand',
                'assets.serial_number'
            )
            ->orderBy('inventory_counts.created_at', 'desc');
        return $query->get();
    }

    public function getAssetsByLocation(Request $request)
    {
        $locationId = $request->query('location_id');
        if (!$locationId) {
            return response()->json(['error' => 'location_id is required'], 400);
        }
        return Asset::where('location_id', $locationId)->get();
    }

    public function deleteInventoryCount($id)
    {
        try {
            $count = InventoryCount::findOrFail($id);
            $count->delete();
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
            InventoryCount::where('session_id', $id)->delete();
            $transaction->delete();
            return response()->json(['message' => 'Transaction deleted successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Transaction delete error: ' . $e->getMessage());
            return response()->json(['error' => 'Transaction not found or could not be deleted'], 404);
        }
    }
}
