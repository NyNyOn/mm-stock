<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; // ✅ Make sure Auth is imported if used in authorization
use Illuminate\Support\Facades\Log; // ✅ Make sure Log is imported if used

class DisposalController extends Controller
{
    /**
     * Display a listing of equipment awaiting disposal or already sold.
     */
    public function index()
    {
        // ✅ Ensure authorization if needed (e.g., using a Gate or Policy)
        // $this->authorize('viewAny', Equipment::class); // Example

        try {
            // ✅ Eager load primaryImage and latestImage relationships
            $disposals = Equipment::with([
                                'category',
                                'location',
                                'primaryImage', // Load primary image relationship
                                'latestImage'   // Load latest image relationship (fallback)
                            ])
                            ->whereIn('status', ['disposed', 'sold'])
                            ->latest('updated_at')
                            ->paginate(20);

            // ✅ Get the default department key for image URLs
            $defaultDeptKey = config('department_stocks.default_key', 'it');

            return view('disposal.index', compact('disposals', 'defaultDeptKey')); // Pass defaultDeptKey

        } catch (\Exception $e) {
            Log::error('Error loading Disposal index page: ' . $e->getMessage());
            // Consider returning a user-friendly error view or redirecting with an error message
            return redirect()->route('dashboard')->with('error', 'ไม่สามารถโหลดข้อมูลหน้าตัดจำหน่ายได้');
        }
    }

    /**
     * Restore a disposed equipment back to stock.
     */
    public function restore(Equipment $equipment)
    {
        // ✅ Ensure authorization
        // $this->authorize('restore', $equipment); // Example

        if ($equipment->status !== 'disposed') {
            return back()->with('error', 'ไม่สามารถดำเนินการได้ (สถานะไม่ถูกต้อง)');
        }

        DB::beginTransaction();
        try {
            // Attempt to find main stock (using lockForUpdate might be safer if high concurrency)
            $mainStock = Equipment::where('name', $equipment->name)
                                // Consider adding more criteria like part_no if necessary for uniqueness
                                ->where('part_no', $equipment->part_no)
                                ->whereIn('status', ['available', 'low_stock', 'out_of_stock']) // Check against multiple valid stock statuses
                                ->lockForUpdate() // Lock the row during transaction
                                ->first();

            if ($mainStock) {
                // Increment main stock quantity
                $mainStock->increment('quantity', $equipment->quantity);
                 // No need to save() after increment
                
                // Use forceDelete() if you want to permanently remove the disposed record
                $equipment->forceDelete(); // Or delete() for soft delete
                Log::info("Restored disposed item ID {$equipment->id} to main stock ID {$mainStock->id}.");

            } else {
                // If no main stock found, just change status back to available
                $equipment->status = 'available'; // Set status back
                // The Equipment model's boot() method should handle status calculation based on quantity
                // However, explicitly setting it here might be desired for clarity in this specific action.
                // Re-evaluate if the boot() method covers this case sufficiently.
                $equipment->save();
                 Log::info("Restored disposed item ID {$equipment->id} by changing status to 'available' (no main stock found).");
            }

            DB::commit();
            return back()->with('success', "นำอุปกรณ์ '{$equipment->name}' กลับเข้าสต็อกเรียบร้อยแล้ว");

        } catch (\Exception $e) {
            DB::rollBack();
             Log::error("Error restoring equipment ID {$equipment->id}: " . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการคืนสต็อก: ' . $e->getMessage());
        }
    }

    /**
     * Mark a disposed equipment as sold.
     */
    public function markAsSold(Equipment $equipment)
    {
         // ✅ Ensure authorization
        // $this->authorize('markAsSold', $equipment); // Example

        if ($equipment->status === 'disposed') {
            $equipment->status = 'sold'; // Set the new status
            $equipment->save();
            Log::info("Marked equipment ID {$equipment->id} as sold.");
            return back()->with('success', "บันทึกการขายอุปกรณ์ '{$equipment->name}' เรียบร้อยแล้ว");
        }
         Log::warning("Attempted to mark equipment ID {$equipment->id} as sold, but status was '{$equipment->status}'.");
        return back()->with('error', 'ไม่สามารถดำเนินการได้ (สถานะไม่ถูกต้อง)');
    }
}

