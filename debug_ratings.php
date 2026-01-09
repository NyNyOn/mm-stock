

use App\Models\Equipment;
use App\Models\EquipmentRating;

// 1. Find the equipment
$keyword = "รายการทดสอบไอที";
$items = Equipment::where('name', 'like', "%$keyword%")->get();

if ($items->isEmpty()) {
    echo "❌ Equipment not found.\n";
    exit;
}

foreach ($items as $item) {
    echo "\n------------------------------------------------\n";
    echo "✅ Found Equipment: {$item->name} (ID: {$item->id})\n";

    // 2. Direct Rating Check
    $ratings = EquipmentRating::where('equipment_id', $item->id)->get();
    echo "📊 Total Ratings Count: {$ratings->count()}\n";
    foreach ($ratings as $r) {
        echo "   - ID: {$r->id}, Score: " . ($r->rating_score === null ? 'NULL' : $r->rating_score) . ", Comment: {$r->comment}\n";
    }

    // 3. Test withAvg
    $queryItem = Equipment::where('id', $item->id)
        ->withAvg('ratings', 'rating_score')
        ->withCount('ratings')
        ->first();

    echo "\n🔍 Query Result:\n";
    echo "   - ratings_avg_rating_score: " . var_export($queryItem->ratings_avg_rating_score, true) . "\n";
    echo "   - ratings_count: {$queryItem->ratings_count}\n";
}
exit();

