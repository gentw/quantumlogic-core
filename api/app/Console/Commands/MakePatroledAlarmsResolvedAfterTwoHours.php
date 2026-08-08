<?php

namespace App\Console\Commands;

use App\Http\Traits\MessengerTrait;
use App\Models\AlarmIncomingLog;
use App\Models\IncomingAlarm;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Pusher\Pusher;

class MakePatroledAlarmsResolvedAfterTwoHours extends Command
{
    use MessengerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-patroled-alarms-resolved-after-two-hours';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make patroled alarms resolved after two hours';

    protected $pusher;

    public function __construct()
    {
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
        $patroledAlarms = IncomingAlarm::where('status', 'patrol_dispatched') // Adjust status as per your schema
            ->where('updated_at', '<', Carbon::now()->subHours(2))
            ->get();

        foreach ($patroledAlarms as $alarm) {
            $this->notifyClient($alarm);

            $alarm->update(
                [
                    'base_notified' => 1,
                    'status' => 'resolved',
                ]
            );

            AlarmIncomingLog::create([
                'alarm_id' => $alarm->id,
                'event_type' => 'resolved',
                'user_id' => null,
                'details' => json_encode([
                    'response' => 'Kane kaluar 2 ore pasi qe klientit i ishte caktuar te dergohet patrula per kete alarm, prandaj tani po mbyllet si status i zgjidhur nga vet sistemi.',
                    'base_notified' => true,
                ]),
            ]);
        }
    }

    // Method to notify the base (you might implement an event, notification, or other method here)
    protected function notifyClient(IncomingAlarm $alarm)
    {
        $agents = User::where('role', 'agent')->get();
        $client_name = $alarm->client->name;
        foreach ($agents as $agent) {
            $this->addGeneralNotification(
                $alarm->user_id,
                $agent->id,
                $alarm->id,
                null,
                'alarm',
                "Alarmi '$alarm->alarm_description' për klientin '$client_name' u mbyll si e ZGJIDHUR nga sistemi."
            );
            $alarmNotif = $this->fetchAlarmNotification($alarm->user_id, $agent->id, $alarm->id);

            $data = [
                'user_id' => $alarm->user_id,
                'user_name' => $alarm->client->name,
                'alarm_id' => $alarm->id,
            ];

            $notifData = [
                'notification_data' => $alarmNotif,
                'user_name' => $data['user_name'],
            ];

            $this->pusher->trigger("notification.agent.{$agent->id}", 'Notification', $notifData);
        }

        $this->addGeneralNotification(
            $agents[0]->id,
            $alarm->user_id,
            $alarm->id,
            null,
            'alarm',
            "Alarmi '$alarm->alarm_description' për juve u mbyll si e ZGJIDHUR nga sistemi."
        );
        $alarmNotif = $this->fetchAlarmNotification($agents[0], $alarm->user_id, $alarm->id);

        $data = [
            'user_id' => $agents[0]->id,
            'user_name' => $agents[0]->name,
            'alarm_id' => $alarm->id,
        ];

        $notifData = [
            'notification_data' => $alarmNotif,
            'user_name' => $data['user_name'],
        ];

        $this->pusher->trigger("notification.client.{$alarm->user_id}", 'Notification', $notifData);
    }
}
