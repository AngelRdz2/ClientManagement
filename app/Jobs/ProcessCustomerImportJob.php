<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Services\Import\CustomerImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessCustomerImportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $batchId) {}

    public function handle(CustomerImportService $service): void
    {
        $batch = ImportBatch::with('user')->find($this->batchId);

        if ($batch && in_array($batch->status, [ImportStatus::Queued, ImportStatus::Validated], true)) {
            $service->process($batch);
        }
    }

    public function failed(?Throwable $e): void
    {
        ImportBatch::whereKey($this->batchId)
            ->where('status', '!=', ImportStatus::Completed)
            ->update(['status' => ImportStatus::Failed, 'failure_message' => mb_substr((string) $e?->getMessage(), 0, 900)]);
    }
}
