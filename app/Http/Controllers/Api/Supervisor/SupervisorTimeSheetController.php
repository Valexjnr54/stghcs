<?php

namespace App\Http\Controllers\Api\Supervisor;

use Carbon\Carbon;
use App\Models\WeekLog;
use App\Models\Schedule;
use App\Models\TimeSheet;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ProgressReport;
use App\Models\ActivitySheet;
use App\Models\WeeklySignOff;
use App\Models\AssignGig;

class SupervisorTimeSheetController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware(['role:Supervisor']);
    }
    

public function weeklyLog(Request $request)
    {
        // Fetch week logs for a specific timesheet including time sheet and incident reports data
        $weekLogs = WeekLog::with(['timeSheet.user', 'incidentReports'])
            ->whereHas('timeSheet', function ($query) use ($request) {
                $query->where('unique_id', $request->timesheet_id);
            })
            ->orderBy('week_number', 'desc')
            ->get();
    
        // Default state
        $state = 'Clock in';
    
        // Check if there is at least one log and it has an associated timesheet
        $firstWeekLog = $weekLogs->first();
        if (is_null($firstWeekLog) || is_null($firstWeekLog->timeSheet)) {
            // Fetch the timesheet directly if not available in week logs
            $timeSheet = $firstWeekLog ? $firstWeekLog->timeSheet : TimeSheet::where('unique_id', $request->timesheet_id)->with('user')->first();
            
            // Check if timesheet is null
            if (is_null($timeSheet)) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Timesheet not found'
                ], 404);
            }
            
            // Check if user is null
            $user = $timeSheet->user;
            if (is_null($user)) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found'
                ], 404);
            }
    
            return response()->json([
                'status' => 200,
                'response' => 'Week Log not found',
                'message' => 'Weekly activity log',
                'state' => $state,
                'employee_name' => $user->first_name . ' ' . $user->last_name,
                'employee_title' => optional($user->roles->first())->name,
                'employee_image' => $user->passport,
                'employee_activities' => []
            ], 200);
        }
    
        // Get the user associated with the timeSheet
        $user = $firstWeekLog->timeSheet->user;
        if (is_null($user)) {
            return response()->json([
                'status' => 404,
                'message' => 'User not found'
            ], 404);
        }
    
        // Format the response according to the specified structure
        $formattedLogs = $weekLogs->groupBy('week_number')->map(function ($weekGroup) use (&$state, $request) {
    // Get the start and end dates of the current week
    $year = $weekGroup->first()->year;
    $weekNumber = $weekGroup->first()->week_number;
    $startDate = Carbon::now()->setISODate($year, $weekNumber)->startOfWeek();
    $endDate = Carbon::now()->setISODate($year, $weekNumber)->endOfWeek();

    // Grouping by day within each week to handle timesheet entries and incident reports correctly
    $days = $weekGroup->groupBy(function ($log) {
        return Carbon::parse($log->timeSheet->created_at)->format('m-d-Y');
    });

    // Mapping each day's data
    $activitiesByDay = $days->map(function ($dayGroup) use ($startDate, $endDate, $request, $weekNumber) {
        $dayActivities = [];

        foreach ($dayGroup as $log) {
            // Process time sheet entries
            if ($log->timeSheet && $log->timeSheet->activities) {
                $entries = json_decode($log->timeSheet->activities, true);
                foreach ($entries as $entry) {
                    $clockInDate = Carbon::parse($entry['clock_in']);
                    if ($clockInDate->between($startDate, $endDate)) {
                        $schedule = Schedule::where(['gig_id' => $log->timeSheet->gig_id])->first();
                        $scheduleArray = json_decode($schedule->schedule ?? '[]', true);
                        $day = $clockInDate->format('l');
                        $times = $this->getStartAndEndTime($scheduleArray, $day);
                        $entryKey = $entry['clock_in'] . '-' . $entry['clock_out'];
                        if (!isset($dayActivities[$entryKey])) { // Check if this entry already exists
                            $dayActivities[$entryKey] = [
                                'date' => $clockInDate->format('m-d-Y'),
                                'day' => $clockInDate->format('l'),
                                'activity_id' => $entry['activity_id'],
                                'clock_in' => $clockInDate->format('m-d-Y H:i:s'),
                                'clock_out' => $entry['clock_out'] ? Carbon::parse($entry['clock_out'])->format('m-d-Y H:i:s') : null,
                                'expected_clock_in_time' => isset($entry['emergency_clock_in']) && $entry['emergency_clock_in'] === true 
                                                            ? $clockInDate->format('H:i:s') 
                                                            : Carbon::parse($times['start_time'])->format('H:i:s'),
                                'expected_clock_out_time' => isset($entry['emergency_clock_in']) && $entry['emergency_clock_in'] === true 
                                                                ? ($entry['clock_out'] ? Carbon::parse($entry['clock_out'])->format('H:i:s') : null)
                                                                : Carbon::parse($times['end_time'])->format('H:i:s'),
                                'report' => [],
                                'progress' => $this->fetchProgressReports($request->timesheet_id, $entry['activity_id']),
                                'activity_sheet' => $this->fetchActivitySheets($request->timesheet_id, $entry['activity_id']),
                                'weekly_sign_off' => $this->fetchWeeklySignOff($request->timesheet_id, $weekNumber) // Now has access to $weekNumber
                            ];
                        }
                    }
                }
            }
        }

        // Process incident reports for the same date
        foreach ($dayGroup as $log) {
            if ($log->incidentReports && $log->incidentReports->isNotEmpty()) {
                foreach ($log->incidentReports as $report) {
                    $reportDate = Carbon::parse($report->created_at)->format('m-d-Y');
                    foreach ($dayActivities as &$activity) {
                        if ($reportDate == $activity['date']) { // Ensure report is for the correct day
                            $reportExists = false;
                            foreach ($activity['report'] as $existingReport) {
                                if ($existingReport['report_id'] == $report->id) {
                                    $reportExists = true;
                                    break;
                                }
                            }
                            if (!$reportExists) {
                                $activity['report'][] = [
                                    'report_id' => $report->id,
                                    'title' => $report->title,
                                    'description' => $report->description,
                                    'incident_time' => $report->incident_time,
                                    'created_at' => $report->created_at->format('m-d-Y H:i:s')
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        // Sort dayActivities by 'clock_in' in descending order (most recent first)
        usort($dayActivities, function ($a, $b) {
            return Carbon::createFromFormat('m-d-Y H:i:s', $b['clock_in'])->timestamp - Carbon::createFromFormat('m-d-Y H:i:s', $a['clock_in'])->timestamp;
        });

        return array_values($dayActivities); // Convert associative array back to indexed array
    });

    // Flatten the activities by day to a single level
    $flattenedActivities = $activitiesByDay->flatten(1);

    // If no activities exist for this week, skip this week's data
    if ($flattenedActivities->isEmpty()) {
        return null; // Return null to avoid adding empty weeks
    }

    // Determine the state based on the last entry
    $lastEntry = $flattenedActivities->last();
    if ($lastEntry) {
        $state = $lastEntry['clock_out'] ? 'Clock in' : 'Clock out';
    }

    return [
        'week' => $weekNumber,
        'year' => $year,
        'activities' => $flattenedActivities,
    ];
})->filter(); // Use filter to remove null values (empty weeks)

    
        // Get the last entry from the timesheet activities directly
        $timeSheet = TimeSheet::where('unique_id', $request->timesheet_id)->first();
        $lastEntry = null;
        if ($timeSheet && $timeSheet->activities) {
            $activities = json_decode($timeSheet->activities, true);
            $lastEntry = end($activities);
            $lastEntry = [
                'clock_in' => Carbon::parse($lastEntry['clock_in'])->format('m-d-Y H:i:s'),
                'clock_out' => $lastEntry['clock_out'] ? Carbon::parse($lastEntry['clock_out'])->format('m-d-Y H:i:s') : null,
            ];
        }
        
        $gig_id = $timeSheet->gig_id;
        $user_id  = $timeSheet->user_id;
        
        // Retrieve assign_gig
        $assignGig = AssignGig::with(['gig.client', 'schedule', 'assignee'])
            ->where(['user_id' => $user_id, 'gig_id' => $gig_id])
            ->first();
            
        $now = Carbon::now();
        $activity = ActivitySheet::where(['support_worker_id' => $user_id, 'gig_id' => $gig_id, 'activity_date' => $now->format('m-d-Y')])->exists();
        
        $progress_report = ProgressReport::where(['support_worker_id' => $user_id, 'gig_id' => $gig_id, 'progress_date' => $now->format('m-d-Y')])->exists();
            
        $schedule = $assignGig->schedule;
        $today = strtolower($now->format('l'));
        $scheduledDays = $this->getScheduledDaysForWeek($schedule);
        $isLastDay = false;
        if (in_array($today, $scheduledDays)) {
            $isLastDay = $this->isTodayLastScheduledDay($scheduledDays, $today);
        }
    
        return response()->json([
            'status' => 200,
            'message' => 'Weekly activity log',
            'state' => $lastEntry && is_null($lastEntry['clock_out']) ? 'Clock out' : 'Clock in',
            'has_activity_sheet' => $activity,
            'has_progress_note' => $progress_report,
            'is_last_day_of_the_week' => $isLastDay,
            'employee_name' => $user->first_name . ' ' . $user->last_name,
            'employee_title' => $user->roles->first()->name,
            'employee_image' => $user->passport,
            'employee_activities' => $formattedLogs->values()->all(), // Only non-empty weeks
            'last_entry' => $lastEntry
        ], 200);
}

    protected function isTodayLastScheduledDay(array $scheduledDays, $today)
    {
        $lastScheduledDay = end($scheduledDays);
        return $today === $lastScheduledDay;
    }

private function fetchProgressReports($timesheet_id, $activity_id)
{
    return ProgressReport::where('timesheet_id', $timesheet_id)
        ->where('activity_id', $activity_id)
        ->get()
        ->map(function ($progress) {
            return [
                'progress_id' => $progress->id,
                'title' => $progress->title,
                'description' => $progress->description,
                'progress_time' => $progress->progress_time,
                'created_at' => $progress->created_at->format('m-d-Y H:i:s')
            ];
        })
        ->toArray();
}

private function fetchWeeklySignOff($timesheet_id,$weekNumber)
{
    return WeeklySignOff::where(['timesheet_id'=> $timesheet_id, 'sign_off_week_number'=> $weekNumber])
        ->with(['gig','user'])
        ->get()
        ->map(function ($signoff) {
            return [
                'progress_id' => $signoff->id,
                'title' => $signoff->user->first_name.' '.$signoff->user->last_name.' submitted an activity report concerning '. $signoff->gig->client->first_name.' '.$signoff->gig->client->last_name,
                'client_condition' => $signoff->client_condition,
                'challenges' => $signoff->challenges,
                'services_not_provided' => $signoff->services_not_provided,
                'other_information' => $signoff->other_information,
                'support_worker_signature' => $signoff->support_worker_signature,
                'client_signature' => $signoff->client_signature,
                'sign_off_time' => $signoff->sign_off_time,
                'created_at' => $signoff->created_at->format('m-d-Y H:i:s')
            ];
        })
        ->toArray();
}

private function fetchActivitySheets($timesheet_id, $activity_id)
{
    return ActivitySheet::where('timesheet_id', $timesheet_id)
        ->where('activity_id', $activity_id)
        ->with(['gig','user','client'])
        ->get()
        ->map(function ($activity) {
            return [
                'activity_id' => $activity->id,
                'title' => $activity->user->first_name.' '.$activity->user->last_name.' submitted an activity report concerning '. $activity->client->first_name.' '.$activity->client->last_name,
                'activity' => $activity->activity_sheet,
                'activity_time' => $activity->activity_time,
                'created_at' => $activity->created_at->format('m-d-Y H:i:s')
            ];
        })
        ->toArray();
}

protected function getScheduledDaysForWeek($schedule)
    {
        // Assume the $schedule contains a list of scheduled days in a week
        // E.g. ['2024-09-11', '2024-09-12', '2024-09-14']
        return json_decode($schedule->days, true);
    }

    public function weeklyLogNumber(Request $request)
    {
        // Fetch week logs for a specific week number including time sheet and incident reports data
        $weekLogs = WeekLog::with(['timeSheet.user', 'incidentReports'])
            ->where(['week_number' => $request->week_number, 'year' => $request->year])
            ->whereHas('timeSheet', function ($query) use ($request) {
                $query->where('user_id', $request->user_id); // Filter by user ID
            })
            ->orderBy('week_number', 'desc')
            ->get();
    
        // Check if there are any logs for the specified user
        if ($weekLogs->isEmpty()) {
            return response()->json([
                'status' => 404,
                'message' => 'No logs found for the specified user'
            ], 404);
        }
    
        // Get the start and end dates of the specified week number
        $year = $request->year ?? Carbon::now()->year;
        $startDate = Carbon::now()->setISODate($year, $request->week_number)->startOfWeek();
        $endDate = Carbon::now()->setISODate($year, $request->week_number)->endOfWeek();
    
        // Group logs by user (there should be only one user since we filtered by user ID)
        $logsGroupedByUser = $weekLogs->groupBy(function ($log) {
            return $log->timeSheet->user->id;
        })->map(function ($userLogs) use ($startDate, $endDate) {
            // Get user information
            $user = $userLogs->first()->timeSheet->user;
    
            // Map user logs to desired format
            $userActivities = $userLogs->groupBy('week_number')->map(function ($weekGroup) use ($startDate, $endDate) {
                // Group by day within each week
                $days = $weekGroup->groupBy(function ($log) {
                    return Carbon::parse($log->timeSheet->created_at)->format('m-d-Y');
                });
    
                // Map each day's data
                $activitiesByDay = $days->map(function ($dayGroup) use ($startDate, $endDate) {
                    $dayActivities = [];
    
                    foreach ($dayGroup as $log) {
                        // Process time sheet entries
                        if ($log->timeSheet && $log->timeSheet->activities) {
                            $entries = json_decode($log->timeSheet->activities, true);
                            foreach ($entries as $entry) {
                                $clockInDate = Carbon::parse($entry['clock_in']);
                                if ($clockInDate->between($startDate, $endDate)) {
                                    $schedule = Schedule::where(['gig_id' => $log->timeSheet->gig_id])->first();
                                    $scheduleArray = json_decode($schedule->schedule, true);
                                    $day = $clockInDate->format('l');
                                    $times = $this->getStartAndEndTime($scheduleArray, $day);
                                    $entryKey = $entry['clock_in'] . '-' . $entry['clock_out'];
                                    if (!isset($dayActivities[$entryKey])) { // Check if this entry already exists
                                        $dayActivities[$entryKey] = [
                                            'date' => $clockInDate->format('m-d-Y'),
                                            'day' => $clockInDate->format('l'),
                                            'clock_in' => $clockInDate->format('m-d-Y H:i:s'),
                                            'clock_out' => $entry['clock_out'] ? Carbon::parse($entry['clock_out'])->format('m-d-Y H:i:s') : null,
                                            'expected_clock_in_time' => Carbon::parse($times['start_time'])->format('H:i:s'),
                                            'expected_clock_out_time' => Carbon::parse($times['end_time'])->format('H:i:s'),
                                            'report' => []
                                        ];
                                    }
                                }
                            }
                        }
                    }
    
                    // Process incident reports for the same date
                    foreach ($dayGroup as $log) {
                        if ($log->incidentReports && $log->incidentReports->isNotEmpty()) {
                            foreach ($log->incidentReports as $report) {
                                $reportDate = Carbon::parse($report->created_at)->format('m-d-Y');
                                foreach ($dayActivities as &$activity) {
                                    if ($reportDate == $activity['date']) { // Ensure report is for the correct day
                                        $reportExists = false;
                                        foreach ($activity['report'] as $existingReport) {
                                            if ($existingReport['report_id'] == $report->id) {
                                                $reportExists = true;
                                                break;
                                            }
                                        }
                                        if (!$reportExists) {
                                            $activity['report'][] = [
                                                'report_id' => $report->id,
                                                'title' => $report->title,
                                                'description' => $report->description,
                                                'incident_time' => $report->incident_time,
                                                'created_at' => $report->created_at->format('m-d-Y H:i:s')
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
    
                    return array_values($dayActivities); // Convert associative array back to indexed array
                });
    
                // Flatten the activities by day to a single level
                $flattenedActivities = $activitiesByDay->flatten(1);
    
                return [
                    'week' => $weekGroup->first()->week_number,
                    'year' => $weekGroup->first()->year,
                    'activities' => $flattenedActivities
                ];
            });
    
            return [
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'logs' => $userActivities->values()->all()
            ];
        })->first(); // Since there will be only one user, we can use first() to get the user data
    
        return response()->json([
            'status' => 200,
            'message' => 'Weekly activity log for specified user',
            'user' => $logsGroupedByUser
        ], 200);
    }

    private function getStartAndEndTime($scheduleArray, $day)
    {
        foreach ($scheduleArray as $schedule) {
            if (strtolower($schedule['day']) === strtolower($day)) {
                return [
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time']
                ];
            }
        }
        return null; // Return null if the day is not found
    }

    public function timesheet(Request $request)
    {
        // Get the authenticated user's location_id
        $userLocationId = auth('api')->user()->location_id;

        // Fetch all timesheets where the user has the same location_id
        $timesheets = TimeSheet::whereHas('user', function ($query) use ($userLocationId) {
            $query->where('location_id', $userLocationId);
        })->with(['gigs.client', 'gigs.schedule', 'user'])->orderBy('created_at','desc')->get();

        if ($timesheets->isEmpty()) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Time Sheet(s) does not exist'], 404);
        }

        $formattedGigs = $timesheets->map(function ($timesheet) {
            return [
                'id' => $timesheet->unique_id,
                'title' => $timesheet->gigs->title,
                'description' => $timesheet->gigs->description,
                'type' => $timesheet->gigs->gig_type,
                'client_address' => $timesheet->gigs->client ? $timesheet->gigs->client->address1 : null,
                'schedule' => $timesheet->gigs->schedule,
                'dateCreated' => $timesheet->gigs->created_at->format('m-d-Y'),
            ];
        });

        return response()->json(['status' => 200, 'response' => 'Time Sheet(s) fetch successfully', 'data' => $formattedGigs], 200);
    }

    public function single_timesheet(Request $request)
    {
        $timesheet = TimeSheet::where(['user_id' => auth('api')->user()->id, 'id' => $request->id])->with(['incidents_report', 'gigs.client', 'user'])->first();
        if (!$timesheet) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Time Sheet does not exist'], 404);
        }
        return response()->json(['status' => 200, 'response' => 'Time Sheet fetch successfully', 'data' => $timesheet], 200);
    }

    public function single_timesheet_by_uniqueID(Request $request)
    {
        $timesheet = TimeSheet::where(['unique_id' => $request->unique_id])->with(['gigs.client','gigs.schedule', 'user'])->first();
        if (!$timesheet) {
            return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Time Sheet does not exist'], 404);
        }
        return response()->json(['status' => 200, 'response' => 'Time Sheet fetch successfully', 'data' => $timesheet], 200);
    }
    
    public function getActivities()
    {
    // Get the authenticated user's location_id
    $userLocationId = auth('api')->user()->location_id;

    // Fetch all timesheets where the user has the same location_id
    $timesheets = TimeSheet::whereHas('user', function ($query) use ($userLocationId) {
        $query->where('location_id', $userLocationId);
    })->with(['gigs.client', 'gigs.schedule', 'user'])->orderBy('created_at', 'desc')->get();

    if ($timesheets->isEmpty()) {
        return response()->json(['status' => 404, 'response' => 'Not Found', 'message' => 'Time Sheet(s) does not exist'], 404);
    }

    // Group the timesheets by user and format the data
    $groupedTimesheets = $timesheets->groupBy('user.id')->map(function ($group) {
        $user = $group->first()->user;
        $activities = $group->map(function ($timesheet) {
            return [
                'timesheet_id' => $timesheet->unique_id,
                'activities' => json_decode($timesheet->activities),
                'activity_time' => $timesheet->updated_at
            ];
        });

        return [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'activities' => $activities
        ];
    });

    return response()->json(['status' => 200, 'response' => 'Time Sheet(s) fetched successfully', 'data' => $groupedTimesheets->values()], 200);
}
}
