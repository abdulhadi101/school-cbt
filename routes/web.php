<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\ExamPublishController;
use App\Http\Controllers\Staff\GradingController;
use App\Http\Controllers\Staff\InvigilationController;
use App\Http\Controllers\Student\AttemptController;
use App\Http\Controllers\Student\AttemptPageController;
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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'permission:attempts.take'])->group(function () {
    Route::post('/attempts/start', [AttemptController::class, 'start'])->name('attempts.start');
    Route::get('/attempts/{attempt}/take', [AttemptPageController::class, 'show'])->name('attempts.take');
    Route::post('/attempts/{attempt}/answers', [AttemptController::class, 'saveAnswer'])->name('attempts.answers');
    Route::post('/attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
});

Route::middleware('auth')->get('/attempts/{attempt}/result', [AttemptController::class, 'result'])->name('attempts.result');

Route::middleware(['auth', 'permission:exams.publish'])->group(function () {
    Route::post('/staff/exams/{exam}/publish', [ExamPublishController::class, 'publish'])->name('staff.exams.publish');
});

Route::middleware(['auth', 'permission:grades.grade'])->group(function () {
    Route::post('/staff/answers/{answer}/grade', [GradingController::class, 'grade'])->name('staff.answers.grade');
});

Route::middleware(['auth', 'permission:grades.view'])->group(function () {
    Route::get('/staff/attempts/{attempt}', [GradingController::class, 'showAttempt'])->name('staff.attempts.show');
});

Route::middleware(['auth', 'permission:results.release'])->group(function () {
    Route::post('/staff/attempts/{attempt}/release', [GradingController::class, 'release'])->name('staff.attempts.release');
});

Route::middleware(['auth', 'permission:attempts.invigilate'])->group(function () {
    Route::get('/staff/exams/{exam}/invigilation', [InvigilationController::class, 'show'])->name('staff.exams.invigilation');
    Route::get('/staff/exams/{exam}/invigilation/status', [InvigilationController::class, 'status'])->name('staff.exams.invigilation.status');
});

require __DIR__.'/auth.php';
