<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\IncomingAlarm;
use App\Models\AlarmIncomingLog;
use App\Models\User;
use Carbon\Carbon;
use Pusher\Pusher;
use App\Http\Traits\MessengerTrait;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Api\FirebaseController;

class CheckNoResponseIncomingAlarms extends Command
{
    use MessengerTrait;

    protected $signature = 'alarms:check-noresponse';
    protected $description = 'Check for alarms where the client did not respond within 60 seconds';
    protected $pusher;

    public function __construct() {
        parent::__construct();
        $this->pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options', [])
        );
    }

    public function handle()
    {
        $timeThreshold = Carbon::now()->subSeconds(60);

        // Find alarms that are active and unresponded for more than 60 seconds
        $unrespondedAlarms = IncomingAlarm::where('status', 'pending') // Adjust status as per your schema
                                  ->where('created_at', '<=', $timeThreshold)
                                  ->get();

        foreach ($unrespondedAlarms as $alarm) {
            $this->notifyBase($alarm);

            $alarm->update(
                [
                    'base_notified' => 1,
                    'status'        => 'no_response'
                ]        
            );

            AlarmIncomingLog::create([
                'alarm_id' => $alarm->id,
                'event_type' => 'no_response',
                'user_id' => null,
                'details' => json_encode([
                    'response' => "Eskalim... S'ka pergjigje nga klienti per 1 minute pas aktivizimit alarmit. (Prej sistemit)",
                    'base_notified'   => true
                ]),
            ]);
        }
    }

    // Method to notify the base (you might implement an event, notification, or other method here)
    protected function notifyBase(IncomingAlarm $alarm)
    {
        $agents = User::where('role', 'agent')->get();
        
        foreach($agents as $agent) {
            $this->addNotification($alarm->user_id, $agent->id, $alarm->id, null, "alarm", "Klienti " . $alarm->client->name . " nuk u pergjigj ndaj Alarmit!");
                        
            $alarmNotif = $this->fetchAlarmNotification($alarm->user_id, $agent->id, $alarm->id);
            
            $data = [
                "user_id" => $alarm->user_id,
                'user_name' => $alarm->client->name,
            ];

            $notifData = [
                'notification_data' => $alarmNotif,
                'user_name'         => $data['user_name'],
            ];

            $this->pusher->trigger("notification.agent.{$agent->id}", 'Notification', $notifData);    
            
            // $firebaseController = App::make(FirebaseController::class);
            // $response = $firebaseController->notification("Klienti " . $alarm->client->name . " nuk u pergjigj ndaj Alarmit!", $alarm->client->id, $agent->id, $alarm->id, "alarm");

        }
        $this->pusher->trigger("agents_base_notified_for_no_response", 'NoResponseAlarmClient', $notifData);
    }
}
