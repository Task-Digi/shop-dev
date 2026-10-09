<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleItemRequest;
use App\Http\Requests\UpdateSaleItemRequest;
use App\Http\Requests\StoreZeroSalesDayRequest;
use App\Models\SalesList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SaleData;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use App\Services\SaleItemService;
use Illuminate\Validation\ValidationException;

class SaleItemController extends Controller
{
    public function __construct(private readonly SaleItemService $saleItemService)
    {
    }

    public function index()
    {
        return view('sale-items.index');
    }

    public function create()
    {
        return view('sale-items.create');
    }

    public function view()
    {
        $search = null;
        $initialDate = request('date');
        $initialSearch = request('search');

        return view('sale-items.view', compact('search', 'initialDate', 'initialSearch'));
    }

    public function getSaleItems(Request $request)
    {
        $page = $request->input('page');
        $offset = ($page - 1) * $request->input('length');
        $saleItems = SalesList::orderBy('date', 'desc')
            ->skip($offset)
            ->take($request->input('length'))
            ->get();

        return response()->json($saleItems);
    }

    public function store(StoreSaleItemRequest $request)
    {
        try {
            $this->saleItemService->create($request->validated());

            $message = !empty($request->input('original_orderid')) || !empty($request->input('is_return'))
                ? 'Return items recorded and linked successfully!'
                : 'Sale items created successfully!';

            return back()->with('success', $message);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error($e);
            return back()->with('error', 'Error storing sale items. Please try again.');
        }
    }

    public function storeZeroSalesDay(StoreZeroSalesDayRequest $request)
    {
        $date = $request->validated('zero_sales_date');
        $location = $request->validated('zero_sales_location');

        try {
            $created = DB::transaction(function () use ($date, $location): bool {
                if (DB::table('sale_data')->where('date', $date)->where('location', $location)->exists()) {
                    throw ValidationException::withMessages([
                        'zero_sales_date' => 'Actual sales already exist for this location and date.',
                    ]);
                }

                return DB::table('sales_reporting_days')->insertOrIgnore([
                    'date' => $date,
                    'location' => $location,
                    'client' => 'The shop itself',
                    'sales_amount' => 0,
                    'confirmation_source' => 'Data Entry: confirmed by user',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]) === 1;
            });

            if (! $created) {
                throw ValidationException::withMessages([
                    'zero_sales_date' => 'A zero-sales record already exists for this location and date.',
                ]);
            }

            return back()->with('success', "0 Sales recorded for {$location} on {$date}.");
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error($exception);
            return back()->with('error', 'Unable to store the zero-sales day. Please try again.');
        }
    }

    public function getLastSubmissionDate()
    {
        $lastSubmission = SaleData::orderBy('created_at', 'desc')->first();
        $lastSubmissionDate = $lastSubmission ? $lastSubmission->date : date('Y-m-d');

        return response()->json(['lastSubmissionDate' => $lastSubmissionDate]);
    }

    public function edit(int|string $id)
    {
        $saleItem = SaleData::query()->findOrFail($id);
        return view('sale-items.edit', compact('saleItem'));
    }

    public function update(UpdateSaleItemRequest $request, int|string $id)
    {
        $saleData = SaleData::query()->find($id);
        if (!$saleData) {
            return redirect('/Dashboard')->with('error', 'Sale item not found. It may already have been deleted.');
        }

        Log::info("Update called for ID: $id");
        try {
            $this->saleItemService->update($saleData, $request->validated());

            return redirect('/Dashboard')->with('success', 'Sale item updated successfully!');
        } catch (\Exception $e) {
            Log::error("Error updating sale item ID: $id. Error: " . $e->getMessage());
            return back()->with('error', 'Error updating sale item. Please try again.');
        }
    }

    public function destroy(int|string $id)
    {
        try {
            $saleData = SaleData::query()->find($id);

            if (!$saleData) {
                return redirect('/Dashboard')->with('error', 'Sale item not found. It may already have been deleted.');
            }

            $this->saleItemService->delete($saleData);
            return redirect('/Dashboard')->with('success', 'Sale item deleted successfully!');
        } catch (\Exception $e) {
            Log::error("Error deleting sale item ID: $id. Error: " . $e->getMessage());
            return back()->with('error', 'Error deleting sale item. Please try again.');
        }
    }

    public function validateOrderId(Request $request)
    {
        $orderid = trim((string) $request->input('orderid', ''));

        $existingOrder = SalesList::query()->where('orderid', $orderid)->exists();

        if ($existingOrder) {
            return response()->json(['error' => 'Order ID already exists']);
        }

        return response()->json(['success' => 'Order ID is valid']);
    }

