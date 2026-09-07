<?php

namespace App\Console\Commands;

use App\Models\AbsenDelegasi;
use App\Models\AbsenHistory;
use App\Services\AbsenService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AbsenAutoResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'absen:auto-reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Otomatis reset wewenang pendelegasian absen setelah shift berakhir';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $details = AbsenService::getCurrentShiftDetails();
        $this->info("Current Shift: {$details['shift']} (Production Date: {$details['production_date']})");

        $resetCount = AbsenService::checkAndPerformAutoReset();

        $this->info("Auto reset selesai. {$resetCount} record di-reset ke ON.");

        return Command::SUCCESS;
    }
}
