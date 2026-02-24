try {
    echo "Testing connection...\n";
    DB::connection()->getPdo();
    echo "Connected to " . DB::connection()->getDatabaseName() . "\n";
    echo "Has sites: " . (Schema::hasTable("sites") ? "Yes" : "No") . "\n";
    echo "Has locations: " . (Schema::hasTable("locations") ? "Yes" : "No") . "\n";
    if (Schema::hasTable("sites")) {
        echo "Sites count: " . \App\Models\Site::count() . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
exit;
