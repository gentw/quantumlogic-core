<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alarm_incoming_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alarm_id');
            $table->string('event_type', 50);
            $table->unsignedBigInteger('user_id')->nullable();

            $table->json('details')->nullable();

            // Foreign key constraints
            $table->foreign('alarm_id')->references('id')->on('alarm_incomings')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /*******************
     * Details json samples for every event_type:
     *
     * event_type: "Alarm Triggered"
        {
        "alarm_location": {
            "latitude": 40.7128,
            "longitude": -74.0060
        },
        "alarm_trigger_method": "motion_sensor",
        "triggered_at": "2024-11-02T12:00:00Z"
        }


        *** Event_type "Client Notified"

        {
        "notification_method": "push_notification",
        "sent_at": "2024-11-02T12:00:10Z",
        "notification_expiry": "2024-11-02T12:01:10Z"
        }


        *** Event_type: No Response Escalation

        {
        "escalation_type": "auto_escalation",
        "escalated_at": "2024-11-02T12:01:15Z",
        "base_notified": true
        }


        *** Event_type: Patrol Dispatched

        {
        "patrol_team_id": 5,
        "dispatched_at": "2024-11-02T12:05:00Z",
        "estimated_arrival": "2024-11-02T12:20:00Z",
        "patrol_vehicle": "Vehicle B-45",
        "dispatch_reason": "client_no_response"
        }


        * Event_type: Client Response (It's me)

        {
        "response_time": "2024-11-02T12:02:00Z",
        "client_location": {
            "latitude": 40.7129,
            "longitude": -74.0059
        },
        "response_note": "Accidental alarm, all clear."
        }
     */

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarm_incoming_logs');
    }
};
