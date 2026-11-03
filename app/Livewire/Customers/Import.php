<?php

namespace App\Livewire\Customers;

use App\Enums\ImportStatus;
use App\Jobs\ProcessCustomerImportJob;
use App\Models\ImportBatch;
use App\Services\ActivityLogger;
use App\Services\Import\CustomerImportService;
use App\Services\Import\ImportColumns;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Title('Importar clientes')]
class Import extends Component
{
    use WithFileUploads;

    public $file;

    #[Url(as: 'lote')]
    #[Locked]
    public ?int $batchId = null;

    public array $analysis = [];

    public function mount(): void
    {
        $this->authorize('customers.import');

        if ($this->batchId && ($batch = ImportBatch::find($this->batchId)) && $batch->status === ImportStatus::Validated) {
            $this->analysis = app(CustomerImportService::class)->analyze($batch);
        }
    }

    public function updatedFile(CustomerImportService $service): void
    {
        $this->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:xlsx,xls,csv,txt'],
        ], [], ['file' => 'archivo']);

        $extension = Str::lower($this->file->getClientOriginalExtension());
        $path = $this->file->storeAs('imports', now()->format('Ymd_His').'_'.Str::random(6).'.'.$extension, 'local');

        $batch = ImportBatch::create([
            'user_id' => auth()->id(),
            'original_filename' => Str::limit($this->file->getClientOriginalName(), 240, ''),
            'stored_path' => $path,
            'status' => ImportStatus::Uploaded,
        ]);

        $this->batchId = $batch->id;
        $this->reset('file');

        try {
            $this->analysis = $service->analyze($batch);
        } catch (Throwable $e) {
            report($e);
            $batch->update(['status' => ImportStatus::Failed, 'failure_message' => 'No fue posible leer el archivo: '.Str::limit($e->getMessage(), 300)]);
            $this->analysis = [];
            $this->dispatch('alert', type: 'error', title: 'Archivo no válido', message: 'No fue posible leer el archivo. Verifica que sea un Excel o CSV con la estructura de la plantilla.');
        }
    }

    public function import(CustomerImportService $service): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);
        abort_unless($batch->user_id === auth()->id() || auth()->user()->can('settings.manage'), 403);

        if ($batch->status !== ImportStatus::Validated || $batch->valid_rows === 0) {
            $this->dispatch('alert', type: 'warning', title: 'Nada que importar', message: 'El archivo no tiene filas válidas o ya fue procesado.');

            return;
        }

        ActivityLogger::log('import.started', "Inició la importación de “{$batch->original_filename}” ({$batch->valid_rows} filas válidas)", $batch);

        if ($batch->valid_rows > (int) config('crm.import_queue_threshold', 300)) {
            $batch->update(['status' => ImportStatus::Queued]);
            ProcessCustomerImportJob::dispatch($batch->id);
            $this->dispatch('toast', type: 'info', message: 'Importación enviada a la cola de procesamiento');
        } else {
            try {
                $service->process($batch->load('user'));
                $this->dispatch('toast', type: 'success', message: 'Importación completada');
            } catch (Throwable) {
                $this->dispatch('alert', type: 'error', title: 'Error en la importación', message: 'Ocurrió un error y la importación se detuvo. Revisa el detalle en el historial.');
            }
        }

        $this->analysis = [];
    }

    public function startOver(): void
    {
        $this->reset('file', 'batchId', 'analysis');
    }

    public function render()
    {
        $batch = $this->batchId ? ImportBatch::find($this->batchId) : null;

        return view('livewire.customers.import', [
            'batch' => $batch,
            'recent' => ImportBatch::with('user:id,name')->latest('id')->limit(8)->get(),
            'columns' => ImportColumns::all(),
            'previewErrors' => $batch && $batch->error_rows > 0 ? $batch->importErrors()->limit(25)->get() : collect(),
            'polling' => $batch && in_array($batch->status, [ImportStatus::Queued, ImportStatus::Processing], true),
        ]);
    }
}
