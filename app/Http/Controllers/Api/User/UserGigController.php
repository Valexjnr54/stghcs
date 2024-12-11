<?php

namespace App\Http\Controllers\Api\User;

use Carbon\Carbon;
use App\Models\Gig;
use App\Models\User;
use App\Models\Schedule;
use App\Models\AssignGig;
use App\Models\TimeSheet;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Mail\AcceptGigMailToUser;
use App\Mail\AcceptGigMailToClient;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class UserGigController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:DSW|CSP']);
    }

    public function gigs()
    {
        $user = User::find(auth('api')->user()->id);
        
        if($user->hasRole('CSP')){
            // CSPs can view all gigs
            //$gigs = Gig::with(['schedule','client','creator.location'])->where(['status'=>'pending'])->limit(500)->latest()->get();
            $gigs = Gig::whereHas('client', function($query) {
                $query->where('status', '!=', 'inactive');
            })
            ->with(['schedule', 'client', 'creator.location'])
            ->where(['status' => 'pending'])
            ->limit(500)
            ->latest()
            ->get();

        }else{
            // Assuming the user's location is set and related to their model
            $locationId = $user->location->id;
            // Get gigs where the client's location matches the user's location
            /*$gigs = Gig::whereHas('creator', function($query) use ($locationId) {
                $query->where('location_id', $locationId);
            })->with(['schedule','client','creator.location'])->where(['status'=>'pending'])->limit(500)->latest()->get();*/
            $gigs = Gig::whereHas('creator', function($query) use ($locationId) {
                $query->where('location_id', $locationId);
            })
            ->whereHas('client', function($query) {
                $query->where('status', '!=', 'inactive');
            })
            ->with(['schedule', 'client', 'creator.location'])
            ->where(['status' => 'pending'])
            ->limit(500)
            ->latest()
            ->get();

        }
        if ($gigs->isEmpty()) {
            return response()->json(['status'=>200,'response'=>'Successful','message'=>'Shifts fetched successfully',"gigs"=>$gigs ], 200);
        }
        $formattedGigs = $gigs->map(function ($gig) {
            return [
                'id' => $gig->id,
                'title' => $gig->title,
                'description' => $gig->description,
                'type' => $gig->gig_type,
                'location' => $gig->creator->location ? $gig->creator->location->city : null,
                'dateCreated' => $gig->created_at,
            ];
        });
        ActivityLog::create([
            'action' => 'View All shifts',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all users at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($gigs),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Shifts fetched successfully","gigs"=>$formattedGigs],200);
    }

    public function gigs_zip()
    {
        $user = auth()->user();
        // Assuming the user's location is set and related to their model
        $zip_code = $user->zip_code;
        // Get gigs where the client's location matches the user's location
        $gigs = Gig::whereHas('client', function($query) use ($zip_code) {
            $query->where('zip_code', $zip_code);
        })->with(['schedule'])->get();
        if ($gigs->isEmpty()) {
            return response()->json(['status'=>200,'response'=>'Successful','message'=>'Shifts fetched successfully',"data"=>$gigs ], 200);
        }
        ActivityLog::create([
            'action' => 'View All shifts',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all users at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($gigs),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Shifts fetched successfully","data"=>$gigs],200);
    }

    public function gig(Request $request)
    {
        $user = User::find(auth('api')->user()->id); // Ensure you're using API authentication guard
        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.',
                'code' => 401
            ], 401);
        }

        if($user->hasRole('CSP')){
            $gig = Gig::where('id', $request->gig_id)
                    ->with(['schedule', 'client', 'assignments.assignee'])
                    ->first();
        } else {
            $userLocationId = $user->location_id;
            $gig = Gig::where('id', $request->gig_id)
                    ->whereHas('creator', function($query) use ($userLocationId) {
                        $query->where('location_id', $userLocationId);
                    })
                    ->with(['schedule', 'client', 'assignments.assignee'])
                    ->first();
        }

        if (!$gig) {
            return response()->json([
                'message' => 'Shift not found or access denied.',
                'code' => 404
            ], 404);
        }
        ActivityLog::create([
            'action' => 'View A shifts',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a shift at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Shift fetched successfully","data"=>$gig],200);
    }

    
    public function active_gigs(Request $request)
    {
        $user = User::find(auth('api')->user()->id); // Ensure you're using API authentication guard
        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.',
                'code' => 401
            ], 401);
        }

        if($user->hasRole('CSP')){
            $gig = AssignGig::where('user_id', $user->id)->with(['assignee','gig.client','gig.creator.location','schedule'])->get();
        }else{
            $gig = AssignGig::where('user_id', $user->id)->with(['assignee','gig.client','gig.creator.location','schedule'])->get();
        }

        if (!$gig) {
            return response()->json([
                'message' => 'Shift not found.',
                'code' => 404
            ], 404);
        }
        $formattedGigs = $gig->map(function ($gig) {
            return [
                'id' => $gig->gig->id,
                'title' => $gig->gig->title,
                'description' => $gig->gig->description,
                'type' => $gig->gig->gig_type,
                'location' => $gig->gig->creator->location ? $gig->gig->creator->location->city : null,
                'dateCreated' => $gig->gig->created_at,
            ];
        });
        ActivityLog::create([
            'action' => 'View Active Shifts',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed active Shift at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Assigned Shift(s) fetched successfully","gigs"=>$formattedGigs],200);
    }

    public function accept_gig(Request $request)
    {
        
        $user = User::find(auth('api')->user()->id); // Ensure you're using API authentication guard
        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.',
                'code' => 401
            ], 401);
        }
        $userLocationId = $user->location_id;

        /*$assigned = AssignGig::where('gig_id', $request->gig_id)->exists();
        if ($assigned) {
            return response()->json([
                'message' => 'Shift is already assigned to a user.',
                'code' => 400
            ], 400);
        }*/

        if ($user->hasRole('CSP')) {
            $gig = Gig::where('id', $request->gig_id)->with(['schedule','client'])->first();
        } else if ($user->hasRole('DSW') || $user->hasRole('Supervisor')) {
            $gig = Gig::where('id', $request->gig_id)
                ->whereHas('creator', function ($query) use ($userLocationId) {
                    $query->where('location_id', $userLocationId);
                })->with(['schedule', 'client'])->first();
        } else {
            return response()->json([
                'message' => 'User must be either DSW, CSP or Supervisor.',
                'code' => 400
            ], 400);
        }

        if (!$gig) {
            return response()->json([
                'message' => 'Shift not found or access denied.',
                'code' => 404
            ], 404);
        }
        
        if($gig->client->status === 'inactive'){
            return response()->json([
                'status' => 400,
                'response' => 'Client is inactive',
                'message' => 'Client for this shift is inactive.',
            ], 400);
        }

        $schedule = Schedule::where('id', $request->gig_id)->first();
        // Check if the Schedule was actually retrieved
        if ($schedule == null) {
            // Handling the case where the Schedule is not found
            return response()->json([
                'status' => 404,
                'message' => 'The requested Shift has no schedule.'
            ], 404);
        }
        $schedule_id = $schedule->id;

        // Decode the schedule JSON to check for conflicts
        $currentSchedule = json_decode($schedule->schedule, true);

        // Check if the user has already been assigned to a Shift with the same time shift
        $existingAssignments = AssignGig::where('user_id', $user->id)
            ->with('schedule')
            ->get();

        foreach ($existingAssignments as $assignment) {
            $existingSchedule = json_decode($assignment->schedule->schedule, true);
            foreach ($currentSchedule as $currentShift) {
                foreach ($existingSchedule as $existingShift) {
                    if ($currentShift['day'] == $existingShift['day'] &&
                        $currentShift['start_time'] == $existingShift['start_time'] &&
                        $currentShift['end_time'] == $existingShift['end_time']) {
                        return response()->json([
                            'status' => 409,
                            'response' => 'Conflict',
                            'message' => 'This schedule conflicts with an active schedule, please, select a shift with a different schedule.'
                        ], 409);
                    }
                }
            }
        }

        $assign_gig = AssignGig::create([
            'gig_id' => $request->gig_id,
            'user_id' => $user->id,
            'schedule_id' => $schedule_id,
        ]);

        $assigned_gig = AssignGig::where('id', $assign_gig->id)->with(['assignee' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'other_name', 'email', 'phone_number', 'location_id', 'gender', 'id_card', 'address1', 'address2', 'city', 'zip_code', 'dob', 'employee_id', 'points', 'email_verified_at', 'is_temporary_password', 'status', 'created_at', 'updated_at', 'deleted_at');
        }, 'gig', 'schedule'])->first();
        if (!$assigned_gig) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Assigned Shift not found'], 404);
        }
        $unid = $this->generateUniqueAlphanumeric();
        TimeSheet::create([
            'gig_id' => $request->gig_id,
            'user_id' => $user->id,
            'unique_id' => $unid,
            'status' => 'started'
        ]);
        Gig::where(['id'=>$request->gig_id])->update(['status' => 'accepted']);
        /*Mail::to($gig['client']->email)->send(new AcceptGigMailToClient($user, $gig['client'], $assigned_gig));
        Mail::to($user->email)->send(new AcceptGigMailToUser($user, $gig['client'], $assigned_gig));*/
        ActivityLog::create([
            'action' => 'Shift has been accepted by ' . $user->last_name . ' ' . $user->first_name,
            'description' => auth()->user()->last_name . ' ' . auth()->user()->first_name . ' accepted shift with ID ' . $request->gig_id . ' at ' . Carbon::now()->format('h:i:s A'),
            'subject_id' => $assign_gig->id,
            'subject_type' => get_class($assign_gig),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status' => 200, 'response' => 'Accepted shift', 'message' => 'shift accepted successfully', 'data' => [$assigned_gig]], 200);
    }


    public function decline_gig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_id' => ['required'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all], 422);
        }

        $assigned_gig = AssignGig::where(['gig_id' => $request->gig_id])->first();
        $gig = Gig::where(['id'=>$request->gig_id]);
        if(!$assigned_gig){
            return response()->json(['status' => 404, 'response' => 'Unprocessable Content', 'message' => 'Shift not assigned to user'], 404);
        }
        $timeSheet = TimeSheet::where(['gig_id' => $request->gig_id, 'user_id' => auth('api')->user()->id])->first();
        if(!$timeSheet){
            return response()->json(['status' => 404, 'response' => 'Unprocessable Content', 'message' => 'Shift not assigned to user'], 404);
        }
        if($timeSheet->activities != null || $gig->status == 'assigned'){
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'message' => 'You can not decline this shift'], 422);
        }
        $assigned_gig->delete();
        $timeSheet->delete();
        Gig::find($request->gig_id)->update(['status' => 'pending']);
        ActivityLog::create([
            'action' => 'Declined an Assigned shift',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' declined an assigned shift at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($assigned_gig),
            'subject_id' => $request->gig_id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful','message' => 'Assigned shift has been Declined successfully'],200);
    }

    public function generateUniqueAlphanumeric($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString . time(); // Append a timestamp to ensure uniqueness
    }
}
