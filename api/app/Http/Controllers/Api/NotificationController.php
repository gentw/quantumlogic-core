<?php
namespace App\Http\Controllers\Api;
use Illuminate\Http\Request;
use App\Models\IncomingAlarm;
use App\Models\NotificationList;
use App\Models\NotificationReminder;
use App\Models\NotificationReminderGroup;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    //

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/fetch",
     *     summary="Fetch notifications for the authenticated user",
     *     tags={"Notifications"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Notifications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="notifications", type="array", description="List of notifications", 
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1, description="Notification ID"),
     *                     @OA\Property(property="content", type="string", example="You have a new message!", description="Notification content"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-10-30T12:45:30Z", description="Time when the notification was last updated"),
     *                     @OA\Property(property="time_ago", type="string", example="10 minuta me pare", description="Time since the notification was updated"),
     *                     @OA\Property(property="read", type="integer", example=0, description="Read status of the notification (0 = unread, 1 = read)")
     *                 )
     *             ),
     *             @OA\Property(property="total_notifications", type="integer", example=5, description="Total number of notifications"),
     *             @OA\Property(property="unread_notifications", type="integer", example=2, description="Total number of unread notifications")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No notifications found"
     *     )
     * )
     */
    public function fetchNotifications(Request $request) {
        $authUserId = Auth::user()->id;
        $notifications = NotificationList::with('alarm')->where('recipient_user_id', $authUserId)
            ->where('updated_at', '>=', Carbon::now()->subWeeks(2));

        $unreadNotifications = NotificationList::where('recipient_user_id', $authUserId)
            ->where('updated_at', '>=', Carbon::now()->subWeeks(2))
            ->where('read', 0)
            ->count();

        return response()->json([
            "notifications"         => $notifications->orderBy('created_at', 'desc')->get()->map(function ($notification) {
                $seconds = $notification->updated_at->diffInSeconds(Carbon::now());
                $minutes = $notification->updated_at->diffInMinutes(Carbon::now());
                $hours = $notification->updated_at->diffInHours(Carbon::now());
                $days = $notification->updated_at->diffInDays(Carbon::now());
                $weeks = $notification->updated_at->diffInWeeks(Carbon::now());
        
                if ($seconds < 60) {
                    $notification->time_ago = "{$seconds} sekonda me pare";
                } elseif ($minutes < 60) {
                    $notification->time_ago = "{$minutes} minuta me pare";
                } elseif ($hours < 24) {
                    $notification->time_ago = "{$hours} ore me pare";
                } elseif ($days < 7) {
                    $notification->time_ago = "{$days} dite me pare";
                } else {
                    $notification->time_ago = "{$weeks} jave me pare";
                }
        
                return $notification;
            }),
            'total_notifications'   => $notifications->count(),
            "unread_notifications"  => $unreadNotifications
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/read",
     *     summary="Mark a notification as read or unread",
     *     tags={"Notifications"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1, description="ID of the notification"),
     *             @OA\Property(property="type", type="string", enum={"read", "unread"}, example="read", description="Type of action to perform on the notification")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification updated successfully",
     *         @OA\JsonContent(
     *             type="string",
     *             example="success"
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request parameters"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Notification not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function readNotification(Request $request) {
        $type = $request['type'];

        if($type == 'read') {
            $read = NotificationList::where('id', $request['id'])->update([
                'read' => 1
            ]);
        } 

        if($type == 'unread') {
            $read = NotificationList::where('id', $request['id'])->update([
                'read' => 0
            ]);
        } 
        
        return response()->json("success");        
    }    

    public function createOrEditReminder(Request $request, $reminder_id = null) {
        $rules = [
            'subject' => 'required|string',
            'type' => 'required|string',
            'priority' => 'required|string',
            'deliverySchedule' => 'required|string',
        ];
    
        if ($request->system != 1) {
            $rules['recipient'] = 'required|array|min:1';
        }
    
        if ($request->deliverySchedule == 'me_vone') {
            $rules['execute_time'] = 'required|date';
        }
    
        $validator = Validator::make($request->all(), $rules);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ju lutem plotesoni gjitha fushat!',
                'errors' => $validator->errors()
            ], 422);
        }
    
        // For Editing: Fetch existing reminder
        $reminder = $reminder_id ? NotificationReminderGroup::find($reminder_id) : null;
    
        if ($reminder_id && !$reminder) {
            return response()->json([
                'message' => 'Reminder not found.'
            ], 404);
        }

        if(!$reminder) {
            $reminderGroup = NotificationReminderGroup::create(
                [
                    'subject' => $request->subject
                ]
            );
        }
    
        $recipients = $request->system == 1 ? [null] : $request->recipient;
        
        

        foreach ($recipients as $recipient) {
            $data = [
                'user_id' => Auth::id(),
                'subject' => $request->subject,
                'type' => $request->type,
                'priority' => $request->priority,
                'delivery_schedule' => $request->deliverySchedule,
                'system' => $request->system,
            ];
    
            if ($request->system == 0) {
                $data['recipient_id'] = $recipient;
            }
    
            if ($request->deliverySchedule == 'me_vone') {
                $data['execute_time'] = $request->execute_time;
            }
    
            if ($reminder) {
                // For Editing: Update the existing reminder
                $reminder->update($data);
            } else {
                $data['notif_reminder_group_id'] = $reminderGroup->id;
                // For Creating: Create a new reminder
                NotificationReminder::create($data);
            }
        }
    
        return response()->json([
            'status' => 'success',
            'message' => $reminder ? 'Reminder updated successfully.' : 'Reminder created successfully.'
        ], 200);
    }

    public function deleteReminder($reminder_id) {
        try {
            $reminder = Notification::findOrFail($reminder_id);
            $reminder->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Reminder deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete reminder.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function fetchReminder($reminder_id) {
        try {
            $reminder = Notification::findOrFail($reminder_id);
            return response()->json([
                'status' => 'success',
                'data' => $reminder
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Reminder not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

}
