<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class SnapshotController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('priority.index', ['tab' => 'snapshots']);
    }
}
