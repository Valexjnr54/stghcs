<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\WeekLog;
use App\Models\AssignGig;
use App\Models\TimeSheet;
use App\Models\ProgressReport;

class ProgressReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:DSW|CSP|Supervisor']);
    }

    public function progress_report(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required'],
            'description' => ['required'],
            'progress_time' => ['required'],
            'gig_id' => ['required', 'exists:gigs,id'],
            /*'task_performed' => ['required', 'array'],
            'task_performed.*.name' => ['required', 'string'],
            'task_performed.*.time' => ['required', 'date_format:Y-m-d H:i:s'],*/
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

        // Create a new progress report with the validated data
        $progressReport = ProgressReport::create([
            'title' => $request->title,
            'description' => $request->description,
            'progress_time' => $request->progress_time,
            'progress_date' => Carbon::parse($request->progress_time)->format('m-d-Y'),
            'progress_week_number' => $now->weekOfYear,
            'progress_year' => Carbon::parse($request->progress_time)->format('Y'),
            'timesheet_id' => $timesheet->unique_id,
            'activity_id' => $lastActivityId,
            'gig_id' => $request->gig_id,
            'support_worker_id' => auth('api')->user()->id
            // 'task_performed' => $task_performed
        ]);
        WeekLog::create([
            'title'=> auth('api')->user()->last_name." ".auth('api')->user()->first_name." reported a progress",
            'week_number' => $now->weekOfYear,
            'year' => $now->year,
            'day' => $now->format('l'),
            'time' => $now->format('h:i A'),
            'user_id' => auth('api')->user()->id,
            'type' => "Progress Reported",
            'timesheet_id' => $timesheet->unique_id,
            'activity_id' => $lastActivityId
        ]);
        return response()->json(['status' => 200,'response' => 'Progress reported successfully','data' => $progressReport], 200);
    }
    
    public function all_reported_progress(Request $request)
    {
        $progressReport = ProgressReport::where([
            'support_worker_id' => auth('api')->user()->id
        ])->with(['gig.client','user'])->get();
        if ($progressReport->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Assigned shift(s) does not exist'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Progress reported successfully','data' => $progressReport], 200);
    }

    public function single_reported_progress(Request $request)
    {
        $progressReport = ProgressReport::where(['support_worker_id' => auth('api')->user()->id,'id'=>$request->progress_report_id])->with(['gig.client','user'])->first();
        // Check if the progress report was found
        if (!$progressReport) {
            return response()->json(['status' => 404,'response' => 'Not Found','message' => 'No matching progress report found'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Progress reported successfully','data' => $progressReport], 200);
    }
    
    public function update_progress_report(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required'],
            'description' => ['required'],
            'progress_time' => ['required'],
            'gig_id' => ['required', 'exists:gigs,id'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }
        
        try {
            $report = ProgressReport::findOrFail($request->progress_report_id);
            if($report->support_worker_id != auth('api')->user()->id)
            {
                return response()->json([
                    'status' => 400,
                    'response' => 'Invalid support worker',
                    'message' => 'This progress report does not belong to this user'
                ], 400);
            }
            
            if($report->gig_id != $request->gig_id)
            {
                return response()->json([
                    'status' => 400,
                    'response' => 'Invalid Gig',
                    'message' => 'Gig mismatch in the progress note.'
                ], 400);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Progress Note not found'], 404);
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
        
        // Dynamically build the data array based on the request inputs
        $data = [];
        
        if ($request->has('title')) {
            $data['title'] = $request->title;
        }
        
        if ($request->has('description')) {
            $data['description'] = $request->description;
        }
        
        if ($request->has('progress_time')) {
            $data['progress_time'] = $request->progress_time;
            $data['progress_date'] = Carbon::parse($request->progress_time)->format('m-d-Y');
            $data['progress_year'] = Carbon::parse($request->progress_time)->format('Y');
            $data['progress_week_number'] = $now->weekOfYear;
        }
        
        if (!empty($data)) {
            $report->update($data);
        }
        
        return response()->json(['status' => 200, 'response' => 'Progress note updated', 'message' => 'Progress note updated successfully', 'data' => $report], 200);
    }
}
