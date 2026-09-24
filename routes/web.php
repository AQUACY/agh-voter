<?php

use App\Http\Controllers\Api\V1\Admin\LiveResultsController;
use App\Http\Controllers\BallotController;
use App\Http\Controllers\Ec\BallotSetupController;
use App\Http\Controllers\Ec\DashboardController;
use App\Http\Controllers\Ec\ElectionController;
use App\Http\Controllers\Ec\ManualBallotController;
use App\Http\Controllers\Ec\ProfileController;
use App\Http\Controllers\PublicResultsController;
use App\Http\Controllers\VoteMethodController;
use App\Http\Controllers\VoterAuthController;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureElectoralCommission;
use App\Http\Middleware\EnsureVoterAuthenticated;
use Illuminate\Support\Facades\Route;

Route::get('/', [VoterAuthController::class, 'enter'])->name('voter.enter');
Route::post('/otp/request', [VoterAuthController::class, 'requestOtp'])->name('voter.otp.request');
Route::get('/verify', [VoterAuthController::class, 'verifyForm'])->name('voter.verify');
Route::post('/otp/verify', [VoterAuthController::class, 'verifyOtp'])->name('voter.otp.verify');
Route::post('/otp/resend', [VoterAuthController::class, 'requestOtp'])->name('voter.otp.resend');

Route::middleware(EnsureVoterAuthenticated::class)->group(function () {
    Route::get('/method', [VoteMethodController::class, 'show'])->name('voter.method');
    Route::post('/method/online', [VoteMethodController::class, 'online'])->name('voter.method.online');
    Route::get('/ballot', [BallotController::class, 'show'])->name('voter.ballot');
    Route::post('/ballot', [BallotController::class, 'store'])->name('voter.ballot.store');
});

Route::get('/paper/print', [VoteMethodController::class, 'print'])->name('voter.paper.print');
Route::post('/paper/done', [VoteMethodController::class, 'done'])->name('voter.paper.done');

Route::get('/done', [BallotController::class, 'done'])->name('voter.done');
Route::get('/results', PublicResultsController::class)->name('results.public');

Route::prefix('ec')->group(function () {
    Route::get('/login', [DashboardController::class, 'loginForm'])->name('ec.login');
    Route::post('/login', [DashboardController::class, 'login'])->name('ec.login.attempt');
    Route::post('/logout', [DashboardController::class, 'logout'])->name('ec.logout');

    Route::middleware(['auth', EnsureElectoralCommission::class])->group(function () {
        Route::get('/', [DashboardController::class, 'dashboard'])->name('ec.dashboard');
        Route::get('/settings', [ElectionController::class, 'edit'])->name('ec.settings');
        Route::put('/settings', [ElectionController::class, 'update'])->name('ec.settings.update');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('ec.profile');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('ec.profile.password');
        Route::post('/open', [ElectionController::class, 'open'])->name('ec.open');
        Route::post('/close', [ElectionController::class, 'close'])->name('ec.close');
        Route::post('/setup', [ElectionController::class, 'setup'])->name('ec.setup');
        Route::post('/restore', [ElectionController::class, 'restore'])
            ->middleware(EnsureAdministrator::class)
            ->name('ec.restore');

        Route::get('/ballot', [BallotSetupController::class, 'index'])->name('ec.ballot');
        Route::get('/ballot/print', [ManualBallotController::class, 'print'])->name('ec.ballot.print');
        Route::post('/positions', [BallotSetupController::class, 'storePosition'])->name('ec.positions.store');
        Route::delete('/positions/{position}', [BallotSetupController::class, 'destroyPosition'])->name('ec.positions.destroy');
        Route::post('/positions/{position}/unopposed', [BallotSetupController::class, 'updateUnopposed'])->name('ec.positions.unopposed');
        Route::post('/positions/{position}/candidates', [BallotSetupController::class, 'storeCandidate'])->name('ec.candidates.store');
        Route::post('/candidates/{candidate}/photo', [BallotSetupController::class, 'updatePhoto'])->name('ec.candidates.photo');
        Route::delete('/candidates/{candidate}', [BallotSetupController::class, 'destroyCandidate'])->name('ec.candidates.destroy');

        Route::get('/tally', [ManualBallotController::class, 'tally'])->name('ec.tally');
        Route::put('/tally', [ManualBallotController::class, 'saveTallies'])->name('ec.tally.save');
        Route::post('/publish', [ManualBallotController::class, 'publish'])->name('ec.publish');

        Route::get('/voters', [DashboardController::class, 'voters'])->name('ec.voters');
        Route::post('/voters', [DashboardController::class, 'storeVoter'])->name('ec.voters.store');
        Route::post('/voters/{voter}/paper', [DashboardController::class, 'markPaperVote'])->name('ec.voters.paper');
        Route::post('/voters/{voter}/paper/reprint', [DashboardController::class, 'reprintPaperVote'])->name('ec.voters.paper.reprint');
        Route::delete('/voters/{voter}', [DashboardController::class, 'destroyVoter'])->name('ec.voters.destroy');
        Route::post('/voters/import', [DashboardController::class, 'importVoters'])->name('ec.voters.import');
        Route::get('/voters/template', [DashboardController::class, 'template'])->name('ec.voters.template');
        Route::get('/serials', [DashboardController::class, 'lookupSerial'])->name('ec.serials');
        Route::get('/audit', [DashboardController::class, 'audit'])->name('ec.audit');
    });
});

Route::prefix('api/v1')->group(function () {
    Route::post('/auth/otp/request', [VoterAuthController::class, 'requestOtp']);
    Route::post('/auth/otp/verify', [VoterAuthController::class, 'verifyOtp']);
    Route::post('/auth/otp/resend', [VoterAuthController::class, 'requestOtp']);

    Route::middleware(EnsureVoterAuthenticated::class)->group(function () {
        Route::post('/method/online', [VoteMethodController::class, 'online']);
        Route::get('/ballot', [BallotController::class, 'payload']);
        Route::post('/ballot', [BallotController::class, 'store']);
    });

    Route::middleware(['auth', EnsureElectoralCommission::class])->group(function () {
        Route::get('/admin/results/live', LiveResultsController::class);
    });
});