    public function getOrderDetails(Request $request)
    {
        $orderId = trim((string) $request->input('orderid', ''));
        if ($orderId === '') {
            return response()->json(['success' => false, 'message' => 'Please provide an Order ID.'], 400);
        }

        $items = SaleData::query()->where('orderid', $orderId)->get();
        if ($items->isEmpty()) {
            return response()->json(['success' => false, 'message' => "Order #{$orderId} not found in sales records."], 404);
        }

        $positiveSold = (int) $items->where('count', '>', 0)->sum('count');
        if ($positiveSold <= 0) {
            return response()->json([
                'success' => false,
                'message' => "Order #{$orderId} is a return entry. Returns cannot be linked to another return.",
            ], 422);
        }

        $first = $items->first();

        // Retrieve existing returns linked to this order
        $linkedReturns = SaleData::query()
            ->where('original_orderid', $orderId)
            ->get()
            ->groupBy('product_id');

        $products = $items->where('count', '>', 0)->map(function ($item) use ($linkedReturns) {
            $alreadyReturned = abs((int) ($linkedReturns->get($item->product_id)?->sum('count') ?? 0));
            $origCount = (int) $item->count;
            $available = max(0, $origCount - $alreadyReturned);

            return [
                'product_id' => (string) $item->product_id,
                'product_name' => (string) $item->product_name,
                'price' => (float) $item->price,
                'retail' => (float) $item->retail,
                'original_count' => $origCount,
                'already_returned_count' => $alreadyReturned,
                'available_return_count' => $available,
            ];
        })->values();

        $order = [
            'orderid' => (string) $first->orderid,
            'date' => (string) $first->date,
            'location' => (string) $first->location,
            'type' => (string) $first->type,
            'payment' => (string) $first->payment,
            'customer_id' => (string) $first->customer_id,
            'customer_name' => (string) $first->customer_name,
            'products' => $products,
        ];

        return response()->json([
            'success' => true,
            'order' => $order,
            ...$order,
        ]);
    }

    public function validateProductId(Request $request)
    {
        $productid = $request->input('productid');

        $existingOrder = SalesList::query()->where('productid', $productid)->exists();

        if ($existingOrder) {
            return response()->json(['error' => 'productid already exists']);
        }

        return response()->json(['success' => 'productid is valid']);
    }

    public function getSalesByDate(Request $request)
    {
        $selectedDate = $request->selected_date;
        $sales = SalesList::query()->where('date', $selectedDate)->get();
        return response()->json($sales);
    }

    public function datasearch(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $initialDate = $request->input('date');
        $initialSearch = $search !== '' ? $search : null;

        return view('sale-items.view', compact('search', 'initialDate', 'initialSearch'));
    }

    public function listall()
    {
        $search = null;
        $initialDate = null;
        $initialSearch = null;
        return view('sale-items.view', compact('search', 'initialDate', 'initialSearch'));
    }

