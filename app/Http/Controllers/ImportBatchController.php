<?php

namespace App\Http\Controllers;

use App\GoogleDriveStorage;
use App\Http\Requests\StoreImportBatchRequest;
use App\Jobs\ProcessImportBatch;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ImportBatchController extends Controller
{
    public function index(GoogleDriveStorage $googleDriveStorage): View
    {
        return view('imports.index', [
            'batches' => ImportBatch::query()->with('uploader')->latest()->paginate(15),
            'googleDriveConnected' => $googleDriveStorage->isConnected(),
        ]);
    }

    public function store(StoreImportBatchRequest $request, GoogleDriveStorage $googleDriveStorage): RedirectResponse|JsonResponse
    {
        if (! $googleDriveStorage->isConnected()) {
            return back()->withErrors(['file' => 'Hubungkan Google Drive sebelum mengunggah file.']);
        }

        $file = $request->file('file');
        $path = $file->store('imports/'.now()->format('Y/m'));
        $batch = ImportBatch::create([
            'uploaded_by' => $request->user()->id,
            'source_type' => $request->string('source_type')->toString(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'checksum' => hash_file('sha256', Storage::path($path)),
            'report_year' => $request->integer('report_year') ?: null,
            'report_quarter' => $request->string('report_quarter')->toString() ?: null,
        ]);

        try {
            $driveFile = $googleDriveStorage->upload($file, $batch->source_type);
            $batch->update(['drive_file_id' => $driveFile['id'], 'status' => 'uploaded']);
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
}
