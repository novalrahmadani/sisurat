<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\LetterTemplate;
use App\Models\StaffMember;
use App\Models\StudyProgram;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('master-data/Overview', [
            'counts' => [
                'schools' => Institution::query()->where('institution_type', 'school')->count(),
                'pondok' => Institution::query()->where('institution_type', 'pesantren')->count(),
                'districts' => District::query()->count(),
                'staff' => StaffMember::query()->count(),
                'studyPrograms' => StudyProgram::query()->count(),
                'faculties' => Faculty::query()->count(),
                'letterTemplates' => LetterTemplate::query()->whereIn('key', array_column(LetterTemplate::catalog(), 'key'))->count(),
            ],
        ]);
    }
}
