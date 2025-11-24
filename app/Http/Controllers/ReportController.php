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
use App\Models\Changelog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $users = User::orderBy('fullname')->get();
        $initialReportType = $request->query('report_type');

        return view('reports.index', compact('categories', 'locations', 'users', 'initialReportType'));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|string|in:stock_summary,transaction_history,borrow_report,low_stock,warranty,maintenance_report,po_report,disposal_report,consumable_return_report,user_activity_report,inventory_valuation,department_cost,top_movers,dead_stock,audit_logs',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'category_id' => 'sometimes|nullable|integer|exists:categories,id',
            'location_id' => 'sometimes|nullable|integer|exists:locations,id',
            'user_id' => 'sometimes|nullable|integer|exists:users,id',
        ]);

        $reportType = $validated['report_type'];
        $data = [];

        switch ($reportType) {
            case 'stock_summary': $data = $this->generateStockSummary($validated); break;
            case 'transaction_history': $data = $this->generateTransactionHistory($validated); break;
            case 'borrow_report': $data = $this->generateBorrowReport($validated); break;
            case 'low_stock': $data = $this->generateLowStockReport($validated); break;
            case 'warranty': $data = $this->generateWarrantyReport($validated); break;
            case 'maintenance_report': $data = $this->generateMaintenanceReport($validated); break;
            case 'po_report': $data = $this->generatePoReport($validated); break;
            case 'disposal_report': $data = $this->generateDisposalReport($validated); break;
            case 'consumable_return_report': $data = $this->generateConsumableReturnReport($validated); break;
            case 'user_activity_report': $data = $this->generateUserActivityReport($validated); break;
            
            // New Reports
            case 'inventory_valuation': $data = $this->generateInventoryValuationReport($validated); break;
            case 'department_cost': $data = $this->generateDepartmentCostReport($validated); break;
            case 'top_movers': $data = $this->generateTopMoversReport($validated); break;
            case 'dead_stock': $data = $this->generateDeadStockReport($validated); break;
            case 'audit_logs': $data = $this->generateAuditLogsReport($validated); break;
        }

        return response()->json($data);
    }

    private function applyDateFilter($query, array $filters, $dateColumn = 'created_at')
    {
        return $query->when($filters['start_date'] ?? null, function ($q, $startDate) use ($filters, $dateColumn) {
            $endDate = Carbon::parse($filters['end_date'] ?? now())->endOfDay();
            return $q->whereBetween($dateColumn, [Carbon::parse($startDate)->startOfDay(), $endDate]);
        });
    }

    // --- Existing Reports ---

    private function generateStockSummary(array $filters)
    {
        $query = Equipment::with(['category', 'location', 'unit'])
            ->whereNotIn('status', ['disposed', 'sold']);
            
        if(isset($filters['category_id'])) $query->where('category_id', $filters['category_id']);
        if(isset($filters['location_id'])) $query->where('location_id', $filters['location_id']);
        
        return $query->orderBy('name')->get();
    }

    private function generateTransactionHistory(array $filters)
    {
        $query = Transaction::with(['equipment', 'user']);
        
        if(isset($filters['category_id'])) {
            $query->whereHas('equipment', function($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }
        
        return $this->applyDateFilter($query, $filters, 'transaction_date')
            ->orderBy('transaction_date', 'desc')
            ->get();
    }

    private function generateBorrowReport(array $filters)
    {
        $query = Transaction::with(['equipment', 'user'])
            ->whereIn('type', ['borrow', 'borrow_temporary'])
            ->where('status', '!=', 'completed');
            
        return $this->applyDateFilter($query, $filters, 'transaction_date')
            ->orderBy('transaction_date', 'asc')
            ->get();
    }

    private function generateLowStockReport(array $filters)
    {
        $query = Equipment::with(['category', 'location', 'unit'])
            ->whereColumn('quantity', '<=', 'min_stock')
            ->where('min_stock', '>', 0);
            
        if(isset($filters['category_id'])) $query->where('category_id', $filters['category_id']);
        if(isset($filters['location_id'])) $query->where('location_id', $filters['location_id']);
        
        return $query->orderBy('quantity', 'asc')->get();
    }

    private function generateWarrantyReport(array $filters)
    {
        $query = Equipment::with(['category'])->whereNotNull('warranty_date');
        
        if (empty($filters['start_date'])) {
             $query->whereBetween('warranty_date', [now(), now()->addDays(60)]);
        } else {
             $this->applyDateFilter($query, $filters, 'warranty_date');
        }
        
        if(isset($filters['category_id'])) $query->where('category_id', $filters['category_id']);
        
        return $query->orderBy('warranty_date')->get();
    }

    private function generateMaintenanceReport(array $filters)
    {
        $query = MaintenanceLog::with(['equipment', 'reportedBy']);
        return $this->applyDateFilter($query, $filters)->orderBy('created_at', 'desc')->get();
    }

    private function generatePoReport(array $filters)
    {
        $query = PurchaseOrder::with(['orderedBy', 'items.equipment']);
        return $this->applyDateFilter($query, $filters, 'ordered_at')->orderBy('ordered_at', 'desc')->get();
    }

    private function generateDisposalReport(array $filters)
    {
        $query = Equipment::with(['category'])->whereIn('status', ['disposed', 'sold']);
        return $this->applyDateFilter($query, $filters, 'updated_at')->orderBy('updated_at', 'desc')->get();
    }

    private function generateConsumableReturnReport(array $filters)
    {
        $query = ConsumableReturn::with(['requester', 'originalTransaction.equipment', 'approver']);
        return $this->applyDateFilter($query, $filters)->orderBy('created_at', 'desc')->get();
    }

    private function generateUserActivityReport(array $filters)
    {
        if (empty($filters['user_id'])) return [];
        
        $query = Transaction::with(['equipment'])->where('user_id', $filters['user_id']);
        return $this->applyDateFilter($query, $filters, 'transaction_date')
            ->orderBy('transaction_date', 'desc')
            ->get();
    }

    // --- New Reports ---

    private function generateInventoryValuationReport(array $filters)
    {
        $query = Equipment::with(['category', 'location'])
            ->where('quantity', '>', 0)
            ->whereNotIn('status', ['disposed', 'sold']);

        if(isset($filters['category_id'])) $query->where('category_id', $filters['category_id']);

        $items = $query->get()->map(function($item) {
            $item->total_value = $item->quantity * ($item->price ?? 0);
            return $item;
        });

        return $items->sortByDesc('total_value')->values();
    }

    private function generateDepartmentCostReport(array $filters)
    {
        $query = Transaction::with(['equipment', 'user'])
            ->where('type', 'withdraw');

        $this->applyDateFilter($query, $filters, 'transaction_date');

        $transactions = $query->get();

        $grouped = $transactions->groupBy('user_id')->map(function($txs, $userId) {
            $user = $txs->first()->user;
            $totalCost = $txs->sum(function($tx) {
                return abs($tx->quantity_change) * ($tx->equipment->price ?? 0);
            });
            
            return [
                'user_name' => $user ? $user->fullname : 'Unknown',
                'department' => $user ? ($user->department ?? '-') : '-',
                'item_count' => $txs->count(),
                'total_qty' => $txs->sum(fn($t) => abs($t->quantity_change)),
                'total_cost' => $totalCost
            ];
        });

        return $grouped->sortByDesc('total_cost')->values();
    }

    private function generateTopMoversReport(array $filters)
    {
        $query = Transaction::with('equipment.category')
            ->select('equipment_id', DB::raw('count(*) as tx_count'), DB::raw('sum(abs(quantity_change)) as total_qty'))
            ->where('type', 'withdraw')
            ->groupBy('equipment_id');

        $this->applyDateFilter($query, $filters, 'transaction_date');

        $results = $query->orderByDesc('total_qty')->limit(20)->get();

        return $results->map(function($row) {
            $eq = Equipment::find($row->equipment_id);
            return [
                'equipment_name' => $eq ? $eq->name : 'Unknown',
                'category' => $eq && $eq->category ? $eq->category->name : '-',
                'tx_count' => $row->tx_count,
                'total_qty' => $row->total_qty
            ];
        });
    }

    private function generateDeadStockReport(array $filters)
    {
        $days = 90;
        $thresholdDate = Carbon::now()->subDays($days);

        $query = Equipment::with(['category', 'location'])
            ->where('quantity', '>', 0)
            ->whereNotIn('status', ['sold', 'disposed'])
            ->whereDoesntHave('transactions', function ($q) use ($thresholdDate) {
                $q->where('transaction_date', '>=', $thresholdDate);
            });

        if(isset($filters['category_id'])) $query->where('category_id', $filters['category_id']);

        return $query->get()->map(function($item) {
            $lastTx = $item->transactions()->latest('transaction_date')->first();
            $item->last_movement = $lastTx ? $lastTx->transaction_date : $item->created_at;
            $item->days_silent = Carbon::parse($item->last_movement)->diffInDays(now());
            return $item;
        })->sortByDesc('days_silent')->values();
    }

    private function generateAuditLogsReport(array $filters)
    {
        if (!class_exists(Changelog::class)) return [];
        $query = Changelog::with('user')->orderBy('created_at', 'desc');
        return $this->applyDateFilter($query, $filters)->get();
    }
}