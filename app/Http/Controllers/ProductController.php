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
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
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
