<?php

namespace App\Jobs;

use App\Models\TicketDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Exception;

class ImportTicketDetailCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes timeout for large CSV files
    public int $tries = 3;

    protected ?string $filePath;
    protected ?string $apiUrl;
    protected bool $deleteFileAfter;

    /**
     * Create a new job instance.
     *
     * @param string|null $filePath Local path to CSV file in storage or filesystem
     * @param string|null $apiUrl API URL to fetch CSV content from
     * @param bool $deleteFileAfter Whether to delete local file after import
     */
    public function __construct(?string $filePath = null, ?string $apiUrl = null, bool $deleteFileAfter = false)
    {
        $this->filePath = $filePath;
        $this->apiUrl = $apiUrl;
        $this->deleteFileAfter = $deleteFileAfter;
    }

    /**
     * Map of CSV header variations (SSMS or snake_case) to DB column names.
     */
    protected function getHeaderMap(): array
    {
        return [
            'id_no'          => 'id_no',
            'step_no'        => 'step_no',
            'product_code'   => 'product_code',
            'product_name'   => 'product_name',
            'productname'    => 'product_name',
            'product_type'   => 'product_type',
            'target_wt'      => 'target_wt',
            'actual_wt'      => 'actual_wt',
            'unit'           => 'unit',
            'comp_date'      => 'comp_date',
            'comp_time'      => 'comp_time',
            'transfer_state' => 'transfer_state',
            'error_code'     => 'error_code',
            'machine'        => 'machine',
            'tank_no'        => 'tank_no',
            'id_type'        => 'id_type',
            'product_lot'    => 'product_lot',
            'recipe_code'    => 'recipe_code',
            'lr'             => 'lr',
            'fabric_weight'  => 'fabric_weight',
            'volume'         => 'volume',
            'recipe_type'    => 'recipe_type',
            'conc'           => 'conc',
            'concunit'       => 'conc_unit',
            'conc_unit'      => 'conc_unit',
            'remark'         => 'remark',
            'adjust'         => 'adjust',
            'price'          => 'price',
            'res_double1'    => 'res_double1',
            'res_double2'    => 'res_double2',
            'res_double3'    => 'res_double3',
            'res_double4'    => 'res_double4',
            'res_string1'    => 'res_string1',
            'res_string2'    => 'res_string2',
            'res_string3'    => 'res_string3',
            'res_string4'    => 'res_string4',
            'reweight'       => 'reweight',
            'dyeweighttime'  => 'dye_weight_time',
            'dye_weight_time'=> 'dye_weight_time',
            'redye'          => 're_dye',
            're_dye'         => 're_dye',
            'usercode'       => 'user_code',
            'user_code'      => 'user_code',
            'useraccount'    => 'user_account',
            'user_account'   => 'user_account',
            'batch_no'       => 'batch_no',
            'record_order'   => 'record_order',
            'station'        => 'station',
            'process'        => 'process',
            'gravity'        => 'gravity',
            'currentstock'   => 'current_stock',
            'current_stock'  => 'current_stock',
        ];
    }

    /**
     * List of numeric columns that need comma-to-dot conversion.
     */
    protected function getNumericColumns(): array
    {
        return [
            'target_wt', 'actual_wt', 'lr', 'fabric_weight', 'volume',
            'conc', 'price', 'res_double1', 'res_double2', 'res_double3',
            'res_double4', 'gravity', 'current_stock', 'step_no', 'record_order'
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting ImportTicketDetailCsvJob processing...");

        $handle = null;
        $tempPath = null;

        $targetFile = null;
        if (!empty($this->filePath)) {
            if (file_exists($this->filePath)) {
                $targetFile = $this->filePath;
            } elseif (Storage::disk('local')->exists($this->filePath)) {
                $targetFile = Storage::disk('local')->path($this->filePath);
            } elseif (file_exists(storage_path('app/private/' . $this->filePath))) {
                $targetFile = storage_path('app/private/' . $this->filePath);
            } elseif (file_exists(storage_path('app/' . $this->filePath))) {
                $targetFile = storage_path('app/' . $this->filePath);
            }
        }

        try {
            if (!empty($this->apiUrl)) {
                Log::info("Fetching CSV data from API URL: {$this->apiUrl}");
                $response = Http::timeout(120)->get($this->apiUrl);

                if (!$response->successful()) {
                    throw new Exception("Failed to fetch CSV from API. Status: " . $response->status());
                }

                $tempPath = storage_path('app/temp_import_' . uniqid() . '.csv');
                file_put_contents($tempPath, $response->body());
                $handle = fopen($tempPath, 'r');
            } elseif (!empty($targetFile) && file_exists($targetFile)) {
                $handle = fopen($targetFile, 'r');
            } else {
                throw new Exception("Invalid file path or API URL provided to ImportTicketDetailCsvJob. Target path: " . ($this->filePath ?? 'null'));
            }

            if (!$handle) {
                throw new Exception("Could not open CSV file stream.");
            }

            $headerMap = $this->getHeaderMap();
            $numericColumns = $this->getNumericColumns();
            $headers = [];
            $now = now()->toDateTimeString();

            // Read CSV Header
            if (($rawHeader = fgetcsv($handle, 0, ",")) !== false) {
                foreach ($rawHeader as $index => $colName) {
                    $cleanName = strtolower(trim(str_replace(["\xEF\xBB\xBF", '"', "'", "[", "]"], '', $colName)));
                    $headers[$index] = $headerMap[$cleanName] ?? $cleanName;
                }
            }

            // BEST PRACTICE 1: LazyCollection Generator (Line-by-line reading via yield)
            $lazyCollection = LazyCollection::make(function () use ($handle, $headers, $numericColumns) {
                while (($row = fgetcsv($handle, 0, ",")) !== false) {
                    if (count($row) === 1 && empty($row[0])) {
                        continue; // Skip empty rows
                    }

                    $record = [];
                    foreach ($headers as $index => $dbColumn) {
                        $val = isset($row[$index]) ? trim($row[$index]) : null;
                        if ($val === '' || $val === 'NULL' || $val === 'null' || $val === '?') {
                            $val = null;
                        } elseif (in_array($dbColumn, $numericColumns) && $val !== null) {
                            // Convert Indonesian/European comma decimal (e.g. "1,00000") to dot ("1.00000")
                            $val = str_replace(',', '.', $val);
                            if (!is_numeric($val)) {
                                $val = null;
                            }
                        }
                        $record[$dbColumn] = $val;
                    }

                    // Strict Validation: Skip record if any of unique constraint columns is null/empty
                    if (
                        empty($record['id_no']) ||
                        is_null($record['step_no']) || $record['step_no'] === '' ||
                        empty($record['product_code'])
                    ) {
                        Log::warning("Skipping invalid CSV record in ImportTicketDetailCsvJob: missing required unique identifier (id_no, step_no, or product_code)", [
                            'id_no' => $record['id_no'] ?? null,
                            'step_no' => $record['step_no'] ?? null,
                            'product_code' => $record['product_code'] ?? null,
                        ]);
                        continue;
                    }

                    yield $record;
                }
            });

            // Columns to update during bulk upsert (all columns except id and created_at)
            $columnsToUpdate = array_values(array_unique(array_filter($headers, fn($col) => $col !== 'created_at' && $col !== 'id')));

            $totalImported = 0;

            // BEST PRACTICE 2: Process in Chunks of 1000 & Execute Database Upsert (Bulk Action)
            $lazyCollection->chunk(1000)->each(function ($chunk) use (&$totalImported, $columnsToUpdate, $now) {
                $batchRecords = $chunk->map(function ($record) use ($now) {
                    $record['created_at'] = $now;
                    $record['updated_at'] = $now;
                    return $record;
                })->values()->toArray();

                if (!empty($batchRecords)) {
                    // Bulk Upsert using composite unique constraint: id_no + step_no + product_code
                    TicketDetail::upsert($batchRecords, ['id_no', 'step_no', 'product_code'], $columnsToUpdate);

                    $totalImported += count($batchRecords);
                }
            });

            fclose($handle);
            $handle = null;

            Log::info("ImportTicketDetailCsvJob completed successfully! Total processed/upserted rows: {$totalImported}");

        } catch (Exception $e) {
            Log::error("ImportTicketDetailCsvJob failed: " . $e->getMessage(), [
                'exception' => $e
            ]);
            if ($handle) {
                fclose($handle);
            }
            throw $e;
        } finally {
            // Clean up temporary files
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
            if ($this->deleteFileAfter) {
                if (!empty($targetFile) && file_exists($targetFile)) {
                    @unlink($targetFile);
                } elseif (!empty($this->filePath) && Storage::disk('local')->exists($this->filePath)) {
                    Storage::disk('local')->delete($this->filePath);
                }
            }
        }
    }
}
