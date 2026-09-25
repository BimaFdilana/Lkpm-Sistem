<?php

use App\Http\Controllers\AnnualTargetController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\CheckpointSnapshotController;
use App\Http\Controllers\DailyPriorityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\GoogleDriveAuthorizationController;
use App\Http\Controllers\ImportBatchController;
use App\Http\Controllers\IntegratedDataController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\PicController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\SnapshotController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/dashboard/target-tahunan', [AnnualTargetController::class, 'storeFromDashboard'])->middleware('role:kepala_dinas')->name('dashboard.annual-target.store');
    Route::get('/target-tahunan', [AnnualTargetController::class, 'index'])->middleware('role:kepala_dinas')->name('annual-targets.index');
    Route::post('/target-tahunan', [AnnualTargetController::class, 'store'])->middleware('role:kepala_dinas')->name('annual-targets.store');
    Route::get('/prioritas-harian', [DailyPriorityController::class, 'index'])->middleware('role:kepala_bagian')->name('priority.index');
    Route::post('/prioritas-harian/snapshot', [DailyPriorityController::class, 'run'])->middleware('role:kepala_bagian')->name('priority.snapshot');
    Route::get('/assignments', [AssignmentController::class, 'index'])->middleware('role:kepala_bagian,pic')->name('assignments.index');
    Route::post('/assignments/rebalance', [AssignmentController::class, 'rebalance'])->middleware('role:kepala_bagian')->name('assignments.rebalance');
    Route::get('/assignments/candidates', [AssignmentController::class, 'candidates'])->middleware('role:kepala_bagian')->name('assignments.candidates');
    Route::post('/assignments/candidates/assign', [AssignmentController::class, 'assignCandidates'])->middleware('role:kepala_bagian')->name('assignments.candidates.assign');
    Route::post('/assignments/candidates/assign-verification', [AssignmentController::class, 'assignInitialVerification'])->middleware('role:kepala_bagian')->name('assignments.candidates.assign-verification');
    Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->middleware('role:kepala_bagian,pic')->name('assignments.show');
    Route::put('/assignments/{assignment}/pic', [AssignmentController::class, 'updatePic'])->middleware('role:kepala_bagian')->name('assignments.pic.update');
    Route::post('/assignments/{assignment}/follow-ups', [FollowUpController::class, 'store'])->middleware('role:pic')->name('assignments.follow-ups.store');
    Route::get('/pics', [PicController::class, 'index'])->middleware('role:kepala_bagian')->name('pics.index');
    Route::post('/pics', [PicController::class, 'store'])->middleware('role:kepala_bagian')->name('pics.store');
    Route::get('/periods', [PeriodController::class, 'index'])->middleware('role:kepala_bagian')->name('periods.index');
    Route::post('/periods', [PeriodController::class, 'store'])->middleware('role:kepala_bagian')->name('periods.store');
    Route::post('/periods/{period}/activate', [PeriodController::class, 'activate'])->middleware('role:kepala_bagian')->name('periods.activate');
    Route::post('/checkpoints', [CheckpointSnapshotController::class, 'store'])->middleware('role:kepala_bagian')->name('checkpoints.store');
    Route::get('/snapshots', [SnapshotController::class, 'index'])->middleware('role:kepala_bagian')->name('snapshots.index');
    Route::get('/reconciliations', [ReconciliationController::class, 'index'])->middleware('role:kepala_bagian')->name('reconciliations.index');
    Route::get('/reconciliations/export', [ReconciliationController::class, 'export'])->middleware('role:kepala_bagian')->name('reconciliations.export');
    Route::post('/reconciliations', [ReconciliationController::class, 'store'])->middleware('role:kepala_bagian')->name('reconciliations.store');
    Route::get('/data-terpadu', [IntegratedDataController::class, 'index'])->middleware('role:kepala_bagian')->name('integrated-data.index');
    Route::get('/data-terpadu/{project}', [IntegratedDataController::class, 'show'])->middleware('role:kepala_bagian')->name('integrated-data.show');
    Route::get('/imports', [ImportBatchController::class, 'index'])->middleware('role:programmer,kepala_bagian')->name('imports.index');
    Route::post('/imports', [ImportBatchController::class, 'store'])->middleware('role:programmer,kepala_bagian')->name('imports.store');
    Route::get('/imports/{batch}/status', [ImportBatchController::class, 'status'])->middleware('role:programmer,kepala_bagian')->name('imports.status');
    Route::post('/imports/{batch}/retry-drive-move', [ImportBatchController::class, 'retryDriveMove'])->middleware('role:programmer,kepala_bagian')->name('imports.retry-drive-move');
    Route::post('/imports/settings/snapshot-time', [ImportBatchController::class, 'updateSnapshotTime'])->middleware('role:programmer,kepala_bagian')->name('imports.snapshot-time.update');
    Route::get('/google-drive/connect', [GoogleDriveAuthorizationController::class, 'create'])->middleware('role:programmer,kepala_bagian')->name('google-drive.connect');
    Route::get('/google-drive/callback', [GoogleDriveAuthorizationController::class, 'store'])->middleware('role:programmer,kepala_bagian')->name('google-drive.callback');
    Route::post('/google-drive/verify', [GoogleDriveAuthorizationController::class, 'verify'])->middleware('role:programmer,kepala_bagian')->name('google-drive.verify');
});
