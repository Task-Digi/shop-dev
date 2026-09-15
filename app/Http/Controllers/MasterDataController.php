<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MasterDataController extends Controller
{
    /**
     * Display Master Data Management Dashboard (Customers, Products, Sales Records, Orders)
     */
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'customers');
        $search = trim($request->get('search', ''));
        $filterMissing = $request->boolean('missing_only', false);

        // Stats
        $totalCustomers = DB::table('customers')->count();
        $missingCustomersCount = DB::table('customers')
            ->where(function($q) {
                $q->where('customer_name', 'like', 'customer_%')
                  ->orWhereNull('customer_name')
                  ->orWhere('customer_name', '');
            })->count();

        $totalProducts = DB::table('products')->count();
        $missingProductsCount = DB::table('products')
            ->where(function($q) {
                $q->where('product_name', 'like', 'product_%')
                  ->orWhereNull('product_name')
                  ->orWhere('product_name', '')
                  ->orWhereRaw('product_name = product_id');
            })->count();

        $totalSalesCount = DB::table('sale_data')->count();

        // 1. Fetch Customers
        $customersQuery = DB::table('customers');
        if (!empty($search) && $activeTab === 'customers') {
            $customersQuery->where(function($q) use ($search) {
                $q->where('customer_id', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }
        if ($filterMissing && $activeTab === 'customers') {
            $customersQuery->where(function($q) {
                $q->where('customer_name', 'like', 'customer_%')
                  ->orWhereNull('customer_name')
                  ->orWhere('customer_name', '');
            });
        }
        $customers = $customersQuery->orderBy('customer_id', 'desc')->paginate(25, ['*'], 'customers_page');

        // 2. Fetch Products
        $productsQuery = DB::table('products');
        if (!empty($search) && $activeTab === 'products') {
            $productsQuery->where(function($q) use ($search) {
                $q->where('product_id', 'like', "%{$search}%")
                  ->orWhere('product_name', 'like', "%{$search}%");
            });
        }
        if ($filterMissing && $activeTab === 'products') {
            $productsQuery->where(function($q) {
                $q->where('product_name', 'like', 'product_%')
                  ->orWhereNull('product_name')
                  ->orWhere('product_name', '')
                  ->orWhereRaw('product_name = product_id');
            });
        }
        $products = $productsQuery->orderBy('product_id', 'desc')->paginate(25, ['*'], 'products_page');

        // 3. Fetch Sales Records (Direct Table Browser / Editor)
        $salesQuery = DB::table('sale_data');
        if (!empty($search) && $activeTab === 'sales') {
            $salesQuery->where(function($q) use ($search) {
                $q->where('orderid', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('product_id', 'like', "%{$search}%")
                  ->orWhere('product_name', 'like', "%{$search}%")
                  ->orWhere('date', 'like', "%{$search}%");
            });
        }
        $sales = $salesQuery->orderBy('id', 'desc')->paginate(25, ['*'], 'sales_page');

        // 4. Order Items Lookup (if order search submitted in Orders tab)
        $orderId = trim($request->get('order_id', ''));
        $orderItems = [];
        $orderCustomer = null;
        $orderTotal = 0;
        $orderItemCount = 0;

        if (!empty($orderId)) {
            $orderItems = DB::table('sale_data')
                ->where('orderid', $orderId)
                ->orderBy('id', 'asc')
                ->get();

            if ($orderItems->isNotEmpty()) {
                $orderCustomer = [
                    'id' => $orderItems->first()->customer_id ?? '',
                    'name' => $orderItems->first()->customer_name ?? '',
                    'date' => $orderItems->first()->date ?? '',
                ];
                $orderTotal = $orderItems->sum(function($item) {
                    return $item->count * $item->price;
                });
                $orderItemCount = $orderItems->sum('count');
            }
        }

        return view('master.index', compact(
            'customers', 'products', 'sales', 'orderItems', 'activeTab', 'search', 
            'filterMissing', 'orderId', 'totalCustomers', 'missingCustomersCount', 
            'totalProducts', 'missingProductsCount', 'totalSalesCount',
            'orderCustomer', 'orderTotal', 'orderItemCount'
        ));
    }

    /**
     * Update or Add Customer Name across (customers, sale_data)
     */
    public function updateCustomer(Request $request)
    {
        $request->validate([
            'customer_id' => 'required',
            'customer_name' => 'required|string|max:255',
        ]);

        $customerId = trim($request->input('customer_id'));
        $customerName = trim($request->input('customer_name'));

        try {
            DB::beginTransaction();

            // 1. Update or Insert into `customers` table
            $existingCustomer = DB::table('customers')->where('customer_id', $customerId)->first();
            if ($existingCustomer) {
                DB::table('customers')
                    ->where('customer_id', $customerId)
                    ->update([
                        'customer_name' => $customerName,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('customers')->insert([
                    'customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'KS_exists' => 0,
                    'crm_exists' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 2. Cascade Update into `sale_data` table
            $saleDataUpdated = DB::table('sale_data')
                ->where('customer_id', $customerId)
                ->update(['customer_name' => $customerName]);

            // 3. Cascade Update into `sales_lists` only if customer_name column exists
            if (Schema::hasTable('sales_lists') && Schema::hasColumn('sales_lists', 'customer_name')) {
                $column = Schema::hasColumn('sales_lists', 'customer_id') ? 'customer_id' : 'customerid';
                DB::table('sales_lists')
                    ->where($column, $customerId)
                    ->update(['customer_name' => $customerName]);
            }

            DB::commit();

            return redirect()->route('master.index', ['tab' => 'customers'])
                ->with('success', "Customer #{$customerId} successfully updated to '{$customerName}'! ({$saleDataUpdated} records synced in sale_data)");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MasterData Customer Update Failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error updating customer: ' . $e->getMessage());
        }
    }

    /**
     * Update Product Name across (products, sale_data)
     */
    public function updateProduct(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'product_name' => 'required|string|max:255',
        ]);

        $productId = trim($request->input('product_id'));
        $productName = trim($request->input('product_name'));

        try {
            DB::beginTransaction();

            // 1. Update or Insert into `products` table
            $existingProduct = DB::table('products')->where('product_id', $productId)->first();
            if ($existingProduct) {
                DB::table('products')
                    ->where('product_id', $productId)
                    ->update([
                        'product_name' => $productName,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('products')->insert([
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 2. Cascade Update into `sale_data` table
            $saleDataUpdated = DB::table('sale_data')
                ->where('product_id', $productId)
                ->update(['product_name' => $productName]);

            DB::commit();

            return redirect()->route('master.index', ['tab' => 'products'])
                ->with('success', "Product #{$productId} updated to '{$productName}'! ({$saleDataUpdated} items synced in sale_data)");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MasterData Product Update Failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error updating product: ' . $e->getMessage());
        }
    }

    /**
     * Update a single Sales Record across (sale_data, sales_lists)
     */
    public function updateSaleRecord(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'customer_id' => 'required',
            'customer_name' => 'required|string',
            'product_id' => 'required',
            'product_name' => 'required|string',
            'count' => 'required|numeric',
            'price' => 'required|numeric',
        ]);

        $id = $request->input('id');
        $customerId = trim($request->input('customer_id'));
        $customerName = trim($request->input('customer_name'));
        $productId = trim($request->input('product_id'));
        $productName = trim($request->input('product_name'));
        $count = $request->input('count');
        $price = $request->input('price');
        $retail = $request->input('retail', $price);
        $date = $request->input('date');

        try {
            DB::beginTransaction();

            $saleRecord = DB::table('sale_data')->where('id', $id)->first();
            if (!$saleRecord) {
                return redirect()->back()->with('error', 'Sales record not found!');
            }

            // 1. Update sale_data record
            $updateSaleData = [
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'product_id' => $productId,
                'product_name' => $productName,
                'count' => $count,
                'price' => $price,
                'retail' => $retail,
            ];
            if (!empty($date)) {
                $updateSaleData['date'] = $date;
            }
            DB::table('sale_data')->where('id', $id)->update($updateSaleData);

            // 2. Sync corresponding sales_lists record if exists
            $salesListId = $saleRecord->sales_list_id ?? null;
            if ($salesListId && Schema::hasTable('sales_lists')) {
                $updateList = [
                    'count' => $count,
                    'productid' => $productId,
                ];
                if (Schema::hasColumn('sales_lists', 'customerid')) {
                    $updateList['customerid'] = $customerId;
                } elseif (Schema::hasColumn('sales_lists', 'customer_id')) {
                    $updateList['customer_id'] = $customerId;
                }
                if (!empty($date) && Schema::hasColumn('sales_lists', 'date')) {
                    $updateList['date'] = $date;
                }
                DB::table('sales_lists')->where('id', $salesListId)->update($updateList);
            }

            // 3. Auto-update / insert into customers table if new name provided
            $cust = DB::table('customers')->where('customer_id', $customerId)->first();
            if ($cust) {
                DB::table('customers')->where('customer_id', $customerId)->update(['customer_name' => $customerName]);
            } else {
                DB::table('customers')->insert([
                    'customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'KS_exists' => 0,
                    'crm_exists' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 4. Auto-update / insert into products table if new product name provided
            $prod = DB::table('products')->where('product_id', $productId)->first();
            if ($prod) {
                DB::table('products')->where('product_id', $productId)->update(['product_name' => $productName]);
            } else {
                DB::table('products')->insert([
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return redirect()->route('master.index', ['tab' => 'sales'])
                ->with('success', "Sales Record #{$id} (Order #{$saleRecord->orderid}) successfully updated across sale_data, sales_lists, customers & products!");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MasterData SaleRecord Update Failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error updating sales record: ' . $e->getMessage());
        }
    }

    /**
     * Adjust Order Item Quantity / Return
     */
    public function adjustOrderItem(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'order_id' => 'required',
            'count' => 'required|numeric',
        ]);

        $id = $request->input('id');
        $orderId = $request->input('order_id');
        $newCount = $request->input('count');

        try {
            DB::beginTransaction();

            $item = DB::table('sale_data')->where('id', $id)->first();
            if (!$item) {
                return redirect()->back()->with('error', 'Item not found!');
            }

            $updateData = ['count' => $newCount];
            if ($newCount == 0) {
                $updateData['price'] = 0;
                $updateData['retail'] = 0;
            } elseif ($newCount < 0 && $item->price < 0) {
                $updateData['price'] = abs($item->price);
                $updateData['retail'] = abs($item->retail);
            }

            DB::table('sale_data')->where('id', $id)->update($updateData);

            // Sync with sales_lists if sales_list_id exists
            if (!empty($item->sales_list_id) && Schema::hasTable('sales_lists')) {
                DB::table('sales_lists')->where('id', $item->sales_list_id)->update(['count' => $newCount]);
            }

            DB::commit();

            return redirect()->route('master.index', ['tab' => 'orders', 'order_id' => $orderId])
                ->with('success', "Item #{$id} count updated to {$newCount}!");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error adjusting order item: ' . $e->getMessage());
        }
    }

    /**
     * Delete an Order Item from sale_data & sales_lists
     */
    public function deleteOrderItem(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'order_id' => 'required',
        ]);

        $id = $request->input('id');
        $orderId = $request->input('order_id');

        try {
            DB::beginTransaction();

            $item = DB::table('sale_data')->where('id', $id)->first();
            if ($item && !empty($item->sales_list_id) && Schema::hasTable('sales_lists')) {
                DB::table('sales_lists')->where('id', $item->sales_list_id)->delete();
            }

            DB::table('sale_data')->where('id', $id)->delete();

            DB::commit();

            return redirect()->route('master.index', ['tab' => 'orders', 'order_id' => $orderId])
                ->with('success', "Item #{$id} deleted successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error deleting item: ' . $e->getMessage());
        }
    }
}
