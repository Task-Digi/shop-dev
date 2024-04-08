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

        return response()->json($product);
    }
}