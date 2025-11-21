<?php

namespace App\Http\Controllers;

use App\SalesList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\SaleData;
use App\Product;
use App\Customer;

class SaleItemController extends Controller
{
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
        $saleItems = DB::table('sale_data')
            ->orderBy('date', 'desc')
            ->take(50)
            ->get();

        return view('sale-items.view', compact('saleItems'));
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


    public function store(Request $request)
    {
        $existingOrder = SalesList::where('orderid', $request->input('orderid'))->exists();

        if ($existingOrder) {
            // Return with an error message if the orderId already exists
            return back()->withErrors(['order_id' => 'Order ID already exists. Please choose a different one.']);
        }

        try {
            // Loop through each productid and count
            foreach ($request->input('productid') as $key => $productId) {
                // Insert into SalesList table
                $salesList = SalesList::create([
                    'date' => $request->input('date'),
                    'location' => $request->input('location'),
                    'type' => $request->input('type'),
                    'payment' => $request->input('payment'),
                    'customerid' => $request->input('customerid'),
                    'orderid' => $request->input('orderid'),
                    'productid' => $productId,
                    'count' => $request->input('count')[$key],
                ]);

                // Retrieve customer and product details
                $customer = Customer::where('customer_id', $request->input('customerid'))->first();
                $product = Product::where('product_id', $productId)->first();


                // Insert corresponding data into sale_data table
                $salesData = SaleData::create([
                    'date' => $request->input('date'),
                    'location' => $request->input('location'),
                    'type' => $request->input('type'),
                    'payment' => $request->input('payment'),
                    'customer_id' => $request->input('customerid'),
                    'customer_name' => $customer ? $customer->customer_name : null,
                    'crm_exists' => $customer ? $customer->crm_exists : null,
                    'crm_link' => $customer ? $customer->crm_link : null,
                    'crm_id' => $customer ? $customer->crm_id : null,
                    'orderid' => $request->input('orderid'),
                    'product_id' => $productId,
                    'product_name' => $product ? $product->product_name : null,
                    'price' => $product ? $product->price : null,
                    'retail' => $product ? $product->retail : null,
                    'count' => $request->input('count')[$key],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // return $salesData;
            }

            // Redirect back to the previous page with a success message
            return back()->with('success', 'Sale items created successfully!');
        } catch (\Exception $e) {
            // Log the exception for debugging
            \Log::error($e);

            // Return with an error message
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
        $saleItem = SaleData::find($id);
        return view('sale-items.edit', compact('saleItem'));
    }

    public function update(Request $request, $id)
    {
        // Validate the input data
        $request->validate([
            'date' => 'required',
            'location' => 'required',
            'type' => 'required',
            'payment' => 'required',
            'customerid' => 'required',
            'productid' => 'required',
            'orderid' => 'required',
            'count' => 'required',
        ]);

        // Update the sales_lists table
        $saleItem = SalesList::find($id);
        $saleItem->update($request->all());

        // Fetch the related customer and product details
        $customer = Customer::where('customer_id', $request->customerid)->first();
        $product = Product::where('product_id', $request->productid)->first();

        // Update the sale_data table
        $saleData = SaleData::find($id);
        $saleData->date = $request->date;
        $saleData->location = $request->location;
        $saleData->type = $request->type;
        $saleData->payment = $request->payment;
        $saleData->customer_id = $request->customerid;
        $saleData->customer_name = $customer ? $customer->customer_name : null;
        $saleData->orderid = $request->orderid;
        $saleData->product_id = $request->productid;
        $saleData->product_name = $product ? $product->product_name : null;
        $saleData->price = $product ? $product->price : null;
        $saleData->retail = $product ? $product->retail : null;
        $saleData->count = $request->count;
        $saleData->updated_at = now();

        // Save the updated sale_data
        $saleData->save();

        // Redirect to the view page after updating
        return redirect('/Dashboard')->with('success', 'Sale item updated successfully!');
    }

    public function destroy($id)
    {
        $saleItem = SalesList::find($id);
        $saleData = SaleData::find($id);
    
        if ($saleItem) {
            $saleItem->delete();
        }
    
        if ($saleData) {
            $saleData->delete();
        }
    
        return redirect('/Dashboard')->with('success', 'Sale item deleted successfully!');
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
        $query = DB::table('sale_data');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($query) use ($search) {
                $query->where('date', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('payment', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('orderid', 'like', "%{$search}%")
                    ->orWhere('product_id', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%");
            });
        } else {
            // Order by the date column (assuming you have a date column) and limit to latest 50 results
            $query->orderBy('date', 'desc')->limit(50);
        }

        // Select the necessary columns from the sale_data table
        $saleItems = $query->get();
        return view('sale-items.view', compact('saleItems', 'search'));
    }


    public function listall()
    {
        $saleItems = SaleData::all();
        return view('sale-items.view', compact('saleItems'));
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
        $query = $request->get('query');
        $customers = DB::table('customers')
            ->where('customer_name', 'LIKE', '%' . $query . '%')
            ->get();
        $output = '<ul class="dropdown-menu" style="display:block; position:relative">';
        foreach ($customers as $customer) {
            $output .= '<li>' . $customer->customer_name . '</li>';
        }
        $output .= '</ul>';
        echo $output;
    }
}
