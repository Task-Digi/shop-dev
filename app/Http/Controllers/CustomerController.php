<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Customer;

class CustomerController extends Controller
{
    public function getCustomerDetails(Request $request)
    {
        
        $customer_id = $request->input('customer_id');
        $customer = Customer::where('customer_id', $customer_id)->first();

        return response()->json($customer);
    }
}
