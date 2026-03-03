<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\SalesList;
use Carbon\Carbon;

class CsvController extends Controller
{
    public function downloadCsv()
    {
        // Get all data from sales_lists table
        $saleItems = SalesList::all();

        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'csv');

        // Open the temporary file for writing
        $file = fopen($tempFile, 'w');

        // Write headers to the file
        fputcsv($file, [
            'Date',
            'Location',
            'Type',
            'Payment',
            'Customer ID',
            'Order ID',
            'Product ID',
            'Count',
        ]);

        // Write data to the file
        foreach ($saleItems as $saleItem) {
            $formattedDate = Carbon::parse($saleItem->date)->format('Y-m-d');

            fputcsv($file, [
                $formattedDate,
                $saleItem->location,
                $saleItem->type,
                $saleItem->payment,
                $saleItem->customerid,
                $saleItem->orderid,
                $saleItem->productid,
                $saleItem->count,
            ]);
        }

        // Close the file
        fclose($file);

        // Return the response with the CSV file
        return response()->download($tempFile, 'sales_data.csv')->deleteFileAfterSend(true);
    }
}
