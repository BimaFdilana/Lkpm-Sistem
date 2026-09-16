<?php

namespace App\Http\Controllers;

use App\GoogleDriveStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GoogleDriveAuthorizationController extends Controller
{
    public function create(Request $request, GoogleDriveStorage $googleDriveStorage): RedirectResponse
    {
        $state = Str::random(64);
        $request->session()->put('google_drive.oauth_state', $state);

        return redirect()->away($googleDriveStorage->authorizationUrl($state));
    }

    public function store(Request $request, GoogleDriveStorage $googleDriveStorage): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_drive.oauth_state');
        $receivedState = $request->query('state');

        if (! is_string($expectedState) || ! is_string($receivedState) || ! hash_equals($expectedState, $receivedState)) {
            abort(403);
        }

        if ($request->query('error') !== null || ! is_string($request->query('code'))) {
            return redirect()->route('imports.index')->withErrors(['file' => 'Otorisasi Google Drive dibatalkan atau tidak lengkap.']);
        }

        try {
            $googleDriveStorage->completeAuthorization($request->query('code'), $request->user());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('imports.index')->withErrors(['file' => 'Google Drive tidak dapat dihubungkan. Periksa konfigurasi OAuth lalu coba lagi.']);
        }

        return redirect()->route('imports.index')->with('status', 'Google Drive terhubung. File impor akan disimpan menggunakan akun Google Drive yang baru diotorisasi.');
    }
}
