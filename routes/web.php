<?php

use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\AwardActionController;
use App\Http\Controllers\AwardsController;
use App\Http\Controllers\AcademyController;
use App\Http\Controllers\CommunityFormController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\MijnCnController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\AwardManagementController;
use App\Http\Controllers\Staff\HrController;
use App\Http\Controllers\Staff\AccessController;
use App\Http\Controllers\Staff\ContentController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'index'])->name('install');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');

Route::get('/', HomeController::class)->name('home');
Route::get('/zoeken', [PageController::class, 'search'])->name('search');
Route::get('/login', [DiscordController::class, 'redirect'])->name('login');
Route::get('/auth/discord', [DiscordController::class, 'redirect'])->name('discord.login');
Route::get('/auth/discord/callback', [DiscordController::class, 'callback'])->name('discord.callback');
Route::post('/uitloggen', [DiscordController::class, 'logout'])->name('logout');

Route::get('/awards', [AwardsController::class, 'index'])->name('awards');
Route::get('/awards/finale', [AwardsController::class, 'finale'])->name('awards.finale');
Route::get('/awards/hall-of-fame', [AwardsController::class, 'hallOfFame'])->name('awards.hall');
Route::get('/awards/nominatie/{nomination}', [AwardsController::class, 'nomination'])->name('awards.nomination');
Route::get('/mini-awards', [PageController::class, 'show'])->defaults('page', 'mini-awards')->name('mini.awards');
Route::get('/nieuws', [NewsController::class, 'index'])->name('nieuws');
Route::get('/nieuws/{content:slug}', [NewsController::class, 'show'])->name('news.show');
Route::get('/partners', [PageController::class, 'show'])->defaults('page', 'partners')->name('partners');
Route::get('/staff', [PageController::class, 'show'])->defaults('page', 'staff')->name('staff');
Route::get('/contact', [PageController::class, 'show'])->defaults('page', 'contact')->name('contact');
Route::get('/solliciteren', [PageController::class, 'show'])->defaults('page', 'solliciteren')->name('solliciteren');
Route::get('/partner-worden', [PageController::class, 'show'])->defaults('page', 'partner-worden')->name('partner.worden');
Route::post('/formulier/{type}', [CommunityFormController::class, 'store'])
    ->whereIn('type', ['contact', 'application', 'partnership'])
    ->middleware('throttle:5,10')
    ->name('forms.store');

Route::middleware('auth')->group(function () {
    Route::get('/mijn-cn', DashboardController::class)->name('dashboard');
    Route::get('/mijn-cn/academy', [AcademyController::class, 'index'])->name('academy.index');
    Route::get('/mijn-cn/academy/opleiding/{path}', [AcademyController::class, 'path'])->name('academy.path');
    Route::get('/mijn-cn/academy/les/{lesson}', [AcademyController::class, 'lesson'])->name('academy.lesson');
    Route::post('/mijn-cn/academy/les/{lesson}/afronden', [AcademyController::class, 'completeLesson'])->name('academy.lesson.complete');
    Route::post('/mijn-cn/academy/opdracht/{lesson}', [AcademyController::class, 'submitAssignment'])->name('academy.assignment.submit');
    Route::post('/mijn-cn/academy/toets/{lesson}', [AcademyController::class, 'submitAssessment'])->name('academy.assessment.submit');
    Route::get('/mijn-cn/academy/resultaat/{attempt}', [AcademyController::class, 'result'])->name('academy.attempt.result');
    Route::get('/mijn-cn/{module}', [MijnCnController::class, 'show'])
        ->whereIn('module', ['profile', 'notifications', 'inbox', 'nominations', 'votes', 'results', 'lessons', 'exams', 'certificates', 'badges', 'tasks', 'nomi', 'settings', 'absences', 'birthdays', 'community'])
        ->name('mijncn.module');
    Route::patch('/mijn-cn/profiel/bijwerken', [MijnCnController::class, 'updateProfile'])->name('mijncn.profile.update');
    Route::post('/mijn-cn/meldingen/gelezen', [MijnCnController::class, 'markNotificationsRead'])->name('mijncn.notifications.read');
    Route::post('/mijn-cn/nomi/vragen', [MijnCnController::class, 'askNomi'])->middleware('throttle:10,1')->name('mijncn.nomi.ask');
    Route::post('/mijn-cn/taken/{task}/claimen', [MijnCnController::class, 'claimTask'])->name('mijncn.tasks.claim');
    Route::post('/mijn-cn/taken/{task}/voltooien', [MijnCnController::class, 'completeTask'])->name('mijncn.tasks.complete');
    Route::post('/mijn-cn/afwezigheid', [MijnCnController::class, 'reportAbsence'])->name('mijncn.absences.store');
    Route::delete('/mijn-cn/afwezigheid/{absence}', [MijnCnController::class, 'cancelAbsence'])->name('mijncn.absences.cancel');
    Route::post('/awards/categorie/{category}/nomineren', [AwardActionController::class, 'nominate'])->name('awards.nominate');
    Route::post('/awards/nominatie/{nomination}/stem', [AwardActionController::class, 'vote'])->name('awards.vote');
});

