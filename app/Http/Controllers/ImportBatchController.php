<?php

namespace App\Http\Controllers;

use App\AuditLogger;
use App\GoogleDriveStorage;
use App\Http\Requests\StoreImportBatchRequest;
use App\Jobs\ProcessImportBatch;
use App\Models\ImportBatch;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportBatchController extends Controller
{
    public function index(GoogleDriveStorage $googleDriveStorage): View
    {
        return view('imports.index', [
            'batches' => ImportBatch::query()->with('uploader')->latest()->paginate(15),
            'googleDriveConnected' => $googleDriveStorage->isConnected(),
            'prioritySnapshotTime' => Setting::query()->where('key', 'priority.snapshot_time')->value('value') ?: config('lkpm.priority_snapshot_time'),
        ]);
    }

    public function store(StoreImportBatchRequest $request, GoogleDriveStorage $googleDriveStorage): RedirectResponse|JsonResponse
    {
        if (! $googleDriveStorage->isConnected()) {
            return back()->withErrors(['file' => 'Hubungkan Google Drive sebelum mengunggah file.']);
        }

        $file = $request->file('file');
        $checksum = hash_file('sha256', $file->getRealPath());
        $existingBatch = ImportBatch::query()
            ->where('source_type', $request->string('source_type')->toString())
            ->where('checksum', $checksum)
            ->whereIn('status', ['uploaded', 'processing', 'ready'])
            ->latest('id')
            ->first();

        if ($existingBatch !== null) {
            return back()->withErrors(['file' => "File yang sama sudah pernah diunggah pada batch #{$existingBatch->id} ({$existingBatch->original_name}). Data tidak diimpor ulang."]);
        }

        $path = $file->store('imports/'.now()->format('Y/m'));
        $batch = ImportBatch::create([
            'uploaded_by' => $request->user()->id,
            'source_type' => $request->string('source_type')->toString(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'checksum' => $checksum,
            'report_year' => $request->integer('report_year') ?: null,
            'report_quarter' => $request->string('report_quarter')->toString() ?: null,
        ]);

        try {
            $driveFile = $googleDriveStorage->upload($file, $batch->source_type);
            $batch->update(['drive_file_id' => $driveFile['id'], 'drive_state' => 'source', 'drive_error' => null, 'status' => 'uploaded']);
        } catch (\Throwable $exception) {
            report($exception);
            $batch->update(['status' => 'failed', 'summary' => ['message' => 'Unggah Google Drive gagal.']]);

            return back()->withErrors(['file' => 'File tersimpan lokal, tetapi unggah Google Drive gagal. Hubungkan ulang Google Drive atau periksa akses folder.']);
        }

        ProcessImportBatch::dispatch($batch);

        if ($request->expectsJson()) {
            return response()->json([
                'batch_id' => $batch->id,
                'message' => 'File tersimpan. Proses validasi dimulai di belakang layar.',
                'status_url' => route('imports.status', $batch),
            ], 202);
        }

        return back()->with('status', 'File tersimpan. Proses validasi dimulai di belakang layar.');
    }

    public function status(ImportBatch $batch): JsonResponse
    {
        $progress = match ($batch->status) {
            'uploaded' => 35,
            'processing' => 70,
            'ready' => 100,
            'failed' => 100,
            default => 15,
        };

        return response()->json([
            'status' => $batch->status,
            'progress' => $progress,
            'accepted_rows' => $batch->accepted_rows,
            'rejected_rows' => $batch->rejected_rows,
            'message' => $batch->summary['message'] ?? null,
        ]);
    }

    public function retryDriveMove(ImportBatch $batch, GoogleDriveStorage $googleDriveStorage, AuditLogger $auditLogger): RedirectResponse
    {
        abort_if($batch->drive_file_id === null, 422, 'Batch ini tidak memiliki file Google Drive.');
        abort_unless(in_array($batch->status, ['ready', 'failed'], true), 422, 'Pemindahan hanya dapat diulang setelah proses impor selesai.');

        try {
            if ($batch->status === 'ready') {
                $googleDriveStorage->archive($batch);
                $driveState = 'archived';
                $message = 'File berhasil dipindahkan ke folder arsip Google Drive.';
            } else {
                $googleDriveStorage->markAsFailed($batch);
                $driveState = 'failed_folder';
                $message = 'File berhasil dipindahkan ke folder impor gagal Google Drive.';
            }

            $batch->update(['drive_state' => $driveState, 'drive_error' => null, 'drive_moved_at' => now()]);
            $auditLogger->log(request()->user(), 'import_drive_move_retried', $batch, ['drive_state' => $driveState]);

            return back()->with('status', $message);
        } catch (\Throwable $exception) {
            report($exception);
            $batch->update(['drive_error' => $exception->getMessage()]);

            return back()->withErrors(['file' => 'File belum dapat dipindahkan. Periksa koneksi serta akses folder Google Drive lalu coba lagi.']);
        }
    }

    public function updateSnapshotTime(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate(['priority_snapshot_time' => ['required', 'date_format:H:i']]);
        $setting = Setting::query()->firstOrNew(['key' => 'priority.snapshot_time']);
        $setting->value = $validated['priority_snapshot_time'];
        $setting->updated_by = $request->user()->id;
        $setting->save();
        $auditLogger->log($request->user(), 'priority_snapshot_time_updated', $setting, ['time' => $validated['priority_snapshot_time']]);

        return back()->with('status', 'Jam snapshot prioritas diperbarui menjadi '.$validated['priority_snapshot_time'].' WIB.');
    }
}
