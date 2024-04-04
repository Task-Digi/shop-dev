<?php

use App\Http\Controllers\SaleItemController;
use App\Http\Controllers\AuthLoginController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\Auth\New_LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CustomerController;
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

// use \CompanyController;




Route::get('/view', [SaleItemController::class, 'view'])->name('saleitems.view');
Route::get('/93WwgVzcc9shQaxnd34c', [SaleItemController::class, 'create']);
Route::post('/home', [SaleItemController::class, 'store']);


Route::get('/download-csv', 'CsvController@downloadCsv')->name('download.csv');

Route::get('/{id}/edit', [SaleItemController::class, 'edit']);
Route::put('/{id}', [SaleItemController::class, 'update']);
Route::delete('/{id}', [SaleItemController::class, 'destroy']);


Route::get('/login', [New_LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthLoginController::class, 'login']);


// piratheep routes 
 Route::get('/get-customer-details', [CustomerController::class, 'getCustomerDetails'])->name('get_customer_details');
 Route::get('/validate-order-id', [SaleItemController::class, 'validateOrderId'])->name('validate_order_id');



// Route::group(['as'=>'admin.','prefix' => 'admin','namespace'=>'Admin','middleware'=>['auth','admin']], function () {
// 		Route::get('dashboard', 'DashboardController@index')->name('dashboard');
// });

// Route::group(['as'=>'user.','prefix' => 'user','namespace'=>'User','middleware'=>['auth','user']], function () {
// 		Route::get('dashboard', 'DashboardController@index')->name('dashboard');
// });
//Coupon Route
// Route::post('/coupon', [CouponController::class, 'store'])->name('coupon.store');
// Route::get('/', [CouponController::class, 'showForm']);
// Route::post('/get-coupons', [CouponController::class, 'getCoupons']);
// Route::post('/mark-as-used', [CouponController::class, 'markAsUsed']);
// Route::get('/logout', [CouponController::class, 'logout'])->name('logout');

// Route::get('/login', [CouponController::class, 'showLoginForm'])->name('login');
// Route::post('/login', [CouponController::class, 'login']);
// Route::get('/create', [CouponController::class, 'showCreateView'])->name('coupons.create');

//create coupons

// Route::get('/check-mobile-number/{mobileNumber}', 'CouponController@checkMobileNumber');
// Route::get('/coupons/getExistingCoupons', 'CouponController@getExistingCoupons')->name('coupons.getExistingCoupons');
// Route::post('/coupons/store', [CouponController::class, 'store'])->name('coupons.store');
// Route::get('/coupons/couponList', [CouponController::class, 'showCouponList'])->name('coupons.couponList');

// Route::get('/coupons/edit/{id}', 'CouponController@edit')->name('coupons.edit');
// Route::put('/coupons/update/{id}', 'CouponController@update')->name('coupons.update');
// Route::put('/coupons/update/{id}', 'CouponController@update')->name('coupons.update');

// Route::get('/coupons/edit/{id}', [CouponController::class, 'edit'])->name('coupons.edit');
// Route::put('/coupons/updateBulk', 'CouponController@updateBulk')->name('coupons.updateBulk');
// Route::delete('/coupons/delete/{id}', [CouponController::class, 'destroy'])->name('coupons.destroy');

//calendar route
// Route::get('/calendar', [CalendarController::class, 'showCalendar'])->name('calendar');
// routes/web.php

// Route::get('/login', 'AuthController@showLoginForm')->name('login');
// Route::post('/login', 'AuthController@login');


// Route::resource('companies', CompanyController::class);

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('test', function () {
    Artisan::call('cache:clear');
});


 


//Route::get('/migration/{filter}', 'Controller@migration')->name('migration');

// Route::get('/dashboard/{user}/{standard}', 'Front\DashboardController@dashboard')->name('front.dashboard');
// Route::get('/dashboard-2/{user}/{standard}', 'Front\DashboardController@dashboard2')->name('front.dashboard.two');
// Route::get('/dashboard-3/{user}/{standard}', 'Front\DashboardController@dashboard3')->name('front.dashboard.three');
// Route::get('/dashboard-4/{user}/{standard}', 'Front\DashboardController@dashboard4')->name('front.dashboard.four');

// Route::get('/dashboard-1-multiple/{user}/{standard}', 'Front\DashboardMultipleController@dashboard')->name('front.dashboard.multiple.one');
// Route::get('/dashboard-2-multiple/{user}/{standard}', 'Front\DashboardMultipleController@dashboard2')->name('front.dashboard.multiple.two');
// Route::get('/dashboard-3-multiple/{user}/{standard}', 'Front\DashboardMultipleController@dashboard3')->name('front.dashboard.multiple.three');
// Route::get('/dashboard-4-multiple/{user}/{standard}', 'Front\DashboardMultipleController@dashboard4')->name('front.dashboard.multiple.four');

// Route::get('/questions', 'Front\FrontController@questions')->name('front.question.page.one');
// Route::post('/questions', 'Front\FrontController@saveAnswerMandatory');
// Route::get('/questions-final', 'Front\FrontController@otherQuestions')->name('front.question.page.two');
// Route::post('/questions-final', 'Front\FrontController@saveAllAnswer');
// Route::get('/thanks', 'Front\FrontController@thanks')->name('front.question.page.three');

// Route::get('/project/{access_code}', 'Front\FrontController@index')->name('front.retail.project');
// Route::get('/project1/{access_code}', 'Front\FrontController@index')->name('front.retail.project1');
// Route::get('/routine/A8H7G', 'Front\FrontController@retailRoutine')->name('front.retail.routine');
// Route::get('/information/A8H7G', 'Front\FrontController@RetailInformation')->name('front.retail.information');
// Route::get('/survey/{id}', 'Front\FrontController@surveyDetails')->name('front.survey.details');
// Route::get('/{access_code?}', 'Front\FrontController@index')->name('front.home');
// Route::post('/{access_code?}', 'Front\FrontController@checkCustomer');


// Route::get('que-update/change-nps', 'Front\DashboardController@changeNPS');

// Auth::routes();

// // Route::get('/home', 'HomeController@index')->name('home');

//   Route::get('/login_2', [New_LoginController::class, 'show'])->name('login_2');
  
//   Route::get('/admin', 'AdminController@index');

// Route::get('/superadmin', 'SuperAdminController@index');
