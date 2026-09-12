<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\AuditLogController;
use App\Http\Controllers\Staff\ExamDraftController;
use App\Http\Controllers\Staff\ExamPublishController;
use App\Http\Controllers\Staff\ExamScheduleController;
use App\Http\Controllers\Staff\GradingController;
use App\Http\Controllers\Staff\InvigilationController;
use App\Http\Controllers\Staff\QuestionBankController;
use App\Http\Controllers\Staff\ResultReleaseController;
use App\Http\Controllers\Student\AttemptController;
use App\Http\Controllers\Student\AttemptPageController;
use App\Http\Controllers\Student\ExamListController;
use App\Http\Controllers\Student\StudentResultsController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/health', HealthController::class)
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ])
    ->name('health');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'permission:attempts.take'])->group(function () {
    Route::get('/student/exams', ExamListController::class)->name('student.exams.index');
    Route::post('/attempts/start', [AttemptController::class, 'start'])->name('attempts.start');
    Route::get('/attempts/{attempt}/take', [AttemptPageController::class, 'show'])->name('attempts.take');
    Route::post('/attempts/{attempt}/answers', [AttemptController::class, 'saveAnswer'])->name('attempts.answers');
    Route::post('/attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
});

Route::middleware('auth')->get('/attempts/{attempt}/result', [AttemptController::class, 'result'])->name('attempts.result');

Route::middleware(['auth', 'permission:attempts.take'])->group(function () {
    Route::get('/student/results', [StudentResultsController::class, 'index'])->name('student.results.index');
    Route::get('/student/results/{attempt}', [StudentResultsController::class, 'show'])->name('student.results.show');
});

Route::middleware(['auth', 'permission:exams.publish'])->group(function () {
    Route::post('/staff/exams/{exam}/publish', [ExamPublishController::class, 'publish'])->name('staff.exams.publish');
});

Route::middleware(['auth', 'permission:exams.view'])->group(function () {
    Route::get('/staff/exams', [ExamDraftController::class, 'index'])->name('staff.exams.index');
    Route::get('/staff/exams/{exam}/edit', [ExamDraftController::class, 'edit'])->name('staff.exams.edit');
    Route::get('/staff/exams/{exam}/schedule', [ExamScheduleController::class, 'edit'])->name('staff.exams.schedule.edit');
});

Route::middleware(['auth', 'permission:exams.create'])->group(function () {
    Route::get('/staff/exams/create', [ExamDraftController::class, 'create'])->name('staff.exams.create');
    Route::post('/staff/exams', [ExamDraftController::class, 'store'])->name('staff.exams.store');
});

Route::middleware(['auth', 'permission:exams.update'])->group(function () {
    Route::put('/staff/exams/{exam}', [ExamDraftController::class, 'update'])->name('staff.exams.update');
    Route::put('/staff/exams/{exam}/schedule', [ExamScheduleController::class, 'update'])->name('staff.exams.schedule.update');
});

Route::middleware(['auth', 'permission:exams.submit'])->group(function () {
    Route::post('/staff/exams/{exam}/submit', [ExamDraftController::class, 'submit'])->name('staff.exams.submit');
});

Route::middleware(['auth', 'permission:exams.approve'])->group(function () {
    Route::post('/staff/exams/{exam}/approve', [ExamDraftController::class, 'approve'])->name('staff.exams.approve');
});

Route::middleware(['auth', 'permission:exams.publish'])->group(function () {
    Route::post('/staff/exams/{exam}/draft-publish', [ExamDraftController::class, 'publish'])->name('staff.exams.draft_publish');
});

Route::middleware(['auth', 'permission:grades.grade'])->group(function () {
    Route::post('/staff/answers/{answer}/grade', [GradingController::class, 'grade'])->name('staff.answers.grade');
    Route::get('/staff/grading', [GradingController::class, 'index'])->name('staff.grading.index');
    Route::get('/staff/grading/{exam}', [GradingController::class, 'gradePage'])->name('staff.grading.exam');
    Route::get('/staff/grading/attempts/{attempt}', [GradingController::class, 'attempt'])->name('staff.grading.attempt');
});

Route::middleware(['auth', 'permission:grades.view'])->group(function () {
    Route::get('/staff/attempts/{attempt}', [GradingController::class, 'showAttempt'])->name('staff.attempts.show');
});

Route::middleware(['auth', 'permission:results.release'])->group(function () {
    Route::get('/staff/results/{exam}', [ResultReleaseController::class, 'show'])->name('staff.results.show');
    Route::post('/staff/results/{exam}/release', [ResultReleaseController::class, 'release'])->name('staff.results.release');
    Route::post('/staff/results/{exam}/release-all', [ResultReleaseController::class, 'releaseAll'])->name('staff.results.release-all');
    Route::post('/staff/attempts/{attempt}/release', [GradingController::class, 'release'])->name('staff.attempts.release');
    Route::get('/staff/results/{exam}/export', [ResultReleaseController::class, 'export'])->name('staff.results.export');
});

Route::middleware(['auth', 'permission:attempts.invigilate'])->group(function () {
    Route::get('/staff/exams/{exam}/invigilation', [InvigilationController::class, 'show'])->name('staff.exams.invigilation');
    Route::get('/staff/exams/{exam}/invigilation/status', [InvigilationController::class, 'status'])->name('staff.exams.invigilation.status');
});

Route::middleware(['auth', 'permission:questions.view'])->group(function () {
    Route::get('/staff/questions', [QuestionBankController::class, 'index'])->name('staff.questions.index');
    Route::get('/staff/questions/{question}/edit', [QuestionBankController::class, 'edit'])->name('staff.questions.edit');
});

Route::middleware(['auth', 'permission:questions.create'])->group(function () {
    Route::get('/staff/questions/create', [QuestionBankController::class, 'create'])->name('staff.questions.create');
    Route::post('/staff/questions', [QuestionBankController::class, 'store'])->name('staff.questions.store');
});

Route::middleware(['auth', 'permission:questions.update'])->group(function () {
    Route::put('/staff/questions/{question}', [QuestionBankController::class, 'update'])->name('staff.questions.update');
});

Route::middleware(['auth', 'permission:questions.review'])->group(function () {
    Route::post('/staff/questions/{question}/ready', [QuestionBankController::class, 'markReady'])->name('staff.questions.ready');
    Route::post('/staff/questions/{question}/retire', [QuestionBankController::class, 'retire'])->name('staff.questions.retire');
});

Route::middleware(['auth', 'permission:results.release'])->group(function () {
    Route::get('/staff/audit-logs', [AuditLogController::class, 'index'])->name('staff.audit-logs.index');
});

require __DIR__.'/auth.php';
