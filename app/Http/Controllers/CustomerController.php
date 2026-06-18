<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;

class CustomerController extends Controller
{
    public function getCustomerDetails(Request $request)
    {
        // Retrieve the customer_id from the request
        $customer_id = $request->input('customer_id');

        // Retrieve the customer details based on the provided customer_id
        $customer = Customer::where('customer_id', $customer_id)->first();

        if ($customer) {
            // If a customer is found, return the customer details as JSON response
            return response()->json($customer);
        } else {
            // If no customer is found, create a new customer with the provided customer_id and customer_name "newcustomer"
            $newCustomer = Customer::create([
                'customer_id' => $customer_id,
                'customer_name' => 'customer_' . $customer_id,
            ]);

            // Return the newly created customer details as JSON response
            return response()->json($newCustomer);
        }
    }
}
