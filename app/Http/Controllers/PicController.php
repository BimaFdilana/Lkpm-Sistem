<?php

namespace App\Http\Controllers;

use App\AuditLogger;
use App\Http\Requests\StorePicRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PicController extends Controller
{
    public function index(): View
    {
        return view('pics.index', [
            'pics' => User::query()
                ->where('role', 'pic')
                ->withCount(['assignments as active_assignment_count' => fn ($query) => $query->where('status', 'active')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StorePicRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $pic = User::create([...$request->validated(), 'role' => 'pic']);
        $auditLogger->log($request->user(), 'pic_created', $pic);

        return back()->with('status', 'PIC baru berhasil ditambahkan.');
    }
}
