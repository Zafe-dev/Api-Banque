<?php

namespace App\Console\Commands;

use App\Jobs\ArchiveBlockedAccounts;
use App\Jobs\RestoreExpiredAccounts;
use Illuminate\Console\Command;

class RunArchiveJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'archive:run {--type=all : Type of job to run (archive, restore, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run archive and restore jobs for blocked accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');

        $this->info('Starting archive jobs...');

        if ($type === 'all' || $type === 'archive') {
            $this->info('Dispatching ArchiveBlockedAccounts job...');
            ArchiveBlockedAccounts::dispatch();
            $this->info('✅ ArchiveBlockedAccounts job dispatched');
        }

        if ($type === 'all' || $type === 'restore') {
            $this->info('Dispatching RestoreExpiredAccounts job...');
            RestoreExpiredAccounts::dispatch();
            $this->info('✅ RestoreExpiredAccounts job dispatched');
        }

        $this->info('All requested jobs have been dispatched successfully!');
        $this->info('Use `php artisan queue:work` to process the jobs.');
    }
}
