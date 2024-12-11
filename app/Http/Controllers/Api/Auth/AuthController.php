<?php

namespace App\Http\Controllers\Api\Auth;

use Carbon\Carbon;
use App\Models\User;
use App\Mail\VerifyEmail;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\SetupRequest;
use App\Http\Controllers\Controller;
use App\Models\AssignGig;
use App\Models\IncidentReport;
use App\Models\RewardPoint;
use App\Models\RewardPointLog;
use App\Models\SupervisorInCharge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Log;
use App\Rules\ValidZipCode;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login','checkToken', 'profile','resend']]);
    }
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = Auth::attempt($credentials)) {
            return response()->json(['status' => 403, 'response' => 'Unauthorized', 'message' => 'Unauthorized User'], 403);
        }
        $user = User::where('email', $request->email)->first();
        $is_verified = $user->email_verified_at;

        $sender = "no-reply@stghcs.com";
        /*if ($is_verified == null) {
            return response()->json(['status' => 400, 'response' => 'Bad Request', 'message' => "E-mail Verification Required"], 400);
        }*/

        ActivityLog::create([
            'action' => 'Login',
            'description' => $user->last_name . ' ' . $user->first_name . ' Logged in at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);

        return $this->createToken($token);
    }

    public function createToken($token)
    {
        $user = User::find(auth('api')->user()->id);
        $active = AssignGig::where('user_id', auth('api')->user()->id)->count();
        $assign_gig =  AssignGig::where('user_id', auth('api')->user()->id)->with(['gig.client','schedule'])->get();
        $incidents =  IncidentReport::where('user_id', auth('api')->user()->id)->get();
        $user->makeHidden(['ssn','verification_code']);
        if($user->hasRole('Manager') || $user->hasRole('Admin')){
            $can_create = true;
        }else{
            $can_create = false;
        }
        if (!$user->hasRole('Admin')) {
            if ($user->ssn == null) {
                return response()->json([
                    'status' => 200,
                    'response' => 'Complete Setup',
                    'message' => "Please Update your details to continue.",
                    'profile_completed' => false,
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth('api')->factory()->getTTL() * 360000,
                    'active_gig' => $active,
                    'user' => $user,
                    'location' => $user->location->city,
                    'assigned_gigs' => $assign_gig,
                    'incidents' => $incidents,
                    'can_create_users' => true,
                ]);
            }
        }
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'profile_completed' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 360000,
            'active_gig' => $active,
            'user' => $user,
            'location' => $user->location->city,
            'assigned_gigs' => $assign_gig,
            'incidents' => $incidents,
            'can_create_users' => $can_create
        ]);
    }
    
    public function userResponse($token,$message)
    {
        $user = User::find(auth('api')->user()->id);
        $active = AssignGig::where('user_id', auth('api')->user()->id)->count();
        $assign_gig =  AssignGig::where('user_id', auth('api')->user()->id)->with(['gig.client','schedule'])->get();
        $incidents =  IncidentReport::where('user_id', auth('api')->user()->id)->get();
        $user->makeHidden(['ssn','verification_code']);
        if($user->hasRole('Manager') || $user->hasRole('Admin')){
            $can_create = true;
        }else{
            $can_create = false;
        }
        if (!$user->hasRole('Admin')) {
            if ($user->ssn == null) {
                return response()->json([
                    'status' => 200,
                    'response' => 'Successful',
                    'message' => $message,
                    'message' => "Please Update your details to continue.",
                    'profile_completed' => false,
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth('api')->factory()->getTTL() * 360000,
                    'active_gig' => $active,
                    'user' => $user,
                    'location' => $user->location->city,
                    'assigned_gigs' => $assign_gig,
                    'incidents' => $incidents,
                    'can_create_users' => true,
                ]);
            }
        }
        return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'message' => $message,
            'profile_completed' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 360000,
            'active_gig' => $active,
            'user' => $user,
            'location' => $user->location->city,
            'assigned_gigs' => $assign_gig,
            'incidents' => $incidents,
            'can_create_users' => $can_create
        ]);
    }
    
    public function email_verify(Request $request)
    {
        $request->validate([
            'verification_code' => 'required',
        ]);

        try {
            $user = User::where('email', auth()->user()->email)->firstOrFail();
    
            if ($user->email_verified_at) {
                return response()->json([
                    'status' => 400,
                    'response' => 'Bad Request',
                    'message' => 'Email already verified',
                ], 400);
            }
    
            if ($user->verification_code != $request->verification_code) {
                return response()->json([
                    'status' => 400,
                    'response' => 'Bad Request',
                    'message' => 'Invalid verification code',
                ], 400);
            }
    
            $user->email_verified_at = Carbon::now();
            $user->verification_code = null; // Clear the code after successful verification
            $user->save();
    
            ActivityLog::create([
                'action' => 'E-mail Verification',
                'description' => $user->last_name . ' ' . $user->first_name . ' verified email address at ' . Carbon::now()->format('h:i:s A'),
                'subject_id' => $user->id,
                'subject_type' => get_class($user),
                'user_id' => auth()->id(),
            ]);
    
            return response()->json([
                'status' => 200,
                'response' => 'Successful',
                'message' => 'E-mail address has been verified',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'response' => 'Request Failed',
                'message' => 'Invalid email or verification code',
            ], 400);
        }
    }
    
    public function profile(Request $request)
    {
        try {
            // Get the authenticated user
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token has expired'
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token is invalid'
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token not provided'
            ], 401);
        }
    
        // Validate the employee ID
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }
    
        // Fetch the user based on employee ID
        $user = User::where('employee_id', $request->employee_id)
                    ->with([
                        'assigned_gig.gig.client',
                        'assigned_gig.gig.schedule',
                        'assigned_gig.gig.timesheet',
                        'rewardPointLogs',
                        'incident_report',
                        'roles', // Ensure roles are loaded
                        'assigned_gig' => function($query) {
                            $query->orderBy('created_at', 'desc');
                        }
                    ])->first();
    
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        $user->makeHidden(['ssn','verification_code']);
    
        // Check the user's role
        $roles = $user->roles->pluck('name'); // Extract role names
        
        if ($roles->contains('Supervisor')) {
        // If user is a supervisor, fetch all support workers under this supervisor using SupervisorInCharge
        $supportWorkers = User::where('location_id', $user->location_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['DSW', 'CSP']);
            })
            ->whereHas('supervisor_in_charges', function($query) use ($user) {
                $query->where('supervisor_id', $user->id);
            })
            ->get()
            ->each->makeHidden(['ssn','verification_code']); // Hide SSN for each support worker if they exist
    
        return response()->json([
            'status' => 200,
            'response' => $user->last_name . ' ' . $user->first_name . ' fetched successfully',
            'user' => $user,
            'supervisor' => null,
            'support_workers' => $supportWorkers,
            'roles' => $roles
        ], 200);
    
        } elseif ($roles->contains('DSW') || $roles->contains('CSP')) {
        // Fetch the supervisor for the user
        $supervisorId = SupervisorInCharge::where('user_id', $user->id)->value('supervisor_id');
        
        $supervisor = $supervisorId ? User::where('id', $supervisorId)->first() : null;
    
        // Check if supervisor exists before calling makeHidden
        if ($supervisor) {
            $supervisor->makeHidden(['ssn','verification_code']);
        }
    
        return response()->json([
            'status' => 200,
            'response' => $user->last_name . ' ' . $user->first_name . ' fetched successfully',
            'user' => $user,
            'supervisor' => $supervisor,
            'support_workers' => null,
            'roles' => $roles
        ], 200);
    }
    
    
        // Default response if the role does not match any specific conditions
        return response()->json([
            'status' => 200,
            'response' => $user->last_name . ' ' . $user->first_name . ' fetched successfully',
            'user' => $user,
            'roles' => $roles
        ], 200);
    }

    public function logout()
    {
        $user = User::where('id', auth('api')->user()->id)->firstOrFail();
        ActivityLog::create([
            'action' => 'Logout',
            'description' => $user->last_name . ' ' . $user->first_name . ' Logged out at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $user->id,
            'subject_type' => get_class($user),
            'user_id' => auth()->id(),
        ]);
        auth('api')->logout();
        return response()->json([
            'status' => 200, 'response' => 'Successful',
            'message' => 'User Logged out successful'
        ]);
    }
    
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|min:14', // Added min length for old password
            'password' => [
                'required',
                'confirmed',
                'min:14',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
            ],
        ]);
    
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $validator->errors()->all()], 422);
        }
    
        $user = User::find(auth()->user()->id);
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['status' => 422, 'response' => 'Incorrect Current Password', 'message' => ['Your current password is incorrect.']], 422);
        }
    
        if (Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 422, 'response' => 'Password Same As Old Password', 'message' => ['Password already used, choose a different password.']], 422);
        }
    
        // Consider a lock mechanism here if needed
    
        $user->password = Hash::make($request->password);
        $user->is_temporary_password = 0;
        $user->save();
    
        $token = $request->bearerToken();
        
        return $this->userResponse($token,'Password has been successfully updated');
}

    public function reset_temporary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_password' => 'required',
            'password' => [
                'required',
                'confirmed',
                'min:14',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
            ],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }

        $user = User::where('email', auth()->user()->email)->firstOrFail();
        $temp_password = $user->password;
        if (!Hash::check($request->temp_password, $temp_password)) {
            return response()->json(['status' => 401, 'response' => 'Incorrect temporary password', 'message' => 'Temporary password is incorrect.'], 401);
        }
        $user->password = Hash::make($request->password);
        $user->is_temporary_password = 0;
        $user->save();

        $token = $request->bearerToken();
        
        return $this->userResponse($token,'Password has been successfully reset.');
    }
    
    public function complete_setup(SetupRequest $request)
    {
        $user = User::with(['roles', 'location'])->find(auth('api')->user()->id);
        if (!$user) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'User Not Found!'], 404);
        }
         // Format the date of birth
        $formattedDob = Carbon::parse($request->dob)->format('m-d-Y');
        
        $idNameToStore = null;
        
        try {
            if ($request->hasFile('profile_image')) {
                $idNameToStore = $this->uploadFile($request->file('profile_image'), 'stghcs/profile_image');
                
                /*if ($idNameToStore) {
                    $data['passport'] = $idNameToStore;
                } else {
                    return response()->json(['status' => 422, 'response' => 'Unprocessable Entity', 'message' => 'Failed to upload profile image.'], 422);
                }*/
            }
        } catch (\Exception $e) {
            // Log the error with details
            Log::error('Profile image upload failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $data = [
            'ssn' => encrypt($request->ssn),
            'gender' => $request->gender,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'dob' => $formattedDob,
            'passport' => $idNameToStore
        ];

        if (!$user->update($data)) {
            return response()->json(['status' => 500, 'response' => 'Internal Server Error', 'message' => 'Failed to update user data.'], 500);
        }

        $point = 0; // Initialize $point
        if ($user->ssn != null && $user->profile_image != null && $user->passport != null && $user->gender != null && $user->address1 != null && $user->city != null && $user->zip_code != null && $user->dob != null) {
            $reward = RewardPoint::where(['name' => 'Setup Completion'])->first();
            if ($reward) {
                $point = $reward->points;
                $current_point = $user->point;
                $new_point = $point + $current_point;
                $user->update([
                    'points' => $new_point
                ]);
                RewardPointLog::create([
                    'title' => 'Setup Completion by ' . $user->last_name . ' ' . $user->first_name,
                    'user_id' => $user->id,
                    'points' => $point
                ]);
            }
        }

        if ($point > 0) {
            ActivityLog::create([
                'action' => 'Account Setup Completion by ' . $user->last_name . ' ' . $user->first_name,
                'description' => $user->last_name . ' ' . $user->first_name . ' was awarded ' . $point . ' for completing the profile setup at ' . Carbon::now()->format('h:i:s A'),
                'subject_id' => $user->id,
                'subject_type' => get_class($user),
                'user_id' => auth()->id(),
            ]);
        }

        return response()->json(['status' => 200, 'response' => 'Success', 'message' => 'Setup completed successfully.', 'user' => $user]);
    }
    
    public function update_profile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'other_name' => ['nullable'],
            'gender' => ['required','string'],
            'dob' => ['nullable'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }
        
        $user = User::with(['roles', 'location'])->find(auth('api')->user()->id);
        if (!$user) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'User Not Found!'], 404);
        }
        
        // Format the date of birth
        $formattedDob = Carbon::parse($request->dob)->format('m-d-Y');

        $data = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'other_name' => $request->other_name,
            'gender' => $request->gender,
            'dob' => $formattedDob,
        ];

        if (!$user->update($data)) {
            return response()->json(['status' => 500, 'response' => 'Internal Server Error', 'message' => 'Failed to update user data.'], 500);
        }
        
        $user->load('roles', 'location');

        //return response()->json(['status' => 200, 'response' => 'Success', 'message' => 'Profile updated successfully.', 'user' => $user]);
        $active = AssignGig::where('user_id', auth('api')->user()->id)->count();
        $assign_gig =  AssignGig::where('user_id', auth('api')->user()->id)->with(['gig.client','schedule'])->get();
        $incidents =  IncidentReport::where('user_id', auth('api')->user()->id)->get();
        if($user->hasRole('Manager') || $user->hasRole('Admin')){
            $can_create = true;
        }else{
            $can_create = false;
        }
        
        $token = $request->bearerToken();
        
        return $this->userResponse($token,'Profile updated successfully.');
    }
    
    public function update_contact_detail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address1' => ['required','string'],
            'address2' => ['nullable','string'],
            'city' => ['required','string'],
            'zip_code' => ['required', 'string', 'regex:/^\d{5}(-\d{4})?$/', new ValidZipCode]
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }
        
        $user = User::with(['roles', 'location'])->find(auth('api')->user()->id);
        if (!$user) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'User Not Found!'], 404);
        }

        $data = [
            'address1' => $request->address1,
            'address2' => $request->address2,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
        ];

        if (!$user->update($data)) {
            return response()->json(['status' => 500, 'response' => 'Internal Server Error', 'message' => 'Failed to update user data.'], 500);
        }
        
        $user->load('roles', 'location');

        //return response()->json(['status' => 200, 'response' => 'Success', 'message' => 'Contact informations updated successfully.', 'user' => $user]);
        
        $active = AssignGig::where('user_id', auth('api')->user()->id)->count();
        $assign_gig =  AssignGig::where('user_id', auth('api')->user()->id)->with(['gig.client','schedule'])->get();
        $incidents =  IncidentReport::where('user_id', auth('api')->user()->id)->get();
        if($user->hasRole('Manager') || $user->hasRole('Admin')){
            $can_create = true;
        }else{
            $can_create = false;
        }
        
        $token = $request->bearerToken();
        
        return $this->userResponse($token,'Contact informations updated successfully.');
    }
    
    public function update_profile_image(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'profile_image' => ['required','file','mimes:jpeg,jpg,bmp,png,webp']
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => implode(', ', $validator->errors()->all())], 422);
        }
        
        $user = User::find(auth('api')->user()->id);
        if (!$user) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'User Not Found!'], 404);
        }

        $data = [];

        if ($request->hasFile('profile_image')) {
            $idNameToStore = $this->uploadFile($request->file('profile_image'), 'stghcs/profile_image');
            if ($idNameToStore) {
                $data['passport'] = $idNameToStore;
            } else {
                return response()->json(['status' => 422, 'response' => 'Unprocessable Entity', 'message' => 'Failed to upload Profile Image.'], 422);
            }
        }

        if (!$user->update($data)) {
            return response()->json(['status' => 500, 'response' => 'Internal Server Error', 'message' => 'Failed to update user data.'], 500);
        }
        
        $user->load('roles', 'location');
        
        $active = AssignGig::where('user_id', auth('api')->user()->id)->count();
        $assign_gig =  AssignGig::where('user_id', auth('api')->user()->id)->with(['gig.client','schedule'])->get();
        $incidents =  IncidentReport::where('user_id', auth('api')->user()->id)->get();
        if($user->hasRole('Manager') || $user->hasRole('Admin')){
            $can_create = true;
        }else{
            $can_create = false;
        }

        //return response()->json(['status' => 200, 'response' => 'Success', 'message' => 'Profile image updated successfully.', 'user' => $user]);
        /*return response()->json([
            'status' => 200,
            'response' => 'Successful',
            'message' => 'Profile image updated successfully.',
            'profile_completed' => true,
            'active_gig' => $active,
            'user' => $user,
            'location' => $user->location->city,
            'assigned_gigs' => $assign_gig,
            'incidents' => $incidents,
            'can_create_users' => $can_create
        ]);*/
        $token = $request->bearerToken();
        
        return $this->userResponse($token,'Profile image updated successfully.');
    }
    
    private function uploadFile($file, $folder)
    {
        try {
            $uploadedFile = cloudinary()->upload($file->getRealPath(), [
                'folder' => $folder,
                'resource_type' => 'image'
            ]);
            return $uploadedFile->getSecurePath();
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function checkToken(Request $request)
    {
        try {
            // Get the authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token has expired'
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token is invalid'
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 401,
                'response' => 'Unauthorized',
                'message' => 'Token not provided'
            ], 401);
        }
        // If the request reaches here, the token is still active
        return response()->json([
            'status' => 200,
            'response' => 'Authorized',
            'message' => 'Token is active',
        ],200);
    }
    
    public function resend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users,email'],
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => $errors], 422);
        }
        
        // Get the authenticated user
        $user = User::where(['email' => $request->email])->first();
        
        if (!$user) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'User not found',
            ], 404);
        }

        // Check if user is already verified
        if ($user->email_verified_at != null) {
            return response()->json([
                'status' => 400,
                'response' => 'Bad Request',
                'message' => 'Email already verified',
            ], 400);
        }
        
        $sender = "no-reply@stghcs.com";

        // Generate a new verification code (if using a custom code)
        $otp = rand(10000, 99999); // Random 6-character code
        $user->verification_code = $otp;
        $user->save();

        // Send the verification code via email
        Mail::to($user->email)->send(new VerifyEmail($user, $otp, $sender));

        return response()->json(['message' => 'Verification code resent successfully.'], 200);
    }
}
