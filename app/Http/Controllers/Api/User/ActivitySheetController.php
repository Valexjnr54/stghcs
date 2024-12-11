<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\WeekLog;
use App\Models\AssignGig;
use App\Models\TimeSheet;
use App\Models\ActivitySheet;

class ActivitySheetController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:DSW|CSP|Supervisor']);
    }

    public function activity_sheet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_id' => ['required', 'exists:gigs,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'activity' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $assigned_gig = AssignGig::where(['gig_id' => $request->gig_id, 'user_id' => auth('api')->user()->id])->first();
        if(!$assigned_gig)
        {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'The shift is not assigned to the user'
            ], 404);
        }
        $timesheet = Timesheet::where(['gig_id' => $request->gig_id, 'user_id' => auth('api')->user()->id])->first();
        if(!$timesheet)
        {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'The shift has not timesheet'
            ], 404);
        }
        // Current date and time
        $now = Carbon::now();
        
        // Decode the activities JSON data
        $timesheet_activities = json_decode($timesheet->activities, true);
    
        // Initialize variable for the last activity ID with null clock_out
        $lastActivityId = null;
        $collection = collect($timesheet_activities);
        $timesheet_activity = $collection->last();
    
        if (is_null($timesheet_activity['clock_out'])) {
            $lastActivityId = $timesheet_activity['activity_id'];
        }else{
            return response()->json([
                'status' => 400,
                'response' => 'Bad Request',
                'message' => 'No Active Shift Found'
            ], 404);
        }
        
        // $task_performed = json_encode($request->task_performed);

        // Create a new activity report with the validated data
        $activitySheet = ActivitySheet::create([
            'activity_sheet' => json_encode($request->activity),
            'activity_time' => $now,
            'activity_date' => Carbon::parse($now)->format('m-d-Y'),
            'activity_day' => Carbon::parse($now)->format('l'),
            'activity_week_number' => $now->weekOfYear,
            'activity_year' => Carbon::parse($now)->format('Y'),
            'timesheet_id' => $timesheet->unique_id,
            'activity_id' => $lastActivityId,
            'gig_id' => $request->gig_id,
            'client_id' => $request->client_id,
            'support_worker_id' => auth('api')->user()->id
            // 'task_performed' => $task_performed
        ]);
        WeekLog::create([
            'title'=> auth('api')->user()->last_name." ".auth('api')->user()->first_name." logged an activity sheet.",
            'week_number' => $now->weekOfYear,
            'year' => $now->year,
            'day' => $now->format('l'),
            'time' => $now->format('h:i A'),
            'user_id' => auth('api')->user()->id,
            'type' => "Activity Logged",
            'timesheet_id' => $timesheet->unique_id,
            'activity_id' => $lastActivityId
        ]);
        return response()->json(['status' => 200,'response' => 'Activity logged successfully','data' => $activitySheet], 200);
    }
    
    public function all_activity_sheet(Request $request)
    {
        $activitySheet = ActivitySheet::where([
            'support_worker_id' => auth('api')->user()->id
        ])->with(['gig.client','user'])->get();
        
        
        return response()->json(['status' => 200,'response' => 'Activity Sheet Fetched successfully','data' => $activitySheet], 200);
    }

    public function single_activity_sheet(Request $request)
    {
        $activitySheet = ActivitySheet::where(['support_worker_id' => auth('api')->user()->id,'id'=>$request->activity_sheet_id])->with(['gig.client','user'])->first();
        // Check if the progress report was found
        if (!$activitySheet) {
            return response()->json(['status' => 404,'response' => 'Not Found','message' => 'No matching activity sheet found'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Activity sheet fetch successfully','data' => $activitySheet], 200);
    }
}
