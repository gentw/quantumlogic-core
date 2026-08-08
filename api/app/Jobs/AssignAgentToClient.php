<?php

namespace App\Jobs;

use App\Models\AgentQueue;
use App\Models\ChatAgentClientOnLine;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AssignAgentToClient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $agentId;

    public function __construct($agentId)
    {
        $this->agentId = $agentId;
    }

    public function handle()
    {
        // Retrieve the next client in queue
        $queueEntry = AgentQueue::orderBy('created_at', 'asc')->first();

        try {
            if ($queueEntry) {
                // Check for an available agent
                $agent = User::where('id', $this->agentId)->where('is_busy', 0)->where('role', 'agent')->first();

                if ($agent) {
                    // Assign agent to the client
                    $agent->is_busy = 1;
                    $agent->save();

                    ChatAgentClientOnLine::create(
                        [
                            'client_id' => $queueEntry->client_id,
                            'agent_id' => $this->agentId,
                            'status' => 1,
                        ]
                    );
                    $queueEntry->delete();
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
