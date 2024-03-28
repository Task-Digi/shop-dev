<?php

namespace App\Http\Controllers;
use App\SalesList;
use Illuminate\Http\Request;

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
    
    public function view(){
        $saleItems = SalesList::all();
        return view('sale-items.view', compact('saleItems'));
    }
    
    public function store(Request $request)
    {
        // Validate the common input data
        // $request->validate([
        //     'date' => 'required',
        //     'location' => 'required',
        //     'type' => 'required',
        //     'payment' => 'required',
        //     'customerid' => 'required',
        //     'orderid' => 'required',
        //     'productid' => 'required',
        //     'count' => 'required',
        // ]);
    $existingOrder = SalesList::where('orderid', $request->input('orderid'))->exists();
        
        if ($existingOrder) {
            // Return with an error message if the orderId already exists
            return back()->withErrors(['order_id' => 'Order ID already exists. Please choose a different one.']);
        }
        try {
            // Create a new sale item for each set of productid and count
            foreach ($request->input('productid') as $key => $productId) {
                SalesList::create([
                    'date' => $request->input('date'),
                    'location' => $request->input('location'),
                    'type' => $request->input('type'),
                    'payment' => $request->input('payment'),
                    'customerid' => $request->input('customerid'),
                    'orderid' => $request->input('orderid'),
                    'productid' => $productId,
                    'count' => $request->input('count')[$key],
                ]);
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

    public function edit($id)
    {
        $saleItem = SalesList::find($id);
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
    
        $saleItem = SalesList::find($id);
        $saleItem->update($request->all());
    
        // Redirect to the view page after updating
        return redirect('/view')->with('success', 'Sale item updated successfully!');
    }
    
    public function destroy($id)
    {
        $saleItem = SalesList::find($id);
        $saleItem->delete();
    
        return redirect('/view')->with('success', 'Sale item deleted successfully!');
    }

}
