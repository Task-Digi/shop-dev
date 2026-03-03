<?php

namespace App\Http\Controllers;

use App\Models\SalesList;
use App\Models\ICT;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function index(Request $request, $customerId)
    {
        // Get the days filter, defaulting to 7 days
        $days = $request->input('days', 7);
        $search = $request->input('search'); // Get the search term if provided

        // Initialize the sales data queries
        $salesData1Query = DB::table('sale_data')
            ->select(
                'customer_name',
                DB::raw('SUM(count * price) as total_sales')
            )
            ->groupBy('customer_name')
            ->orderBy('total_sales', 'DESC');

        $salesDataQuery = DB::table('sale_data')
            ->leftJoin('customers', 'sale_data.customer_id', '=', 'customers.customer_id')
            ->select(
                'sale_data.location',
                'sale_data.customer_id',
                'sale_data.customer_name',
                'customers.KS_exists',
                DB::raw('COUNT(DISTINCT sale_data.orderid) as order_id_count'),
                DB::raw('SUM(sale_data.count) as total_products_sold'),
                DB::raw('SUM(sale_data.count * sale_data.price) as total_sales')
            )
            ->groupBy('sale_data.location', 'sale_data.customer_id', 'sale_data.customer_name', 'customers.KS_exists')
            ->orderBy('total_sales', 'DESC');

        // Apply the filter conditions based on customerId and days
        if ($customerId === 'all') {
            // If "All Days" (days = 0) is selected, we retrieve all records without any date limit
            if ($days != 0) {
                $salesData1Query->whereBetween('date', [now()->subDays($days), now()]);
                $salesDataQuery->whereBetween('date', [now()->subDays($days), now()]);
            }

            if ($search) {
                // Qualify the column with table name to avoid ambiguity when joining customers
                $salesData1Query->where('sale_data.customer_name', 'LIKE', "%{$search}%");
                $salesDataQuery->where('sale_data.customer_name', 'LIKE', "%{$search}%");
            }
        } else {
            // When specific customer is selected, filter by customer ID or name
            if (is_numeric($customerId)) {
                $salesData1Query->where('customer_id', 'LIKE', "%{$customerId}%");
                $salesDataQuery->where('customer_id', 'LIKE', "%{$customerId}%");
            } else {
                // When a non-numeric customerId (treated as name) is provided, search the sale_data.customer_name
                $salesData1Query->where('sale_data.customer_name', 'LIKE', "%{$customerId}%");
                $salesDataQuery->where('sale_data.customer_name', 'LIKE', "%{$customerId}%");
            }

            // Apply days filter if not showing all days for specific customer
            if ($days != 0) {
                $salesData1Query->whereBetween('date', [now()->subDays($days), now()]);
                $salesDataQuery->whereBetween('date', [now()->subDays($days), now()]);
            }
        }

        // Execute the queries
        $salesData1 = $salesData1Query->get();
        $salesData = $salesDataQuery->get();

        // Return both salesData and salesData1 to the view, along with search term
        return view('reports.customer', compact('salesData', 'salesData1', 'days', 'search'));
    }

    public function getCustomerReportData(Request $request)
    {
        try {
            // Retrieve inputs
            $location = $request->input('location');
            $customerId = $request->input('customer_id');
            $days = $request->input('days');

            // Prepare the SQL query
            $query = "
                SELECT
                    orderid,
                    MAX(date) AS sales_date, -- Get the latest date for each order
                    location,
                    type,
                    payment,
                    customer_id,
                    customer_name,
                    ROUND(SUM(count * price), 2) AS total_sales, -- Total sales for the order
                    ROUND(SUM(count * retail), 2) AS total_products_price, -- Total product price for the order
                    SUM(count) AS total_products_sold -- Total products sold for the order
                FROM
                    sale_data
                WHERE
                    location = ?
                    AND customer_id = ?
            ";

            // Add the date filtering condition based on the 'days' variable
            if ($days == 0) {
                $params = [$location, $customerId]; // No date filter, just location and customer_id
            } else if ($days !== 'all') {
                $startDate = now()->subDays($days); // Calculate the start date
                $query .= " AND date >= ?";
                $params = [$location, $customerId, $startDate]; // Add the start date to the parameters
            } else {
                $params = [$location, $customerId]; // No date filter, just location and customer_id
            }

            // Group and order the results
            $query .= "
                GROUP BY
                    orderid, location, type, payment, customer_id, customer_name
                ORDER BY
                    sales_date ASC
            ";

            // Fetch data from the database
            $customerData = DB::select($query, $params);

            return response()->json($customerData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reportindex()
    {
        return view('reports.graph');
    }

    public function productIndex(Request $request, $productid)
    {
        $days = $request->input('days', 7);
        $searchName = $request->input('search', '');

        // Base query to retrieve sales data, grouped by product and location
        $salesDataQuery = DB::table('sale_data')
            ->select(
                'product_id',
                'product_name',
                'location',
                DB::raw('COUNT(DISTINCT customer_id) AS customer_count'),
                DB::raw('COUNT(DISTINCT orderid) AS order_id_count'),
                DB::raw('SUM(count) AS product_id_count'),
                DB::raw('SUM(count * price) AS total_products_price')
            )
            ->groupBy('product_id', 'product_name', 'location')
            ->orderBy('product_name', 'ASC');

        // Base query to retrieve total sales per product for graph data
        $salesData1Query = DB::table('sale_data')
            ->select('product_name', DB::raw('SUM(count * price) as total_sales'))
            ->groupBy('product_name')
            ->orderBy('product_name', 'ASC');

        // Apply date filter if 'days' is not set to 'all'
        if ($days !== 'all') {
            $dateNDaysAgo = now()->subDays($days)->toDateString();
            $salesDataQuery->where('date', '>=', $dateNDaysAgo);
            $salesData1Query->where('date', '>=', $dateNDaysAgo);
        }

        // Apply filters based on product name or ID
        if ($productid === 'ProductName' && !empty($searchName)) {
            $salesDataQuery->where('product_name', 'LIKE', "%{$searchName}%");
            $salesData1Query->where('product_name', 'LIKE', "%{$searchName}%");
            $product_name = $searchName;
        } elseif ($productid !== 'all') {
            $salesDataQuery->where('product_id', $productid);
            $salesData1Query->where('product_id', $productid);

            // Retrieve product name based on product ID
            $result = DB::table('sale_data')
                ->select('product_name')
                ->where('product_id', $productid)
                ->first();

            $product_name = $result ? $result->product_name : 'Unknown Product';
        } else {
            $product_name = 'All Products';
        }

        // Execute the queries
        $salesData = $salesDataQuery->get();
        $salesData1 = $salesData1Query->get();

        // Return the view with necessary data
        return view('reports.product', compact('salesData', 'salesData1', 'product_name', 'productid', 'days', 'searchName'));
    }

    public function getSalesData()
    {
        // Calculate the date 50 days ago
        $dateFiftyDaysAgo = now()->subDays(30)->toDateString();

        // Query to get the sales data
        $salesData = DB::table('sales_lists')
            ->select(DB::raw('date, SUM(count * price) as total_sales'))
            ->join('products', 'sales_lists.productid', '=', 'products.product_id')
            ->where('date', '>=', $dateFiftyDaysAgo)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return response()->json($salesData);
    }

    public function getSalesDataCustomer($customerId)
    {
        $dateFiftyDaysAgo = now()->subDays(30)->toDateString();
        // Query to get the sales data
        $salesData = DB::table('sales_lists')
            ->select(DB::raw('date, SUM(count * price) as total_sales'))
            ->join('products', 'sales_lists.productid', '=', 'products.product_id')
            ->where('customerid', "=", $customerId)
            ->where('date', '>=', $dateFiftyDaysAgo)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return response()->json($salesData);
    }

    public function viewIndex(Request $request)
    {
        // Get the 'days' and 'searchDate' values from the request
        $days = $request->input('days', 7); // Default to 7 days if no selection is provided
        $searchDate = $request->input('searchDate');

        // Prepare the base query for sales data
        $salesDataQuery = DB::table('sale_data')
            ->select(
                'date',
                'location',
                DB::raw('COUNT(DISTINCT customer_id) AS customer_count'),
                DB::raw('GROUP_CONCAT(DISTINCT customer_id SEPARATOR ", ") AS customer_ids'),
                DB::raw('COUNT(DISTINCT orderid) AS order_id_count'),
                DB::raw('SUM(count) AS product_id_count'),
                DB::raw('SUM(count * price) AS total_products_price'),
                DB::raw('SUM(count * retail) AS total_retail_value')
            )
            ->groupBy('date', 'location')
            ->orderBy('date', 'DESC');

        // Prepare the query for total sales per day
        $salesData1Query = DB::table('sale_data')
            ->select('date', DB::raw('SUM(count * price) as total_sales'))
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        // Apply date filtering based on the input
        if ($searchDate) {
            // If a specific date is searched, filter by that date
            $salesDataQuery->whereDate('date', '=', $searchDate);
            $salesData1Query->whereDate('date', '=', $searchDate);
            $days = null;
        } elseif ($days && $days !== 'all') {
            // If $days is provided and not 'all', filter by the range of days
            $dateNDaysAgo = now()->subDays((int)$days)->toDateString();
            $salesDataQuery->where('date', '>=', $dateNDaysAgo);
            $salesData1Query->where('date', '>=', $dateNDaysAgo);
        }
        // No filter applied for 'all' days selection, as it fetches all records

        // Execute the queries
        $salesData = $salesDataQuery->get();
        $salesData1 = $salesData1Query->get();

        // Assuming you want to initialize the customer_name and customerId variables
        $customer_name = "";
        $customerId = "";

        return view('reports.index', compact('salesData', 'customer_name', 'customerId', 'salesData1', 'days', 'searchDate'));
    }

    public function reportDashboard(Request $request)
    {
        return view('reports.dashboard');
    }

    public function fetchCustomerData($saleId)
    {
        $customerData = SalesList::findOrFail($saleId)->customers;

        // Return customer data as JSON response
        return response()->json($customerData);
    }

    public function getCustomersByDate($date)
    {
        try {
            $customerData = DB::table('sale_data')
                ->select(
                    'customer_id',
                    'customer_name',
                    'date',
                    'location',
                    'crm_exists',
                    'crm_link',
                    'crm_id',
                    DB::raw('COUNT(DISTINCT orderid) AS order_count'),
                    DB::raw('SUM(count) AS product_count'),
                    DB::raw('ROUND(SUM(count * price), 2) AS total_price')
                )
                ->where('date', $date)
                ->groupBy('customer_id', 'customer_name', 'date', 'location', 'crm_exists', 'crm_link', 'crm_id')
                ->get();

            return response()->json($customerData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update KS_exists flag for a customer.
     * Receives JSON: { customer_id: string|int, KS_exists: 0|1 }
     * Only updates the customers table to avoid changing other existing behaviour.
     */
    public function updateKsStatus(Request $request)
    {
        try {
            $customerId = $request->input('customer_id');
            $ksExists = $request->input('KS_exists');

            if (is_null($customerId)) {
                return response()->json(['success' => false, 'message' => 'Missing customer_id'], 400);
            }

            // Ensure ksExists is an integer 0 or 1
            $ksExists = (int) $ksExists;
            $ksExists = $ksExists === 1 ? 1 : 0;

            // Update only the customers table as requested
            DB::table('customers')->where('customer_id', $customerId)->update(['KS_exists' => $ksExists]);

            return response()->json(['success' => true, 'message' => 'KS status updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update KS status: ' . $e->getMessage()], 500);
        }
    }

    public function markCrmExists(Request $request)
    {
        try {
            $customerId = $request->input('customer_id');
            $crmExists = $request->input('crm_exists');

            DB::table('sale_data')->where('customer_id', $customerId)->update(['crm_exists' => $crmExists]);
            DB::table('customers')->where('customer_id', $customerId)->update(['crm_exists' => $crmExists]);

            return response()->json(['message' => 'CRM status updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update CRM status: ' . $e->getMessage()], 500);
        }
    }

    public function updateCrmId(Request $request)
    {
        try {
            $customerId = $request->input('customer_id');
            $crmId = $request->input('crm_id');

            DB::table('customers')->where('customer_id', $customerId)->update(['crm_id' => $crmId]);
            DB::table('sale_data')->where('customer_id', $customerId)->update(['crm_id' => $crmId]);

            return response()->json(['message' => 'CRM ID updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update CRM ID: ' . $e->getMessage()], 500);
        }
    }

    public function getLatestCrmId(Request $request)
    {
        $customerId = $request->input('customer_id');

        $crmId = DB::table('customers')
            ->where('customer_id', $customerId)
            ->value('crm_id');

        return response()->json(['crm_id' => $crmId]);
    }

    public function getCustomerData(Request $request)
    {
        try {
            $date = $request->input('date');
            $location = $request->input('location');
            $customerId = $request->input('customer_id');

            // Execute raw SQL query to fetch customer data from the sale_data table
            $customerData = DB::select("
            SELECT
                orderid,
                date AS sales_date,
                location,
                customer_name,
                SUM(count) AS product_count,
                ROUND(SUM(count * price), 2) AS total_product_count
            FROM
                sale_data
            WHERE
                date = ?
                AND location = ?
                AND customer_id = ?
            GROUP BY
                orderid, date, location, customer_name
        ", [$date, $location, $customerId]);

            return response()->json($customerData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProductData(Request $request)
    {
        try {
            // Retrieve input values
            $days = $request->input('days'); // 'days' can be 'all', 7, 30, 90, etc.
            $location = $request->input('location');
            $product_id = $request->input('product_id');

            // Prepare the base query for fetching product data
            $productDataQuery = DB::table('sale_data')
                ->select(
                    'date',
                    'location',
                    'product_id',
                    'product_name',
                    DB::raw('COUNT(DISTINCT customer_id) AS customer_count'),
                    DB::raw('COUNT(DISTINCT orderid) AS order_id_count'),
                    DB::raw('SUM(count) AS product_quantity_sold'),
                    DB::raw('SUM(count * price) AS total_sales')
                );

            // Apply filters based on user input
            if ($location) {
                $productDataQuery->where('location', $location);
            }

            if ($product_id) {
                $productDataQuery->where('product_id', $product_id);
            }

            // Calculate the date range based on the 'days' input
            if ($days && $days !== 'all') {
                // Calculate the date range for last 'days'
                $endDate = now()->toDateString(); // Today's date
                $startDate = now()->subDays($days)->toDateString(); // Date 'days' ago

                // Filter by date range
                $productDataQuery->whereBetween('date', [$startDate, $endDate]);
            }

            // Group by date, location, and product_id to aggregate results
            $productDataQuery->groupBy('date', 'location', 'product_id', 'product_name')
                ->orderBy('date', 'ASC')
                ->orderBy('location')
                ->orderBy('product_id');

            // Execute the query
            $productData = $productDataQuery->get();

            // Return the response as JSON
            return response()->json($productData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProductfinalData(Request $request)
    {
        // Get input parameters
        $date = $request->input('date');
        $location = $request->input('location');
        $productId = $request->input('productId');

        // Build main query with subquery for aggregated values
        $query = DB::table('sale_data')
            ->select(
                'sale_data.customer_name',
                DB::raw('GROUP_CONCAT(DISTINCT sale_data.date ORDER BY sale_data.date ASC SEPARATOR ", ") as date'), // Concatenate dates
                DB::raw('GROUP_CONCAT(DISTINCT sale_data.location ORDER BY sale_data.location ASC SEPARATOR ", ") as location'), // Concatenate locations
                DB::raw('GROUP_CONCAT(DISTINCT sale_data.orderid ORDER BY sale_data.orderid ASC SEPARATOR ", ") as orderid'), // Concatenate order IDs
                DB::raw('GROUP_CONCAT(subquery.total_quantity_sold ORDER BY sale_data.orderid ASC SEPARATOR ", ") as total_quantity_sold'), // Concatenate quantity sold sums
                DB::raw('GROUP_CONCAT(subquery.total_sales ORDER BY sale_data.orderid ASC SEPARATOR ", ") as total_sales') // Concatenate total sales sums
            )
            ->join(
                DB::raw('(SELECT product_id, orderid, customer_name, SUM(count) as total_quantity_sold, SUM(price * count) as total_sales FROM sale_data GROUP BY product_id, orderid, customer_name) as subquery'),
                function ($join) {
                    $join->on('sale_data.orderid', '=', 'subquery.orderid')
                        ->on('sale_data.customer_name', '=', 'subquery.customer_name')
                        ->on('sale_data.product_id', '=', 'subquery.product_id');
                }
            );

        // Apply filters based on the provided input parameters
        if ($date) {
            $query->where('sale_data.date', $date);
        }

        if ($productId) {
            $query->where('sale_data.product_id', $productId);
        }

        if ($location) {
            $query->where('sale_data.location', $location);
        }

        // Group by customer_name to avoid ONLY_FULL_GROUP_BY issues
        $query->groupBy('sale_data.customer_name');

        // Execute query and get results
        $salesData = $query->get();

        // Return response as JSON
        return response()->json($salesData);
    }

    public function getCustomerfinalData(Request $request)
    {
        $customerId = $request->input('customerId');
        $orderId = $request->input('orderid');

        // Query the sale_data table directly
        $salesDetails = DB::table('sale_data')
            ->select(
                'type',
                'payment',
                'customer_id',
                'product_id',
                'count',
                'product_name',
                'price',
                DB::raw('ROUND(count * price, 2) AS total_price') // Calculate total price with two decimal places
            )
            ->where('customer_id', $customerId)
            ->where('orderid', $orderId)
            ->get();

        return response()->json($salesDetails);
    }

    /**
     * Display ICT products with pagination and search
     */
    public function ict(Request $request)
    {
        $search = $request->input('search', '');

        $query = DB::table('ict');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ean_code', 'like', "%{$search}%")
                    ->orWhere('colour_code', 'like', "%{$search}%")
                    ->orWhere('colour_name', 'like', "%{$search}%")
                    ->orWhere('finish', 'like', "%{$search}%")
                    ->orWhere('tin_size', 'like', "%{$search}%")
                    ->orWhere('ean_code_base', 'like', "%{$search}%")
                    ->orWhere('base_description', 'like', "%{$search}%")
                    ->orWhere('base_code', 'like', "%{$search}%");
            });
        }

        $ictData = $query->orderBy('id', 'asc')->paginate(25);

        return view('reports.ict', compact('ictData', 'search'));
    }

    /**
     * Search ICT products
     */
    public function search(Request $request)
    {
        return $this->ict($request); // Reuse the ict method for consistency
    }

    /**
     * Update quantity for ICT product
     */
    /**
     * Update quantity for ICT product
     */
    public function updateQuantity(Request $request)
    {
        // Enable detailed error reporting for debugging
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        \Log::info('=== ICT QUANTITY UPDATE START ===');
        \Log::info('Request data:', $request->all());
        \Log::info('Headers:', $request->headers->all());

        try {
            // Check if we're receiving the request
            if (!$request->has('id') || !$request->has('quantity')) {
                \Log::error('Missing required fields', ['request' => $request->all()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields: id or quantity'
                ], 400);
            }

            $id = $request->input('id');
            $quantity = $request->input('quantity');

            $eanCodeBase = $request->input('ean_code_base');

            \Log::info('Processing update:', ['id' => $id, 'ean_code_base' => $eanCodeBase, 'quantity' => $quantity]);

            // Basic validation
            if (!is_numeric($quantity) || $quantity < 0) {
                \Log::error('Invalid quantity', ['quantity' => $quantity]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid quantity'
                ], 422);
            }

            if (empty($eanCodeBase)) {
                \Log::error('Missing EAN Code Base', ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Missing EAN Code Base'
                ], 422);
            }

            // Update the database by EAN Code Base
            $affected = DB::table('ict')
                ->where('ean_code_base', $eanCodeBase)
                ->update([
                    'qty' => (int)$quantity
                ]);

            \Log::info('Update result:', ['affected_rows' => $affected]);

            if ($affected) {
                \Log::info("ICT product quantity updated successfully by EAN", [
                    'ean_code_base' => $eanCodeBase,
                    'quantity' => $quantity,
                    'user' => auth()->id() ?? 'unknown'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Quantity updated successfully',
                    'data' => [
                        'ean_code_base' => $eanCodeBase,
                        'quantity' => $quantity
                    ]
                ]);
            } else {
                // Check if records exist with this EAN
                $exists = DB::table('ict')->where('ean_code_base', $eanCodeBase)->exists();

                if (!$exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No records found with this EAN Code Base'
                    ], 404);
                }

                \Log::warning("No rows affected - quantity may already be set to this value", [
                    'ean_code_base' => $eanCodeBase,
                    'quantity' => $quantity
                ]);

                return response()->json([
                    'success' => true, // Still success if no change needed
                    'message' => 'Quantity already set to this value',
                    'data' => [
                        'ean_code_base' => $eanCodeBase,
                        'quantity' => $quantity
                    ]
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to update ICT quantity', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        } finally {
            \Log::info('=== ICT QUANTITY UPDATE END ===');
        }
    }

    /**
     * Display KS page with all customers and search functionality.
     * This method fetches unique customers and displays them in a table.
     * Users can search for customers and toggle KS status with buttons.
     */
    public function ksPage(Request $request)
    {
        try {
            $search = $request->input('search', ''); // Get search term if provided

            // Query to get all unique customers with their KS_exists status
            // Include updated_at so we can order by the most recently updated customers first
            $query = DB::table('customers')
                ->select('customer_id', 'customer_name', 'KS_exists', 'updated_at')
                ->distinct();

            // Apply search filter if search term is provided
            if ($search) {
                $query->where('customer_name', 'LIKE', "%{$search}%");
            }

            // Order by latest updated customer first, then fallback to customer_name
            $customers = $query->orderBy('updated_at', 'DESC')->orderBy('customer_name', 'ASC')->get();

            // Return the view with customers data
            return view('customers.ks_customers', compact('customers', 'search'));
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error in ksPage: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load KS page: ' . $e->getMessage()], 500);
        }
    }
    public function customerProductsByDate(Request $request)
    {
        try {
            $date = $request->input('date');
            $location = $request->input('location');
            $customerId = $request->input('customer_id');

            $query = DB::table('sale_data')
                ->select(
                    'product_id',
                    'product_name',
                    DB::raw('SUM(count) as count'),
                    'price',
                    DB::raw('ROUND(SUM(count * price), 2) AS total_price')
                )
                ->where('date', $date)
                ->where('location', $location);

            if ($customerId !== null && $customerId !== 'null' && $customerId !== '') {
                $query->where('customer_id', $customerId);
            }

            $salesDetails = $query->groupBy('product_id', 'product_name', 'price')->get();

            return response()->json($salesDetails);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
