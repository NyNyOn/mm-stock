<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Equipment;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\PurchaseOrder;
use App\Models\ConsumableReturn;
use App\Models\User;
use Carbon\Carbon;

class ReportController extends Controller
{
    // ✅ (1/2) เพิ่ม Request $request เป็น parameter
    public function index(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $users = User::orderBy('fullname')->get();

        // ✅ (2/2) ดึงค่า report_type จาก query string, ถ้าไม่มีให้เป็น null
        $initialReportType = $request->query('report_type');

        // ส่งค่า $initialReportType ไปยัง view ด้วย
        return view('reports.index', compact('categories', 'locations', 'users', 'initialReportType'));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            // ... validation rules ...
            'report_type' => 'required|string|in:stock_summary,transaction_history,borrow_report,low_stock,warranty,maintenance_report,po_report,disposal_report,consumable_return_report,user_activity_report',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'category_id' => 'sometimes|nullable|integer|exists:categories,id',
            'location_id' => 'sometimes|nullable|integer|exists:locations,id',
            'user_id' => 'sometimes|nullable|integer|exists:depart_it_db.sync_ldap,id',
        ]);

        $reportType = $validated['report_type'];
        $data = [];

        // ... (switch case for generating reports - no changes needed here) ...
        switch ($reportType) {
            case 'stock_summary':
                $data = $this->generateStockSummary($validated);
                break;
            case 'transaction_history':
                $data = $this->generateTransactionHistory($validated);
                break;
            case 'borrow_report':
                $data = $this->generateBorrowReport($validated);
                break;
            case 'low_stock':
                $data = $this->generateLowStockReport($validated);
                break;
            case 'warranty':
                $data = $this->generateWarrantyReport($validated);
                break;
            case 'maintenance_report':
                $data = $this->generateMaintenanceReport($validated);
                break;
            case 'po_report':
                $data = $this->generatePoReport($validated);
                break;
            case 'disposal_report':
                $data = $this->generateDisposalReport($validated);
                break;
            case 'consumable_return_report':
                $data = $this->generateConsumableReturnReport($validated);
                break;
            case 'user_activity_report':
                $data = $this->generateUserActivityReport($validated);
                break;
        }


        return response()->json($data);
    }

    // ... (private helper functions generate...Report - no changes needed here) ...
     private function applyDateFilter($query, array $filters, $dateColumn = 'created_at')
    {
        return $query->when($filters['start_date'] ?? null, function ($q, $startDate) use ($filters, $dateColumn) {
            $endDate = Carbon::parse($filters['end_date'] ?? now())->endOfDay();
            return $q->whereBetween($dateColumn, [Carbon::parse($startDate)->startOfDay(), $endDate]);
        });
    }

    private function generateMaintenanceReport(array $filters)
    {
        $query = MaintenanceLog::with(['equipment.unit', 'reportedBy']);
        return $this->applyDateFilter($query, $filters)->orderBy('created_at', 'desc')->get();
    }

    private function generatePoReport(array $filters)
    {
        $query = PurchaseOrder::with(['items.equipment', 'orderedBy']);
        return $this->applyDateFilter($query, $filters, 'ordered_at')->orderBy('ordered_at', 'desc')->get();
    }

    private function generateDisposalReport(array $filters)
    {
        $query = Equipment::with(['category', 'location'])->whereIn('status', ['disposed', 'sold']);
        return $this->applyDateFilter($query, $filters, 'updated_at')->orderBy('updated_at', 'desc')->get();
    }

    private function generateConsumableReturnReport(array $filters)
    {
        $query = ConsumableReturn::with(['originalTransaction.equipment', 'requester', 'approver']);
        return $this->applyDateFilter($query, $filters)->orderBy('created_at', 'desc')->get();
    }

    private function generateUserActivityReport(array $filters)
    {
        if (empty($filters['user_id'])) {
            return [];
        }
        $query = Transaction::with(['equipment'])->where('user_id', $filters['user_id']);
        return $this->applyDateFilter($query, $filters, 'transaction_date')->orderBy('transaction_date', 'desc')->get();
    }

    private function generateStockSummary(array $filters)
    {
        $query = Equipment::with(['category', 'location', 'unit'])
            ->when($filters['category_id'] ?? null, function ($q, $categoryId) {
                return $q->where('category_id', $categoryId);
            })
            ->when($filters['location_id'] ?? null, function ($q, $locationId) {
                return $q->where('location_id', $locationId);
            });
        return $query->orderBy('name')->get();
    }

    private function generateTransactionHistory(array $filters)
    {
        $query = Transaction::with(['equipment', 'user'])
            ->when($filters['start_date'] ?? null, function ($q, $startDate) use ($filters) {
                $endDate = Carbon::parse($filters['end_date'] ?? now())->endOfDay();
                return $q->whereBetween('transaction_date', [Carbon::parse($startDate)->startOfDay(), $endDate]);
            })
            ->when($filters['category_id'] ?? null, function ($q, $categoryId) {
                return $q->whereHas('equipment', function($subQuery) use ($categoryId) {
                    $subQuery->where('category_id', $categoryId);
                });
            });
        return $query->orderBy('transaction_date', 'desc')->get();
    }

    private function generateBorrowReport(array $filters)
    {
        $query = Transaction::with(['equipment', 'user'])
            ->where('type', 'borrow')
            ->where('status', 'completed'); // Should probably be based on returned_quantity vs quantity_change
        return $this->applyDateFilter($query, $filters, 'transaction_date')
            ->orderBy('transaction_date', 'asc')->get();
    }

    private function generateLowStockReport(array $filters)
    {
        $query = Equipment::with(['category', 'location', 'unit']) // Include unit
            ->whereColumn('quantity', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->when($filters['category_id'] ?? null, function ($q, $categoryId) {
                return $q->where('category_id', $categoryId);
            })
            ->when($filters['location_id'] ?? null, function ($q, $locationId) {
                return $q->where('location_id', $locationId);
            });

        return $query->orderBy('quantity', 'asc')->get();
    }

    private function generateWarrantyReport(array $filters)
    {
        $query = Equipment::with(['category', 'location'])
            ->whereNotNull('warranty_date')
            ->when($filters['start_date'] ?? null, function ($q, $startDate) use ($filters) {
                $endDate = Carbon::parse($filters['end_date'] ?? now())->endOfDay();
                return $q->whereBetween('warranty_date', [$startDate, $endDate]);
            }, function ($q) {
                // Default: show items expiring within the next 60 days if no date range is given
                return $q->where('warranty_date', '>=', now()->startOfDay()) // Only future/today
                         ->where('warranty_date', '<=', now()->addDays(60)->endOfDay());
            })
            ->when($filters['category_id'] ?? null, function ($q, $categoryId) {
                return $q->where('category_id', $categoryId);
            });

        return $query->orderBy('warranty_date', 'asc')->get();
    }
}
