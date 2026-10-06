<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Tests\TestCase;

class SupplierOrderImportPriceTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_records');
        Schema::dropIfExists('products');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_id')->unique();
            $table->string('product_name')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('retail', 10, 2)->default(0);
            $table->string('ean_code')->nullable();
        });

        Schema::create('order_records', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id')->unique();
            $table->date('order_date')->nullable();
            $table->date('planned_delivery')->nullable();
            $table->string('status')->default('Started');
            $table->string('staff')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id');
            $table->string('order_id2')->nullable();
            $table->date('order_date')->nullable();
            $table->string('ordered_by')->nullable();
            $table->date('planned_delivery')->nullable();
            $table->string('status')->nullable();
            $table->string('your_reference')->nullable();
            $table->string('sku')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('packaging_quantity')->default(1);
            $table->string('packaging_unit')->nullable();
            $table->integer('ordered_quantity')->default(0);
            $table->integer('delivered')->default(0);
            $table->integer('quantity')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        parent::tearDown();
    }

    public function test_legacy_xls_saves_net_price_to_order_item_and_existing_product(): void
    {
        $this->app['db']->table('products')->insert([
            'product_id' => '199909',
            'product_name' => 'Existing product',
            'price' => 2085.00,
            'retail' => 0,
        ]);

        $response = $this->post(route('order-delivery.import'), [
            'order_file' => $this->legacyXlsUpload(
                $this->legacyRow('ORDER-A', '199909', 2085, 0, 15, 0, 0, 0)
            ),
        ]);

        $response->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('order_items', [
            'order_id' => 'ORDER-A',
            'sku' => '199909',
            'price' => 1772.25,
        ]);
        $this->assertDatabaseHas('products', [
            'product_id' => '199909',
            'price' => 1772.25,
        ]);
    }

    public function test_legacy_xls_saves_same_net_price_when_creating_product(): void
    {
        $response = $this->post(route('order-delivery.import'), [
            'order_file' => $this->legacyXlsUpload(
                $this->legacyRow('ORDER-B', '264980', 653, 4, 15, 0, 8, 0)
            ),
        ]);

        $response->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('order_items', [
            'order_id' => 'ORDER-B',
            'sku' => '264980',
            'price' => 510.96,
        ]);
        $this->assertDatabaseHas('products', [
            'product_id' => '264980',
            'price' => 510.96,
        ]);
    }

    public function test_actual_supplier_xls_rows_store_discounted_net_prices(): void
    {
        $zipPath = base_path('Aug (3).zip');

        if (! is_file($zipPath)) {
            $this->markTestSkipped('The supplied August ZIP is not available.');
        }

        $response = $this->post(route('order-delivery.import'), [
            'order_file' => $this->actualSupplierXlsUpload($zipPath),
        ]);

        $response->assertSessionHasNoErrors()->assertSessionHas('success');

        foreach ([
            '264980' => 510.96,
            '302085' => 255.18,
        ] as $sku => $expectedPrice) {
            $this->assertDatabaseHas('order_items', [
                'sku' => $sku,
                'price' => $expectedPrice,
            ]);
            $this->assertDatabaseHas('products', [
                'product_id' => $sku,
                'price' => $expectedPrice,
            ]);
        }
    }

    public function test_malformed_legacy_discount_rolls_back_the_import(): void
    {
        $response = $this->post(route('order-delivery.import'), [
            'order_file' => $this->legacyXlsUpload(
                $this->legacyRow('ORDER-C', 'BAD-DISCOUNT', 100, 0, 'bad', 0, 0, 0)
            ),
        ]);

        $response->assertSessionHasErrors('message');
        $this->assertDatabaseCount('order_records', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_standard_headed_csv_import_remains_supported(): void
    {
        $response = $this->post(route('order-delivery.import'), [
            'order_file' => $this->standardCsvUpload(),
        ]);

        $response->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('order_items', [
            'order_id' => 'ORDER-STANDARD',
            'sku' => 'STANDARD-1',
            'price' => 2085.00,
        ]);
        $this->assertDatabaseHas('products', [
            'product_id' => 'STANDARD-1',
            'price' => 2085.00,
        ]);
    }

    private function legacyXlsUpload(array $row): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([$this->legacyHeader(), $row]);

        $path = tempnam(sys_get_temp_dir(), 'supplier-order-');
        $this->temporaryFiles[] = $path;
        (new Xls($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'supplier-order.xls',
            'application/vnd.ms-excel',
            null,
            true
        );
    }

    private function standardCsvUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'supplier-order-standard-');
        $this->temporaryFiles[] = $path;
        file_put_contents(
            $path,
            "OrderId;OrderDate;StockKeepingUnit;ItemName;PackagingQuantity;PackagingUnit;OrderedQuantity;DeliveredQuantity;Price\n"
            ."ORDER-STANDARD;01.08.2026;STANDARD-1;Standard product;1;SPA;1;1;2085,00\n"
        );

        return new UploadedFile($path, 'supplier-order.csv', 'text/csv', null, true);
    }

    private function actualSupplierXlsUpload(string $zipPath): UploadedFile
    {
        $zip = new \ZipArchive;
        $zip->open($zipPath);
        $source = $zip->getStream('Aug/ordre_750553.xls');

        if ($source === false) {
            $zip->close();
            $this->fail('Aug/ordre_750553.xls is missing from the supplied ZIP.');
        }

        $path = tempnam(sys_get_temp_dir(), 'actual-supplier-order-');
        $this->temporaryFiles[] = $path;
        $destination = fopen($path, 'wb');
        stream_copy_to_stream($source, $destination);
        fclose($destination);
        fclose($source);
        $zip->close();

        return new UploadedFile(
            $path,
            'ordre_750553.xls',
            'application/vnd.ms-excel',
            null,
            true
        );
    }

    private function legacyHeader(): array
    {
        return [
            'OrderId', 'OrderDate', 'OrderedBy', 'PlannedDelivery', 'Status',
            'YourReference', 'StockKeepingUnit', 'ItemName', 'PackagingQuantity',
            'PackagingUnit', 'OrderedQuantity', 'DeliveredQuantity', 'Price',
            'LineDiscount', 'SpecialDiscount', 'InvoiceId', 'InvoiceDate',
            '', '', '', '', '', '',
        ];
    }

    private function legacyRow(
        string $orderId,
        string $sku,
        int $grossWhole,
        int $grossDecimal,
        int|string|null $discount1Whole,
        int|string|null $discount1Decimal,
        int|string|null $discount2Whole,
        int|string|null $discount2Decimal
    ): array {
        return [
            $orderId,
            '01.08.2026',
            'Test Buyer',
            '05.08.2026',
            'Open',
            'Test Reference',
            $sku,
            'Supplier test product',
            1,
            0,
            'SPA',
            1,
            0,
            1,
            0,
            $grossWhole,
            $grossDecimal,
            $discount1Whole,
            $discount1Decimal,
            $discount2Whole,
            $discount2Decimal,
            'INV-1',
            '01.08.2026',
        ];
    }
}
