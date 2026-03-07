<?php

namespace App\Services;

use App\Models\Approval;
use Illuminate\Support\Facades\Cache;

class ApprovalCacheService
{
    private const PENDING_COUNTS_KEY = 'approval:pending_counts:v1';

    private const TTL_SECONDS = 60;

    /**
     * Ambil jumlah pending approval per type (FM, VP, KEPALA_SHIFT).
     * Dipakai di sidebar layout untuk badge count.
     *
     * @return array{ pendingApprovalFM: int, pendingApprovalVP: int, pendingApprovalKepalaShift: int }
     */
    public function getPendingCounts(): array
    {
        return Cache::remember(self::PENDING_COUNTS_KEY, self::TTL_SECONDS, function () {
            return [
                'pendingApprovalFM' => Approval::where('type', 'FM')
                    ->where('status', 'pending')
                    ->count(),
                'pendingApprovalVP' => Approval::where('type', 'VP')
                    ->where('status', 'pending')
                    ->count(),
                'pendingApprovalKepalaShift' => Approval::where('type', 'KEPALA_SHIFT')
                    ->where('status', 'pending')
                    ->count(),
            ];
        });
    }

    public function forgetPendingCounts(): void
    {
        Cache::forget(self::PENDING_COUNTS_KEY);
    }
}
