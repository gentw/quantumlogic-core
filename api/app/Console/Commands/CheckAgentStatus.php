<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Jobs\AssignAgentToClient;

class CheckAgentStatus extends Command
{
    protected $signature = 'check:agent-status';
    protected $description = 'Check if any agents are free for chat and dispatch the job';

    public function handle()
    {
        $agents = User::where('is_busy', 0)->where('role', 'agent')->get();

        foreach ($agents as $agent) {
            AssignAgentToClient::dispatch($agent->id);
        }

        $this->info('Checked agent status and dispatched jobs for free agents.');

        sleep(1);
    }
}
