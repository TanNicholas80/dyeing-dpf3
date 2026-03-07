<?php

namespace App\Observers;

use App\Models\Approval;
use App\Services\ApprovalCacheService;

class ApprovalObserver
{
    public function created(Approval $approval): void
    {
        app(ApprovalCacheService::class)->forgetPendingCounts();
    }

    public function updated(Approval $approval): void
    {
        if ($approval->wasChanged('status')) {
            app(ApprovalCacheService::class)->forgetPendingCounts();
        }
    }

    public function deleted(Approval $approval): void
    {
        app(ApprovalCacheService::class)->forgetPendingCounts();
    }
}
