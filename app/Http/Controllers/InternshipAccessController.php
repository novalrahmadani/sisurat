<?php

namespace App\Http\Controllers;

use App\Http\Requests\InternshipAccessRequest;
use App\Models\InternshipSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InternshipAccessController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('internship/Settings', ['setting' => InternshipSetting::current()->only(['latitude', 'longitude', 'radius_meters'])]);
    }

    public function update(InternshipAccessRequest $request): RedirectResponse
    {
        InternshipSetting::current()->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Titik lokasi dan radius absensi magang diperbarui.']);

        return to_route('internships.access.edit');
    }
}
