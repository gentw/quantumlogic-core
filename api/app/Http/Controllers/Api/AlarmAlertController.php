<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\MessengerTrait;
use App\Models\AlarmIncomingLog;
use App\Models\IncomingAlarm;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Pusher\Pusher;

class AlarmAlertController extends Controller
{
    use MessengerTrait;

    protected $pusher;

    public function __construct()
    {
        $this->pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options', [])
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/alarm/{alarm}/respondToAlarm",
     *     summary="ClientSide: Respond to an alarm",
     *     description="Allows the client to respond to an alarm. If the client confirms they triggered the alarm, it is marked as resolved. If not, security is dispatched.",
     *     tags={"Alarms"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="alarm",
     *         in="path",
     *         description="ID of the alarm to respond to",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"response"},
     *
     *             @OA\Property(property="response", type="string", enum={"is_me", "not_me"}, description="Response to the alarm. 'is_me' if the client triggered it, 'not_me' if not.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="You're not authorized!")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Alarm not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Alarm not found.")
     *         )
     *     )
     * )
     */
    public function respondToAlarm(Request $request, IncomingAlarm $alarm)
    {
        $authUser = Auth::user()->id;
        $userId = $alarm->user_id;

        if ($authUser != $userId) {
            return response()->json("You're not authorized!");
        }

        $response = $request->input('response'); // 'safe' or 'not_me'

        if ($response == 'is_me') {
            $alarm->update(
                [
                    'status' => 'resolved',
                    'is_me' => 1,
                ]);

            AlarmIncomingLog::create([
                'alarm_id' => $alarm->id,
                'event_type' => 'resolved',
                'user_id' => $userId,
                'details' => json_encode([
                    'response' => 'is_me',
                    'description' => 'Klienti konfirmoi qe alarmi ishte aktivizuar nga vet ai.',
                ]),
            ]);
            $notifData = [];
            $this->pusher->trigger('agents_base_notified_for_no_response', 'NoResponseAlarmClient', $notifData);
        } elseif ($response == 'not_me') {
            $alarm->update([
                'status' => 'patrol_dispatched',
                'is_me' => 2,
                'base_notified' => 1,
            ]
            );

            AlarmIncomingLog::create([
                'alarm_id' => $alarm->id,
                'event_type' => 'patrol_dispatched',
                'user_id' => $userId,
                'details' => json_encode([
                    'response' => 'not_me',
                    'description' => 'Klienti raportoi qe alarmi nuk ishte aktivizuar nga ai.',
                ]),
            ]);

            $agents = User::where('role', 'agent')->get();

            foreach ($agents as $agent) {
                $this->addNotification($alarm->user_id, $agent->id, $alarm->id, null, 'alarm', 'Klienti '.$alarm->client->name.': (NUK JAM UNE) ndaj alarmit aktivizuar.');

                $alarmNotif = $this->fetchAlarmNotification($alarm->user_id, $agent->id, $alarm->id);

                $data = [
                    'user_id' => $alarm->user_id,
                    'user_name' => $alarm->client->name,
                ];

                $notifData = [
                    'notification_data' => $alarmNotif,
                    'user_name' => $data['user_name'],
                ];

                $this->pusher->trigger("notification.agent.{$agent->id}", 'Notification', $notifData);

                // $firebaseController = App::make(FirebaseController::class);
                // $firebaseController->notification("Klienti " . $alarm->client->name . ": (NUK JAM UNE) ndaj alarmit aktivizuar.", $alarm->user_id, $agent->id, $alarm->id, "alarm");
            }
            $this->pusher->trigger('agents_base_notified_for_no_response', 'NoResponseAlarmClient', $notifData);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fetchAlarms",
     *     summary="ClientSide: Fetch a list of alarms with optional filters",
     *     description="Retrieves a paginated list of alarms based on filters like search query, status, date range, and sorting options.",
     *     operationId="fetchAlarms",
     *     tags={"Alarms"},
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="sortBy",
     *         in="query",
     *         required=false,
     *         description="Field to sort by",
     *
     *         @OA\Schema(type="string", example="updated_at")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sortDesc",
     *         in="query",
     *         required=false,
     *         description="Sort order: true for descending, false for ascending",
     *
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *
     *     @OA\Parameter(
     *         name="rangePicker",
     *         in="query",
     *         required=false,
     *         description="Date range in the format 'YYYY-MM-DD to YYYY-MM-DD'",
     *
     *         @OA\Schema(type="string", example="2024-10-01 to 2024-10-31")
     *     ),
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter alarms by status (e.g., 'is_me' or other status values)",
     *
     *         @OA\Schema(type="string", example="is_me")
     *     ),
     *
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=false,
     *         description="Search query to filter alarms by description",
     *
     *         @OA\Schema(type="string", example="Alarm triggered")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfully fetched alarms",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="alarms", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="alarm_description", type="string", example="Unauthorized access detected"),
     *                 @OA\Property(property="is_me", type="string", example="safe"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-10-15T14:30:00Z"),
     *                 @OA\Property(property="date", type="string", example="15-10-2024"),
     *                 @OA\Property(property="time", type="string", example="14:30:00"),
     *             )),
     *             @OA\Property(property="total", type="integer", example=100)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function fetchAlarms(Request $request)
    {
        $perPage = $request['perPage'];
        $page = $request['page'];
        $sortBy = $request['sortBy'];
        $sortDesc = $request['sortDesc'];
        $rangePicker = $request['rangePicker'];
        $startDate = '';
        $endDate = '';
        $status = $request['status'];

        $q = $request['q'];

        $status = $request['status'];

        $q = $q ?? '';
        $status = $status ?? '';

        if (strlen($rangePicker)) {

            if (strpos($rangePicker, 'to') !== false) {
                $rangePicker = str_replace(' ', '', $rangePicker);
                $rangePicker = explode('to', $rangePicker);

                $startDate = $rangePicker[0];
                $endDate = $rangePicker[1];
            }
        }

        $alarms = IncomingAlarm::where('user_id', $request->user()->id)->where(function ($query) use ($q) {
            $query->whereRaw('alarm_description like ?', ['%'.$q.'%']);
        });
        if ($status != '') {
            $alarms->where('is_me', $status);
        }

        if (isset($rangePicker)) {
            $alarms->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('updated_at', [$startDate, $endDate]);
            });
        }

        $alarms = $alarms->orderBy($sortBy, $sortDesc ? 'desc' : 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        $formattedAlarms = $alarms->getCollection()->map(function ($alarm) {
            $alarm->date = Carbon::parse($alarm->updated_at)->format('d-m-Y');
            $alarm->time = Carbon::parse($alarm->updated_at)->format('H:i:s');

            return $alarm;
        });

        $alarms->setCollection($formattedAlarms);
        $total_rows = $alarms->total();
        $alarms = $alarms->items();

        return response()->json([
            'alarms' => $alarms,
            'total' => $total_rows,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/client/alarm/{alarm}/logs",
     *     summary="ClientSide: Fetch alarm logs for clients by Alarm ID",
     *     description="Retrieves all logs related to a specific alarm, intended for client users to review alarm activity.",
     *     operationId="fetchAlarmLogsForClient",
     *     tags={"Alarms"},
     *
     *     @OA\Parameter(
     *         name="alarm",
     *         in="path",
     *         required=true,
     *         description="ID of the alarm to fetch logs for",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfully fetched alarm logs",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="alarm_id", type="integer", example=1),
     *                 @OA\Property(property="event_type", type="string", example="resolved"),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2024-10-15T14:30:00Z"),
     *                 @OA\Property(property="details", type="object",
     *                     example={"response": "is_me", "description": "Klienti konfirmoi qe alarmi ishte aktivizuar nga vet ai."}
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Alarm not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function fetchAlarmLogsByAlarm(IncomingAlarm $alarm)
    {
        $logs = $alarm->alarm_logs;

        foreach ($logs as $log) {
            $log->alarm;
        }

        return response()->json($logs);
    }

    // For agents
    /**
     * @OA\Get(
     *     path="/api/v1/fetchAlarmsForAgents",
     *     summary="Agent side:: Fetch a list of alarms with optional filters",
     *     description="Retrieves a paginated list of alarms based on filters like search query, status, date range, and sorting options.",
     *     operationId="fetchAlarmsForAgents",
     *     tags={"Alarms"},
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="sortBy",
     *         in="query",
     *         required=false,
     *         description="Field to sort by",
     *
     *         @OA\Schema(type="string", example="updated_at")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sortDesc",
     *         in="query",
     *         required=false,
     *         description="Sort order: true for descending, false for ascending",
     *
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *
     *     @OA\Parameter(
     *         name="rangePicker",
     *         in="query",
     *         required=false,
     *         description="Date range in the format 'YYYY-MM-DD to YYYY-MM-DD'",
     *
     *         @OA\Schema(type="string", example="2024-10-01 to 2024-10-31")
     *     ),
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter alarms by status (e.g., 'is_me' or other status values)",
     *
     *         @OA\Schema(type="string", example="is_me")
     *     ),
     *
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=false,
     *         description="Search query to filter alarms by description",
     *
     *         @OA\Schema(type="string", example="Alarm triggered")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfully fetched alarms",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="alarms", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="alarm_description", type="string", example="Unauthorized access detected"),
     *                 @OA\Property(property="is_me", type="string", example="safe"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-10-15T14:30:00Z"),
     *                 @OA\Property(property="date", type="string", example="15-10-2024"),
     *                 @OA\Property(property="time", type="string", example="14:30:00"),
     *             )),
     *             @OA\Property(property="total", type="integer", example=100)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function fetchAlarmsForAgents(Request $request)
    {
        $perPage = $request['perPage'];
        $page = $request['page'];
        $sortBy = $request['sortBy'];
        $sortDesc = $request['sortDesc'];
        $rangePicker = $request['rangePicker'];
        $startDate = '';
        $endDate = '';
        $status = $request['status'];

        $q = $request['q'];

        $status = $request['status'];

        $q = $q ?? '';
        $status = $status ?? ['no_response', 'patrol_dispatched'];

        if (strlen($rangePicker)) {

            if (strpos($rangePicker, 'to') !== false) {
                $rangePicker = str_replace(' ', '', $rangePicker);
                $rangePicker = explode('to', $rangePicker);

                $startDate = $rangePicker[0];
                $endDate = $rangePicker[1];
            }
        }

        $alarms = IncomingAlarm::with('alarm_logs', 'client')->where(function ($query) use ($q) {
            $query->whereRaw('alarm_description like ?', ['%'.$q.'%']);
        });
        if (is_array($status)) {
            $alarms->whereIn('status', $status)
                ->where(function ($query) {
                    $query->where('is_me', 0)
                        ->orWhere('is_me', 2);
                });
        } else {
            $alarms->where('status', $status);
        }

        if (isset($rangePicker)) {
            $alarms->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('updated_at', [$startDate, $endDate]);
            });
        }

        $alarms = $alarms->orderBy($sortBy, $sortDesc ? 'desc' : 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        $formattedAlarms = $alarms->getCollection()->map(function ($alarm) {
            $alarm->date = Carbon::parse($alarm->updated_at)->format('d-m-Y');
            $alarm->time = Carbon::parse($alarm->updated_at)->format('H:i:s');

            return $alarm;
        });

        $alarms->setCollection($formattedAlarms);
        $total_rows = $alarms->total();
        $alarms = $alarms->items();

        return response()->json([
            'alarms' => $alarms,
            'total' => $total_rows,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/alarm/{alarm}/logs",
     *     summary="ClientSide: Fetch alarm logs for agents",
     *     description="Retrieves all logs related to a specific alarm, intended for agent users to review alarm activity.",
     *     operationId="fetchAlarmLogsForAgents",
     *     tags={"Alarms"},
     *
     *     @OA\Parameter(
     *         name="alarm",
     *         in="path",
     *         required=true,
     *         description="ID of the alarm to fetch logs for",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfully fetched alarm logs",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="alarm_id", type="integer", example=1),
     *                 @OA\Property(property="event_type", type="string", example="resolved"),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2024-10-15T14:30:00Z"),
     *                 @OA\Property(property="details", type="object",
     *                     example={"response": "is_me", "description": "Klienti konfirmoi qe alarmi ishte aktivizuar nga vet ai."}
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Alarm not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function fetchAlarmLogsForAgents(IncomingAlarm $alarm)
    {
        $logs = $alarm->alarm_logs;

        return response()->json($logs);
    }

    /**
     * @OA\Post(
     *     path="/alarm/{alarm}/changeStatusByAgent",
     *     summary="Change status of an alarm by agent",
     *     description="Allows an agent to update the status of a specific alarm based on the client's response.",
     *     operationId="changeStatusByAgent",
     *     tags={"Alarms"},
     *
     *     @OA\Parameter(
     *         name="alarm",
     *         in="path",
     *         required=true,
     *         description="ID of the alarm to change status for",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"response"},
     *
     *             @OA\Property(property="response", type="string",
     *                 enum={"resolved", "no_response", "patrol_dispatched", "pending"},
     *                 example="resolved",
     *                 description="The status to set for the alarm")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid response type provided",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="invalid_response_type")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Alarm not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function changeStatusByAgent(Request $request, IncomingAlarm $alarm)
    {
        $authUser = Auth::user()->id;
        $authUserName = Auth::user()->name;
        $userId = $alarm->user_id;

        $response = $request->input('response'); // 'safe' or 'not_me'

        if (! in_array($response, ['resolved', 'no_response', 'patrol_dispatched', 'pending'])) {
            return response()->json(['error' => 'invalid_response_type'], 400);
        }

        if ($response == 'resolved') {
            $alarm->update([
                'status' => 'resolved',
                'is_me' => 2,
            ]);

            AlarmIncomingLog::create([
                'alarm_id' => $alarm->id,
                'event_type' => 'resolved',
                'user_id' => $authUser,
                'details' => json_encode([
                    'description' => 'Agjenti morri informacion nga klienti qe alarmi ishte aktivizuar gabimisht nga klienti.',
                ]),
            ]);

        } elseif ($response == 'no_response') {
            $alarm->update([
                'status' => 'no_response',
                'is_me' => 2,
            ]);

            AlarmIncomingLog::create([
                'alarm_id' => $alarm->id,
                'event_type' => 'no_response',
                'user_id' => $authUser,
                'details' => json_encode([
                    'response' => "Eskalim... S'ka pergjigje nga klienti",
                    'base_notified' => true,
                ]),
            ]);
        } elseif ($response == 'patrol_dispatched') {
            $alarm->update(
                [
                    'status' => 'patrol_dispatched',
                    'is_me' => 2,
                ]);

            AlarmIncomingLog::create([
                'alarm_id' => $alarm->id,
                'event_type' => 'patrol_dispatched',
                'user_id' => $authUser,
                'details' => json_encode([
                    'response' => "Patrulla niset, pasi s'ka njoftim nga klienti.",
                ]),
            ]);

            $this->addGeneralNotification($authUser, $alarm->user_id, $alarm->id, null, 'alarm', "Patrulla u nis nga $authUserName për shkak të mungesës së përgjigjes ndaj alarmit: {$alarm->alarm_description}");

            $alarmNotif = $this->fetchAlarmNotification($authUser, $alarm->user_id, $alarm->id);

            $data = [
                'user_id' => $authUser,
                'user_name' => $authUserName,
            ];

            $notifData = [
                'notification_data' => $alarmNotif,
                'user_name' => $data['user_name'],
            ];

            $this->pusher->trigger("notification.client.{$alarm->user_id}", 'Notification', $notifData);

            // $firebaseController = App::make(FirebaseController::class);
            // $firebaseController->notification("Patrulla u nis nga $authUserName për shkak të mungesës së përgjigjes ndaj alarmit: {$alarm->alarm_description}", $authUser, $alarm->user_id, $alarm->id, "alarm");
        } elseif ($response == 'pending') {
            $alarm->update(['status' => 'pending']);

            AlarmIncomingLog::create([
                'alarm_id' => $alarm->id,
                'event_type' => 'pending',
                'user_id' => $authUser,
                'details' => json_encode([
                    'response' => 'Nje gabim ndodhi, perseri e kthyer => (ne Pritje)',
                ]),
            ]);
        }

        return response()->json(
            [
                'status' => 'success',
            ]
        );
    }
}
