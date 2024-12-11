<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\Gig;
use App\Models\GigType;
use App\Models\ActivityLog;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class GigTypeController extends Controller
{
    public function index()
    {
        $gig_types = GigType::all();
        if ($gig_types->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Gig type(s) does not exist'], 404);
        }
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Gig types fetched successfully","data"=>$gig_types],200);
    }
    public function show(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_type_id' => 'required',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }
        $gig_type = GigType::where('id', $request->gig_type_id)->first();
        if (!$gig_type) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Gig type does not exist'], 404);
        }
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Gig types fetched successfully","data"=>$gig_type],200);
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'shortcode' => 'required|string|max:255',
            'waiver_activities' => ['required'],
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $shortcode = Str::upper($request->shortcode);
        $waiver_activities = json_encode($request->waiver_activities);
        
        /*$plan_of_care_activities = $request->plan_of_care_activities;
        foreach ($request->details as $detail) {
            if (!in_array($detail['activity'], $plan_of_care_activities)) {
                return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => ['invalid_activity' => ["The activity {$detail['activity']} is not valid or does not exist in provided plan of care activities."]]], 422);
            }
        }
    
        // Extracting days from the schedule
        $detailedActivities = array_map(function ($item) {
            return $item['activity'];
        }, $request->details);
        $detailedActivities = array_unique($detailedActivities); // Remove duplicates to simplify comparison
    
        // Checking if all days are covered
        $missingActivities = array_diff($request->plan_of_care_activities, $detailedActivities);
        if (!empty($missingActivities)) {
            return response()->json([
                'status' => 422,
                'response' => 'Unprocessable Content',
                'errors' => ['days' => ['Not all Activities are covered in the detail. Missing: ' . implode(', ', $missingActivities)]]
            ], 422);
        }*/
        

        $gig_type = GigType::create([
            'title' => $request->title,
            'shortcode'=> $shortcode,
            'waiver_activities' => $waiver_activities
        ]);
        return response()->json(['status'=>201,'response'=>'Gig type Created','message'=>'Gig type created successfully','data'=>$gig_type], 201);
    }
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'shortcode' => 'required|string|max:255',
            'waiver_activities' => ['required'],
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $shortcode = Str::upper($request->shortcode);
        
        $gig_type = GigType::find($request->id);
        if (!$gig_type) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $gig_type->update([
            'title' => $request->title,
            'shortcode'=> $shortcode,
            'waiver_activities' => json_encode($request->waiver_activities)
        ]);
        return response()->json(['status'=>200,'response'=>'Gig type Updated','message'=>'Gig type updated successfully','data'=>$gig_type], 201);
    }
    public function destroy(Request $request)
    {
        $gig_type = GigType::find($request->id);
        if (!$gig_type) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $gig_type->delete();
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'Gig type Deleted successfully']);
    }
    public function poc_activities(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gig_type_id' => 'required',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }
        $gig_type = GigType::where('id', $request->gig_type_id)->select(['waiver_activities'])->first();
        if (!$gig_type) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Gig type does not exist'], 404);
        }
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Gig type activities fetched successfully","data"=>$gig_type],200);
    }
    public function add_extra_poc_activities(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'extra' => 'nullable|array' // Validate the new 'extra' data as an array
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'response' => 'Unprocessable Content', 'errors' => $validator->errors()->all()], 422);
        }
        
        // Find the GigType by id
        $gig_type = GigType::find($request->gig_type_id);
        if (!$gig_type) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Not Found!'], 404);
        }
    
        // Get the existing 'extra' field and decode it from JSON
        $existingExtra = json_decode($gig_type->extra, true) ?? [];
    
        // Merge the existing 'extra' with the new 'extra' from the request
        $newExtra = $request->input('extra', []);
        $mergedExtra = array_merge($existingExtra, $newExtra);
    
        // Update the GigType with the merged extra and other fields
        $gig_type->update([
            'extra' => json_encode($mergedExtra) // Encode merged extra back to JSON
        ]);
    
        // Return the response
        return response()->json(['status' => 200, 'response' => 'Gig type extra created', 'message' => 'Gig type extra created successfully', 'data' => $gig_type], 201);
    }

}
