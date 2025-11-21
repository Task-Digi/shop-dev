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

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Authentication Routes
Route::get('/login', [New_LoginController::class, 'showLoginForm'])->name('login');
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
Route::get('/93WwgVzcc9shQaxnd34c', [SaleItemController::class, 'create']);
Route::post('/home', [SaleItemController::class, 'store']);
Route::get('/date-get', [SaleItemController::class, 'getLastSubmissionDate']);
Route::get('/{id}/edit', [SaleItemController::class, 'edit']);
Route::put('/{id}', [SaleItemController::class, 'update']);
Route::delete('/{id}', [SaleItemController::class, 'destroy']);

// CSV Download Route
Route::get('/download-csv', 'CsvController@downloadCsv')->name('download.csv');

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

// Test Route
Route::get('test', function () {
    Artisan::call('cache:clear');
    return 'Cache cleared successfully';
});

// Fallback Route for 404 errors
Route::fallback(function () {
    return view('errors.404');
});

// =============================================================================
// COMMENTED OUT ROUTES (For Reference - Keep if you need them later)
// =============================================================================

// Route::group(['as'=>'admin.','prefix' => 'admin','namespace'=>'Admin','middleware'=>['auth','admin']], function () {
// 		Route::get('dashboard', 'DashboardController@index')->name('dashboard');
// });

// Route::group(['as'=>'user.','prefix' => 'user','namespace'=>'User','middleware'=>['auth','user']], function () {
// 		Route::get('dashboard', 'DashboardController@index')->name('dashboard');
// });

// Calendar route (commented out)
// Route::get('/calendar', [CalendarController::class, 'showCalendar'])->name('calendar');

// Company resource route (commented out)
// Route::resource('companies', CompanyController::class);

// Welcome route (commented out)
// Route::get('/', function () {
//     return view('welcome');
// });

// Migration route (commented out)
// Route::get('/migration/{filter}', 'Controller@migration')->name('migration');

// Front dashboard routes (commented out)
// Route::get('/dashboard/{user}/{standard}', 'Front\DashboardController@dashboard')->name('front.dashboard');
// Route::get('/dashboard-2/{user}/{standard}', 'Front\DashboardController@dashboard2')->name('front.dashboard.two');
// Route::get('/dashboard-3/{user}/{standard}', 'Front\DashboardController@dashboard3')->name('front.dashboard.three');
// Route::get('/dashboard-4/{user}/{standard}', 'Front\DashboardController@dashboard4')->name('front.dashboard.four');

// Route::get('/dashboard-1-multiple/{user}/{standard}', 'Front\DashboardMultipleController@dashboard')->name('front.dashboard.multiple.one');
// Route::get('/dashboard-2-multiple/{user}/{standard}', 'Front\DashboardMultipleController@dashboard2')->name('front.dashboard.multiple.two');
// Route::get('/dashboard-3-multiple/{user}/{standard}', 'Front\DashboardMultipleController@dashboard3')->name('front.dashboard.multiple.three');
// Route::get('/dashboard-4-multiple/{user}/{standard}', 'Front\DashboardMultipleController@dashboard4')->name('front.dashboard.multiple.four');

// Front question routes (commented out)
// Route::get('/questions', 'Front\FrontController@questions')->name('front.question.page.one');
// Route::post('/questions', 'Front\FrontController@saveAnswerMandatory');
// Route::get('/questions-final', 'Front\FrontController@otherQuestions')->name('front.question.page.two');
// Route::post('/questions-final', 'Front\FrontController@saveAllAnswer');
// Route::get('/thanks', 'Front\FrontController@thanks')->name('front.question.page.three');

// Front project routes (commented out)
// Route::get('/project/{access_code}', 'Front\FrontController@index')->name('front.retail.project');
// Route::get('/project1/{access_code}', 'Front\FrontController@index')->name('front.retail.project1');
// Route::get('/routine/A8H7G', 'Front\FrontController@retailRoutine')->name('front.retail.routine');
// Route::get('/information/A8H7G', 'Front\FrontController@RetailInformation')->name('front.retail.information');
// Route::get('/survey/{id}', 'Front\FrontController@surveyDetails')->name('front.survey.details');
// Route::get('/{access_code?}', 'Front\FrontController@index')->name('front.home');
// Route::post('/{access_code?}', 'Front\FrontController@checkCustomer');

// NPS update route (commented out)
// Route::get('que-update/change-nps', 'Front\DashboardController@changeNPS');

// Auth routes (commented out - using custom login above)
// Auth::routes();
// Route::get('/home', 'HomeController@index')->name('home');

// Alternative login route (commented out)
// Route::get('/login_2', [New_LoginController::class, 'show'])->name('login_2');

// Admin routes (commented out)
// Route::get('/admin', 'AdminController@index');
// Route::get('/superadmin', 'SuperAdminController@index');