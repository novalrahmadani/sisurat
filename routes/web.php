<?php

use App\Http\Controllers\AttendanceEventController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\GeneralLetterController;
use App\Http\Controllers\GuideBookController;
use App\Http\Controllers\GuideVideoController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\InternshipAccessController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\InterviewFormController;
use App\Http\Controllers\InterviewReportController;
use App\Http\Controllers\LetterBatchController;
use App\Http\Controllers\LetterTemplateController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\OutgoingLetterController;
use App\Http\Controllers\PmbSignatoryController;
use App\Http\Controllers\PublicAttendanceController;
use App\Http\Controllers\PublicFormFlyerController;
use App\Http\Controllers\PublicFormQrController;
use App\Http\Controllers\PublicInternshipController;
use App\Http\Controllers\PublicInterviewController;
use App\Http\Controllers\PublicQuranAssessmentController;
use App\Http\Controllers\QuranAssessmentController;
use App\Http\Controllers\QuranExamController;
use App\Http\Controllers\StaffMemberController;
use App\Http\Controllers\StudyProgramController;
use App\Http\Controllers\UserAccountController;
use App\Models\AttendanceEvent;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::inertia('informasi', 'Information')->name('information');

Route::get('tes-baca-alquran/{token}', [PublicQuranAssessmentController::class, 'join'])->where('token', '[A-Za-z0-9]{64}')->name('quran-exams.join');
Route::post('tes-baca-alquran/{token}', [PublicQuranAssessmentController::class, 'submit'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:quran-submissions')->name('quran-exams.submit');

Route::get('absensi-magang', [PublicInternshipController::class, 'show'])->name('internship.show');
Route::get('absensi-magang/sesi', [PublicInternshipController::class, 'session'])->name('internship.session');
Route::post('absensi-magang/daftar', [PublicInternshipController::class, 'register'])->middleware('throttle:internship-registration')->name('internship.register');
Route::get('absensi-magang/akses/{token}', [PublicInternshipController::class, 'recovery'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:internship-verification')->name('internship.recovery');
Route::post('absensi-magang/akses/{token}', [PublicInternshipController::class, 'recover'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:internship-verification')->name('internship.recover');
Route::post('absensi-magang/periksa', [PublicInternshipController::class, 'verify'])->middleware('throttle:internship-verification')->name('internship.verify');
Route::post('absensi-magang/masuk', [PublicInternshipController::class, 'verifyPin'])->middleware('throttle:internship-pin')->name('internship.verify-pin');
Route::post('absensi-magang/pin', [PublicInternshipController::class, 'issuePin'])->middleware('throttle:internship-pin-issuance')->name('internship.issue-pin');
Route::post('absensi-magang', [PublicInternshipController::class, 'submit'])->middleware('throttle:internship-submissions')->name('internship.submit');
Route::post('absensi-magang/ganti-peserta', [PublicInternshipController::class, 'leave'])->name('internship.leave');

Route::get('hadir/{token}', [PublicAttendanceController::class, 'join'])
    ->where('token', '[A-Za-z0-9]{64}')->name('attendance.join');
Route::post('hadir/{token}', [PublicAttendanceController::class, 'submit'])
    ->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:attendance-submissions')->name('attendance.submit');

Route::get('wawancara/{token}', [PublicInterviewController::class, 'join'])
    ->where('token', '[A-Za-z0-9]{64}')->name('interviews.join');
Route::post('wawancara/{token}', [PublicInterviewController::class, 'submit'])
    ->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:interview-submissions')->name('interviews.submit');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('can:manage-internships')->group(function (): void {
        Route::get('internships', [InternshipController::class, 'index'])->name('internships.index');
        Route::get('internships/settings', [InternshipAccessController::class, 'edit'])->name('internships.access.edit');
        Route::put('internships/settings', [InternshipAccessController::class, 'update'])->name('internships.access.update');
        Route::post('internships/participants', [InternshipController::class, 'store'])->name('internships.store');
        Route::put('internships/participants/{participant}', [InternshipController::class, 'update'])->name('internships.update');
        Route::delete('internships/participants/{participant}', [InternshipController::class, 'destroy'])->name('internships.destroy');
        Route::post('internships/participants/{participant}/recovery', [InternshipController::class, 'recoverAccess'])->name('internships.recover-access');
        Route::get('internships/qr', [PublicFormQrController::class, 'internship'])->name('internships.qr');
        Route::get('internships/flyer', [PublicFormFlyerController::class, 'internship'])->name('internships.flyer');
        Route::get('internships/export', [InternshipController::class, 'export'])->name('internships.export');
        Route::get('internships/print', [InternshipController::class, 'print'])->name('internships.print');
        Route::get('internships/{attendance}/signature/{kind}', [InternshipController::class, 'signature'])->whereIn('kind', ['in', 'out'])->name('internships.signature');
    });

    Route::resource('settings/accounts', UserAccountController::class)->except(['show', 'destroy'])
        ->parameters(['accounts' => 'account'])->names('accounts');

    Route::get('interviews/{interview}/qr', [PublicFormQrController::class, 'interview'])->name('interviews.qr');
    Route::get('interviews/{interview}/flyer', [PublicFormFlyerController::class, 'interview'])->name('interviews.flyer');
    Route::get('interviews/{interview}/export', [InterviewReportController::class, 'csv'])->name('interviews.export');
    Route::get('interviews/{interview}/pdf', [InterviewReportController::class, 'pdf'])->name('interviews.pdf');
    Route::get('interviews/{interview}/submissions/{submission}', [InterviewFormController::class, 'showSubmission'])->name('interviews.submissions.show');
    Route::get('interviews/{interview}/submissions/{submission}/print', [InterviewFormController::class, 'printSubmission'])->name('interviews.submissions.print');
    Route::delete('interviews/{interview}/submissions/{submission}', [InterviewFormController::class, 'destroySubmission'])->name('interviews.submissions.destroy');
    Route::resource('interviews', InterviewFormController::class)->parameters(['interviews' => 'interview']);

    Route::get('quran-exams/{quran_exam}/qr', [PublicFormQrController::class, 'quran'])->name('quran-exams.qr');
    Route::get('quran-exams/{quran_exam}/flyer', [PublicFormFlyerController::class, 'quran'])->name('quran-exams.flyer');
    Route::resource('quran-exams', QuranExamController::class)->only(['index', 'create', 'store', 'show', 'update'])->parameters(['quran-exams' => 'quran_exam']);
    Route::get('quran-tests/pdf', [QuranAssessmentController::class, 'pdf'])->name('quran-tests.pdf');
    Route::get('quran-tests/export', [QuranAssessmentController::class, 'export'])->name('quran-tests.export');
    Route::get('quran-tests/{quran_test}/print', [QuranAssessmentController::class, 'print'])->name('quran-tests.print');
    Route::resource('quran-tests', QuranAssessmentController::class)->parameters(['quran-tests' => 'quran_test']);

    Route::get('attendance/rapat', [AttendanceEventController::class, 'index'])
        ->defaults('attendanceType', AttendanceEvent::TYPE_MEETING)->name('attendance.meetings.index');
    Route::get('attendance/rapat/create', [AttendanceEventController::class, 'create'])
        ->defaults('attendanceType', AttendanceEvent::TYPE_MEETING)->name('attendance.meetings.create');
    Route::get('attendance/tes-pmb', [AttendanceEventController::class, 'index'])
        ->defaults('attendanceType', AttendanceEvent::TYPE_PMB_TEST)->name('attendance.pmb-tests.index');
    Route::get('attendance/tes-pmb/create', [AttendanceEventController::class, 'create'])
        ->defaults('attendanceType', AttendanceEvent::TYPE_PMB_TEST)->name('attendance.pmb-tests.create');
    Route::get('attendance/kegiatan-lainnya', [AttendanceEventController::class, 'index'])
        ->defaults('attendanceType', AttendanceEvent::TYPE_OTHER)->name('attendance.other.index');
    Route::get('attendance/kegiatan-lainnya/create', [AttendanceEventController::class, 'create'])
        ->defaults('attendanceType', AttendanceEvent::TYPE_OTHER)->name('attendance.other.create');

    Route::get('attendance/{attendance}/qr', [PublicFormQrController::class, 'attendance'])->name('attendance.qr');
    Route::get('attendance/{attendance}/flyer', [PublicFormFlyerController::class, 'attendance'])->name('attendance.flyer');
    Route::get('attendance/{attendance}/export', [AttendanceEventController::class, 'export'])->name('attendance.export');
    Route::get('attendance/{attendance}/print', [AttendanceEventController::class, 'print'])->name('attendance.print');
    Route::get('attendance/{attendance}/signatures/{record}', [AttendanceEventController::class, 'signature'])->name('attendance.signature');
    Route::delete('attendance/{attendance}/records/{record}', [AttendanceEventController::class, 'destroyRecord'])->name('attendance.records.destroy');
    Route::resource('attendance', AttendanceEventController::class);

    Route::get('master-data', MasterDataController::class)->name('master-data.index');
    Route::get('master-data/schools', [InstitutionController::class, 'index'])
        ->defaults('institutionType', 'school')->name('master-data.schools');
    Route::get('master-data/pesantren', [InstitutionController::class, 'index'])
        ->defaults('institutionType', 'pesantren')->name('master-data.pesantren');
    Route::resource('master-data/districts', DistrictController::class)->except(['show'])
        ->parameters(['districts' => 'district'])->names('master-data.districts');
    Route::resource('master-data/staff-members', StaffMemberController::class)->except(['show'])
        ->parameters(['staff-members' => 'staff_member'])->names('master-data.staff-members');
    Route::resource('master-data/study-programs', StudyProgramController::class)->except(['show'])
        ->parameters(['study-programs' => 'study_program'])->names('master-data.study-programs');
    Route::resource('master-data/faculties', FacultyController::class)->except(['show'])
        ->parameters(['faculties' => 'faculty'])->names('master-data.faculties');
    Route::resource('master-data/letter-templates', LetterTemplateController::class)->only(['index', 'edit', 'update'])
        ->parameters(['letter-templates' => 'letter_template'])->names('master-data.letter-templates');
    Route::get('master-data/pmb-signatory', [PmbSignatoryController::class, 'edit'])->name('master-data.pmb-signatory.edit');
    Route::post('master-data/pmb-signatory', [PmbSignatoryController::class, 'update'])->name('master-data.pmb-signatory.update');
    Route::get('master-data/pmb-signatory/media/{signatory}/{kind}', [PmbSignatoryController::class, 'media'])->whereIn('kind', ['signature', 'stamp'])->name('master-data.pmb-signatory.media');

    Route::get('outgoing-letters', [OutgoingLetterController::class, 'index'])->name('outgoing-letters.index');
    Route::get('outgoing-letters/create', [OutgoingLetterController::class, 'create'])->name('outgoing-letters.create');
    Route::get('outgoing-letters/export', [OutgoingLetterController::class, 'csv'])->name('outgoing-letters.export');
    Route::get('outgoing-letters/pdf', [OutgoingLetterController::class, 'pdf'])->name('outgoing-letters.pdf');
    Route::post('outgoing-letters/manual', [OutgoingLetterController::class, 'store'])->name('outgoing-letters.store');
    Route::get('outgoing-letters/manual/{entry}/edit', [OutgoingLetterController::class, 'edit'])->name('outgoing-letters.edit');
    Route::put('outgoing-letters/manual/{entry}', [OutgoingLetterController::class, 'update'])->name('outgoing-letters.update');
    Route::delete('outgoing-letters/manual/{entry}', [OutgoingLetterController::class, 'destroy'])->name('outgoing-letters.destroy');

    Route::get('letters/permission/school', [LetterBatchController::class, 'create'])
        ->defaults('letterType', 'permission')->defaults('institutionType', 'school')->name('letters.permission.school');
    Route::get('letters/permission/pesantren', [LetterBatchController::class, 'create'])
        ->defaults('letterType', 'permission')->defaults('institutionType', 'pesantren')->name('letters.permission.pesantren');
    Route::get('letters/assignment/school', [LetterBatchController::class, 'create'])
        ->defaults('letterType', 'assignment')->defaults('institutionType', 'school')->name('letters.assignment.school');
    Route::get('letters/assignment/pesantren', [LetterBatchController::class, 'create'])
        ->defaults('letterType', 'assignment')->defaults('institutionType', 'pesantren')->name('letters.assignment.pesantren');
    Route::get('letters/pkkmb', [LetterBatchController::class, 'create'])
        ->defaults('letterType', 'pkkmb')->name('letters.pkkmb');
    Route::get('letters/general', [GeneralLetterController::class, 'create'])->name('letters.general');
    Route::post('letters/general', [GeneralLetterController::class, 'store'])->name('letters.general.store');
    Route::get('letters/directory', [LetterBatchController::class, 'directory'])->name('letters.directory');
    Route::delete('letters/batches', [LetterBatchController::class, 'destroySelected'])->name('letters.batches.destroy-selected');
    Route::get('letters/batches/{batch}', [LetterBatchController::class, 'show'])->name('letters.batch');
    Route::delete('letters/batches/{batch}', [LetterBatchController::class, 'destroy'])->name('letters.batches.destroy');
    Route::delete('letters/batches/{batch}/letters/{letter}', [LetterBatchController::class, 'destroyLetter'])->name('letters.batches.letters.destroy');
    Route::get('letters/create', [LetterBatchController::class, 'choose'])->name('letters.create');
    Route::resource('letters', LetterBatchController::class)->only(['index', 'store']);
    Route::resource('institutions', InstitutionController::class)->except(['index', 'show']);
    Route::inertia('guide', 'Guide')->name('guide');
    Route::get('guide/book', [GuideBookController::class, 'show'])->name('guide.book');
    Route::get('guide/book/download', [GuideBookController::class, 'download'])->name('guide.book.download');
    Route::get('guide/video', [GuideVideoController::class, 'show'])->name('guide.video');
    Route::get('guide/video/download', [GuideVideoController::class, 'download'])->name('guide.video.download');
    Route::get('guide/video/poster', [GuideVideoController::class, 'poster'])->name('guide.video.poster');
});

require __DIR__.'/settings.php';
