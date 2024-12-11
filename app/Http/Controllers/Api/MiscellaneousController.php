<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\WeekLog;
use App\Models\AssignGig;
use Illuminate\Http\Request;
use App\Models\IncidentReport;
use App\Models\TimeSheet;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\ActivitySheet;
use App\Models\ProgressReport;

class MiscellaneousController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function all_reported_incident(Request $request)
    {
        $incidentReport = IncidentReport::where([
            'user_id' => auth('api')->user()->id
        ])->with(['gig.client','user'])->get();
        if ($incidentReport->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Assigned Gig(s) does not exist'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Incident reported successfully','data' => $incidentReport], 200);
    }

    public function single_reported_incident(Request $request)
    {
        $incidentReport = IncidentReport::where(['id'=>$request->incident_report_id])->with(['gig.client','user'])->first();
        // Check if the incident report was found
        if (!$incidentReport) {
            return response()->json(['status' => 404,'response' => 'Not Found','message' => 'No matching incident report found'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Incident reported successfully','data' => $incidentReport], 200);
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
        $activitySheet = ActivitySheet::where(['id'=>$request->activity_sheet_id])->with(['gig.client','user'])->first();
        // Check if the progress report was found
        if (!$activitySheet) {
            return response()->json(['status' => 404,'response' => 'Not Found','message' => 'No matching activity sheet found'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Activity sheet fetch successfully','data' => $activitySheet], 200);
    }
    
    public function all_reported_progress(Request $request)
    {
        $progressReport = ProgressReport::where([
            'support_worker_id' => auth('api')->user()->id
        ])->with(['gig.client','user'])->get();
        if ($progressReport->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Assigned Gig(s) does not exist'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Progress reported successfully','data' => $progressReport], 200);
    }

    public function single_reported_progress(Request $request)
    {
        $progressReport = ProgressReport::where(['id'=>$request->progress_report_id])->with(['gig.client','user'])->first();
        // Check if the progress report was found
        if (!$progressReport) {
            return response()->json(['status' => 404,'response' => 'Not Found','message' => 'No matching progress report found'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Progress reported successfully','data' => $progressReport], 200);
    }
    
    public function all_sign_off(Request $request)
    {
        $signOff = WeeklySignOff::where([
            'support_worker_id' => auth('api')->user()->id
        ])->with(['gig.client','user'])->get();
        if ($signOff->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Assigned Gig(s) does not exist'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Sign Off(s) Fetched successfully','data' => $signOff], 200);
    }

    public function single_sign_off(Request $request)
    {
        $signOff = WeeklySignOff::where(['id'=>$request->sign_off_id])->with(['gig.client','user'])->first();
        // Check if the progress report was found
        if (!$signOff) {
            return response()->json(['status' => 404,'response' => 'Not Found','message' => 'No matching progress report found'], 404);
        }
        return response()->json(['status' => 200,'response' => 'Sign Off Fetched successfully','data' => $signOff], 200);
    }
}