    public function dataTable(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        if ($length <= 0 || $length > 5000) {
            $length = 5000;
        }

        $columns = [
            0 => 'date',
            1 => 'location',
            2 => 'type',
            3 => 'payment',
            4 => 'customer_id',
            5 => 'customer_name',
            6 => 'orderid',
            7 => 'product_id',
            8 => 'count',
        ];

        $base = DB::table('sale_data');
        $recordsTotal = (clone $base)->count();

        $query = clone $base;

        // Column-header filters
        if ($request->filled('date_filter')) {
            $query->whereDate('date', $request->input('date_filter'));
        }
        foreach (['location', 'type', 'payment'] as $col) {
            $val = $request->input($col . '_filter');
            if ($val !== null && $val !== '') {
                $query->where($col, $val);
            }
        }
        foreach (['customer_id', 'customer_name', 'orderid', 'product_id', 'count'] as $col) {
            $val = $request->input($col . '_filter');
            if ($val !== null && $val !== '') {
                $query->where($col, 'like', '%' . $val . '%');
            }
        }

        // Top-bar free-text search
        $globalSearch = trim((string) $request->input('search_global', ''));
        if ($globalSearch === '') {
            $globalSearch = trim((string) data_get($request->input('search'), 'value', ''));
        }

        if ($globalSearch !== '') {
            $searchDate = null;
            try {
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $globalSearch)) {
                    $searchDate = \Carbon\Carbon::createFromFormat('d/m/Y', $globalSearch)->format('Y-m-d');
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $globalSearch)) {
                    $searchDate = $globalSearch;
                }
            } catch (\Exception $e) {
                // Ignore unparseable dates
            }

            $query->where(function ($q) use ($globalSearch, $searchDate) {
                $q->where('location', 'like', "%{$globalSearch}%")
                    ->orWhere('payment', 'like', "%{$globalSearch}%")
                    ->orWhere('type', 'like', "%{$globalSearch}%")
                    ->orWhere('orderid', 'like', "%{$globalSearch}%")
                    ->orWhere('product_id', 'like', "%{$globalSearch}%")
                    ->orWhere('customer_id', 'like', "%{$globalSearch}%")
                    ->orWhere('customer_name', 'like', "%{$globalSearch}%")
                    ->orWhere('product_name', 'like', "%{$globalSearch}%");

                if (preg_match('/^-?\d+$/', $globalSearch)) {
                    $q->orWhere('count', (int) $globalSearch);
                }

                if ($searchDate) {
                    $q->orWhereDate('date', $searchDate);
                }
            });
        }

        $recordsFiltered = (clone $query)->count();

        // Ordering
        $orderColIdx = (int) data_get($request->input('order'), '0.column', 0);
        $orderDir    = strtolower((string) data_get($request->input('order'), '0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColIdx] ?? 'date';

        $rows = $query->orderBy($orderColumn, $orderDir)
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get([
                'id',
                'date',
                'location',
                'type',
                'payment',
                'customer_id',
                'customer_name',
                'orderid',
                'original_orderid',
                'product_id',
                'count',
            ]);

        // Find any returns linked to these orders
        $orderIds = $rows->pluck('orderid')->filter()->unique();
        $linkedReturns = DB::table('sale_data')
            ->whereIn('original_orderid', $orderIds)
            ->select('original_orderid', 'product_id', DB::raw('SUM(count) as total_returned'))
            ->groupBy('original_orderid', 'product_id')
            ->get()
            ->keyBy(fn ($r) => $r->original_orderid . '_' . $r->product_id);

        $data = $rows->map(function ($row) use ($linkedReturns) {
            $key = $row->orderid . '_' . $row->product_id;
            $returnedCount = abs((int) ($linkedReturns->get($key)?->total_returned ?? 0));
            $origCount = (int) $row->count;
            $netCount = $origCount > 0 ? ($origCount - $returnedCount) : $origCount;

            return [
                'id'               => $row->id,
                'date'             => $row->date ? \Carbon\Carbon::parse($row->date)->format('d/m/Y') : '',
                'location'         => (string) $row->location,
                'type'             => (string) $row->type,
                'payment'          => (string) $row->payment,
                'customer_id'      => (string) $row->customer_id,
                'customer_name'    => (string) $row->customer_name,
                'orderid'          => (string) $row->orderid,
                'original_orderid' => (string) ($row->original_orderid ?? ''),
                'product_id'       => (string) $row->product_id,
                'count'            => (string) $row->count,
                'returned_count'   => $returnedCount,
                'net_count'        => $netCount,
                'is_return'        => $origCount < 0 || !empty($row->original_orderid),
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function filterOptions()
    {
        return response()->json([
            'location' => DB::table('sale_data')->select('location')->whereNotNull('location')->where('location', '!=', '')->distinct()->orderBy('location')->pluck('location'),
            'type'     => DB::table('sale_data')->select('type')->whereNotNull('type')->where('type', '!=', '')->distinct()->orderBy('type')->pluck('type'),
            'payment'  => DB::table('sale_data')->select('payment')->whereNotNull('payment')->where('payment', '!=', '')->distinct()->orderBy('payment')->pluck('payment'),
        ]);
    }

    public function dashboardView()
    {
        return view('sale-items.sale_Item');
    }

    public function add()
    {
        return view('sale-items.add');
    }

    public function autocompleteSearch(Request $request)
    {
        $query = trim((string) $request->get('query', ''));
        if ($query === '') {
            return response('');
        }

        $like = '%' . $query . '%';
        $suggestions = [];

        $fields = [
            ['column' => 'customer_name', 'label' => 'Customer',   'limit' => 8],
            ['column' => 'customer_id',   'label' => 'CustomerID', 'limit' => 6],
            ['column' => 'orderid',       'label' => 'OrderID',    'limit' => 6],
            ['column' => 'product_id',    'label' => 'ProductID',  'limit' => 6],
            ['column' => 'product_name',  'label' => 'Product',    'limit' => 6],
            ['column' => 'location',      'label' => 'Location',   'limit' => 5],
            ['column' => 'payment',       'label' => 'Payment',    'limit' => 5],
            ['column' => 'type',          'label' => 'Type',       'limit' => 5],
        ];

        foreach ($fields as $field) {
            $values = DB::table('sale_data')
                ->select($field['column'])
                ->whereNotNull($field['column'])
                ->where($field['column'], '!=', '')
                ->where($field['column'], 'LIKE', $like)
                ->distinct()
                ->orderBy($field['column'])
                ->limit($field['limit'])
                ->pluck($field['column']);

            foreach ($values as $val) {
                if ($val === null || $val === '') {
                    continue;
                }
                $suggestions[] = [
                    'label' => $field['label'],
                    'value' => (string) $val,
                ];
            }
        }

        if (empty($suggestions)) {
            return response('<p class="px-3 py-2 text-muted small mb-0">No matches found.</p>');
        }

        $html = '<ul class="list-unstyled mb-0 py-1">';
        foreach ($suggestions as $s) {
            $value = e($s['value']);
            $label = e($s['label']);
            $html .= '<li class="dropdown-item sold-registry-suggest d-flex justify-content-between align-items-center px-3 py-2"'
                . ' data-value="' . $value . '" role="option" style="cursor:pointer">'
                . '<span class="suggest-value">' . $value . '</span>'
                . '<small class="suggest-label text-muted ml-2">' . $label . '</small>'
                . '</li>';
        }
        $html .= '</ul>';

        return response($html);
    }
}
