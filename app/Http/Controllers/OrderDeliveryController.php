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
        $orders = OrderRecord::with(['items'])
            ->latestOrderDateFirst()
            ->paginate(OrderRecord::DELIVERY_LIST_PER_PAGE);

        // Collect all SKUs from all orders at once to bulk-load products
        $allSkus = $orders->flatMap(function($order) {
            return $order->items->pluck('sku');
        })->unique();
        
        $allProducts = Product::whereIn('product_id', $allSkus)->get()->groupBy('product_id');

        foreach ($orders as $order) {
            $orderItems = $order->items;
            $order->total_unique_items = $orderItems->count();
            $order->total_quantity = $orderItems->sum('quantity');

            $skus = $orderItems->pluck('sku')->unique();
            
            // Map SKUs to EANs using the bulk-loaded products
            $productsEans = collect();
            foreach ($skus as $sku) {
                if (isset($allProducts[$sku])) {
                    $productsEans = $productsEans->merge($allProducts[$sku]->pluck('ean_code'));
                }
            }
            $productsEans = $productsEans->unique();
            
            $itemNames = $orderItems->pluck('item_name');
            $productNames = collect();
            foreach ($skus as $sku) {
                if (isset($allProducts[$sku])) {
                    $productNames = $productNames->merge($allProducts[$sku]->pluck('product_name'));
                }
            }

            // Product lookup index: searchable by VareNr (SKU), EAN and product names only.
            $order->searchable_products = $skus
                ->merge($productsEans)
                ->merge($itemNames)
                ->merge($productNames)
                ->map(function ($value) {
                    return trim((string)$value);
                })
                ->filter()
                ->unique()
                ->implode(' ');

            $order->source_orders = $orderItems->pluck('order_id2')->filter(function ($id) use ($order) {
                return trim((string)$id) !== trim((string)$order->order_id);
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
            $realPath = $file->getRealPath();
            $extension = strtolower($file->getClientOriginalExtension());
            $isCsv = in_array($extension, ['csv', 'txt']);

            if ($isCsv) {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
                // Try to guess delimiter
                $handle = fopen($realPath, 'r');
                $firstLine = fgets($handle);
                if ($handle) fclose($handle);
                $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
                $reader->setDelimiter($delimiter);
                $spreadsheet = $reader->load($realPath);
            } else {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($realPath);
            }

            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, true, true, false);

            if (empty($rows) || count($rows) < 2) {
                return back()->withErrors(['message' => 'File is empty or missing data rows']);
            }

            $header = array_shift($rows);

            if (!$header || count($header) < 5) {
                return back()->withErrors(['message' => 'Invalid file format, missing required columns, or delimiter not recognized.']);
            }

            $header = array_map(function ($h) {
                return trim((string)$h, "\xEF\xBB\xBF \t\n\r\0\x0B");
            }, $header);

            DB::beginTransaction();
            $invalidDates = ['0001-01-01', '0000-00-00', null];

            foreach ($rows as $row) {
                if (count($row) < count($header)) {
                    $row = array_pad($row, count($header), null);
                } else if (count($row) > count($header)) {
                    $row = array_slice($row, 0, count($header));
                }

                // Convert row to UTF-8 and TRIM all values
                $row = array_map(function ($item) use ($isCsv) {
                    if ($item === null) return '';
                    if ($isCsv) {
                        $item = mb_convert_encoding((string)$item, 'UTF-8', 'ISO-8859-1');
                    }
                    return trim((string)$item, "\xEF\xBB\xBF \t\n\r\0\x0B");
                }, $row);

                $data = array_combine($header, $row);

                // Extracting potentially relevant fields with fallbacks
                $csvOrderId = trim($data['OrderId'] ?? $data['OrdreNr'] ?? $data['OrderNumber'] ?? '');

                if (empty($csvOrderId)) continue;

                $isAppend = $request->filled('target_order_id');
                $currentOrderId = $isAppend ? trim($request->input('target_order_id')) : $csvOrderId;
                $currentOrderId2 = $csvOrderId;

                // Stop duplicate file imports
                if (!$isAppend) {
                    // Cache the uniqueness check so we don't query db for every row
                    if (!isset($checkedOrderIds)) {
                        $checkedOrderIds = [];
                    }
                    if (!in_array($csvOrderId, $checkedOrderIds)) {
                        if (OrderRecord::where('order_id', $csvOrderId)->exists()) {
                            DB::rollBack();
                            return back()->withErrors(['message' => "Order #{$csvOrderId} has already been imported! If you want to add items to it, please use the 'Append' option."]);
                        }
                        $checkedOrderIds[] = $csvOrderId;
                    }
                }

                // Robust Price and SKU extraction
                $sku = $data['StockKeepingUnit'] ?? $data['ProductNumber'] ?? $data['Varekode'] ?? $data['SKU'] ?? $data['Vare Nr'] ?? null;
                if ($sku !== null) $sku = trim((string)$sku);

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

                // Update session info if specifically provided in request (highest priority)
                if ($request->filled('staff')) {
                    $orderRecord->staff = $request->staff;
                    $updateNeeded = true;
                }
                if ($request->filled('planned_delivery')) {
                    $parsed = $this->parseDate($request->planned_delivery);
                    if ($parsed) {
                        $orderRecord->planned_delivery = $parsed;
                        $updateNeeded = true;
                    }
                }

                if ($updateNeeded) {
                    $orderRecord->save();
                }

                $price = $this->parseNumber($data['Price'] ?? $data['Pris'] ?? $data['UnitPrice'] ?? $data['Enhetspris'] ?? 0);
                $ean = $data['EAN'] ?? $data['GTIN'] ?? $data['Barcode'] ?? $data['Strekkode'] ?? null;
                if ($ean !== null) $ean = trim((string)$ean);

                $itemName = Product::normalizeName(
                    $data['ItemName'] ?? $data['ProductName'] ?? $data['Beskrivelse'] ?? $data['Varenavn'] ?? 'Unknown Item'
                ) ?? 'Unknown Item';
                $orderedBy = $data['OrderedBy'] ?? $data['BestiltAv'] ?? $data['Ordered By'] ?? null;
                $yourRef = $data['YourReference'] ?? $data['DinReferanse'] ?? $data['Your Ref'] ?? null;

                // Always keep each imported line as its own record (no merge).
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
                    'packaging_unit' => $data['PackagingUnit'] ?? $data['Volume'] ?? $data['Size'] ?? $data['Enhet'] ?? 'STK',
                    'ordered_quantity' => $this->parseNumber($data['OrderedQuantity'] ?? $data['ORDERED'] ?? $data['Ordered'] ?? $data['Ordre'] ?? $data['ORDRE'] ?? $data['OrderQty'] ?? 0),
                    'delivered' => $this->parseNumber($data['DeliveredQuantity'] ?? $data['DELIVERED'] ?? $data['Delivered'] ?? $data['Levert'] ?? $data['LEVERT'] ?? $data['Levert antall'] ?? 0),
                    'quantity' => $this->parseNumber($data['OrderedQuantity'] ?? $data['ORDERED'] ?? $data['Ordered'] ?? $data['Ordre'] ?? $data['ORDRE'] ?? $data['Quantity'] ?? 0),
                    'price' => $price
                ]);

                // Automatic Price & Product Sync — delivery line unit price becomes products.price
                // (and new products are created with that price) so manual sales can snapshot it into sale_data.
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
            DB::rollBack();
            Log::error("Error importing file: " . $e->getMessage());
            return back()->withErrors(['message' => 'Error importing file: ' . $e->getMessage()]);
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

        // 2. Build aggregation maps (ORDRE = quantity, LEVERT = delivered — reception targets LEVERT)
        $orderedMap = [];
        $deliveredMap = [];
        foreach ($items as $item) {
            $key = $getTrueKey($item->sku);
            $orderedMap[$key] = ($orderedMap[$key] ?? 0) + (int)$item->quantity;
            $deliveredMap[$key] = ($deliveredMap[$key] ?? 0) + (int)($item->delivered ?? 0);
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
            $scan->order_id2 = $matchedOrderItems->pluck('order_id2')->filter(function ($id) use ($orderId) {
                return trim((string)$id) !== trim((string)$orderId);
            })->unique()->implode(', ');
            $scan->your_reference = $matchedOrderItems->pluck('your_reference')->filter()->unique()->implode(', ');
            $scan->ordered_by = $matchedOrderItems->pluck('ordered_by')->filter()->unique()->implode(', ');
            $scan->order_price = $matchedOrderItems->first() ? $matchedOrderItems->first()->price : 0;
            $scan->packaging_unit = $matchedOrderItems->first() ? $matchedOrderItems->first()->packaging_unit : null;
            $scan->packaging_quantity = $matchedOrderItems->first() ? $matchedOrderItems->first()->packaging_quantity : 1;

            $scan->ordered_total = $orderedMap[$key] ?? 0;
            $scan->delivery_target_total = $deliveredMap[$key] ?? 0;
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
                $virtualScan->order_id2 = $itemsForThisKey->pluck('order_id2')->filter(function ($id) use ($orderId) {
                    return trim((string)$id) !== trim((string)$orderId);
                })->unique()->implode(', ');
                $virtualScan->your_reference = $itemsForThisKey->pluck('your_reference')->filter()->unique()->implode(', ');
                $virtualScan->ordered_by = $itemsForThisKey->pluck('ordered_by')->filter()->unique()->implode(', ');
                $virtualScan->order_price = $firstItem->price;
                $virtualScan->packaging_unit = $firstItem->packaging_unit;
                $virtualScan->packaging_quantity = $firstItem->packaging_quantity;
                $virtualScan->ordered_total = $orderedMap[$key] ?? 0;
                $virtualScan->delivery_target_total = $deliveredMap[$key] ?? 0;
                $virtualScan->scanned_total = 0;

                $enrichedScans->push($virtualScan);
                $handledKeys[] = (string)$key;
            }
        }

        $recentScans = $enrichedScans;

        // Use LEVERT/Delivered as the only expected quantity source.
        $totalExpected = $items->sum(function ($it) {
            return (int)($it->delivered ?? 0);
        });
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
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time)->format('H:i'),
                'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
            ])));
            $this->broadcastGlobalScanState($orderId, array_merge($state, [
                'scan_id' => $scan->id,
                'order_id' => $orderId,
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time)->format('H:i'),
                'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
            ]));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'units' => $scan->units,
            'timestamp' => Carbon::parse($scan->scan_date_time)->format('H:i'),
            'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
        ], $state));
    }

    public function viewProduct(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:order_records,order_id',
            'ean_code' => 'required'
        ]);

        $state = $this->getProductState($request->order_id, $request->ean_code);
        return response()->json(array_merge(['success' => true, 'units' => 1], $state));
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
                'order_id' => $scan->order_id,
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i'),
                'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
            ])));
            $this->broadcastGlobalScanState($scan->order_id, array_merge($state, [
                'update_units' => true,
                'scan_id' => $scan->id,
                'order_id' => $scan->order_id,
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i'),
                'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
            ]));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'units' => $scan->units,
            'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i'),
            'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
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
                'order_id' => $scan->order_id,
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i'),
                'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
            ])));
            $this->broadcastGlobalScanState($scan->order_id, array_merge($state, [
                'update_units' => true,
                'scan_id' => $scan->id,
                'order_id' => $scan->order_id,
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i'),
                'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
            ]));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'units' => $scan->units,
            'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i'),
            'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
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
                'order_id' => $scan->order_id,
                'ean_code' => $scan->ean_code,
                'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
            ])));
            $this->broadcastGlobalScanState($scan->order_id, array_merge($state, [
                'delete_scan' => true,
                'scan_id' => $scan->id,
                'order_id' => $scan->order_id,
                'ean_code' => $scan->ean_code,
                'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
            ]));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
        ], $state));
    }

    public function updateUnitsToMatchOrder(Request $request)
    {
        $request->validate([
            'scan_id' => 'required|exists:order_scans,id'
        ]);

        $scan = OrderScan::findOrFail($request->scan_id);
        $orderId = $scan->order_id;

        // 1) Resolve product identity from what was scanned.
        $product = Product::where('ean_code', $scan->ean_code)
            ->orWhere('product_id', $scan->ean_code)
            ->first();

        $scanSku = trim((string)($scan->sku ?? ''));
        $scanEan = trim((string)($scan->ean_code ?? ''));
        $sku = trim((string)(($product && $product->product_id) ? $product->product_id : $scanEan));
        $trueEan = trim((string)(($product && $product->ean_code) ? $product->ean_code : $scanEan));

        $identityCodes = collect([$scanSku, $scanEan, $sku, $trueEan])
            ->map(function ($v) {
                return trim((string)$v);
            })
            ->filter()
            ->unique()
            ->values();

        // 2) Find all order lines representing the same product.
        // Match by SKU and also by linked Product EAN/product_id to handle mixed imports.
        $orderItemsForSku = OrderItem::where('order_id', $orderId)
            ->where(function ($q) use ($identityCodes) {
                $q->whereIn('sku', $identityCodes->all())
                    ->orWhereHas('product', function ($p) use ($identityCodes) {
                        $p->whereIn('ean_code', $identityCodes->all())
                            ->orWhereIn('product_id', $identityCodes->all());
                    });
            })
            ->get();

        if ($orderItemsForSku->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Order item not found']);
        }

        // Target quantity is LEVERT (delivered). If missing/zero in source file, fallback to ordered quantity.
        $totalDeliveredTarget = (int)$orderItemsForSku->sum(function ($row) {
            return (int)($row->delivered ?? 0);
        });
        $totalOrderedTarget = (int)$orderItemsForSku->sum(function ($row) {
            return (int)($row->quantity ?? 0);
        });
        $totalTarget = $totalDeliveredTarget > 0 ? $totalDeliveredTarget : $totalOrderedTarget;

        // 3) Find already scanned total for the same product (excluding the current row).
        // We want: OtherScans + ThisScan = TotalTarget -> ThisScan = TotalTarget - OtherScans
        $otherScanned = OrderScan::where('order_id', $orderId)
            ->where('deactivated', false)
            ->where('id', '!=', $scan->id)
            ->whereIn('ean_code', $identityCodes->all())
            ->sum('units');

        // 3. Update THIS specific scan row so aggregate scanned matches LEVERT (delivered) for the line(s).
        $scan->units = max(0, $totalTarget - $otherScanned);

        // If it becomes 0, maybe we should deactivate it? But standard is 1 minimum usually.
        // Let's allow 0 or just deactivate if it's 0 to keep data clean, but let's just save.
        $scan->save();

        $state = $this->getProductState($orderId, $scan->ean_code);

        // Broadcast Update
        Log::info("Broadcasting match order for scan: {$scan->id}, new total units matched to delivery target (LEVERT).");
        try {
            broadcast(new ScanBroadcast($orderId, array_merge($state, [
                'update_units' => true,
                'scan_id' => $scan->id,
                'order_id' => $orderId,
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i'),
                'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
            ])));
            $this->broadcastGlobalScanState($orderId, array_merge($state, [
                'update_units' => true,
                'scan_id' => $scan->id,
                'order_id' => $orderId,
                'units' => $scan->units,
                'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i'),
                'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
            ]));
        } catch (\Exception $e) {
            Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return response()->json(array_merge([
            'success' => true,
            'scan_id' => $scan->id,
            'units' => $scan->units,
            'timestamp' => Carbon::parse($scan->scan_date_time ?? Carbon::now())->format('H:i'),
            'updated_at' => $scan->updated_at ? $scan->updated_at->valueOf() : now()->valueOf()
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

        $totalOrdered = (int)$orderItems->sum('quantity');
        $totalDeliveryTarget = (int)$orderItems->sum(function ($row) {
            return (int)($row->delivered ?? 0);
        });
        $orderId2s = $orderItems->pluck('order_id2')->filter(function ($id) use ($orderId) {
            return trim((string)$id) !== trim((string)$orderId);
        })->unique()->implode(', ');
        $firstItem = $orderItems->first();
        $productName = $product ? $product->product_name : ($firstItem ? $firstItem->item_name : "Unknown Item: $ean");

        // Use delivered target when available; otherwise fallback to ordered quantity.
        // This prevents false OVER when delivered is empty/0 but ordered exists.
        $effectiveTarget = $totalDeliveryTarget > 0 ? $totalDeliveryTarget : $totalOrdered;
        $remaining = max(0, $effectiveTarget - $totalScanned);
        $diff = $totalScanned - $effectiveTarget;
        $status = $diff == 0 ? 'COMPLETE' : ($diff > 0 ? 'OVER' : 'UNDER');

        // Overall progress always uses LEVERT/Delivered as expected quantity.
        $allItems = OrderItem::where('order_id', $orderId)->get();
        $totalExpectedOrder = (int)$allItems->sum(function ($row) {
            return (int)($row->delivered ?? 0);
        });
        $totalScannedOrder = OrderScan::where('order_id', $orderId)->where('deactivated', false)->sum('units');
        $progressPercent = $totalExpectedOrder > 0 ? min(100, ($totalScannedOrder / $totalExpectedOrder) * 100) : 0;

        // Detect EAN Missing: if the stored ean_code equals the product_id (VareNummer used as EAN fallback)
        $eanMissing = (!empty($sku) && !empty($trueEan) && strval($trueEan) == strval($sku));

        return [
            'product_name' => $productName,
            'ean_code' => $trueEan, // Always return true EAN if known
            'product_id' => $sku,
            'scanned' => (int)$totalScanned,
            'ordered' => (int)$totalOrdered,
            'delivery_target' => (int)$totalDeliveryTarget,
            'remaining' => $remaining,
            'status' => $status,
            'progress_percent' => round($progressPercent),
            'current_price' => $currentPrice,
            'order_id2' => $orderId2s,
            'your_reference' => $orderItems->pluck('your_reference')->filter()->unique()->implode(', '),
            'ordered_by' => $orderItems->pluck('ordered_by')->filter()->unique()->implode(', '),
            'order_price' => $orderItems->first() ? $orderItems->first()->price : 0,
            'packaging_unit' => $firstItem ? $firstItem->packaging_unit : null,
            'packaging_quantity' => $firstItem ? $firstItem->packaging_quantity : 1,
            'ean_missing' => $eanMissing,
        ];
    }

    /**
     * Update the EAN code for a product (when EAN was missing, i.e. EAN == VareNummer).
     */
    public function updateEan(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'new_ean'    => 'required|string|max:64',
        ]);

        $productId = trim((string)$request->product_id);
        $newEan    = trim((string)$request->new_ean);

        // Find product by product_id (VareNummer)
        $product = Product::where('product_id', $productId)->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found']);
        }

        // Verify it still has the missing-EAN situation
        if ((string)$product->ean_code !== (string)$product->product_id && !empty($product->ean_code)) {
            // EAN was already updated — allow anyway if staff insists
        }

        $oldEan = trim((string)($product->ean_code ?? ''));
        $product->ean_code = $newEan;
        $product->save();

        // Relink scans immediately: unknown/fallback scans stored as product_id (or old EAN) are migrated.
        $updatedScanQuery = OrderScan::where('deactivated', false)
            ->where(function ($q) use ($productId, $oldEan) {
                $q->where('ean_code', $productId);
                if (!empty($oldEan)) {
                    $q->orWhere('ean_code', $oldEan);
                }
            });

        $affectedOrderIds = $updatedScanQuery->pluck('order_id')->unique()->values()->all();
        $updatedScanQuery->update(['ean_code' => $newEan]);

        // Push live refresh for all affected order views and the global mobile view.
        foreach ($affectedOrderIds as $affectedOrderId) {
            try {
                broadcast(new ScanBroadcast($affectedOrderId, [
                    'action' => 'reload_list',
                    'order_id' => $affectedOrderId,
                    'updated_ean' => $newEan,
                ]));
            } catch (\Exception $e) {
                Log::warning("Pusher Broadcast Error on EAN update (order {$affectedOrderId}): " . $e->getMessage());
            }
        }

        try {
            broadcast(new ScanBroadcast('global', [
                'action' => 'reload_list',
                'updated_ean' => $newEan,
            ]));
        } catch (\Exception $e) {
            Log::warning("Global Pusher Broadcast Error on EAN update: " . $e->getMessage());
        }

        Log::info("EAN updated for product {$productId}: new EAN = {$newEan}, affected_orders=" . json_encode($affectedOrderIds));

        return response()->json(['success' => true, 'new_ean' => $newEan]);
    }

    public function updateSessionInfo(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:order_records,order_id',
            'staff' => 'nullable',
            'note' => 'nullable',
            'planned_delivery' => 'nullable|date'
        ]);

        $order = OrderRecord::where('order_id', $request->order_id)->firstOrFail();
        
        if ($request->has('staff')) $order->staff = $request->staff;
        if ($request->has('note')) $order->note = $request->note;
        if ($request->has('planned_delivery')) {
            $parsed = $this->parseDate($request->planned_delivery);
            $order->planned_delivery = $parsed ?: '0001-01-01';
        }

        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Session information updated',
            'planned_delivery_formatted' => $order->planned_delivery && $order->planned_delivery != '0001-01-01' ? \Carbon\Carbon::parse($order->planned_delivery)->format('d.m.Y') : '-'
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

        $order = OrderRecord::where('order_id', $request->order_id)->first();

        // Deviation logic — must match hub ERROR tab + scan_system.js hubRowIsError:
        // ordered lines with zero scans are "REST", not blocking errors (over / partial under / unknown / missing EAN).
        $deviations = $this->getDeviations($request->order_id);
        $blockingDeviations = array_values(array_filter($deviations, function (array $d): bool {
            $expected = (int) ($d['expected'] ?? 0);
            $scanned = (int) ($d['scanned'] ?? 0);
            if ($expected > 0 && $scanned === 0) {
                return false;
            }
            return true;
        }));
        $hasDeviations = !empty($blockingDeviations);

        // Same rule as order-delivery.show + hubRowEanMissing: canonical line key equals VareNr (article no. used as EAN).
        // Do not use Product::where()->orWhere()->first() — it can bind the wrong row and block close while the ERROR tab looks empty.
        $missingEanScanIds = $this->orderHubMissingEanScanIds($request->order_id);
        $hasMissingEans = count($missingEanScanIds) > 0;

        if ($hasDeviations || $hasMissingEans) {
            $detailLines = [];
            $blockingIssues = [];

            if ($hasMissingEans) {
                $detailLines[] = 'VareNr is still standing in for a real EAN on at least one scan (order_scans.id: ' . implode(', ', array_slice($missingEanScanIds, 0, 20)) . (count($missingEanScanIds) > 20 ? ' …' : '') . ').';
                $blockingIssues[] = [
                    'issue_type' => 'missing_ean_placeholder',
                    'line_key' => null,
                    'product_name' => 'VareNr used as EAN (add real EAN)',
                    'vare_nr' => null,
                    'ean' => null,
                    'expected' => null,
                    'scanned' => null,
                    'order_scan_ids' => $missingEanScanIds,
                    'scan_breakdown' => [],
                ];
            }
            if ($hasDeviations) {
                foreach (array_slice($blockingDeviations, 0, 12) as $d) {
                    $label = $d['name'] ?? $d['sku'] ?? 'Line';
                    $ids = $d['order_scan_ids'] ?? [];
                    $idPart = count($ids) ? ' — order_scans.id: ' . implode(', ', array_slice($ids, 0, 15)) . (count($ids) > 15 ? ' …' : '') : '';
                    $lk = isset($d['line_key']) ? (' — line_key: ' . $d['line_key']) : '';
                    $detailLines[] = sprintf(
                        '[%s] %s — ordered/delivered %d, scanned %d%s%s',
                        $d['issue_type'] ?? 'issue',
                        $label,
                        (int) ($d['expected'] ?? 0),
                        (int) ($d['scanned'] ?? 0),
                        $idPart,
                        $lk
                    );
                }
                if (count($blockingDeviations) > 12) {
                    $detailLines[] = '…and ' . (count($blockingDeviations) - 12) . ' more.';
                }
                foreach ($blockingDeviations as $d) {
                    $blockingIssues[] = [
                        'issue_type' => $d['issue_type'] ?? $this->classifyDeviationIssueType($d),
                        'line_key' => $d['line_key'] ?? null,
                        'product_name' => $d['name'] ?? null,
                        'vare_nr' => $d['sku'] ?? null,
                        'ean' => $d['ean'] ?? null,
                        'expected' => (int) ($d['expected'] ?? 0),
                        'scanned' => (int) ($d['scanned'] ?? 0),
                        'order_scan_ids' => $d['order_scan_ids'] ?? [],
                        'scan_breakdown' => $d['scan_breakdown'] ?? [],
                    ];
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Please fix all scanning errors (Over / Under / Unknown Scan / Missing EAN) before finishing the session.',
                'details' => implode("\n", $detailLines),
                'blocking_issues' => $blockingIssues,
            ]);
        }

        $order->status = 'Completed';
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
        $items = OrderItem::where('order_id', $orderId)->get();
        $allScans = OrderScan::where('order_id', $orderId)
            ->where('deactivated', false)
            ->get();

        $scanCodes = $allScans->pluck('ean_code')->map(function ($c) {
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

        $getTrueKey = function ($code) use ($productMapByEan, $productMapById) {
            $code = trim((string)$code);
            $p = $productMapByEan->get($code) ?? $productMapById->get($code);
            return ($p && $p->ean_code) ? trim((string)$p->ean_code) : $code;
        };

        // Same reception target as show.blade.php: LEVERT (delivered) when > 0, otherwise ORDRE (quantity).
        $orderedMap = [];
        $deliveredMap = [];
        foreach ($items as $item) {
            $key = trim((string) $getTrueKey($item->sku));
            $orderedMap[$key] = ($orderedMap[$key] ?? 0) + (int) ($item->quantity ?? 0);
            $deliveredMap[$key] = ($deliveredMap[$key] ?? 0) + (int) ($item->delivered ?? 0);
        }

        $scannedMap = [];
        foreach ($allScans as $scan) {
            $key = trim((string) $getTrueKey($scan->ean_code));
            $scannedMap[$key] = ($scannedMap[$key] ?? 0) + (int) $scan->units;
        }

        $expectedForKey = static function (string $key) use ($deliveredMap, $orderedMap): int {
            $d = (int) ($deliveredMap[$key] ?? 0);

            return $d > 0 ? $d : (int) ($orderedMap[$key] ?? 0);
        };

        $allKeys = array_unique(array_merge(
            array_keys($orderedMap),
            array_keys($deliveredMap),
            array_keys($scannedMap)
        ));

        $deviations = [];
        foreach ($allKeys as $key) {
            $key = trim((string) $key);
            $expected = $expectedForKey($key);
            $scanned = (int) ($scannedMap[$key] ?? 0);
            if ($expected === 0 && $scanned === 0) {
                continue;
            }
            if ($scanned === $expected) {
                continue;
            }
            $p = $productMapByEan->get($key) ?? $productMapById->get($key);
            $firstItem = $items->first(function ($it) use ($getTrueKey, $key) {
                return trim((string) $getTrueKey($it->sku)) === $key;
            });
            $lineKey = $key;
            $deviations[] = [
                'line_key' => $lineKey,
                'sku' => $firstItem ? $firstItem->sku : $key,
                'ean' => $p ? $p->ean_code : $key,
                'name' => $firstItem ? $firstItem->item_name : 'Unknown Scan',
                'expected' => $expected,
                'scanned' => $scanned,
                'diff' => $scanned - $expected,
            ];
        }

        $keyScanDetails = [];
        foreach ($allScans as $scan) {
            $k = trim((string) $getTrueKey($scan->ean_code));
            if (!isset($keyScanDetails[$k])) {
                $keyScanDetails[$k] = [];
            }
            $keyScanDetails[$k][] = [
                'order_scan_id' => (int) $scan->id,
                'raw_input' => trim((string) $scan->ean_code),
                'units' => (int) $scan->units,
            ];
        }

        foreach ($deviations as &$d) {
            $lk = trim((string) ($d['line_key'] ?? ''));
            $breakdown = $lk !== '' ? ($keyScanDetails[$lk] ?? []) : [];
            $d['order_scan_ids'] = array_values(array_unique(array_map(static function ($row) {
                return $row['order_scan_id'];
            }, $breakdown)));
            $d['scan_breakdown'] = $breakdown;
            $d['issue_type'] = $this->classifyDeviationIssueType($d);
        }
        unset($d);

        return $deviations;
    }

    private function classifyDeviationIssueType(array $d): string
    {
        $e = (int) ($d['expected'] ?? 0);
        $s = (int) ($d['scanned'] ?? 0);
        if ($e === 0 && $s > 0) {
            return 'unknown_scan';
        }
        if ($s > $e) {
            return 'over';
        }

        return 'under';
    }

    /**
     * order_scans row IDs where the hub would show “EAN missing — using VareNr” (see show.blade.php $rowEanMissing).
     */
    private function orderHubMissingEanScanIds(string $orderId): array
    {
        $items = OrderItem::where('order_id', $orderId)->get();
        $allScans = OrderScan::where('order_id', $orderId)->where('deactivated', false)->get();
        if ($allScans->isEmpty()) {
            return [];
        }

        $scanCodes = $allScans->pluck('ean_code')->map(function ($c) {
            return trim((string) $c);
        })->unique();
        $orderSkus = $items->pluck('sku')->map(function ($s) {
            return trim((string) $s);
        })->unique();

        $products = Product::whereIn('ean_code', $scanCodes)
            ->orWhereIn('product_id', $scanCodes->merge($orderSkus)->unique())
            ->get();

        $productMapByEan = $products->filter(function ($p) {
            return !is_null($p->ean_code);
        })->keyBy(function ($p) {
            return trim((string) $p->ean_code);
        });

        $productMapById = $products->filter(function ($p) {
            return !is_null($p->product_id);
        })->keyBy(function ($p) {
            return trim((string) $p->product_id);
        });

        $getTrueKey = function ($code) use ($productMapByEan, $productMapById) {
            $code = trim((string) $code);
            $p = $productMapByEan->get($code) ?? $productMapById->get($code);

            return ($p && $p->ean_code) ? trim((string) $p->ean_code) : $code;
        };

        $ids = [];
        foreach ($allScans as $scan) {
            $key = $getTrueKey($scan->ean_code);
            $p = $productMapByEan->get($key) ?? $productMapById->get(trim((string) $scan->ean_code));
            $tempItem = $items->first(function ($it) use ($key, $scan) {
                return trim((string) $it->sku) === $key || trim((string) $it->sku) === trim((string) $scan->ean_code);
            });
            $sku = $p ? $p->product_id : ($tempItem ? $tempItem->sku : null);
            if ($sku !== null && $sku !== '' && $key !== '' && trim((string) $key) === trim((string) $sku)) {
                $ids[] = (int) $scan->id;
            }
        }

        return array_values(array_unique($ids));
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

    /**
     * Return the globally latest scan (any order) as enriched JSON — for mobile-global polling.
     */
    public function globalLatestScan()
    {
        $latestScan = OrderScan::where('deactivated', false)
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$latestScan) {
            return response()->json(['success' => true, 'has_scan' => false]);
        }

        $state = $this->getProductState($latestScan->order_id, $latestScan->ean_code);

        $order = OrderRecord::where('order_id', $latestScan->order_id)->first();

        return response()->json(array_merge([
            'success'    => true,
            'has_scan'   => true,
            'scan_id'    => $latestScan->id,
            'order_id'   => $latestScan->order_id,
            'units'      => $latestScan->units,
            'order_status' => $order ? $order->status : 'Started',
            'updated_at' => $latestScan->updated_at ? $latestScan->updated_at->valueOf() : 0,
            'timestamp'  => Carbon::parse($latestScan->scan_date_time)->format('H:i'),
        ], $state));
    }

    /**
     * Return the latest scan state for an order as JSON (used by mobile for cross-device sync).
     */
    public function latestScanState($orderId)
    {
        $latestScan = OrderScan::where('order_id', $orderId)
            ->where('deactivated', false)
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$latestScan) {
            return response()->json([
                'success' => true,
                'has_scan' => false,
                'scan_id' => null,
            ]);
        }

        $state = $this->getProductState($orderId, $latestScan->ean_code);
        $order = OrderRecord::where('order_id', $orderId)->first();

        return response()->json(array_merge([
            'success'   => true,
            'has_scan'  => true,
            'scan_id'   => $latestScan->id,
            'order_id'  => $orderId,
            'units'     => $latestScan->units,
            'order_status' => $order ? $order->status : 'Started',
            'timestamp' => Carbon::parse($latestScan->scan_date_time)->format('H:i'),
            'updated_at' => $latestScan->updated_at ? $latestScan->updated_at->valueOf() : now()->valueOf()
        ], $state));
    }

    private function broadcastGlobalScanState($orderId, array $payload): void
    {
        try {
            $order = OrderRecord::where('order_id', $orderId)->first();
            $globalPayload = array_merge($payload, [
                'order_id' => $orderId,
                'order_status' => $order ? $order->status : 'Started',
            ]);
            broadcast(new ScanBroadcast('global', $globalPayload));
        } catch (\Exception $e) {
            Log::error("Pusher Global Broadcast Error: " . $e->getMessage());
        }
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
            $latestScan->delivery_target_total = $state['delivery_target'];
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
            $latestScan->delivery_target_total = $state['delivery_target'];
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

    public function reopenOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:order_records,order_id',
            'password' => 'required'
        ]);

        if ($request->password !== '3535') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password'
            ], 403);
        }

        $order = OrderRecord::where('order_id', $request->order_id)->first();
        $order->status = 'Started';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order reopened successfully',
            'status' => $order->status
        ]);
    }

}