Route::prefix('staff')->name('staff.')->middleware(['auth', 'permission:staff.access'])->group(function () {
    Route::get('/dashboard', [StaffController::class, 'index'])->name('dashboard');
    Route::get('/awards', [AwardManagementController::class, 'index'])->name('awards');
    Route::patch('/awards/{edition}/fase', [AwardManagementController::class, 'updateEdition'])->name('awards.phase');
    Route::post('/awards/{edition}/rondes', [AwardManagementController::class, 'saveRound'])->name('awards.rounds.store');
    Route::post('/awards/nominaties/{nomination}/jury', [AwardManagementController::class, 'score'])->name('awards.jury.score');
    Route::post('/awards/{edition}/winnaars-genereren', [AwardManagementController::class, 'generateWinners'])->name('awards.winners.generate');
    Route::post('/awards/{edition}/publiceren', [AwardManagementController::class, 'publishWinners'])->name('awards.winners.publish');
    Route::post('/awards/{edition}/reveal/{position}', [AwardManagementController::class, 'revealPosition'])->name('awards.reveal.position');
    Route::patch('/awards/nominaties/{nomination}/controle', [AwardManagementController::class, 'review'])->name('awards.review');
    Route::get('/academy', [AcademyController::class, 'manage'])->name('academy');
    Route::post('/academy/deelnemers', [AcademyController::class, 'enroll'])->name('academy.enroll');
    Route::post('/academy/opdrachten/{lesson}/{student}', [AcademyController::class, 'review'])->name('academy.review');
    Route::get('/hr', [HrController::class, 'index'])->name('hr');
    Route::patch('/hr/sollicitaties/{application}', [HrController::class, 'updateApplication'])->name('hr.applications.update');
    Route::post('/discord/leden-synchroniseren', [HrController::class, 'syncDiscordMembers'])->name('discord.members.sync');
    Route::middleware('permission:content.manage')->group(function () {
        Route::get('/nieuws', [ContentController::class, 'index'])->name('news.index');
        Route::get('/nieuws/nieuw', [ContentController::class, 'create'])->name('news.create');
        Route::post('/nieuws', [ContentController::class, 'store'])->name('news.store');
        Route::get('/nieuws/{article}/bewerken', [ContentController::class, 'edit'])->name('news.edit');
        Route::put('/nieuws/{article}', [ContentController::class, 'update'])->name('news.update');
        Route::delete('/nieuws/{article}', [ContentController::class, 'destroy'])->name('news.destroy');
    });
    Route::get('/toegang', [AccessController::class, 'index'])->name('access');
    Route::put('/toegang/{user}', [AccessController::class, 'update'])->name('access.update');
    Route::patch('/nominaties/{nomination}', [StaffController::class, 'reviewNomination'])->name('nominations.review');
    Route::patch('/taken/{task}/verplaatsen', [StaffController::class, 'moveTask'])->name('tasks.move');
    Route::post('/taken/{task}/claimen', [StaffController::class, 'claimTask'])->name('tasks.claim');
    Route::post('/taken/{task}/voltooien', [StaffController::class, 'completeTask'])->name('tasks.complete');
});
