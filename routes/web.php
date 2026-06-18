<?php

use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\AwardActionController;
use App\Http\Controllers\AwardsController;
use App\Http\Controllers\AcademyController;
use App\Http\Controllers\CommunityFormController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\MiniAwardsController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\MijnCnController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\AwardManagementController;
use App\Http\Controllers\Staff\HrController;
use App\Http\Controllers\Staff\AccessController;
use App\Http\Controllers\Staff\ContentController;
use App\Http\Controllers\StaffChatController;
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
Route::get('/mini-awards', [MiniAwardsController::class, 'index'])->name('mini.awards');
Route::get('/mini-awards/archief', [MiniAwardsController::class, 'archive'])->name('mini.awards.archive');
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
    Route::get('/mijn-cn/chat', [StaffChatController::class, 'index'])->name('mijncn.chat');
    Route::post('/mijn-cn/chat/installeren', [StaffChatController::class, 'install'])->name('mijncn.chat.install');
    Route::post('/mijn-cn/chat/start', [StaffChatController::class, 'start'])->name('mijncn.chat.start');
    Route::post('/mijn-cn/chat/groep', [StaffChatController::class, 'createGroup'])->name('mijncn.chat.groups.store');
    Route::put('/mijn-cn/chat/groep/{conversation}', [StaffChatController::class, 'updateGroup'])->name('mijncn.chat.groups.update');
    Route::post('/mijn-cn/chat/{conversation}/dempen', [StaffChatController::class, 'mute'])->name('mijncn.chat.mute');
    Route::post('/mijn-cn/chat/{conversation}/archiveren', [StaffChatController::class, 'archive'])->name('mijncn.chat.archive');
    Route::prefix('api/chat')->name('chat.api.')->group(function () {
        Route::get('/conversations', [StaffChatController::class, 'conversationsApi'])->name('conversations');
        Route::get('/messages', [StaffChatController::class, 'messagesApi'])->name('messages');
        Route::get('/search', [StaffChatController::class, 'search'])->middleware('throttle:30,1')->name('search');
        Route::post('/send', [StaffChatController::class, 'sendApi'])->middleware('throttle:30,1')->name('send');
        Route::post('/typing', [StaffChatController::class, 'typing'])->middleware('throttle:90,1')->name('typing');
        Route::post('/read', [StaffChatController::class, 'read'])->name('read');
        Route::get('/presence', [StaffChatController::class, 'presence'])->name('presence');
        Route::patch('/messages/{message}', [StaffChatController::class, 'updateMessage'])->middleware('throttle:30,1')->name('messages.update');
        Route::delete('/messages/{message}', [StaffChatController::class, 'deleteMessage'])->middleware('throttle:30,1')->name('messages.delete');
        Route::post('/messages/{message}/reaction', [StaffChatController::class, 'react'])->middleware('throttle:60,1')->name('messages.react');
        Route::post('/messages/{message}/pin', [StaffChatController::class, 'pin'])->name('messages.pin');
        Route::post('/messages/{message}/acknowledge', [StaffChatController::class, 'acknowledge'])->name('messages.acknowledge');
        Route::post('/messages/{message}/task', [StaffChatController::class, 'createTask'])->name('messages.task');
    });
    Route::patch('/mijn-cn/profiel/bijwerken', [MijnCnController::class, 'updateProfile'])->name('mijncn.profile.update');
    Route::post('/mijn-cn/meldingen/gelezen', [MijnCnController::class, 'markNotificationsRead'])->name('mijncn.notifications.read');
    Route::post('/mijn-cn/nomi/vragen', [MijnCnController::class, 'askNomi'])->middleware('throttle:10,1')->name('mijncn.nomi.ask');
    Route::post('/mijn-cn/taken/{task}/claimen', [MijnCnController::class, 'claimTask'])->name('mijncn.tasks.claim');
    Route::post('/mijn-cn/taken/{task}/voltooien', [MijnCnController::class, 'completeTask'])->name('mijncn.tasks.complete');
    Route::get('/mijn-cn/nominaties/{nomination}/bewerken', [AwardActionController::class, 'editProfile'])->name('mijncn.nominations.edit');
    Route::put('/mijn-cn/nominaties/{nomination}', [AwardActionController::class, 'updateProfile'])->name('mijncn.nominations.update');
    Route::post('/mijn-cn/partners', [MijnCnController::class, 'storePartner'])->name('mijncn.partners.store');
    Route::post('/mijn-cn/partners/database-bijwerken', [MijnCnController::class, 'upgradePartnerRankings'])->name('mijncn.partners.upgrade');
    Route::put('/mijn-cn/partners/{partner}', [MijnCnController::class, 'updatePartner'])->name('mijncn.partners.update');
    Route::delete('/mijn-cn/partners/{partner}', [MijnCnController::class, 'destroyPartner'])->name('mijncn.partners.destroy');
    Route::post('/mijn-cn/afwezigheid', [MijnCnController::class, 'reportAbsence'])->name('mijncn.absences.store');
    Route::delete('/mijn-cn/afwezigheid/{absence}', [MijnCnController::class, 'cancelAbsence'])->name('mijncn.absences.cancel');
    Route::get('/mijn-cn/{module}', [MijnCnController::class, 'show'])
        ->whereIn('module', ['profile', 'notifications', 'inbox', 'nominations', 'votes', 'results', 'lessons', 'exams', 'certificates', 'badges', 'tasks', 'nomi', 'settings', 'absences', 'birthdays', 'community', 'partners'])
        ->name('mijncn.module');
    Route::post('/awards/categorie/{category}/nomineren', [AwardActionController::class, 'nominate'])->name('awards.nominate');
    Route::post('/awards/nominatie/{nomination}/stem', [AwardActionController::class, 'vote'])->name('awards.vote');
});

Route::prefix('staff')->name('staff.')->middleware(['auth', 'permission:staff.access'])->group(function () {
    Route::get('/dashboard', [StaffController::class, 'index'])->name('dashboard');
    Route::get('/awards', [AwardManagementController::class, 'index'])->name('awards');
    Route::get('/mini-awards', [AwardManagementController::class, 'miniIndex'])->name('mini-awards');
    Route::patch('/awards/{edition}/fase', [AwardManagementController::class, 'updateEdition'])->name('awards.phase');
    Route::post('/awards/{edition}/rondes', [AwardManagementController::class, 'saveRound'])->name('awards.rounds.store');
    Route::post('/awards/{edition}/categorieen', [AwardManagementController::class, 'storeCategory'])->name('awards.categories.store');
    Route::put('/awards/categorieen/{category}', [AwardManagementController::class, 'updateCategory'])->name('awards.categories.update');
    Route::delete('/awards/categorieen/{category}', [AwardManagementController::class, 'destroyCategory'])->name('awards.categories.destroy');
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
