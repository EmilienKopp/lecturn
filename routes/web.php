<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Presentations\CreatePresentationController;
use App\Http\Controllers\Presentations\DeletePresentationController;
use App\Http\Controllers\Presentations\EditPresentationController;
use App\Http\Controllers\Presentations\SendReactionController;
use App\Http\Controllers\Presentations\ViewerController;
use App\Http\Controllers\Presentations\EmbedPresentationController;
use App\Http\Controllers\Presentations\ExportPresentationController;
use App\Http\Controllers\Presentations\ListPresentationsController;
use App\Http\Controllers\Presentations\PresentPresentationController;
use App\Http\Controllers\Presentations\UpdatePresentationController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::inertia('/', 'Welcome')->name('home');

Route::get('embed/presentations/{presentation:embed_token}.js', EmbedPresentationController::class)
    ->middleware('throttle:60,1')
    ->name('presentations.embed');

Route::get('present/{presentation:embed_token}', ViewerController::class)
    ->name('presentations.viewer');

Route::post('present/{presentation:embed_token}/reactions', SendReactionController::class)
    ->middleware('throttle:60,1')
    ->name('presentations.reactions');

Route::prefix('{current_team}')
    ->middleware(['auth', ValidateSessionWithWorkOS::class, EnsureTeamMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('presentations', ListPresentationsController::class)->name('presentations.index');
        Route::post('presentations', CreatePresentationController::class)->name('presentations.store');
        Route::get('presentations/{presentation}', EditPresentationController::class)->name('presentations.edit');
        Route::get('presentations/{presentation}/present', PresentPresentationController::class)->name('presentations.present');
        Route::get('presentations/{presentation}/export', ExportPresentationController::class)->name('presentations.export');
        Route::put('presentations/{presentation}', UpdatePresentationController::class)->name('presentations.update');
        Route::delete('presentations/{presentation}', DeletePresentationController::class)->name('presentations.destroy');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
