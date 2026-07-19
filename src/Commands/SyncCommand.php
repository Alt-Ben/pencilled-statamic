<?php

namespace Pencilled\Statamic\Commands;

use Illuminate\Console\Command;
use Pencilled\Statamic\EventSync;
use Statamic\Console\RunsInPlease;

class SyncCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'pencilled:sync';

    protected $description = 'Sync events from the Pencilled booking platform into Statamic';

    public function handle(EventSync $sync): int
    {
        $this->components->info('Syncing events from Pencilled…');

        try {
            $result = $sync->run();
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Created', (string) $result['created']);
        $this->components->twoColumnDetail('Updated', (string) $result['updated']);
        $this->components->twoColumnDetail('Unchanged', (string) $result['unchanged']);
        $this->components->twoColumnDetail('Unpublished', (string) $result['unpublished']);

        return self::SUCCESS;
    }
}
