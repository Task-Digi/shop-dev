<?php

use Illuminate\Support\Facades\Route;


use App\Http\Controllers\SaleItemController;
use App\Http\Controllers\AuthLoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\Auth\New_LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\OrderDeliveryController;
use App\Http\Controllers\TimesheetController;

Route::get('/', function () {
    return view('welcome');
});

// Timesheet Routes
Route::get('/timesheet', [TimesheetController::class, 'index'])->name('timesheet.index');
Route::post('/timesheet/download', [TimesheetController::class, 'download'])->name('timesheet.download');

// Authentication Routes
Route::get('/login', [New_LoginController::class, 'show'])->name('login');
Route::post('/login', [AuthLoginController::class, 'login']);

// Dashboard Routes
Route::get('/Dashboard', [SaleItemController::class, 'datasearch'])->name('saleitems.view');
Route::get('/view', [SaleItemController::class, 'dashboardView'])->name('dashboard.view');

// Data Entry Form Routes
Route::get('/add', [SaleItemController::class, 'add'])->name('saleitems.add');
Route::get('/data-search', [SaleItemController::class, 'datasearch'])->name('saleitems.datasearch');
Route::get('/list-all', [SaleItemController::class, 'listall'])->name('saleitems.list-all');
Route::get('/get-sale-items', [SaleItemController::class, 'getSaleItems'])->name('saleitems.get');
Route::post('/autocomplete/search', [SaleItemController::class, 'autocompleteSearch'])->name('autocomplete.search');

// Sale Items CRUD Routes
Route::get('/93WwgVzcc9shQaxnd34c', [SaleItemController::class, 'create'])->name('saleitems.create_raw');
Route::get('/create', [SaleItemController::class, 'create'])->name('saleitems.create');
Route::get('/sale-items', [SaleItemController::class, 'datasearch'])->name('saleitems.index');
Route::post('/home', [SaleItemController::class, 'store']);
Route::get('/date-get', [SaleItemController::class, 'getLastSubmissionDate']);
Route::get('/{id}/edit', [SaleItemController::class, 'edit']);
Route::put('/{id}', [SaleItemController::class, 'update']);
Route::delete('/{id}', [SaleItemController::class, 'destroy']);

// CSV Download Route
Route::get('/download-csv', 'App\Http\Controllers\CsvController@downloadCsv')->name('download.csv');

// Data Validation Routes
Route::get('/get-customer-details', [CustomerController::class, 'getCustomerDetails'])->name('get_customer_details');
Route::get('/validate-order-id', [SaleItemController::class, 'validateOrderId'])->name('validate_order_id');
Route::post('/validate-product-id', [SaleItemController::class, 'checkProduct'])->name('validate_product_id');
Route::get('/get-product-details', [ProductController::class, 'getProductDetails'])->name('get-product-details');
Route::get('/check-product', [ProductController::class, 'check-product'])->name('check-product');

// Graph and Sales Data Routes
Route::get('/sales-data', [ReportController::class, 'getSalesData'])->name('sales.data');
Route::get('/sales-data/{customerId}', [ReportController::class, 'getSalesDataCustomer'])->name('sales.data-customer');
Route::get('/report/{productid}/product', [ReportController::class, 'productIndex'])->name('product.report');

// Report Routes
Route::get('/report', [ReportController::class, 'viewIndex'])->name('report');
Route::get('/Report_view', [ReportController::class, 'reportDashboard'])->name('Report-view');
Route::get('/report/ks', [ReportController::class, 'ksPage'])->name('report.ks');
Route::get('/report/{customerId}', [ReportController::class, 'index'])->name('report-customer');

// ICT Routes
Route::get('/ict', [ReportController::class, 'ict'])->name('ict');
Route::get('/ict-search', [ReportController::class, 'search'])->name('ict.search');
Route::post('/ict/update-quantity', [ReportController::class, 'updateQuantity'])->name('ict.update-quantity');

// Data API Routes
Route::get('/get-sales-by-date', [SaleItemController::class, 'getSalesByDate'])->name('get_sales_by_date');
Route::get('/get-sales-data-report', [ReportController::class, 'getSalesData'])->name('get_sales_Data_Report');
Route::get('/customers/{date}', [ReportController::class, 'getCustomersByDate']);
Route::get('/customer/products-by-date', [ReportController::class, 'customerProductsByDate']);
Route::get('/customer/details', [ReportController::class, 'getCustomerData']);
Route::get('/CustomerReport/details', [ReportController::class, 'getCustomerReportData']);
Route::get('/product/details', [ReportController::class, 'getProductData']);
Route::get('/customer/finaldetails', [ReportController::class, 'getCustomerfinalData']);
Route::get('/product/finaldetails', [ReportController::class, 'getProductfinalData']);

// CRM and KS Status Update Routes
Route::post('/update-crm-status', [ReportController::class, 'markCrmExists']);
Route::post('/update-ks-status', [ReportController::class, 'updateKsStatus'])->name('update.ks.status');
Route::post('/update-crm-id', [ReportController::class, 'updateCrmId']);
Route::post('/get-latest-crm-id', [ReportController::class, 'getLatestCrmId']);

// Order Delivery Routes
Route::prefix('order-delivery')->group(function () {
    Route::get('/', [OrderDeliveryController::class, 'index'])->name('order-delivery.index');
    Route::get('/mobile-global', [OrderDeliveryController::class, 'mobileGlobal'])->name('order-delivery.mobile-global');
    Route::post('/import', [OrderDeliveryController::class, 'import'])->name('order-delivery.import');
    Route::get('/{orderId}', [OrderDeliveryController::class, 'show'])->name('order-delivery.show');
    Route::post('/scan', [OrderDeliveryController::class, 'scan'])->name('order-delivery.scan');
    Route::post('/view-product', [OrderDeliveryController::class, 'viewProduct'])->name('order-delivery.view-product');
    Route::post('/update-units', [OrderDeliveryController::class, 'updateUnits'])->name('order-delivery.update-units');
    Route::post('/update-exact', [OrderDeliveryController::class, 'updateUnitsExact'])->name('order-delivery.update-exact');
    Route::post('/match-order', [OrderDeliveryController::class, 'updateUnitsToMatchOrder'])->name('order-delivery.match-order');
    Route::post('/update-price', [OrderDeliveryController::class, 'updateProductPrice'])->name('order-delivery.update-price');
    Route::post('/delete-scan', [OrderDeliveryController::class, 'deleteScan'])->name('order-delivery.delete-scan');
    Route::post('/close', [OrderDeliveryController::class, 'closeOrder'])->name('order-delivery.close');
    Route::get('/{orderId}/mobile', [OrderDeliveryController::class, 'mobile'])->name('order-delivery.mobile');
    Route::get('/{orderId}/sync', [OrderDeliveryController::class, 'sync'])->name('order-delivery.sync');
    Route::delete('/{orderId}', [OrderDeliveryController::class, 'deleteOrder'])->name('order-delivery.delete');
});

Route::get('/home', [HomeController::class, 'index'])->name('home');
