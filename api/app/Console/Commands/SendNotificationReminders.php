<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotificationReminder;
use Carbon\Carbon;
use Pusher\Pusher;
use App\Http\Traits\MessengerTrait;
use App\Models\User;

class SendNotificationReminders extends Command
{

    use MessengerTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-notification-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notification reminders';
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
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $reminders = NotificationReminder::where('execute_time', '<=', Carbon::now())->orWhere('delivery_schedule', 'menjehere')->get();

        foreach($reminders as $reminder) {
            try {
                if($reminder->system == 1) {
                    $recipients = User::whereIn('role', ['agent', 'admin'])->get();

                    foreach($recipients as $recipient) {
                        $this->addGeneralNotification(
                            $reminder->user_id,
                            $recipient->id,
                            null,
                            null,
                            $reminder->type,
                            $reminder->subject
                        );            
                        $notif = $this->fetchNotification($reminder->user_id, $recipient->id, $reminder->type);
                        
                        $data = [
                            "user_id" => $recipient->id,
                            'user_name' => $recipient->name
                        ];
                
                        $notifData = [
                            'notification_data' => $notif,
                            'user_name'         => $data['user_name'],
                        ];
                
                        $this->pusher->trigger("notification.{$recipient->role}.{$recipient->id}", 'Notification', $notifData);
                    }
                } else {
                    $this->addGeneralNotification(
                        $reminder->user_id,
                        $reminder->recipient_id,
                        null,
                        null,
                        $reminder->type,
                        $reminder->subject
                    );            
                    $notif = $this->fetchNotification($reminder->user_id, $reminder->recipient_id, $reminder->type);
                    
                    $data = [
                        "user_id" => $reminder->recipient_id,
                        'user_name' => $reminder->recipient->name
                    ];
            
                    $notifData = [
                        'notification_data' => $notif,
                        'user_name'         => $data['user_name'],
                    ];
            
                    $this->pusher->trigger("notification.{$reminder->recipient->role}.{$reminder->recipient_id}", 'Notification', $notifData);
                }

                $reminder->delete();
            } catch (\Exception $e) {
                $this->error("Error processing reminder ID {$reminder->id}: {$e->getMessage()}");
            }
        }
    }
}
