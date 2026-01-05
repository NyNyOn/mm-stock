<?php

use App\Models\Equipment;

// Mock an equipment
$equipment = new Equipment();
$equipment->name = 'Test Item';
$equipment->quantity = 1;
$equipment->min_stock = 0;
$equipment->status = 'available';

// Test case 1: Quantity 1, Min Stock 0 -> Should be available
$equipment->save();
echo "Case 1 (Q:1, Min:0): " . $equipment->status . "\n";

// Test case 2: Quantity 0 -> Should be out_of_stock
$equipment->quantity = 0;
$equipment->save();
echo "Case 2 (Q:0): " . $equipment->status . "\n";

// Test case 3: Quantity 1, Min Stock 5 -> Should be low_stock
$equipment->quantity = 1;
$equipment->min_stock = 5;
$equipment->save();
echo "Case 3 (Q:1, Min:5): " . $equipment->status . "\n";

// Test case 4: User scenario - Quantity 1, Manual Status?
$equipment->quantity = 1;
$equipment->min_stock = 0;
$equipment->status = 'out_of_stock'; // Force set wrong status
$equipment->save(); // Should auto-correct to available
echo "Case 4 (Q:1, Set:out_of_stock): " . $equipment->status . "\n";
