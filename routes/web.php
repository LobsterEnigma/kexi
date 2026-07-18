<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseMeetingCancellationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\TimetableController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/s/{token}', [PublicShareController::class, 'show'])
    ->middleware('throttle:share-lookup')
    ->name('public-shares.show');
Route::post('/s/{token}/unlock', [PublicShareController::class, 'unlock'])
    ->middleware('throttle:share-password')
    ->name('public-shares.unlock');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [TimetableController::class, 'index'])->name('dashboard');
    Route::get('/timetables', [TimetableController::class, 'index'])->name('timetables.index');
    Route::post('/timetables', [TimetableController::class, 'store'])->name('timetables.store');
    Route::get('/timetables/{timetable}', [TimetableController::class, 'show'])->name('timetables.show');
    Route::patch('/timetables/{timetable}', [TimetableController::class, 'update'])->name('timetables.update');
    Route::delete('/timetables/{timetable}', [TimetableController::class, 'destroy'])->name('timetables.destroy');

    Route::post('/timetables/{timetable}/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::patch('/timetables/{timetable}/courses/{course}/archive', [CourseController::class, 'archive'])->name('courses.archive');
    Route::patch('/timetables/{timetable}/courses/{course}/restore', [CourseController::class, 'restore'])->name('courses.restore');
    Route::post('/timetables/{timetable}/courses/{course}/meetings/{meeting}/cancellations', [CourseMeetingCancellationController::class, 'store'])->name('course-meeting-cancellations.store');
    Route::put('/timetables/{timetable}/courses/{course}/meetings/{meeting}/cancellations', [CourseMeetingCancellationController::class, 'update'])->name('course-meeting-cancellations.update');
    Route::delete('/timetables/{timetable}/courses/{course}/meetings/{meeting}/cancellations', [CourseMeetingCancellationController::class, 'destroy'])->name('course-meeting-cancellations.destroy');
    Route::patch('/timetables/{timetable}/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/timetables/{timetable}/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

    Route::post('/timetables/{timetable}/shares', [ShareController::class, 'store'])
        ->middleware('throttle:share-create')
        ->name('shares.store');
    Route::delete('/timetables/{timetable}/shares/{share}', [ShareController::class, 'destroy'])
        ->name('shares.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
