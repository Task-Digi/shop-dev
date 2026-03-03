<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesList;

class SaleListController extends Controller
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
        $saleItems = SalesList::all();
        return view('sale-items.view', compact('saleItems'));
    }

    public function store(Request $request)
    {

        $request->validate([
            'date',
            'location',
            'type',
            'payment',
            'customerid',
            'productid',
            'orderid',
            'count',
        ]);

        // Create a new sale item
        SalesList::create($request->all());

        // Redirect back to the previous page with a success message
        return back()->with('success', 'Sale item created successfully!');
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
            'date',
            'location',
            'type',
            'payment',
            'customerid',
            'productid',
            'orderid',
            'count',
        ]);

        $saleItem = SalesList::find($id);
        $saleItem->update($request->all());

        // Redirect to the view page after updating
        return redirect('/Dashboard')->with('success', 'Sale item updated successfully!');
    }

    public function destroy($id)
    {
        $saleItem = SalesList::find($id);
        $saleItem->delete();

        return redirect('/Dashboard')->with('success', 'Sale item deleted successfully!');
    }
}
