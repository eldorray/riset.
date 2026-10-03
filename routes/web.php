<?php

use App\Billing\Billing;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginLinkController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordLoginController;
use App\Http\Controllers\BrainstormController;
use App\Http\Controllers\CitationController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ManuscriptController;
use App\Http\Controllers\OutlineController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\ReferenceSearchController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WritingController;
use App\Http\Middleware\MeterAiCredits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Landing', [
    'plans' => DB::table('billing_plans')->where('active', true)->orderBy('price')->get(['id', 'name', 'price', 'credits']),
    'whatsapp' => app(Billing::class)->payment()['whatsapp'],
]))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [GoogleController::class, 'login'])->name('login');
    Route::get('/forgot-password', [PasswordController::class, 'forgot'])->name('password.request');
    Route::get('/reset-password/{token}', [PasswordController::class, 'showReset'])->name('password.reset');
    Route::post('/forgot-password', [PasswordController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
    Route::middleware('throttle:10,1')->group(function () {
        Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google');
        Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
        Route::get('/auth/link/{user}', LoginLinkController::class)->middleware('signed')->name('auth.link');
        Route::post('/masuk', PasswordLoginController::class)->name('login.password');
    });
});

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::get('/account/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/account/subscription', [SubscriptionController::class, 'store'])->middleware('throttle:10,1')->name('subscription.store');
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password');
    Route::put('/account/password', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('account.password.update');
    Route::post('/logout', [GoogleController::class, 'logout'])->name('logout');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/projects/brainstorm', BrainstormController::class)->middleware(['throttle:ai', MeterAiCredits::class])->name('projects.brainstorm');

    Route::prefix('/projects/{project}')->name('projects.')->scopeBindings()->group(function () {
        Route::get('/', [ProjectController::class, 'show'])->name('show');
        Route::patch('/archive', [ProjectController::class, 'archive'])->name('archive');
        Route::patch('/', [ProjectController::class, 'update'])->name('update');

        Route::get('/references', [ReferenceController::class, 'index'])->name('references.index');
        Route::get('/references/search', ReferenceSearchController::class)->middleware('throttle:search')->name('references.search');
        Route::post('/references/import', [ReferenceController::class, 'import'])->middleware(['throttle:ai', MeterAiCredits::class])->name('references.import');
        Route::post('/references/{reference}/read', [ReferenceController::class, 'read'])->middleware(['throttle:ai', MeterAiCredits::class])->name('references.read');
        Route::post('/references', [ReferenceController::class, 'store'])->name('references.store');
        Route::put('/references/{reference}', [ReferenceController::class, 'update'])->name('references.update');
        Route::delete('/references/{reference}', [ReferenceController::class, 'destroy'])->name('references.destroy');
        Route::post('/references/{reference}/restore', [ReferenceController::class, 'restore'])->withTrashed()->name('references.restore');

        Route::get('/citations', [CitationController::class, 'index'])->name('citations');

        Route::get('/outline', [OutlineController::class, 'show'])->name('outline');
        Route::put('/outline', [OutlineController::class, 'update'])->name('outline.update');
        Route::post('/outline/generate', [OutlineController::class, 'generate'])->middleware(['throttle:ai', MeterAiCredits::class])->name('outline.generate');

        Route::get('/writing', [WritingController::class, 'show'])->name('writing.show');
        Route::post('/writing', [WritingController::class, 'store'])->middleware('throttle:ai')->name('writing.store');
        Route::post('/writing/{run}/stop', [WritingController::class, 'stop'])->name('writing.stop');
        Route::post('/writing/{run}/review', [WritingController::class, 'review'])->name('writing.review');

        Route::get('/draft', [DraftController::class, 'show'])->name('draft');
        Route::put('/draft', [DraftController::class, 'update'])->name('draft.update');
        Route::post('/draft/generate', [DraftController::class, 'generate'])->middleware(['throttle:ai', MeterAiCredits::class])->name('draft.generate');

        Route::get('/manuscript', [ManuscriptController::class, 'show'])->name('manuscript');
        Route::put('/manuscript', [ManuscriptController::class, 'update'])->name('manuscript.update');
        Route::post('/manuscript/generate', [ManuscriptController::class, 'generate'])->middleware(['throttle:ai', MeterAiCredits::class])->name('manuscript.generate');
        Route::post('/manuscript/prepare', [ManuscriptController::class, 'prepare'])->middleware(['throttle:ai', MeterAiCredits::class])->name('manuscript.prepare');
        Route::post('/manuscript/section', [ManuscriptController::class, 'section'])->middleware(['throttle:ai', MeterAiCredits::class])->name('manuscript.section');

        Route::post('/export/docx', ExportController::class)->name('export');
    });
});

Route::middleware(['auth', 'auth.session', 'admin'])->prefix('/admin')->name('admin.')->group(function () {
    Route::get('/branding', [Admin\BrandingController::class, 'index'])->name('branding.index');
    Route::post('/branding', [Admin\BrandingController::class, 'update'])->name('branding.update');
    Route::delete('/branding', [Admin\BrandingController::class, 'destroy'])->name('branding.destroy');
    Route::get('/billing', [Admin\BillingController::class, 'index'])->name('billing.index');
    Route::put('/billing/payment', [Admin\BillingController::class, 'payment'])->name('billing.payment');
    Route::put('/billing/plans/{plan}', [Admin\BillingController::class, 'plan'])->name('billing.plan');
    Route::post('/billing/requests/{purchase}', [Admin\BillingController::class, 'decide'])->name('billing.decide');
    Route::put('/billing/users/{user}', [Admin\BillingController::class, 'user'])->name('billing.user');
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/citation-styles', [Admin\CitationStyleController::class, 'index'])->name('citation-styles.index');
    Route::put('/citation-styles', [Admin\CitationStyleController::class, 'update'])->name('citation-styles.update');

    Route::get('/document-types', [Admin\DocumentTypeController::class, 'index'])->name('document-types.index');
    Route::put('/document-types/{type}', [Admin\DocumentTypeController::class, 'update'])->name('document-types.update');
    Route::delete('/document-types/{type}', [Admin\DocumentTypeController::class, 'destroy'])->name('document-types.destroy');

    Route::get('/templates', [Admin\DocxTemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [Admin\DocxTemplateController::class, 'store'])->name('templates.store');
    Route::put('/templates/{template}', [Admin\DocxTemplateController::class, 'update'])->name('templates.update');
    Route::delete('/templates/{template}', [Admin\DocxTemplateController::class, 'destroy'])->name('templates.destroy');
});

Route::get('/branding/logo', [Admin\BrandingController::class, 'logo'])->name('branding.logo');
