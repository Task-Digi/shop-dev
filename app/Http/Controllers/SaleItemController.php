<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleItemRequest;
use App\Http\Requests\UpdateSaleItemRequest;
use App\Models\SalesList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SaleData;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use App\Services\SaleItemService;

class SaleItemController extends Controller
{
    public function __construct(private readonly SaleItemService $saleItemService)
    {
    }

    public function index()
    {
        // Fetch all sale items from the database

        return view('sale-items.index');
    }

    public function create()
    {
        return view('sale-items.create');
    }
    public function view()
    {
        // The table is rendered client-side via DataTables server-side processing,
        // so we no longer pre-load any rows here. We only pass the initial
        // free-text search (used by the autocomplete) and the page chrome.
        $search = null;
        $initialDate = request('date');
        $initialSearch = request('search');

        return view('sale-items.view', compact('search', 'initialDate', 'initialSearch'));
    }


    public function getSaleItems(Request $request)
    {
        // Get the current page number
        $page = $request->input('page');
        // Determine how many items to skip based on the page number and page length
        $offset = ($page - 1) * $request->input('length');
        // Fetch data for the current page
        $saleItems = SalesList::orderBy('date', 'desc')
            ->skip($offset)
            ->take($request->input('length'))
            ->get();

        // Return the data as JSON
        return response()->json($saleItems);
    }


    public function store(StoreSaleItemRequest $request)
    {
        try {
            $this->saleItemService->create($request->validated());

            return back()->with('success', 'Sale items created successfully!');
        } catch (\Exception $e) {
            Log::error($e);
            return back()->with('error', 'Error storing sale items. Please try again.');
        }
    }



    public function getLastSubmissionDate()
    {
        // Retrieve the last submission date
        $lastSubmission = SaleData::orderBy('created_at', 'desc')->first();

        if ($lastSubmission) {
            // If a submission exists, get its creation date
            $lastSubmissionDate = $lastSubmission->date;
        } else {
            // If no submissions exist, default to today's date
            $lastSubmissionDate = date('Y-m-d');
        }

        // Return the last submission date as JSON
        return response()->json(['lastSubmissionDate' => $lastSubmissionDate]);
    }
    public function edit($id)
    {
        $saleItem = SaleData::findOrFail($id);
        return view('sale-items.edit', compact('saleItem'));
    }

    public function update(UpdateSaleItemRequest $request, $id)
    {
        $saleData = SaleData::find($id);
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

    public function destroy($id)
    {
        try {
            $saleData = SaleData::find($id);

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
        $orderid = $request->input('orderid');

        // Check if the order ID already exists in the sale_list table
        $existingOrder = SalesList::where('orderid', $orderid)->exists();

        if ($existingOrder) {
            return response()->json(['error' => 'Order ID already exists']);
        }

        return response()->json(['success' => 'Order ID is valid']);
    }
    public function validateProductId(Request $request)
    {
        $productid = $request->input('productid');

        // Check if the order ID already exists in the sale_list table
        $existingOrder = SalesList::where('productid', $productid)->exists();

        if ($existingOrder) {
            return response()->json(['error' => 'productid  already exists']);
        }

        return response()->json(['success' => 'productid is valid']);
    }
    public function getSalesByDate(Request $request)
    {

        $selectedDate = $request->selected_date;
        $sales = SalesList::whereDate('date', $selectedDate)->get(); // Assuming your date column is named 'date'
        return response()->json($sales);
    }

    public function datasearch(Request $request)
    {
        // Page render — the table itself fetches data via dataTable() AJAX.
        // We only keep the URL query params around so the autocomplete /
        // initial filter chips can use them.
        $search = trim((string) $request->input('search', ''));
        $initialDate = $request->input('date');
        $initialSearch = $search !== '' ? $search : null;

        return view('sale-items.view', compact('search', 'initialDate', 'initialSearch'));
    }


    public function listall()
    {
        // Same as view() — server-side DataTables handles the data fetch.
        $search = null;
        $initialDate = null;
        $initialSearch = null;
        return view('sale-items.view', compact('search', 'initialDate', 'initialSearch'));
    }

    /**
     * Server-side processing endpoint for the Sale Item dashboard DataTable.
     *
     * Accepts the standard DataTables request parameters plus a few extras
     * used by the column-header filters (date_filter, location_filter,
     * type_filter, payment_filter) and the top free-text search.
     *
     * Returns: { draw, recordsTotal, recordsFiltered, data: [...] }
     */
    public function dataTable(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        // length = -1 means "All". Cap it so we never blow the page up.
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

        // Top-bar free-text search (also catches DataTables' built-in search.value).
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

        // Stable secondary order by id so pagination is deterministic.
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
                'product_id',
                'count',
            ]);

        $data = $rows->map(function ($row) {
            return [
                'id'            => $row->id,
                'date'          => $row->date ? \Carbon\Carbon::parse($row->date)->format('d/m/Y') : '',
                'location'      => (string) $row->location,
                'type'          => (string) $row->type,
                'payment'       => (string) $row->payment,
                'customer_id'   => (string) $row->customer_id,
                'customer_name' => (string) $row->customer_name,
                'orderid'       => (string) $row->orderid,
                'product_id'    => (string) $row->product_id,
                'count'         => (string) $row->count,
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Returns the distinct values for the low-cardinality column filters
     * (Location, Type, Payment) so the dropdowns can be populated without
     * loading the whole table client-side.
     */
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

        // Each column is searched against sale_data so that suggestions
        // always reflect data the user can actually find in the table.
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
