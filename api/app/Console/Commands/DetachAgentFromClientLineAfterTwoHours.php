<?php

namespace App\Console\Commands;

use App\Models\ChatAgentClientOnLine;
use Carbon\Carbon;
use Illuminate\Console\Command;

// ka mu bo detach mas 2 ore qe ka pauzu komunikimi
// updated_at mbet qaty ne 2 ore kur agjenti nuk kthen pergjigje per qato ore
// ka mu fshi prej listes ChatAgentClientOnLine qajo linje
// edhe mbet agent_id = null te chats
class DetachAgentFromClientLineAfterTwoHours extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:detach-agent-from-client-line-after-two-hours';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $oldRecords = ChatAgentClientOnLine::where('updated_at', '<', Carbon::now()->subHours(2))->get();

        foreach ($oldRecords as $record) {
            if ($record->chat) {
                $record->chat->delete();
                // $record->chat->agent_id = null;
                // $record->chat->save();
            }

            $record->delete();
        }
    }
}
