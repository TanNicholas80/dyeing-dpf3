<?php

namespace App\Services;

use App\Models\Mesin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MesinCacheService
{
    private const SELECTION_LIST_KEY = 'mesin:selection_list:v1';
    private const JENIS_TO_ID_MAP_KEY = 'mesin:jenis_to_id:v1';

    /**
     * Cache master mesin untuk kebutuhan dropdown/filter dashboard.
     */
    public function getSelectionList(): Collection
    {
        return Cache::rememberForever(self::SELECTION_LIST_KEY, function () {
            return Mesin::query()
                ->select('id', 'jenis_mesin')
                ->orderBy('id')
                ->get();
        });
    }

    public function getSelectionListForJenis(?string $jenisMesin): Collection
    {
        if (blank($jenisMesin)) {
            return collect();
        }

        return $this->getSelectionList()
            ->where('jenis_mesin', $jenisMesin)
            ->values();
    }

    public function getIdByJenis(?string $jenisMesin): ?int
    {
        if (blank($jenisMesin)) {
            return null;
        }

        $map = Cache::rememberForever(self::JENIS_TO_ID_MAP_KEY, function () {
            return Mesin::query()
                ->orderBy('id')
                ->pluck('id', 'jenis_mesin')
                ->map(fn ($id) => (int) $id)
                ->all();
        });

        return $map[$jenisMesin] ?? null;
    }

    public function forgetAll(): void
    {
        Cache::forget(self::SELECTION_LIST_KEY);
        Cache::forget(self::JENIS_TO_ID_MAP_KEY);
    }
}
