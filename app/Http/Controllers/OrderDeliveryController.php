<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Events\ScanBroadcast;
use App\Models\OrderItem;
use App\Models\OrderRecord;
use App\Models\OrderScan;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderDeliveryController extends Controller
{
    public function index()
    {
        $orders = OrderRecord::orderBy('updated_at', 'desc')->get();

        foreach ($orders as $order) {
            // Calculate unique items and order sum
            $orderItems = OrderItem::where('order_id', $order->order_id)->get();
            $order->total_unique_items = $orderItems->count();
            $order->total_quantity = $orderItems->sum('quantity');

            // Enhance orders with a list of EANs for searching in the UI
            $skus = $orderItems->pluck('sku')->unique();
            // Map SKUs to EANs if they aren't already EANs
            $products = Product::whereIn('product_id', $skus)->get()->pluck('ean_code')->unique();
            // Combine SKUs and EANs into a searchable string
            $order->searchable_eans = $skus->merge($products)->filter()->unique()->implode(' ');

            // Get unique OrderID2s (source orders)
            $order->source_orders = $orderItems->pluck('order_id2')->filter()->unique()->implode(', ');
        }

        return view('order-delivery.index', compact('orders'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'order_file' => 'required'
        ]);

        try {
            $file = $request->file('order_file');
            $handle = fopen($file->getRealPath(), 'r');

            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';

            $header = fgetcsv($handle, 0, $delimiter);

            if (!$header || count($header) < 5) {
                if ($handle) fclose($handle);
                return back()->withErrors(['message' => 'Invalid CSV format or delimiter not recognized']);
            }

            $header = array_map(function ($h) {
                return trim($h, "\xEF\xBB\xBF \t\n\r\0\x0B");
            }, $header);

            DB::beginTransaction();
            $invalidDates = ['0001-01-01', '0000-00-00', null];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                if (count($row) < count($header)) continue;

                // Convert row to UTF-8 and TRIM all values
                $row = array_map(function ($item) {
                    $item = mb_convert_encoding($item, 'UTF-8', 'ISO-8859-1');
                    return trim($item, "\xEF\xBB\xBF \t\n\r\0\x0B");
                }, $row);

                $data = array_combine($header, $row);

                // Extracting potentially relevant fields with fallbacks
                $csvOrderId = trim($data['OrderId'] ?? $data['OrdreNr'] ?? $data['OrderNumber'] ?? '');

                if (empty($csvOrderId)) continue;

                $currentOrderId = $request->filled('target_order_id') ? trim($request->input('target_order_id')) : $csvOrderId;
                $currentOrderId2 = $csvOrderId;

                // Robust Price and SKU extraction
                $sku = $data['StockKeepingUnit'] ?? $data['ProductNumber'] ?? $data['Varekode'] ?? $data['SKU'] ?? $data['Vare Nr'] ?? null;
                if ($sku !== null) $sku = trim((string)$sku);

                // Add protection: Don't import the same SKU in the same supplier list twice
                $alreadyImported = OrderItem::where('order_id', $currentOrderId)
                    ->where('order_id2', $currentOrderId2)
                    ->where('sku', $sku)
                    ->exists();

                if ($alreadyImported) {
                    continue; // Skip individual duplicate row instead of failing the whole file
                }

                $currentOrderDate = $this->parseDate($data['OrderDate'] ?? $data['Ordredato'] ?? null);
                $currentPlannedDelivery = $this->parseDate($data['PlannedDelivery'] ?? $data['PlanlagtLevering'] ?? $data['Leveringsdato'] ?? null);

                // Find or create the OrderRecord
                $orderRecord = OrderRecord::firstOrCreate(
                    ['order_id' => $currentOrderId],
                    ['status' => 'Started']
                );

                // Update dates if currently missing/invalid
                $updateNeeded = false;
                if ($currentOrderDate && in_array($orderRecord->order_date, $invalidDates)) {
                    $orderRecord->order_date = $currentOrderDate;
                    $updateNeeded = true;
                }
                if ($currentPlannedDelivery && in_array($orderRecord->planned_delivery, $invalidDates)) {
                    $orderRecord->planned_delivery = $currentPlannedDelivery;
                    $updateNeeded = true;
                }
                if ($updateNeeded) {
                    $orderRecord->save();
                }

                $price = $this->parseNumber($data['Price'] ?? $data['Pris'] ?? $data['UnitPrice'] ?? $data['Enhetspris'] ?? 0);
                $ean = $data['EAN'] ?? $data['GTIN'] ?? $data['Barcode'] ?? $data['Strekkode'] ?? null;
                if ($ean !== null) $ean = trim((string)$ean);

                $itemName = $data['ItemName'] ?? $data['ProductName'] ?? $data['Beskrivelse'] ?? $data['Varenavn'] ?? 'Unknown Item';
                $orderedBy = $data['OrderedBy'] ?? $data['BestiltAv'] ?? $data['Ordered By'] ?? null;
                $yourRef = $data['YourReference'] ?? $data['DinReferanse'] ?? $data['Your Ref'] ?? null;

                // Check for EXISTING item in this specific list (order_id2) for this SKU
                $existingItem = OrderItem::where('order_id', $currentOrderId)
                    ->where('order_id2', $currentOrderId2)
                    ->where('sku', $sku)
                    ->first();

                if ($existingItem) {
                    // Update quantity instead of skipping or creating duplicate
                    $existingItem->quantity += $this->parseNumber($data['OrderedQuantity'] ?? $data['Quantity'] ?? 0);
                    $existingItem->ordered_quantity += $this->parseNumber($data['OrderedQuantity'] ?? 0);
                    if ($price > 0) $existingItem->price = $price;
                    $existingItem->save();
                } else {
                    OrderItem::create([
                        'order_id' => $currentOrderId,
                        'order_id2' => $currentOrderId2,
                        'order_date' => $currentOrderDate,
                        'ordered_by' => $orderedBy,
                        'planned_delivery' => $currentPlannedDelivery,
                        'status' => $data['Status'] ?? null,
                        'your_reference' => $yourRef,
                        'sku' => $sku,
                        'item_name' => $itemName,
                        'packaging_quantity' => $this->parseNumber($data['PackagingQuantity'] ?? 1),
                        'packaging_unit' => $data['PackagingUnit'] ?? 'STK',
                        'ordered_quantity' => $this->parseNumber($data['OrderedQuantity'] ?? 0),
                        'delivered' => $this->parseNumber($data['DeliveredQuantity'] ?? $data['Delivered'] ?? 0),
                        'quantity' => $this->parseNumber($data['OrderedQuantity'] ?? $data['Quantity'] ?? 0),
                        'price' => $price
                    ]);
                }

                // Automatic Price & Product Sync
                if ($sku || $ean) {
                    // 1. Primary Match: SKU
                    $product = null;
                    if ($sku) {
                        $product = Product::where('product_id', $sku)->first();
                    }

                    // 2. Secondary Match: EAN (if no SKU match)
                    if (!$product && $ean) {
                        $product = Product::where('ean_code', $ean)->first();
                    }

                    if ($product) {
                        // Smart Update: Don't overwrite good data with bad data
                        if (!empty($itemName) && (empty($product->product_name) || stripos($product->product_name, 'Unknown') !== false)) {
                            $product->product_name = $itemName;
                        }

                        if ($price > 0) {
                            $product->price = $price;
                        }

                        // Update EAN if found in CSV and current is empty
                        if ($ean && empty($product->ean_code)) {
                            $product->ean_code = $ean;
                        }

                        if ($product->isDirty()) {
                            $product->save();
                        }
                    } else {
                        // Auto-create missing product
                        Product::create([
                            'product_id' => $sku ?: $ean, // Use SKU as ID if available, otherwise EAN
                            'product_name' => $itemName,
                            'price' => $price,
                            'ean_code' => $ean,
                            'retail' => 0
                        ]);
                        Log::info("Auto-created product from CSV import: " . ($sku ?: $ean));
                    }
                }
            }

            fclose($handle);
            DB::commit();

            try {
                // Broadcast event to trigger index page auto-refresh for active observers
                broadcast(new ScanBroadcast('global', [
                    'action' => 'reload_list',
                    'timestamp' => \Carbon\Carbon::now()->toDateTimeString()
                ]));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Pusher Broadcast Error during Import: " . $e->getMessage());
            }

            return back()->with('success', 'Order imported and product prices synchronized successfully');
        } catch (\Exception $e) {
            if (isset($handle)) fclose($handle);
            DB::rollBack();
            Log::error("Error importing CSV: " . $e->getMessage());
            return back()->withErrors(['message' => 'Error importing CSV: ' . $e->getMessage()]);
        }
    }

    private function parseNumber($value)
    {
        if (is_numeric($value)) return $value;
        $clean = str_replace([' ', ','], ['', '.'], $value);
        return is_numeric($clean) ? (float)$clean : 0;
    }

    public function show($orderId)
    {
        $order = OrderRecord::where('order_id', $orderId)->firstOrFail();
        $items = OrderItem::where('order_id', $orderId)->get();

        $recentScans = OrderScan::where('order_id', $orderId)
            ->where('deactivated', false)
            ->orderBy('updated_at', 'desc')
            ->get();

        // 1. Normalize and resolve all products and identifiers
        $scanCodes = $recentScans->pluck('ean_code')->map(function ($c) {
            return trim((string)$c);
        })->unique();
        $orderSkus = $items->pluck('sku')->map(function ($s) {
            return trim((string)$s);
        })->unique();

        $products = Product::whereIn('ean_code', $scanCodes)
            ->orWhereIn('product_id', $scanCodes->merge($orderSkus)->unique())
            ->get();

        $productMapByEan = $products->filter(function ($p) {
            return !is_null($p->ean_code);
        })->keyBy(function ($p) {
            return trim((string)$p->ean_code);
        });

        $productMapById = $products->filter(function ($p) {
            return !is_null($p->product_id);
        })->keyBy(function ($p) {
            return trim((string)$p->product_id);
        });

        // helper to get "true key" (Prefer EAN)
        $getTrueKey = function ($code) use ($productMapByEan, $productMapById) {
            $code = trim((string)$code);
            $p = $productMapByEan->get($code) ?? $productMapById->get($code);
            return ($p && $p->ean_code) ? trim((string)$p->ean_code) : $code;
        };

        // 2. Build aggregation maps
        $orderedMap = [];
        foreach ($items as $item) {
            $key = $getTrueKey($item->sku);
            $orderedMap[$key] = ($orderedMap[$key] ?? 0) + $item->quantity;
        }

        $scannedMap = [];
        foreach ($recentScans as $scan) {
            $key = $getTrueKey($scan->ean_code);
            $scannedMap[$key] = ($scannedMap[$key] ?? 0) + $scan->units;
        }

        // 3. Enrich Scan Objects
        $enrichedScans = collect();
        foreach ($recentScans as $scan) {
            $key = $getTrueKey($scan->ean_code);
            $p = $productMapByEan->get($key) ?? $productMapById->get($scan->ean_code);

            // Find matching order line for name/SKU fallback
            $tempItem = $items->first(function ($it) use ($key, $scan) {
                return trim((string)$it->sku) === $key || trim((string)$it->sku) === trim((string)$scan->ean_code);
            });

            $scan->product_name = $p ? $p->product_name : ($tempItem ? $tempItem->item_name : 'Unknown');
            $scan->sku = $p ? $p->product_id : ($tempItem ? $tempItem->sku : null);

            // Get original list IDs (order_id2)
            $matchedOrderItems = $items->filter(function ($it) use ($key, $scan) {
                return trim((string)$it->sku) === $key || trim((string)$it->sku) === trim((string)$scan->ean_code);
            });
            $scan->order_id2 = $matchedOrderItems->pluck('order_id2')->filter()->unique()->implode(', ');
            $scan->your_reference = $matchedOrderItems->pluck('your_reference')->filter()->unique()->implode(', ');
            $scan->ordered_by = $matchedOrderItems->pluck('ordered_by')->filter()->unique()->implode(', ');
            $scan->order_price = $matchedOrderItems->first() ? $matchedOrderItems->first()->price : 0;

            $scan->ordered_total = $orderedMap[$key] ?? 0;
            $scan->scanned_total = $scannedMap[$key] ?? 0;
            $scan->ean_code = $key; // Unified display

            $enrichedScans->push($scan);
        }

        // 4. Add "Missing" items (items ordered but never scanned)
        $handledKeys = array_keys($scannedMap);

        // Group items by their true key to avoid duplicates in the "Missing" list
        $groupedItems = $items->groupBy(function ($item) use ($getTrueKey) {
            return $getTrueKey($item->sku);
        });

        foreach ($groupedItems as $key => $itemsForThisKey) {
            if (!in_array((string)$key, $handledKeys)) {
                $firstItem = $itemsForThisKey->first();
                $virtualScan = new OrderScan();
                $virtualScan->id = 0;
                $virtualScan->is_virtual = true;
                $virtualScan->order_id = $orderId;
                $virtualScan->ean_code = (string)$key;
                $virtualScan->units = 0;
                $virtualScan->created_at = null;
                $virtualScan->scan_date_time = null;

                $product = $productMapByEan->get($key) ?? $productMapById->get($key);

                $virtualScan->product_name = $product ? $product->product_name : $firstItem->item_name;
                $virtualScan->sku = $product ? $product->product_id : $firstItem->sku;
                $virtualScan->order_id2 = $itemsForThisKey->pluck('order_id2')->filter()->unique()->implode(', ');
                $virtualScan->your_reference = $itemsForThisKey->pluck('your_reference')->filter()->unique()->implode(', ');
                $virtualScan->ordered_by = $itemsForThisKey->pluck('ordered_by')->filter()->unique()->implode(', ');
                $virtualScan->order_price = $firstItem->price;
                $virtualScan->ordered_total = $orderedMap[$key] ?? 0;
                $virtualScan->scanned_total = 0;

                $enrichedScans->push($virtualScan);
                $handledKeys[] = (string)$key;
            }
        }

        $recentScans = $enrichedScans;

        $totalExpected = $items->sum('quantity');
        $totalScanned = OrderScan::where('order_id', $orderId)->where('deactivated', false)->sum('units');
        $progressPercent = $totalExpected > 0 ? min(100, ($totalScanned / $totalExpected) * 100) : 0;

        $latestScanId = $recentScans->where('id', '>', 0)->first() ? $recentScans->where('id', '>', 0)->first()->id : null;

        return view('order-delivery.show', compact('order', 'items', 'recentScans', 'totalExpected', 'totalScanned', 'progressPercent', 'latestScanId'));
    }

    public function scan(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:order_records,order_id',
            'ean_code' => 'required'
        ]);

        $orderId = $request->order_id;
        $ean = trim((string)$request->ean_code);

        // 1. Register Scan - ALWAYS create a new record
        // Note: We save exactly what was scanned. If it was an ID, we save the ID.
        $scan = OrderScan::create([
            'order_id' => $orderId,
            'scan_date_time' => Carbon::now(),
            'ean_code' => $ean,
            'units' => 1
        ]);

        // 2. Get high-fidelity state
        $state = $this->getProductState($orderId, $ean);

        // 3. Automatic Price & EAN Sync
        $product = Product::where('ean_code', $ean)->orWhere('product_id', $ean)->first();

        if ($product) {
            // High-Fidelity EAN Sync: If scanned code is a real barcode (EAN-13/8) 
            // and product's EAN is just the SKU or different, update it.
            $isRealEan = is_numeric($ean) && (strlen($ean) == 8 || strlen($ean) == 12 || strlen($ean) == 13 || strlen($ean) == 14);
            if ($isRealEan && $product->ean_code !== $ean) {
                Log::info("Correcting EAN for product {$product->product_id} from scan: {$ean}");
                $product->ean_code = $ean;
                $product->save();
            }

            $orderItem = OrderItem::where('order_id', $orderId)
                ->where('sku', $product->product_id)
                ->first();

            if ($orderItem && $orderItem->price > 0) {
                $product->price = $orderItem->price;
                $product->save();
            }
        }

        // 4. Broadcast Real-Time Update
        $channelName = config('app.env') . '.order.' . $orderId;
        Log::info("Broadcasting scan for order: $orderId, EAN: $ean, Channel: $channelName");
        try {
            broadcast(new ScanBroadcast($orderId, array_merge($state, [
                'scan_id' => $scan->id,
                'order_id' => $orderId,
                'timestamp' => Carbon::parse($scan->scan_date_time)->format('H:i')
            ])));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'timestamp' => Carbon::parse($scan->scan_date_time)->format('H:i')
        ], $state));
    }

    public function viewProduct(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:order_records,order_id',
            'ean_code' => 'required'
        ]);

        $state = $this->getProductState($request->order_id, $request->ean_code);
        return response()->json(array_merge(['success' => true], $state));
    }

    public function updateUnits(Request $request)
    {
        $request->validate([
            'scan_id' => 'required|exists:order_scans,id',
            'change' => 'required|integer'
        ]);

        $scan = OrderScan::findOrFail($request->scan_id);
        $scan->units += $request->change;

        if ($scan->units < 0) $scan->units = 0; // Allow 0 to properly decrement

        $scan->save();

        $state = $this->getProductState($scan->order_id, $scan->ean_code);

        // Broadcast Update
        Log::info("Broadcasting unit update for scan: {$scan->id}, new units: {$scan->units}");
        try {
            broadcast(new ScanBroadcast($scan->order_id, array_merge($state, [
                'update_units' => true,
                'scan_id' => $scan->id,
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i')
            ])));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'units' => $scan->units,
            'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i')
        ], $state));
    }

    public function updateUnitsExact(Request $request)
    {
        $request->validate([
            'scan_id' => 'required|exists:order_scans,id',
            'units' => 'required|integer|min:0'
        ]);

        $scan = OrderScan::findOrFail($request->scan_id);
        $scan->units = $request->units;
        $scan->save();

        $state = $this->getProductState($scan->order_id, $scan->ean_code);

        // Broadcast Update
        Log::info("Broadcasting exact unit update for scan: {$scan->id}, new units: {$scan->units}");
        try {
            broadcast(new ScanBroadcast($scan->order_id, array_merge($state, [
                'update_units' => true,
                'scan_id' => $scan->id,
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i')
            ])));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'units' => $scan->units,
            'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i')
        ], $state));
    }

    public function deleteScan(Request $request)
    {
        $request->validate([
            'scan_id' => 'required|exists:order_scans,id'
        ]);

        $scan = OrderScan::findOrFail($request->scan_id);
        $scan->deactivated = true;
        $scan->save();

        $state = $this->getProductState($scan->order_id, $scan->ean_code);

        // Broadcast Deletion
        Log::info("Broadcasting scan deletion: {$scan->id}");
        try {
            broadcast(new ScanBroadcast($scan->order_id, array_merge($state, [
                'delete_scan' => true,
                'scan_id' => $scan->id,
                'ean_code' => $scan->ean_code
            ])));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge(['success' => true], $state));
    }

    public function updateUnitsToMatchOrder(Request $request)
    {
        $request->validate([
            'scan_id' => 'required|exists:order_scans,id'
        ]);

        $scan = OrderScan::findOrFail($request->scan_id);
        $orderId = $scan->order_id;

        // 1. Find Ordered Quantity
        $product = Product::where('ean_code', $scan->ean_code)
            ->orWhere('product_id', $scan->ean_code)
            ->first();

        $sku = ($product && $product->product_id) ? $product->product_id : $scan->ean_code;
        $trueEan = ($product && $product->ean_code) ? $product->ean_code : $scan->ean_code;

        $orderItem = OrderItem::where('order_id', $orderId)->where('sku', $sku)->first();
        if (!$orderItem) {
            $orderItem = OrderItem::where('order_id', $orderId)
                ->whereHas('product', function ($q) use ($trueEan) {
                    $q->where('ean_code', $trueEan);
                })->first();
        }

        if (!$orderItem) {
            return response()->json(['success' => false, 'message' => 'Order item not found']);
        }

        $totalOrdered = (int)$orderItem->quantity;

        // 2. Find Total Scanned ALREADY for this item (excluding the current scan row which we will adjust)
        // We want: OtherScans + ThisScan = TotalOrdered -> ThisScan = TotalOrdered - OtherScans
        if ($product) {
            $otherScanned = OrderScan::where('order_id', $orderId)
                ->where('deactivated', false)
                ->where('id', '!=', $scan->id)
                ->where(function ($q) use ($product) {
                    $q->where('ean_code', $product->ean_code)
                        ->orWhere('ean_code', $product->product_id);
                })
                ->sum('units');
        } else {
            $otherScanned = OrderScan::where('order_id', $orderId)
                ->where('deactivated', false)
                ->where('id', '!=', $scan->id)
                ->where('ean_code', $scan->ean_code)
                ->sum('units');
        }

        // 3. Update THIS specific scan row to make up the difference
        // If ordered is 5, and other rows have 2, this row becomes 3. 
        // If total ordered is 5 and others have 5, this becomes 0? Usually we just want to match it.
        // The requirement: "It’s not “add 5 more”. It’s “make total scanned equal ordered”."
        $newUnitsForThisRow = max(1, $totalOrdered - $otherScanned); // Ensure it doesn't go below 1 or 0 depending on logic, let's say 1 minimum for an active scan row

        // Actually, if they press #OK, they want the *total* to equal ordered.
        $scan->units = max(0, $totalOrdered - $otherScanned);

        // If it becomes 0, maybe we should deactivate it? But standard is 1 minimum usually.
        // Let's allow 0 or just deactivate if it's 0 to keep data clean, but let's just save.
        $scan->save();

        $state = $this->getProductState($orderId, $scan->ean_code);

        // Broadcast Update
        Log::info("Broadcasting match order for scan: {$scan->id}, new total units matched to ordered.");
        try {
            broadcast(new ScanBroadcast($orderId, array_merge($state, [
                'update_units' => true,
                'scan_id' => $scan->id,
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i')
            ])));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'units' => $scan->units,
            'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i')
        ], $state));
    }


    /**
     * Helper to get high-fidelity state for any product in an order.
     */
    private function getProductState($orderId, $ean)
    {
        $ean = trim((string)$ean);
        // FIX: Prioritize EAN lookup, then Product ID
        $product = Product::where('ean_code', $ean)->first();
        if (!$product) {
            $product = Product::where('product_id', $ean)->first();
        }

        $currentPrice = $product ? $product->price : 0;
        $sku = ($product && $product->product_id) ? $product->product_id : $ean;
        $trueEan = ($product && $product->ean_code) ? $product->ean_code : $ean;

        // Sum all ACTIVE scans for this Product + Order
        if ($product) {
            $totalScanned = OrderScan::where('order_id', $orderId)
                ->where('deactivated', false)
                ->where(function ($q) use ($product, $ean) {
                    $q->where('ean_code', $product->ean_code)
                        ->orWhere('ean_code', $product->product_id)
                        ->orWhere('ean_code', $ean); // Include what was actually scanned
                })
                ->sum('units');
        } else {
            // Fallback if no product found: just sum exact matches
            $totalScanned = OrderScan::where('order_id', $orderId)
                ->where('ean_code', $ean)
                ->where('deactivated', false)
                ->sum('units');
        }

        // Sum all Ordered Quantity
        // First try to resolve the product to get its ID (SKU)
        $product = Product::where('ean_code', $trueEan)->orWhere('product_id', $trueEan)->first();
        $sku = $product ? $product->product_id : $ean;

        $orderItems = OrderItem::where('order_id', $orderId)
            ->where(function ($q) use ($sku, $trueEan) {
                $q->where('sku', $sku)->orWhere('sku', $trueEan);
            })->get();

        $totalOrdered = $orderItems->sum('quantity');
        $orderId2s = $orderItems->pluck('order_id2')->filter()->unique()->implode(', ');
        $firstItem = $orderItems->first();
        $productName = $product ? $product->product_name : ($firstItem ? $firstItem->item_name : 'Unknown Item');

        // Status Logic
        $remaining = max(0, $totalOrdered - $totalScanned);
        $diff = $totalScanned - $totalOrdered;
        $status = $diff == 0 ? 'COMPLETE' : ($diff > 0 ? 'OVER' : 'UNDER');

        // Overall Progress
        $totalExpectedOrder = OrderItem::where('order_id', $orderId)->sum('quantity');
        $totalScannedOrder = OrderScan::where('order_id', $orderId)->where('deactivated', false)->sum('units');
        $progressPercent = $totalExpectedOrder > 0 ? min(100, ($totalScannedOrder / $totalExpectedOrder) * 100) : 0;

        return [
            'product_name' => $productName,
            'ean_code' => $trueEan, // Always return true EAN if known
            'product_id' => $sku,
            'scanned' => (int)$totalScanned,
            'ordered' => (int)$totalOrdered,
            'remaining' => $remaining,
            'status' => $status,
            'progress_percent' => round($progressPercent),
            'current_price' => $currentPrice,
            'order_id2' => $orderId2s,
            'your_reference' => $orderItems->pluck('your_reference')->filter()->unique()->implode(', '),
            'ordered_by' => $orderItems->pluck('ordered_by')->filter()->unique()->implode(', '),
            'order_price' => $orderItems->first() ? $orderItems->first()->price : 0
        ];
    }

    public function closeOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:order_records,order_id',
            'staff' => 'required',
            'note' => 'nullable',
            'planned_delivery' => 'nullable|date'
        ]);

        $order = OrderRecord::where('order_id', $request->order_id)->first();

        // Deviation Logic
        $deviations = $this->getDeviations($request->order_id);
        $hasDeviations = !empty($deviations);

        $order->status = $hasDeviations ? 'Done with ERR' : 'Completed';
        $order->staff = $request->staff;
        $order->note = $request->note;
        if ($request->has('planned_delivery') && $request->planned_delivery) {
            $order->planned_delivery = $request->planned_delivery;
        }
        $order->delivery_handling_date = Carbon::now();
        $order->save();

        return response()->json([
            'success' => true,
            'status' => $order->status,
            'deviations' => $deviations
        ]);
    }

    private function getDeviations($orderId)
    {
        $deviations = [];
        $items = OrderItem::where('order_id', $orderId)->get();

        // Complex sync similar to getProductState to catch all scans
        // Simplified: Fetch all scans, map by resolved SKU/EAN

        $allScans = OrderScan::where('order_id', $orderId)
            ->where('deactivated', false)
            ->get();

        // Pre-fetch products roughly
        $scanCodes = $allScans->pluck('ean_code')->unique();
        $products = Product::whereIn('ean_code', $scanCodes)->orWhereIn('product_id', $scanCodes)->get();
        // Map both ways
        $codeToProduct = [];
        foreach ($products as $p) {
            if ($p->ean_code) $codeToProduct[$p->ean_code] = $p;
            if ($p->product_id) $codeToProduct[$p->product_id] = $p;
        }

        $scannedBySku = [];
        foreach ($allScans as $s) {
            $p = $codeToProduct[$s->ean_code] ?? null;
            $key = $p ? $p->product_id : $s->ean_code; // Normalize to SKU if possible
            if (!isset($scannedBySku[$key])) $scannedBySku[$key] = 0;
            $scannedBySku[$key] += $s->units;
        }

        foreach ($items as $item) {
            // Get product to find EAN
            $product = Product::where('product_id', $item->sku)->first();
            $ean = $product ? $product->ean_code : $item->sku;

            // Find scanned count for this item
            $scanned = $scannedBySku[$item->sku] ?? 0;
            // Also try looking up by EAN if SKU diff
            if ($scanned == 0 && $product && !empty($product->ean_code)) {
                $scanned = $scannedBySku[$product->ean_code] ?? 0;
            }

            if ($scanned != $item->quantity) {
                $deviations[] = [
                    'sku' => $item->sku,
                    'ean' => $ean,
                    'name' => $item->item_name,
                    'expected' => $item->quantity,
                    'scanned' => $scanned,
                    'diff' => $scanned - $item->quantity
                ];
            }
        }
        return $deviations;
    }


    public function sync($orderId)
    {
        // For mobile/PC real-time update check
        $latestScan = OrderScan::where('order_id', $orderId)
            ->where('deactivated', false)
            ->orderBy('id', 'desc')
            ->first();

        return response()->json(['latest_scan_id' => $latestScan ? $latestScan->id : 0]);
    }

    public function mobileGlobal()
    {
        // 1. Fetch the absolute latest active scan across ALL orders
        $latestScan = OrderScan::where('deactivated', false)
            ->orderBy('id', 'desc')
            ->first();

        $order = null;
        if ($latestScan) {
            $order = OrderRecord::where('order_id', $latestScan->order_id)->first();
            // Enrich scan data
            $state = $this->getProductState($latestScan->order_id, $latestScan->ean_code);
            $latestScan->product_name = $state['product_name'];
            $latestScan->ordered_total = $state['ordered'];
            $latestScan->scanned_total = $state['scanned'];
            $latestScan->sku = $state['product_id'];
        }

        // If no latest scan, find the latest active order
        if (!$order) {
            $order = OrderRecord::where('status', 'Started')->orderBy('id', 'desc')->first();
        }

        // Final fallback
        if (!$order) {
            $order = OrderRecord::orderBy('id', 'desc')->first();
        }

        if (!$order) {
            return redirect()->route('order-delivery.index')->withErrors(['message' => 'No orders available for mobile scanner.']);
        }

        return view('order-delivery.mobile_global', compact('order', 'latestScan'));
    }

    public function mobile($orderId)
    {
        $order = OrderRecord::where('order_id', $orderId)->firstOrFail();

        // Fetch the ABSOLUTE latest scan, active or not (though usually active)
        // We only need the top one.
        $latestScan = OrderScan::where('order_id', $orderId)
            ->where('deactivated', false) // Only active scans
            ->orderBy('id', 'desc')
            ->first();

        // We need to enrich it just like in show() so the view can render totals
        if ($latestScan) {
            // Quick reuse of enrichment logic (simplified for single item)
            $state = $this->getProductState($orderId, $latestScan->ean_code);
            $latestScan->product_name = $state['product_name'];
            $latestScan->ordered_total = $state['ordered'];
            $latestScan->scanned_total = $state['scanned'];
            $latestScan->sku = $state['product_id'];
        }

        return view('order-delivery.mobile_display', compact('order', 'latestScan'));
    }

    public function deleteOrder($orderId)
    {
        try {
            DB::beginTransaction();

            $order = OrderRecord::where('order_id', $orderId)->firstOrFail();

            // Delete associated items and scans
            OrderItem::where('order_id', $orderId)->delete();
            OrderScan::where('order_id', $orderId)->delete();

            $order->delete();

            DB::commit();
            return redirect()->route('order-delivery.index')->with('success', 'Order delivery session #' . $orderId . ' deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error deleting order delivery: " . $e->getMessage());
            return back()->withErrors(['message' => 'Error deleting order delivery: ' . $e->getMessage()]);
        }
    }

    private function parseDate($dateString)
    {
        if (empty($dateString) || strtolower($dateString) == 'null' || $dateString == '00.00.0000') return null;

        // Remove time part if it exists
        $dateOnly = explode(' ', trim($dateString))[0];

        try {
            // Check for dd.mm.yyyy format
            if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $dateOnly)) {
                return Carbon::createFromFormat('d.m.Y', $dateOnly)->format('Y-m-d');
            }
            // Check for dd.mm.yy format
            if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{2}$/', $dateOnly)) {
                return Carbon::createFromFormat('d.m.y', $dateOnly)->format('Y-m-d');
            }

            $parsed = Carbon::parse($dateOnly);
            if ($parsed->year < 1000) return null; // Handle cases like 00.00.0000 that parse to year 0001

            return $parsed->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
