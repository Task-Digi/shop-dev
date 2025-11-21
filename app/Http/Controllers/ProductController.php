<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Product;

class ProductController extends Controller
{
    public function getProductDetails(Request $request)
    {
        $product_id = $request->input('product_id');
        $product = Product::where('product_id', $product_id)->first();

        // Check if the product exists
        if (!$product) {
            // Product does not exist, create a new product with default values
            $product = new Product();
            $product->product_id = $product_id;
            $product->product_name = 'product_' . $product_id; // Set product name as "product_" followed by product ID
            $product->price = 0;
            $product->retail = 0;

            // Save the new product to the database
            $product->save();
        }

        return response()->json($product);
    }

    public function checkProduct(Request $request)
    {
        $product_id = $request->input('productid');
        $product = Product::where('product_id', $product_id)->first();

        if ($product) {
            return response()->json(['exists' => true]);
        } else {
            return response()->json(['exists' => false]);
        }
    }
}
