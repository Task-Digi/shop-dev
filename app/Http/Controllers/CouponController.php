<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Auth;

use App\Coupon;
use App\customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    public function showLoginForm()
    {
        return view('coupon/login');
    }
    
    public function login(Request $request)
    {
        $password = $request->input('password');

        // Assuming you have a 'customer' model with a corresponding table
        $customer = customer::where('password', $password)->first();

        if ($customer) {
            // Password is correct, you can proceed with the login logic
           return view('coupon/create');
        } else {
            // Invalid credentials
            return view('coupon/login');
        }
    }
    
    public function showForm()
    {
        return view('coupon/enterMobile');
    }
    
    public function showCreateView()
    {
        $coupons = Coupon::all(); 

        return view('coupon.create', compact('coupons'));
    }

//     public function store(Request $request)
//     {
//         // Validation
//         $request->validate([
//         'mobile_nr' => 'required',
//         'voucher' => 'required',
//         'end_date' => 'required|date',
//         ]);


//         foreach ($vouchers as $voucher) {
//             Coupon::create([
//                 'mobile_nr' => $request->input('mobile_nr'),
//                 'voucher' => $voucher,
//                 'end_date' => $request->input('end_date'),
//             ]);
//         }
// // dd ($request->input('mobile_nr'));
//         return redirect()->route('coupons.couponList')->with('success', 'Coupon created successfully');
//     }

    public function store(Request $request)
        {
            // Validation
            $request->validate([
                'mobile_nr' => 'required',
            ]);
        
            $mobileNumber = $request->input('mobile_nr');
        
            for ($i = 1; $i <= 5; $i++) {
                // Check if the checkbox for the current voucher is checked
                if ($request->has("voucher$i")) {
                    // Check if the same coupon already exists
                    $existingCoupon = Coupon::where([
                        'mobile_nr' => $mobileNumber,
                        'voucher' => "$i",
                        'end_date' => $request->input("end_date$i"),
                    ])->first();
        
                    if (!$existingCoupon) {
                        // Store the data in the database or perform any other actions
                        Coupon::create([
                            'mobile_nr' => $mobileNumber,
                            'voucher' => "$i",
                            'end_date' => $request->input("end_date$i"),
                        ]);
                    }
                }
            }
        return redirect()->route('coupons.create')->with('success', 'Coupons created successfully');
    }
    
    public function checkMobileNumber($mobileNumber)
    {
        // Fetch the coupons associated with the mobile number
        $coupons = Coupon::where('mobile_nr', $mobileNumber)->get();
    
        // Check if the mobile number exists and has associated coupons
        $exists = $coupons->isNotEmpty();
    
        // Generate HTML content for the details
        $details = '';
        if ($exists) {
            $details .= '<table class="table">';
            $details .= '<thead>';
            $details .= '<tr>';
            $details .= '<th>Mobile Number</th>';
            $details .= '<th>Voucher(s)</th>';
            $details .= '<th>End Date</th>';
            $details .= '<th>Used</th>';
            $details .= '<th>Actions</th>';
            $details .= '</tr>';
            $details .= '</thead>';
            $details .= '<tbody>';
    
            foreach ($coupons as $coupon) {
                $details .= '<tr>';
                $details .= '<td>' . $coupon->mobile_nr . '</td>';
                $details .= '<td>' . $coupon->voucher . '</td>';
                $details .= '<td>' . \Carbon\Carbon::parse($coupon->end_date)->format('Y-m-d') . '</td>';
                $details .= '<td>' . ($coupon->used ? 'Yes' : 'No') . '</td>';
                $details .= '<td>';
                $details .= '<a href="' . route('coupons.edit', $coupon->id) . '">Edit</a>';
                $details .= '<form method="POST" action="' . url('coupons/delete/' . $coupon->id) . '">';
                $details .= csrf_field();
                $details .= method_field('DELETE');
                $details .= '<button type="submit">Delete</button>';
                $details .= '</form>';
                $details .= '</td>';
                $details .= '</tr>';
            }
    
            $details .= '</tbody>';
            $details .= '</table>';
        }
    
        return response()->json(['exists' => $exists, 'details' => $details]);
    }

    public function getExistingCoupons(Request $request)
    {
        $mobileNumber = $request->input('mobile_nr');
        $existingCoupons = Coupon::where('mobile_nr', $mobileNumber)->pluck('voucher')->toArray();
    
        return response()->json($existingCoupons);
    }

    public function showList(Request $request)
    {
        $mobileNumber = $request->input('mobile_nr');
        $user = Coupon::where('mobile_nr', $mobileNumber)->first();
    
        if ($user) {
            $coupons = Coupon::where('mobile_nr', $mobileNumber)->get();
            return view('coupon.showCoupons', compact('mobileNumber', 'coupons'));
        } else {
            // Create new coupons for the entered mobile number
            $this->createNewCoupons($mobileNumber);
    
            // Fetch the newly created coupons
            $coupons = Coupon::where('mobile_nr', $mobileNumber)->get();
    
            return view('coupon.showCoupons', compact('mobileNumber', 'coupons'));
        }
    }

    public function showCouponList()
    {
        $coupons = Coupon::all(); 
    
        return view('coupon.couponList', compact('coupons'));
    }
    
    // public function edit($id)
    // {
    //     $coupon = Coupon::find($id);
    //     $coupons = Coupon::where('mobile_nr', $coupon->mobile_nr)->get();
    
    //     return view('coupon.edit', ['coupon' => $coupon, 'coupons' => $coupons]);
    // }
    
    public function edit($id)
    {
        $coupon = Coupon::find($id);
    
        // Fetch associated coupons along with their end_date values
        $coupons = Coupon::where('mobile_nr', $coupon->mobile_nr)->get();
    
        return view('coupon.edit', ['coupon' => $coupon, 'coupons' => $coupons]);
    }


    public function updateBulk(Request $request)
    {
        $request->validate([
            'mobile_nr' => 'required|string',
            'voucher' => 'required|array',
            'voucher.*' => 'required|string',
            'end_date' => 'required|date',
        ]);

        $mainCoupon = Coupon::where('mobile_nr', $request->input('mobile_nr'))->first();

        if (!$mainCoupon) {
            return Redirect::route('coupons.couponList')->with('error', 'Main coupon not found.');
        }

        // Update the main coupon details
        $mainCoupon->update([
            'end_date' => $request->input('end_date'),
        ]);

        // Update or create the associated coupons
        foreach ($request->input('voucher') as $key => $voucher) {
            $associatedCoupon = $mainCoupon->coupons()
                ->where('voucher', $voucher)
                ->first();

            if ($associatedCoupon) {
                // Update existing associated coupon
                $associatedCoupon->update([
                    'end_date' => $request->input('end_date')[$key],
                ]);
            } else {
                // Create new associated coupon
                Coupon::create([
                    'mobile_nr' => $mainCoupon->mobile_nr,
                    'voucher' => $voucher,
                    'end_date' => $request->input('end_date')[$key],
                ]);
            }
        }

        // Delete any associated coupons that were not included in the update
        $mainCoupon->coupons()
            ->whereNotIn('voucher', $request->input('voucher'))
            ->delete();

        return Redirect::route('coupons.couponList')->with('success', 'Coupons updated successfully.');
    }

     public function destroy($id)
    {
        // Log a message to see if the method is being called
        \Log::info("Destroy method called for coupon ID: $id");
    
        $coupon = Coupon::find($id);
    
        if ($coupon) {
            $coupon->delete();
            // Log a success message
            \Log::info("Coupon deleted successfully");
            return redirect()->route('coupons.couponList')->with('success', 'Coupon deleted successfully');
        } else {
            // Log an error message
            \Log::error("Coupon not found");
            return redirect()->route('coupons.couponList')->with('error', 'Coupon not found');
        }
    }

    public function getCoupons(Request $request)
    {
        $mobileNumber = $request->input('mobile_nr');
        $user = Coupon::where('mobile_nr', $mobileNumber)->first();
    
        if ($user) {
            $coupons = Coupon::where('mobile_nr', $mobileNumber)->get();
            return view('coupon/showCoupons', compact('mobileNumber', 'coupons'));
        } else {
            // Create new coupons for the entered mobile number
            $this->createNewCoupons($mobileNumber);
    
            // Fetch the newly created coupons
            $coupons = Coupon::where('mobile_nr', $mobileNumber)->get();
            
            return view('coupon/showCoupons', compact('mobileNumber', 'coupons'));
        }
    }
    
    //create the new mobile number user with coupon id 3,4,5
    private function createNewCoupons($mobileNumber)
    {
        // Assuming the coupon numbers are 3, 4, 5
        $couponNumbers = [3, 4, 5];
    
        foreach ($couponNumbers as $couponNumber) {
            Coupon::create([
                'mobile_nr' => $mobileNumber,
                'end_date' => Carbon::now()->addDays(30), // Set the end_date to 30 days from now
                'voucher' => $couponNumber,
                'used' => 0, // Default value for the 'used' field
            ]);
        }
    } 

    public function markAsUsed(Request $request)
    {
        $couponId = $request->input('coupon_id');
        $coupon = Coupon::find($couponId);
        $coupon->used = true;
        $coupon->save();

        return response()->json(['message' => 'Coupon marked as used']);
    }
    
    public function logout()
    {
        \Auth::logout();
        // Redirect to the desired page after logout
        return redirect('/login'); // You can change this URL based on your application setup
    }
}
