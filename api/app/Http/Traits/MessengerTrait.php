<?php

namespace App\Http\Traits;

use App\Models\NotificationList;

trait MessengerTrait
{
    public function addNotification($user_id, $recipient_user_id, $alarm_alert_id, $chat_id, $type, $message)
    {
        $notification = NotificationList::updateOrCreate([
            'user_id' => $user_id,
            'chat_id' => $chat_id,
            'recipient_user_id' => $recipient_user_id,
        ],
            [
                'alarm_alert_id' => $alarm_alert_id,
                'type' => $type,
                'message' => $message,
            ]
        );

        return $notification;
    }

    public function addGeneralNotification($user_id, $recipient_user_id, $alarm_alert_id, $chat_id, $type, $message)
    {
        $notification = NotificationList::create([
            'user_id' => $user_id,
            'chat_id' => $chat_id,
            'recipient_user_id' => $recipient_user_id,
            'alarm_alert_id' => $alarm_alert_id,
            'type' => $type,
            'message' => $message,
        ]
        );

        return $notification;
    }

    public function fetchChatNotification($user_id, $recipient_user_id, $chat_id = null)
    {
        if ($chat_id != null) {
            $notification = NotificationList::where('user_id', $user_id)
                ->where('recipient_user_id', $recipient_user_id)
                ->where('chat_id', $chat_id)
                ->where('type', 'chat')
                ->first();
        } else {
            $notification = NotificationList::where('user_id', $user_id)
                ->where('recipient_user_id', $recipient_user_id)
                ->whereNull('chat_id')
                ->where('type', 'chat')
                ->first();
        }

        return $notification;
    }

    public function fetchAlarmNotification($user_id, $recipient_user_id, $alarm_id)
    {

        $notification = NotificationList::where('user_id', $user_id)
            ->where('recipient_user_id', $recipient_user_id)
            ->where('alarm_alert_id', $alarm_id)
            ->where('type', 'alarm')
            ->first();

        return $notification;
    }

    public function fetchNotification($user_id, $recipient_user_id, $type)
    {

        $notification = NotificationList::where('user_id', $user_id)
            ->where('recipient_user_id', $recipient_user_id)
            ->where('type', $type)
            ->latest()
            ->first();

        return $notification;
    }
}
