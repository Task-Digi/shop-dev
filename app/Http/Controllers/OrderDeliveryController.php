<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Events\ScanBroadcast;
use App\OrderItem;
use App\OrderRecord;
use App\OrderScan;
use App\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderDeliveryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        if ($search) {
            // Find products matching EAN, Name, or SKU (Varenummer)
            $productIds = Product::where('ean_code', 'LIKE', "%{$search}%")
                ->orWhere('product_id', 'LIKE', "%{$search}%")
                ->orWhere('product_name', 'LIKE', "%{$search}%")
                ->pluck('product_id');

            // Search order items matching the search query or the found product IDs
            $items = OrderItem::whereIn('sku', $productIds)
                ->orWhere('item_name', 'LIKE', "%{$search}%")
                ->orWhere('sku', 'LIKE', "%{$search}%")
                ->orWhere('order_items.order_id', 'LIKE', "%{$search}%")
                ->join('order_records', 'order_items.order_id', '=', 'order_records.order_id')
                ->select('order_items.*', 'order_records.order_date as record_date')
                ->orderByDesc('record_date')
                ->get();

            foreach ($items as $item) {
                // Calculate Scanned for this specific product/SKU in this order
                // Logic based on getProductState and show method
                $product = Product::where('product_id', $item->sku)->first();
                $ean = $product ? $product->ean_code : $item->sku;

                $scannedTotal = OrderScan::where('order_id', $item->order_id)
                    ->where('deactivated', false)
                    ->where(function ($q) use ($ean, $item) {
                        $q->where('ean_code', $ean)->orWhere('ean_code', $item->sku);
                    })
                    ->sum('units');

                $item->scanned_total = (int)$scannedTotal;
                $item->ean_display = $ean;
                $item->diff = $item->scanned_total - $item->quantity;
                $item->remaining = max(0, $item->quantity - $item->scanned_total);
                $item->status_text = $item->diff >= 0 ? 'COMPLETE' : 'UNDER'; // Simple logic as requested
                $item->status_color = $item->diff >= 0 ? '#22c55e' : '#2563eb';
                
                // For volume info display (consistency with show view)
                $item->is_generic_unit = in_array(strtoupper($item->packaging_unit), ['STK', 'PCS', 'SPA', 'BOX', 'SET', 'PAK', 'FL', 'RUL', 'POS', 'ESK', 'TUB', 'ST', 'BX']);
                $item->has_volume_info = preg_match('/[0-9,.]\s*(L|ML|KG|GR|G)\b/i', $item->packaging_unit);
                $item->show_volume = $item->packaging_quantity && ($item->packaging_quantity > 1 || ($item->packaging_quantity == 1 && ($item->has_volume_info || !$item->is_generic_unit)));
            }

            return view('order-delivery.index', compact('items', 'search'));
        }

        $orders = OrderRecord::orderBy('order_date', 'desc')->get();

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
            $order->source_orders = $orderItems->pluck('order_id2')->filter(function ($id2) use ($order) {
                return $id2 && trim((string)$id2) !== trim((string)$order->order_id);
            })->unique()->implode(', ');
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
                return strtolower(trim($h, "\xEF\xBB\xBF \t\n\r\0\x0B"));
            }, $header);

            DB::beginTransaction();
            $invalidDates = ['0001-01-01', '0000-00-00', null];

            // Define helper to get value from row by multiple possible header keys
            $getField = function ($data, $keys) {
                foreach ($keys as $key) {
                    $lowKey = strtolower($key);
                    if (isset($data[$lowKey]) && $data[$lowKey] !== '') {
                        return $data[$lowKey];
                    }
                }
                return null;
            };

            $clearedOrders = [];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                if (count($row) < count($header)) continue;

                // Convert row to UTF-8 and TRIM all values
                $row = array_map(function ($item) {
                    $item = mb_convert_encoding($item, 'UTF-8', 'ISO-8859-1');
                    return trim($item, "\xEF\xBB\xBF \t\n\r\0\x0B");
                }, $row);

                $data = array_combine($header, $row);

                // Extracting potentially relevant fields with fallbacks
                $csvOrderId = trim($getField($data, ['OrderId', 'OrdreNr', 'OrderNumber', 'Order No', 'Ordre Nr', 'Ordrenummer']) ?? '');

                if (empty($csvOrderId)) continue;

                $currentOrderId = $request->filled('target_order_id') ? trim($request->input('target_order_id')) : $csvOrderId;
                $currentOrderId2 = $csvOrderId;

                // If this is an APPEND (target_order_id provided), DO NOT CLEAR existing items.
                // If it's a FRESH start (no target_order_id), we clear for the first row of each order in the file.
                if (!$request->filled('target_order_id') && !in_array($currentOrderId, $clearedOrders)) {
                    OrderItem::where('order_id', $currentOrderId)->delete();
                    $clearedOrders[] = $currentOrderId;
                }

                $currentOrderDate = $this->parseDate($getField($data, ['OrderDate', 'Ordredato', 'Order Date', 'Dato']));
                $currentPlannedDelivery = $this->parseDate($getField($data, ['PlannedDelivery', 'PlanlagtLevering', 'Leveringsdato', 'Planned Delivery', 'Delivery Date']));

                // Find or create the OrderRecord before checking for item duplicates
                $orderRecord = OrderRecord::firstOrCreate(
                    ['order_id' => $currentOrderId],
                    ['status' => 'Started']
                );

                // Update dates and completed status if currently missing/invalid/different
                $updateNeeded = false;
                if ($currentOrderDate && in_array($orderRecord->order_date, $invalidDates)) {
                    $orderRecord->order_date = $currentOrderDate;
                    $updateNeeded = true;
                }
                if ($currentPlannedDelivery && in_array($orderRecord->planned_delivery, $invalidDates)) {
                    $orderRecord->planned_delivery = $currentPlannedDelivery;
                    $updateNeeded = true;
                }

                // Support importing 'completed' status if column exists in CSV
                $csvCompleted = $getField($data, ['Completed', 'IsCompleted', 'Ferdig', 'StatusCompleted']);
                if ($csvCompleted !== null) {
                    $val = (int)$csvCompleted;
                    if ($orderRecord->completed != $val) {
                        $orderRecord->completed = $val;
                        $updateNeeded = true;
                    }
                }

                if ($updateNeeded) {
                    $orderRecord->save();
                }

                // Robust Price and SKU extraction
                $skuAliases = [
                    'StockKeepingUnit',
                    'ProductNumber',
                    'Varekode',
                    'SKU',
                    'Vare Nr',
                    'VareNr',
                    'VareNr.',
                    'Varenummer',
                    'Varenr',
                    'vnr',
                    'ItemNo',
                    'Item No',
                    'ArticleNumber',
                    'Article Number'
                ];
                $sku = $getField($data, $skuAliases);
                if ($sku !== null) $sku = trim((string)$sku);

                // Add protection: Don't import the same SKU in the same supplier list twice
                $alreadyImported = OrderItem::where('order_id', $currentOrderId)
                    ->where('order_id2', $currentOrderId2)
                    ->where('sku', $sku)
                    ->exists();

                if ($alreadyImported) {
                    continue; // Skip individual duplicate row instead of failing the whole file
                }

                // Robust Price and SKU extraction moved up for protection check

                $price = $this->parseNumber($getField($data, ['Price', 'Pris', 'UnitPrice', 'Enhetspris', 'Unit Price']));

                $eanAliases = ['EAN', 'GTIN', 'Barcode', 'Strekkode', 'EAN13', 'GTIN13', 'EAN-13'];
                $ean = $getField($data, $eanAliases);
                if ($ean !== null) $ean = trim((string)$ean);

                // SKIP ROW: If a product does not have a Varenr (SKU) / EAN, it should not be added to the product list.
                if (empty($sku) && empty($ean)) {
                    continue;
                }

                $itemName = $getField($data, ['ItemName', 'ProductName', 'Beskrivelse', 'Varenavn', 'Item Name', 'Product Name', 'Description']);
                if (empty($itemName)) {
                    $itemName = 'product_' . ($sku ?: $ean ?: 'unknown');
                }
                $orderedBy = $getField($data, ['OrderedBy', 'BestiltAv', 'Ordered By', 'Bestilt av']);
                $yourRef = $getField($data, ['YourReference', 'DinReferanse', 'Your Ref', 'Din ref']);

                OrderItem::create([
                    'order_id' => $currentOrderId,
                    'order_id2' => $currentOrderId2,
                    'order_date' => $currentOrderDate,
                    'ordered_by' => $orderedBy,
                    'planned_delivery' => $currentPlannedDelivery,
                    'status' => $getField($data, ['Status']),
                    'your_reference' => $yourRef,
                    'sku' => $sku,
                    'item_name' => $itemName,
                    'packaging_quantity' => $this->parseNumber($data['PackagingQuantity'] ?? 1),
                    'packaging_unit' => $data['PackagingUnit'] ?? 'STK',
                    'ordered_quantity' => $this->parseNumber($getField($data, ['OrderedQuantity', 'Ordered Quantity', 'BestiltAntall', 'Bestilt antall', 'QtyOrdered']) ?? 0),
                    'delivered' => $this->parseNumber($getField($data, ['DeliveredQuantity', 'Delivered', 'LevertAntall', 'Levert antall']) ?? 0),
                    'quantity' => $this->parseNumber($getField($data, ['OrderedQuantity', 'Quantity', 'Antall', 'Ant']) ?? 0),
                    'price' => $price
                ]);

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
                        if (!empty($itemName) && (empty($product->product_name) || stripos($product->product_name, 'Unknown') !== false || stripos($product->product_name, 'product_') === 0)) {
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
                        Log::info("Matched existing product for CSV import: " . ($sku ?: $ean));
                    } else {
                        // Auto-create missing product ONLY from CSV import
                        Product::create([
                            'product_id' => $sku ?: $ean, // Use SKU as ID if available, otherwise EAN
                            'product_name' => (!empty($itemName) && stripos($itemName, 'product_') !== 0 && stripos($itemName, 'Unknown') === false) ? $itemName : 'Unknown',
                            'price' => $price > 0 ? $price : 0,
                            'ean_code' => $ean ?: ($sku ?: $ean),
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
        session(['active_order_id' => $orderId]);

        // Track globally for mobile sync (cross-device)
        \Illuminate\Support\Facades\Cache::put('warehouse_active_order_id', $orderId, 1440); // 24hr persist

        // Broadcast Switch Order to Global Channel
        try {
            broadcast(new ScanBroadcast('global', [
                'switch_order' => true,
                'order_id' => $orderId,
                'message' => "Desktop switched to Order #$orderId"
            ]));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Switch Order Error: " . $e->getMessage());
        }

        $items = OrderItem::where('order_id', $orderId)->get();

        $recentScans = OrderScan::where('order_id', $orderId)
            ->where('deactivated', false)
            ->orderBy('id', 'desc')
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

        // Build maps with priority for products in the order if duplicates exist
        // We sort such that order-linked products appear LATER, so keyBy overwrites placeholders with them
        $orderSkusArray = $orderSkus->toArray();
        $products = $products->sort(function ($a, $b) use ($orderSkusArray) {
            $aInOrder = in_array(trim((string)$a->product_id), $orderSkusArray);
            $bInOrder = in_array(trim((string)$b->product_id), $orderSkusArray);
            if ($aInOrder && !$bInOrder) return 1;
            if (!$aInOrder && $bInOrder) return -1;
            return 0;
        });

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

            // Better name resolution: prioritize Order Item Name if Product name is the placeholder 'product_...' or 'Unknown'
            $pName = $p ? $p->product_name : 'Unknown';
            if ($p && !empty($p->product_name) && stripos($p->product_name, 'product_') !== 0 && stripos($p->product_name, 'Unknown') === false) {
                $scan->product_name = $p->product_name;
            } elseif ($tempItem && !empty($tempItem->item_name)) {
                $scan->product_name = $tempItem->item_name;
            } else {
                $scan->product_name = $pName;
            }
            $scan->sku = $p ? $p->product_id : ($tempItem ? $tempItem->sku : null);

            // Get original list IDs (order_id2)
            $matchedOrderItems = $items->filter(function ($it) use ($key, $scan) {
                return trim((string)$it->sku) === $key || trim((string)$it->sku) === trim((string)$scan->ean_code);
            });
            $scan->order_id2 = null; // Removed per user request ("Ref:")
            $scan->your_reference = null;
            $scan->ordered_by = null;
            $scan->order_price = $matchedOrderItems->first() ? $matchedOrderItems->first()->price : 0;

            $scan->ordered_total = $orderedMap[$key] ?? 0;
            $scan->scanned_total = $scannedMap[$key] ?? 0;
            $scan->packaging_quantity = $tempItem ? $tempItem->packaging_quantity : 1;
            $scan->packaging_unit = $tempItem ? $tempItem->packaging_unit : 'STK';
            $scan->ean_code = $key; // Unified display
            $enrichedScans->push($scan);
        }

        // 4. GROUPING REMOVED: User requested each scan to come separately.
        // Previously we grouped by EAN to ensure "Only 1 entry" per product.
        // We now bypass this to allow multiple rows for the same EAN.
        $enrichedScans = $enrichedScans->values();

        // 5. Add "Missing" items (items ordered but never scanned)
        // Build a comprehensive set of handled keys including both resolved EAN keys AND raw scan codes.
        // This prevents a virtual row appearing for a product whose scans are stored under varenummer.
        $handledKeys = array_merge(
            array_keys($scannedMap),                                   // e.g. ["7311490000443"]
            $recentScans->pluck('ean_code')->map(function($c) { return trim((string)$c); })->toArray() // raw scan EANs e.g. ["324608"]
        );

        // Also include the resolved order-item keys for scans already enriched (catches varenummer→EAN resolution)
        foreach ($enrichedScans as $enriched) {
            $handledKeys[] = trim((string)$enriched->ean_code);
            if (!empty($enriched->sku)) {
                $handledKeys[] = trim((string)$enriched->sku);
            }
        }
        $handledKeys = array_unique($handledKeys);

        foreach ($items as $item) {
            $key = $getTrueKey($item->sku);
            if (!in_array($key, $handledKeys)) {
                $virtualScan = new OrderScan();
                $virtualScan->id = 0;
                $virtualScan->is_virtual = true;
                $virtualScan->order_id = $orderId;
                $virtualScan->ean_code = $key;
                $virtualScan->units = 0;
                $virtualScan->created_at = null;
                $virtualScan->scan_date_time = null;

                $virtualScan->product_name = $item->item_name;
                $virtualScan->sku = $item->sku;
                $virtualScan->order_id2 = trim((string)$item->order_id2) !== trim((string)$orderId) ? $item->order_id2 : null;
                $virtualScan->your_reference = $item->your_reference;
                $virtualScan->ordered_by = $item->ordered_by;
                $virtualScan->order_price = $item->price;
                $virtualScan->ordered_total = $orderedMap[$key] ?? $item->quantity;
                $virtualScan->scanned_total = 0;
                $virtualScan->packaging_quantity = $item->packaging_quantity;
                $virtualScan->packaging_unit = $item->packaging_unit;

                $enrichedScans->push($virtualScan);
                $handledKeys[] = $key;
            }
        }

        // 5. Final Filter: Show products that were in the CSV OR have been scanned.
        // We hide "Ghost" items that have 0 ordered and 0 scanned.
        $recentScans = $enrichedScans->filter(function($s) {
            return ($s->ordered_total > 0 || $s->scanned_total > 0);
        });

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

        // 1. Check if the product is in the order.
        // We allow unknown barcodes to be scanned initially so they can be assigned via the "ADD EAN" flow.
        $state = $this->getProductState($orderId, $ean);
        $isUnknown = $state['ordered'] <= 0;
        
        // However, if it's a known product AND not in the order, we can reject it to prevent scanning wrong products.
        // For truly unknown barcodes (not in DB at all), we allow them so staff can resolve them.
        $productExists = Product::where('ean_code', $ean)->orWhere('product_id', $ean)->exists();
        if ($isUnknown && $productExists) {
            $product = Product::where('ean_code', $ean)->orWhere('product_id', $ean)->first();
            if (stripos($product->product_name, 'Unknown') === false && stripos($product->product_name, 'product_') !== 0) {
                 return response()->json([
                    'success' => false, 
                    'message' => 'This product (' . $product->product_name . ') is not part of Order #' . $orderId . '.'
                ], 422);
            }
        }

        // 2. Register Scan - ONLY if it's in the order
        $scan = OrderScan::create([
            'order_id' => $orderId,
            'scan_date_time' => Carbon::now(),
            'ean_code' => $ean,
            'units' => 1
        ]);

        // 2. Get high-fidelity state
        $state = $this->getProductState($orderId, $ean);

        // 3. Automatic Price & EAN Sync
        $product = Product::where('ean_code', $ean)->orWhere('product_id', $ean)->get()
            ->sortByDesc(function ($p) use ($orderId) {
                return OrderItem::where('order_id', $orderId)->where('sku', $p->product_id)->exists();
            })
            ->first();

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
        $channelName = config('app.env', 'local') . '.order.' . $orderId;
        Log::info("Broadcasting scan for order: $orderId, EAN: $ean, Channel: $channelName");

        $broadcastData = (array)$state;
        $broadcastData['order_id'] = $orderId;
        $broadcastData['scan_id'] = $scan->id;
        $broadcastData['units'] = $scan->units;
        $broadcastData['timestamp'] = Carbon::parse($scan->scan_date_time)->format('H:i');

        try {
            broadcast(new ScanBroadcast($orderId, $broadcastData));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'timestamp' => Carbon::parse($scan->scan_date_time)->format('H:i')
        ], $broadcastData));
    }

    public function viewProduct(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:order_records,order_id',
            'ean_code' => 'required'
        ]);

        $state = $this->getProductState($request->order_id, $request->ean_code);
        return response()->json(array_merge(['success' => true], (array)$state));
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

        $broadcastData = (array)$state;
        $broadcastData['order_id'] = $scan->order_id;
        $broadcastData['update_units'] = true;
        $broadcastData['scan_id'] = $scan->id;
        $broadcastData['units'] = $scan->units;
        $broadcastData['timestamp'] = Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i');

        try {
            broadcast(new ScanBroadcast($scan->order_id, $broadcastData));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'units' => $scan->units,
            'timestamp' => $broadcastData['timestamp']
        ], $broadcastData));
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

        $broadcastData = (array)$state;
        $broadcastData['order_id'] = $scan->order_id;
        $broadcastData['update_units'] = true;
        $broadcastData['scan_id'] = $scan->id;
        $broadcastData['units'] = $scan->units;
        $broadcastData['timestamp'] = Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i');

        try {
            broadcast(new ScanBroadcast($scan->order_id, $broadcastData));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'units' => $scan->units,
            'timestamp' => $broadcastData['timestamp']
        ], $broadcastData));
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

        $broadcastData = (array)$state;
        $broadcastData['order_id'] = $scan->order_id;
        $broadcastData['delete_scan'] = true;
        $broadcastData['scan_id'] = $scan->id;
        $broadcastData['ean_code'] = $scan->ean_code;

        $nextScan = OrderScan::where('order_id', $scan->order_id)
            ->where('deactivated', false)
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($nextScan) {
            $nextState = $this->getProductState($scan->order_id, $nextScan->ean_code);
            $broadcastData['next_scan_data'] = array_merge((array)$nextState, [
                'order_id' => $scan->order_id,
                'scan_id' => $nextScan->id,
                'units' => $nextScan->units,
                'timestamp' => \Carbon\Carbon::parse($nextScan->scan_date_time ?? \Carbon\Carbon::now())->format('H:i')
            ]);
        } else {
            $broadcastData['next_scan_data'] = null;
        }

        try {
            broadcast(new ScanBroadcast($scan->order_id, $broadcastData));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge(['success' => true], $broadcastData));
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
            $productSkus = \App\Product::where('ean_code', $trueEan)->pluck('product_id')->filter()->unique()->toArray();
            if (!empty($productSkus)) {
                $orderItem = OrderItem::where('order_id', $orderId)->whereIn('sku', $productSkus)->first();
            }
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

        $broadcastData = (array)$state;
        $broadcastData['update_units'] = true;
        $broadcastData['scan_id'] = $scan->id;
        $broadcastData['units'] = $scan->units;
        $broadcastData['timestamp'] = Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i');

        try {
            broadcast(new ScanBroadcast($orderId, $broadcastData));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'units' => $scan->units,
            'timestamp' => $broadcastData['timestamp']
        ], $broadcastData));
    }


    /**
     * Helper to get high-fidelity state for any product in an order.
     */
    private function getProductState($orderId, $ean)
    {
        $ean = trim((string)$ean);

        // Robust Lookup: Find all potential matches
        $matches = Product::where('ean_code', $ean)
            ->orWhere('product_id', $ean)
            ->get();

        $product = null;
        if ($matches->isNotEmpty()) {
            // 1. Priority: Find a match that is actually part of this order items list
            $orderSkus = OrderItem::where('order_id', $orderId)->pluck('sku')->toArray();
            $product = $matches->first(function ($m) use ($orderSkus) {
                return in_array($m->product_id, $orderSkus);
            });

            // 2. Secondary Priority: Match by EAN specifically (prefers real product over SKU-placeholder)
            if (!$product) {
                $product = $matches->where('ean_code', $ean)->first();
            }

            // 3. Last Resort: First available match
            if (!$product) {
                $product = $matches->first();
            }
        }

        if (!$product) {
            Log::info("Scanned/Looked up product not found in database: " . $ean);
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

        // Compare with OrderedQuantity
        $orderItems = OrderItem::where('order_id', $orderId)->where('sku', $sku)->get();
        if ($orderItems->isEmpty()) {
            // Try to find by EAN if SKU mismatch (avoiding whereHas because of DB collation mismatch)
            $productSkus = \App\Product::where('ean_code', $trueEan)->pluck('product_id')->filter()->unique()->toArray();
            if (!empty($productSkus)) {
                $orderItems = OrderItem::where('order_id', $orderId)->whereIn('sku', $productSkus)->get();
            } else {
                $orderItems = collect();
            }
        }

        $totalOrdered = $orderItems->sum('quantity');
        $orderId2s = null; // Removed per user request ("Ref:")
        $firstItem = $orderItems->first();

        // Smart Name: Prioritize order item name if product name is generic or placeholder
        $productName = 'Unknown Item';
        if ($product && !empty($product->product_name) && stripos($product->product_name, 'product_') !== 0 && stripos($product->product_name, 'Unknown') === false) {
            $productName = $product->product_name;
        } elseif ($firstItem && !empty($firstItem->item_name)) {
            $productName = $firstItem->item_name;
        } elseif ($product && !empty($product->product_name)) {
            $productName = $product->product_name;
        }

        // Status Logic
        $remaining = max(0, $totalOrdered - $totalScanned);
        $diff = $totalScanned - $totalOrdered;
        $status = $diff == 0 ? 'COMPLETE' : ($diff > 0 ? 'OVER' : 'UNDER');

        // Overall Progress
        $totalExpectedOrder = OrderItem::where('order_id', $orderId)->sum('quantity');
        $totalScannedOrder = OrderScan::where('order_id', $orderId)->where('deactivated', false)->sum('units');
        $progressPercent = $totalExpectedOrder > 0 ? min(100, ($totalScannedOrder / $totalExpectedOrder) * 100) : 0;

        // Detect EAN missing: when ean_code equals product_id/SKU (varenummer used as substitute)
        $eanMissing = ($sku && $trueEan && trim((string)$sku) === trim((string)$trueEan) && $productName !== 'Unknown Item');

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
            'packaging_quantity' => $firstItem ? $firstItem->packaging_quantity : 1,
            'packaging_unit' => $firstItem ? $firstItem->packaging_unit : 'STK',
            'ordered_by' => $orderItems->pluck('ordered_by')->filter()->unique()->implode(', '),
            'order_price' => $orderItems->first() ? $orderItems->first()->price : 0,
            'ean_missing' => $eanMissing,
        ];
    }

    /**
     * Update (fix) the EAN code for a product.
     * Called when staff adds the real EAN to replace a Varenummer placeholder.
     */
    public function updateEanCode(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'new_ean'    => 'required|string|max:50',
            'scan_id'    => 'nullable|exists:order_scans,id',
        ]);

        $productId = trim($request->product_id);
        $newEan    = trim($request->new_ean);

        // Prevent saving an EAN that's identical to the product_id (still a varenummer)
        if ($newEan === $productId) {
            return response()->json(['success' => false, 'message' => 'The new EAN cannot be the same as Varenummer. Please enter a valid EAN code.']);
        }

        // Find the product
        $product = Product::where('product_id', $productId)->orWhere('ean_code', $productId)->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.']);
        }

        // Check if new EAN is already taken by another product
        $existing = Product::where('ean_code', $newEan)->where('product_id', '!=', $product->product_id)->first();
        if ($existing) {
            // IF THE EXISTING OWNER IS AN "Unknown" product, allow stealing it to resolve the error
            if (stripos($existing->product_name, 'Unknown') !== false || stripos($existing->product_name, 'product_') === 0) {
                Log::info("Stealing EAN {$newEan} from placeholder product: " . $existing->product_name);
                $existing->ean_code = null; // Clear it from the placeholder
                $existing->save();
                
                // If it has no scanner-ID and no info, we could delete it, but clearing EAN is safer.
                // OR: If the existing product has scan records in THIS order, we want them to point to our NEW product.
                // They already have the EAN, so when we update this product's EAN, they will match.
            } else {
                return response()->json(['success' => false, 'message' => 'This EAN code is already assigned to another product: ' . $existing->product_name]);
            }
        }

        $oldEan = $product->ean_code;

        // Update the product EAN
        $product->ean_code = $newEan;
        $product->save();

        // STEP A: DELETE "Unknown Product" scan records stored with the new EAN.
        // These were created when an unknown barcode was scanned for identification purposes only.
        // They should NOT be counted as delivery scans.
        if ($request->order_id) {
            $deletedCount = \App\OrderScan::where('order_id', $request->order_id)
                ->where('ean_code', $newEan)
                ->delete();
            Log::info("Deleted {$deletedCount} unknown-barcode scan record(s) for EAN {$newEan} in order {$request->order_id}");
        }

        // STEP B: TRANSFER valid scans stored under the product's old EAN or varenummer (product_id).
        // These are real delivery scans (e.g. scanned via varenummer when EAN was missing).
        // They should be kept and re-pointed to the new real EAN.
        $codesUsedForScans = array_filter(array_unique([$oldEan, $product->product_id]), function($v) use ($newEan) {
            return $v && $v !== $newEan;
        });
        foreach ($codesUsedForScans as $oldCode) {
            \App\OrderScan::where('ean_code', $oldCode)->update(['ean_code' => $newEan]);
        }

        Log::info("EAN updated for product {$product->product_id}: {$oldEan} -> {$newEan}");

        // REAL-TIME SYNC: Broadcast to other devices (mobile) so they can hide warnings without refresh
        $orderId = $request->order_id;
        if ($orderId) {
            try {
                $state = $this->getProductState($orderId, $newEan);
                broadcast(new ScanBroadcast($orderId, [
                    'ean_updated'      => true,
                    'product_id'       => $product->product_id,
                    'new_ean'          => $newEan,
                    'new_product_name' => $product->product_name,
                    'ordered_quantity' => $state['ordered'] ?? 0,
                    'order_id'         => $orderId
                ]));
            } catch (\Exception $e) {
                Log::error("Pusher Broadcast Error in updateEanCode: " . $e->getMessage());
            }
        }

        // Fetch final state to return accurate ordered quantity to the frontend
        $finalState = $request->order_id ? $this->getProductState($request->order_id, $newEan) : [];

        return response()->json([
            'success'          => true,
            'message'          => 'EAN code updated successfully.',
            'product_id'       => $product->product_id,
            'product_name'     => $product->product_name,
            'new_ean'          => $newEan,
            'old_ean'          => $oldEan,
            'ordered_quantity' => $finalState['ordered'] ?? 0,
            'scanned_total'    => $finalState['scanned'] ?? 0,
        ]);
    }

    public function closeOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:order_records,order_id',
            'staff' => 'required',
            'note' => 'nullable',
            'planned_delivery' => 'nullable|date'
        ]);

        $orderId = $request->order_id;
        $order = OrderRecord::where('order_id', $orderId)->first();

        $deviations = $this->getDeviations($orderId);
        
        if (!empty($deviations)) {
            // Group and prioritize message
            $devTypes = collect($deviations)->pluck('type')->unique();
            $msg = "Cannot close order. Please fix all scanning errors first.";
            
            if ($devTypes->contains('MISSING_EAN')) {
                $msg = "Cannot close order. All EAN codes must be updated before session can be closed.";
            } elseif ($devTypes->contains('UNKNOWN')) {
                $msg = "Cannot close order. Unknown scans must be resolved (matched or deleted).";
            } elseif ($devTypes->contains('UNDER') || $devTypes->contains('OVER')) {
                $msg = "Cannot close order. All product counts must match ordered quantities (no under/over counts allowed).";
            }

            return response()->json([
                'success' => false,
                'message' => $msg,
                'deviations' => $deviations
            ], 422);
        }

        $order->planned_delivery = $this->parseDate($request->planned_delivery);
        $order->staff = $request->staff;
        $order->note = $request->note;
        $order->delivery_handling_date = Carbon::now();
        $order->status = 'Completed';
        $order->completed = 1;

        $order->save();

        return response()->json([
            'success' => true,
            'status' => $order->status
        ]);
    }

    /**
     * Audit all previously 'Completed' orders.
     * If they fail the new validation logic, reset them to 'Started'.
     * This fixes historical data that was marked completed before strict rules were in place.
     */
    public function auditCompletedOrders(Request $request)
    {
        $completedOrders = OrderRecord::where('status', 'Completed')->get();
        $auditLog = [];
        $resetCount = 0;

        foreach ($completedOrders as $order) {
            $deviations = $this->getDeviations($order->order_id);
            
            if (!empty($deviations)) {
                $types = array_unique(array_map(function($d) { return $d['type']; }, $deviations));
                
                // MARK AS DONE WITH ERR
                $order->status = 'Done with ERR';
                $order->completed = 1;
                $order->save();
                
                $resetCount++;
                $auditLog[] = [
                    'order_id' => $order->order_id,
                    'deviations' => $types,
                    'status' => 'Updated to Done with ERR'
                ];
            } else {
                $auditLog[] = [
                    'order_id' => $order->order_id,
                    'deviations' => [],
                    'status' => 'Valid'
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Audited {$completedOrders->count()} orders. Reset {$resetCount} orders that failed new validation rules.",
            'audit' => $auditLog
        ]);
    }

    public function reopenOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:order_records,order_id',
            'password' => 'required'
        ]);

        $order = OrderRecord::where('order_id', $request->order_id)->first();
        $dbPassword = $order->reopen_password ?: '3535';

        if ($request->password !== $dbPassword) {
            return response()->json(['success' => false, 'message' => 'Invalid password'], 403);
        }

        $order->completed = 0;
        $order->status = 'Started'; // Revert to started
        $order->save();

        // Clear any possible query cache (for database cache store)
        try {
            \Illuminate\Support\Facades\Cache::forget('order_status_' . $order->order_id);
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
        } catch (\Exception $e) {}

        // ── BROADCAST TO OTHER DEVICES ──
        $broadcastData = [
            'action' => 'reload_list',
            'status' => 'Started',
            'order_id' => $order->order_id,
            'timestamp' => now()->format('H:i:s')
        ];
        
        try {
            broadcast(new ScanBroadcast($order->order_id, $broadcastData));
        } catch (\Exception $e) {
            Log::error('Reopen Broadcast Failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'new_status' => 'Started'
        ]);
    }

    public function getDeviations($orderId)
    {
        $deviations = [];
        $items = OrderItem::where('order_id', $orderId)->get();
        $allScans = OrderScan::where('order_id', $orderId)->where('deactivated', false)->get();

        // 1. Map expected quantities by SKU
        $expectedMap = [];
        foreach ($items as $item) {
            $expectedMap[trim((string)$item->sku)] = ($expectedMap[trim((string)$item->sku)] ?? 0) + $item->quantity;
        }

        // 2. Resolve all scans and products
        $scanCodes = $allScans->pluck('ean_code')->map(function ($c) {
            return trim((string)$c);
        })->unique();
        $orderSkus = $items->pluck('sku')->map(function ($s) {
            return trim((string)$s);
        })->unique();

        $products = Product::whereIn('ean_code', $scanCodes)
            ->orWhereIn('product_id', $scanCodes->merge($orderSkus)->unique())
            ->get();

        // Build maps with priority for products in the order if duplicates exist
        $orderSkusArray = $orderSkus->toArray();
        $products = $products->sort(function ($a, $b) use ($orderSkusArray) {
            $aInOrder = in_array(trim((string)$a->product_id), $orderSkusArray);
            $bInOrder = in_array(trim((string)$b->product_id), $orderSkusArray);
            if ($aInOrder && !$bInOrder) return 1;
            if (!$aInOrder && $bInOrder) return -1;
            return 0;
        });

        $codeToProduct = [];
        foreach ($products as $p) {
            if ($p->ean_code) $codeToProduct[trim((string)$p->ean_code)] = $p;
            if ($p->product_id) $codeToProduct[trim((string)$p->product_id)] = $p;
        }

        $scannedBySku = [];
        $missingEanSkus = [];

        foreach ($allScans as $s) {
            $code = trim((string)$s->ean_code);
            $p = $codeToProduct[$code] ?? null;
            $sku = $p ? $p->product_id : $code;

            if (!isset($scannedBySku[$sku])) $scannedBySku[$sku] = 0;
            $scannedBySku[$sku] += $s->units;
        }

        // 2.5 Audit all products in the order for MISSING_EAN (including those not scanned)
        foreach ($orderSkus as $sku) {
            $p = $codeToProduct[$sku] ?? null;
            if ($p) {
                $e = trim((string)$p->ean_code);
                $pid = trim((string)$p->product_id);
                if (empty($e) || $e === $pid) {
                    $missingEanSkus[$sku] = true;
                }
            }
        }

        // 3. Audit Ordered Items (OVER/UNDER/MISSING_EAN)
        $alreadyReportedMissingEan = [];
        foreach ($items as $item) {
            $sku = trim((string)$item->sku);
            $scanned = $scannedBySku[$sku] ?? 0;

            if ($scanned != $item->quantity) {
                $deviations[] = [
                    'sku' => $sku,
                    'type' => $scanned > $item->quantity ? 'OVER' : 'UNDER',
                    'name' => $item->item_name,
                    'expected' => $item->quantity,
                    'scanned' => $scanned,
                    'diff' => $scanned - $item->quantity
                ];
            }

            // MISSING_EAN blocks closing even if quantities match.
            // Client requirement: "Any product without EAN -> BLOCK closing"
            if (isset($missingEanSkus[$sku]) && !isset($alreadyReportedMissingEan[$sku])) {
                $deviations[] = [
                    'sku' => $sku,
                    'type' => 'MISSING_EAN',
                    'name' => $item->item_name,
                    'expected' => $item->quantity,
                    'scanned' => $scanned,
                    'diff' => 0
                ];
                $alreadyReportedMissingEan[$sku] = true;
            }
        }

        // 4. Audit Unknown Scans (Scanned but not in order)
        foreach ($scannedBySku as $sku => $scanned) {
            if (!isset($expectedMap[$sku])) {
                $p = $codeToProduct[$sku] ?? null;
                $deviations[] = [
                    'sku' => $sku,
                    'type' => 'UNKNOWN',
                    'name' => $p ? $p->product_name : 'Unknown Scan',
                    'expected' => 0,
                    'scanned' => $scanned,
                    'diff' => $scanned
                ];

                // For unknown scans with missing EAN, still flag MISSING_EAN
                // since these are not in the order at all (they are UNKNOWN deviations already flagged above)
                if (isset($missingEanSkus[$sku]) && !isset($alreadyReportedMissingEan[$sku])) {
                    $deviations[] = [
                        'sku' => $sku,
                        'type' => 'MISSING_EAN',
                        'name' => $p ? $p->product_name : 'Unknown Scan',
                        'expected' => 0,
                        'scanned' => $scanned,
                        'diff' => 0
                    ];
                    $alreadyReportedMissingEan[$sku] = true;
                }
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

    public function getHistory($orderId)
    {
        $scans = OrderScan::where('order_id', $orderId)
            ->where('deactivated', false)
            ->orderBy('updated_at', 'desc')
            ->get();

        $formatted = $scans->map(function ($scan) {
            $state = $this->getProductState($scan->order_id, $scan->ean_code);
            return array_merge((array)$state, [
                'scan_id' => $scan->id,
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time ?? $scan->updated_at)->format('H:i')
            ]);
        });

        return response()->json([
            'success' => true,
            'order_id' => $orderId,
            'scans' => $formatted
        ]);
    }

    public function mobileGlobal()
    {
        $latestScan = null;
        $order = null;

        // 1. Try Global Cache (best for cross-device desktop-to-mobile sync)
        $activeOrderId = \Illuminate\Support\Facades\Cache::get('warehouse_active_order_id') ?? session('active_order_id');
        if ($activeOrderId) {
            $order = OrderRecord::where('order_id', $activeOrderId)->first();
        }

        // 2. Fallback to latest scan globally by UPDATED_AT (not ID)
        if (!$order) {
            $latestScan = OrderScan::where('deactivated', false)
                ->orderBy('updated_at', 'desc')
                ->first();
            if ($latestScan) {
                $order = OrderRecord::where('order_id', $latestScan->order_id)->first();
            }
        }

        // 3. Overall fallbacks
        if (!$order) {
            $order = OrderRecord::where('status', 'Started')->orderBy('id', 'desc')->first();
        }
        if (!$order) {
            $order = OrderRecord::orderBy('id', 'desc')->first();
        }

        if (!$order) {
            return redirect()->route('order-delivery.index')->withErrors(['message' => 'No orders available for mobile scanner.']);
        }

        // Ensure we find the latest scan for THIS specific order (now that we found the order)
        if (!$latestScan) {
            $latestScan = OrderScan::where('order_id', $order->order_id)
                ->where('deactivated', false)
                ->orderBy('updated_at', 'desc')
                ->first();
        }

        if ($latestScan) {
            $state = $this->getProductState($latestScan->order_id, $latestScan->ean_code);
            $latestScan->product_name = $state['product_name'];
            $latestScan->ordered_total = $state['ordered'];
            $latestScan->scanned_total = $state['scanned'];
            $latestScan->sku = $state['product_id'];
        }

        return view('order-delivery.mobile_global', compact('order', 'latestScan'));
    }

    public function mobile($orderId)
    {
        $order = OrderRecord::where('order_id', $orderId)->firstOrFail();

        // Fetch the ABSOLUTE latest scan, active or not (though usually active)
        $latestScan = OrderScan::where('order_id', $orderId)
            ->where('deactivated', false)
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($latestScan) {
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
