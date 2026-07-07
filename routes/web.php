<?php

use App\Http\Controllers\BayarCashController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FacilityBookingController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\DashboardBannerController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InfaqController;
use App\Http\Controllers\FinancialController;
use App\Http\Controllers\InformationHubController;
use App\Http\Controllers\InformationHubAdminController;
use App\Http\Controllers\MemberDashboardController;
use App\Http\Controllers\MemberCardController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\MemberFeeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PopupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCardController;
use App\Http\Controllers\SharePreviewController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\SuperadminOrganizationController;
use App\Http\Controllers\SuperadminSystemSettingController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\PostcodeController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\UsrahController;
use App\Http\Controllers\VideoController;
use App\Models\Poll;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));
Route::get('/api/postcode/lookup', [PostcodeController::class, 'lookup'])->name('postcode.lookup')->middleware('throttle:30,1');
Route::get('/share/info/{newsPost}', [SharePreviewController::class, 'info'])->name('share.info')->middleware('throttle:30,1');
Route::get('/share/artikel/{article:slug}', [SharePreviewController::class, 'article'])->name('share.article')->middleware('throttle:30,1');
Route::get('/share/infaq/{infaq}', [SharePreviewController::class, 'infaq'])->name('share.infaq')->middleware('throttle:30,1');
Route::get('/share/event/{event}', [SharePreviewController::class, 'event'])->name('share.event')->middleware('throttle:30,1');
Route::get('/kad/{memberNo}', [PublicCardController::class, 'show'])->name('public.card')->middleware('throttle:60,1');

Route::get('/privasi', fn () => inertia('PrivacyPolicy'))->name('privacy');
Route::get('/terma-syarat', fn () => inertia('TermsConditions'))->name('terms');

Route::post('/__deploy/{token}', DeployController::class);

Route::get('/artikel', [ArticleController::class, 'index'])->name('articles.index')->middleware('throttle:60,1');
Route::get('/artikel/{article:slug}', [ArticleController::class, 'show'])->name('articles.show')->middleware('throttle:60,1');
Route::post('/artikel/{article:slug}/react', [ArticleController::class, 'react'])->name('articles.react')->middleware('throttle:30,1');
Route::post('/artikel/{article:slug}/comments', [ArticleController::class, 'storeComment'])->name('articles.comments.store')->middleware('throttle:10,1');

Route::get('/sumbangan', [InfaqController::class, 'index'])->name('infaq.index')->middleware('throttle:60,1');
Route::get('/sumbangan/{year}/{month}/{day}/{infaq:slug}', [InfaqController::class, 'show'])->name('infaq.show')->middleware('throttle:60,1');
Route::get('/sumbangan/{year}/{month}/{day}/{infaq:slug}/donate', [InfaqController::class, 'donateForm'])->name('infaq.donate.form')->middleware('throttle:60,1');
Route::post('/sumbangan/{year}/{month}/{day}/{infaq:slug}/donate', [InfaqController::class, 'donate'])->name('infaq.donate')->middleware('throttle:10,1');
Route::get('/sumbangan/{year}/{month}/{day}/{infaq:slug}/success', [InfaqController::class, 'success'])->name('infaq.success')->middleware('throttle:60,1');
Route::get('/sumbangan/{year}/{month}/{day}/{infaq:slug}/qr', [InfaqController::class, 'qrCode'])->name('infaq.qr')->middleware('throttle:60,1');

Route::post('/bayarcash/callback', [BayarCashController::class, 'callback'])->name('bayarcash.callback');
Route::post('/bayarcash/direct-debit/callback', [BayarCashController::class, 'directDebitCallback'])->name('bayarcash.direct-debit.callback');
Route::get('/bayarcash/redirect', [BayarCashController::class, 'redirect'])->name('bayarcash.redirect');

Route::get('/s/{infaq:slug}', fn (\App\Models\Infaq $infaq) => redirect()->route('infaq.show', [
    'year' => $infaq->year,
    'month' => $infaq->month,
    'day' => $infaq->day,
    'infaq' => $infaq->slug,
], 301))->name('infaq.short');

Route::middleware(['auth', 'verified', 'profile_complete'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboardRedirect'])->name('dashboard');

    Route::middleware('role:Superadmin|Admin')->group(function () {
            Route::get('/admin/attendance', [EventController::class, 'adminAttendance'])->name('admin.attendance');
        Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');
        Route::get('/admin/campaigns', [FinancialController::class, 'adminCampaigns'])->name('admin.campaigns.index');
        Route::post('/admin/campaigns', [FinancialController::class, 'storeCampaign'])->name('admin.campaigns.store');
        Route::get('/admin/information-hub/manage', [InformationHubAdminController::class, 'index'])->name('admin.hub.manage');
        Route::patch('/admin/information-hub/members/{user}/role', [InformationHubAdminController::class, 'updateRole'])->name('admin.hub.members.role.update');
        Route::patch('/admin/information-hub/members/{user}/ic-number', [InformationHubAdminController::class, 'updateIcNumber'])->name('admin.hub.members.ic.update');
        Route::patch('/admin/information-hub/members/{user}/toggle-active', [InformationHubAdminController::class, 'toggleActive'])->name('admin.hub.members.toggle-active');
        Route::post('/admin/information-hub/members/{user}/reset-password', [InformationHubAdminController::class, 'resetPassword'])->name('admin.hub.members.reset-password');
        Route::get('/admin/information-hub/members/{targetUser}/logs', [InformationHubAdminController::class, 'activityLog'])->name('admin.hub.members.logs');
        Route::post('/admin/information-hub/members/import-start', [InformationHubAdminController::class, 'importStart'])->name('admin.hub.members.importStart');
        Route::post('/admin/information-hub/members/import-chunk', [InformationHubAdminController::class, 'importChunk'])->name('admin.hub.members.importChunk');
        Route::post('/admin/information-hub/members/import-finish', [InformationHubAdminController::class, 'importFinish'])->name('admin.hub.members.importFinish');
        Route::post('/admin/information-hub/announcements', [InformationHubAdminController::class, 'storeAnnouncement'])->name('admin.hub.announcements.store');
        Route::put('/admin/information-hub/announcements/{announcement}', [InformationHubAdminController::class, 'updateAnnouncement'])->name('admin.hub.announcements.update');
        Route::patch('/admin/information-hub/announcements/{announcement}/pin', [InformationHubAdminController::class, 'togglePinned'])->name('admin.hub.announcements.pin');
        Route::delete('/admin/information-hub/announcements/{announcement}', [InformationHubAdminController::class, 'destroyAnnouncement'])->name('admin.hub.announcements.destroy');
        Route::post('/admin/information-hub/library', [InformationHubAdminController::class, 'storeLibraryItem'])->name('admin.hub.library.store');
        Route::put('/admin/information-hub/library/{libraryItem}', [InformationHubAdminController::class, 'updateLibraryItem'])->name('admin.hub.library.update');
        Route::delete('/admin/information-hub/library/{libraryItem}', [InformationHubAdminController::class, 'destroyLibraryItem'])->name('admin.hub.library.destroy');

        // Admin: monitor-only transactions
        Route::get('/admin/transactions', [PaymentController::class, 'orgTransactions'])->name('admin.transactions');
        Route::get('/admin/members/export', [ExportController::class, 'exportMembers'])->name('admin.members.export');
        Route::get('/admin/usrah', [UsrahController::class, 'adminIndex'])->name('admin.usrah.index');
        Route::post('/admin/usrah/groups', [UsrahController::class, 'storeGroup'])->name('admin.usrah.groups.store');
        Route::put('/admin/usrah/groups/{usrahGroup}', [UsrahController::class, 'updateGroup'])->name('admin.usrah.groups.update');
        Route::delete('/admin/usrah/groups/{usrahGroup}', [UsrahController::class, 'deleteGroup'])->name('admin.usrah.groups.delete');
        Route::post('/admin/usrah/groups/{usrahGroup}/assign', [UsrahController::class, 'assignMembers'])->name('admin.usrah.groups.assign');
        Route::get('/admin/broadcasts', [BroadcastController::class, 'index'])->name('admin.broadcasts.index');
        Route::post('/admin/broadcasts', [BroadcastController::class, 'store'])->name('admin.broadcasts.store');
        Route::get('/admin/facilities/manage', [FacilityBookingController::class, 'manageFacilities'])->name('admin.facilities.manage');
        Route::post('/admin/facilities', [FacilityBookingController::class, 'storeFacility'])->name('admin.facilities.store');
        Route::put('/admin/facilities/{facility}', [FacilityBookingController::class, 'updateFacility'])->name('admin.facilities.update');
        Route::delete('/admin/facilities/{facility}', [FacilityBookingController::class, 'destroyFacility'])->name('admin.facilities.destroy');
        Route::get('/admin/facility-bookings', [FacilityBookingController::class, 'adminIndex'])->name('admin.facility-bookings.index');
        Route::patch('/admin/facility-bookings/{facilityBooking}', [FacilityBookingController::class, 'updateStatus'])->name('admin.facility-bookings.update');
        // Polls / Surveys
        Route::get('/admin/polls', [PollController::class, 'adminIndex'])->name('admin.polls.index');
        Route::get('/admin/polls/create', [PollController::class, 'adminCreate'])->name('admin.polls.create');
        Route::post('/admin/polls', [PollController::class, 'adminStore'])->name('admin.polls.store');
        Route::get('/admin/polls/{poll}', [PollController::class, 'adminEdit'])->name('admin.polls.edit');
        Route::put('/admin/polls/{poll}', [PollController::class, 'adminUpdate'])->name('admin.polls.update');
        Route::delete('/admin/polls/{poll}', [PollController::class, 'adminDestroy'])->name('admin.polls.destroy');
        Route::get('/admin/polls/{poll}/results', [PollController::class, 'adminResults'])->name('admin.polls.results');
        Route::get('/admin/polls/{poll}/export', [PollController::class, 'exportCsv'])->name('admin.polls.export');

        Route::get('/admin/info-terkini/manage', [NewsController::class, 'manage'])->name('admin.news.manage');
        Route::post('/admin/info-terkini', [NewsController::class, 'store'])->name('admin.news.store');
        Route::put('/admin/info-terkini/{newsPost}', [NewsController::class, 'update'])->name('admin.news.update');
        Route::delete('/admin/info-terkini/{newsPost}', [NewsController::class, 'destroy'])->name('admin.news.destroy');
        Route::post('/admin/info-terkini/categories', [NewsController::class, 'storeCategory'])->name('admin.news.categories.store');

        Route::get('/admin/articles', [ArticleController::class, 'adminIndex'])->name('admin.articles.index');
        Route::get('/admin/articles/create', [ArticleController::class, 'adminCreate'])->name('admin.articles.create');
        Route::post('/admin/articles', [ArticleController::class, 'store'])->name('admin.articles.store');
        Route::get('/admin/articles/{article}/edit', [ArticleController::class, 'adminEdit'])->name('admin.articles.edit');
        Route::put('/admin/articles/{article}', [ArticleController::class, 'update'])->name('admin.articles.update');
        Route::delete('/admin/articles/{article}', [ArticleController::class, 'destroy'])->name('admin.articles.destroy');
        Route::delete('/admin/articles/media/{media}', [ArticleController::class, 'deleteMedia'])->name('admin.articles.media.destroy');

        // Video management (Admin + Superadmin)
        Route::get('/admin/videos/manage', [VideoController::class, 'manage'])->name('admin.videos.manage');
        Route::post('/admin/videos', [VideoController::class, 'store'])->name('admin.videos.store');
        Route::put('/admin/videos/{video}', [VideoController::class, 'update'])->name('admin.videos.update');
        Route::delete('/admin/videos/{video}', [VideoController::class, 'destroy'])->name('admin.videos.destroy');

        // Popup management (Admin & Superadmin)
        Route::get('/admin/popups', [PopupController::class, 'index'])->name('admin.popups.index');
        Route::post('/admin/popups', [PopupController::class, 'store'])->name('admin.popups.store');
        Route::put('/admin/popups/{popup}', [PopupController::class, 'update'])->name('admin.popups.update');
        Route::delete('/admin/popups/{popup}', [PopupController::class, 'destroy'])->name('admin.popups.destroy');
    });

    // Superadmin-only: fee management + all transactions
    Route::middleware('role:Superadmin')->group(function () {
        Route::get('/superadmin/fees', [PaymentController::class, 'feesConfig'])->name('superadmin.fees.index');
        Route::put('/superadmin/fees/{organization}', [PaymentController::class, 'updateFee'])->name('superadmin.fees.update');
        Route::get('/superadmin/transactions', [PaymentController::class, 'allTransactions'])->name('superadmin.transactions');
        Route::patch('/superadmin/transactions/{payment}', [PaymentController::class, 'updateTransactionStatus'])->name('superadmin.transactions.update');
        Route::get('/superadmin/pustaka/manage', [InformationHubAdminController::class, 'libraryIndex'])->name('admin.library.manage');
        Route::get('/superadmin/dashboard-banners', [DashboardBannerController::class, 'index'])->name('superadmin.banners.index');
        Route::post('/superadmin/dashboard-banners', [DashboardBannerController::class, 'store'])->name('superadmin.banners.store');
        Route::post('/superadmin/dashboard-banners/seed-demo', [DashboardBannerController::class, 'seedDemo'])->name('superadmin.banners.seed');
        Route::put('/superadmin/dashboard-banners/{dashboardBanner}', [DashboardBannerController::class, 'update'])->name('superadmin.banners.update');
        Route::delete('/superadmin/dashboard-banners/{dashboardBanner}', [DashboardBannerController::class, 'destroy'])->name('superadmin.banners.destroy');
        // Infaq management (Superadmin only)
        Route::get('/superadmin/infaq', [InfaqController::class, 'manage'])->name('superadmin.infaq.index');
        Route::post('/superadmin/infaq', [InfaqController::class, 'store'])->name('superadmin.infaq.store');
        Route::post('/superadmin/infaq/seed-demo', [InfaqController::class, 'seedDemo'])->name('superadmin.infaq.seed');
        Route::put('/superadmin/infaq/{infaq}', [InfaqController::class, 'update'])->name('superadmin.infaq.update');
        Route::delete('/superadmin/infaq/{infaq}', [InfaqController::class, 'destroy'])->name('superadmin.infaq.destroy');
        Route::get('/superadmin/infaq/{infaq}/qr', [InfaqController::class, 'qrCode'])->name('superadmin.infaq.qr');
        Route::get('/superadmin/organizations', [SuperadminOrganizationController::class, 'index'])->name('superadmin.organizations.index');
        Route::put('/superadmin/organizations/{organization}', [SuperadminOrganizationController::class, 'update'])->name('superadmin.organizations.update');
        Route::post('/superadmin/organizations/{organization}/logo', [SuperadminOrganizationController::class, 'updateLogo'])->name('superadmin.organizations.logo.update');

        Route::get('/superadmin/settings', [SuperadminSystemSettingController::class, 'index'])->name('superadmin.settings.index');
        Route::post('/superadmin/settings/system-logo', [SuperadminSystemSettingController::class, 'updateSystemLogo'])->name('superadmin.settings.system-logo.update');
        Route::post('/superadmin/settings/splash', [SuperadminSystemSettingController::class, 'updateSplashSetting'])->name('superadmin.settings.splash.update');
        Route::post('/superadmin/settings/admin-contact', [SuperadminSystemSettingController::class, 'updateAdminContact'])->name('superadmin.settings.admin-contact.update');
        Route::post('/superadmin/settings/chatbot-logo', [SuperadminSystemSettingController::class, 'updateChatbotLogo'])->name('superadmin.settings.chatbot-logo.update');
        Route::post('/superadmin/settings/resend-key', [SuperadminSystemSettingController::class, 'updateResendKey'])->name('superadmin.settings.resend-key.update');
        Route::post('/superadmin/settings/gemini-key', [SuperadminSystemSettingController::class, 'updateGeminiKey'])->name('superadmin.settings.gemini-key.update');
        Route::post('/superadmin/settings/app-name', [SuperadminSystemSettingController::class, 'updateAppName'])->name('superadmin.settings.app-name.update');
        Route::post('/superadmin/settings/og-image', [SuperadminSystemSettingController::class, 'updateOgImage'])->name('superadmin.settings.og-image.update');
        Route::get('/superadmin/email-templates', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'index'])->name('admin.email-templates.index');
        Route::put('/superadmin/email-templates/{emailTemplate}', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'update'])->name('admin.email-templates.update');
        Route::post('/superadmin/members', [InformationHubAdminController::class, 'storeMember'])->name('superadmin.members.store');

        Route::get('/superadmin/knowledge-base', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'index'])->name('admin.knowledge-base.index');
        Route::post('/superadmin/knowledge-base', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'store'])->name('admin.knowledge-base.store');
        Route::post('/superadmin/knowledge-base/{knowledgeArticle}', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'update'])->name('admin.knowledge-base.update');
        Route::delete('/superadmin/knowledge-base/{knowledgeArticle}', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'destroy'])->name('admin.knowledge-base.destroy');

        Route::redirect('/superadmin/logo-settings', '/superadmin/organizations');
    });

    Route::middleware('role:Member')->group(function () {
        Route::get('/member/dashboard', [MemberDashboardController::class, 'index'])->name('member.dashboard');
        Route::get('/member/financial/overview', [FinancialController::class, 'memberOverview'])->name('member.financial.overview');
        Route::get('/member/referral', [MemberDashboardController::class, 'referral'])->name('member.referral');
        Route::get('/member/announcements', [InformationHubController::class, 'announcements'])->name('member.announcements');
        Route::post('/member/announcements/{announcement}/react', [InformationHubController::class, 'react'])->name('member.announcements.react');
        Route::post('/member/announcements/{announcement}/read', [InformationHubController::class, 'markRead'])->name('member.announcements.read');
        Route::get('/member/library', [InformationHubController::class, 'library'])->name('member.library');
        Route::get('/member/information-hub', [InformationHubController::class, 'announcements'])->name('member.hub');
        Route::get('/member/usrah', [UsrahController::class, 'myGroup'])->name('member.usrah');
        Route::get('/member/card', [MemberCardController::class, 'show'])->name('member.card');
        Route::post('/member/usrah/{usrahGroup}/attendance', [UsrahController::class, 'logAttendance'])->name('member.usrah.attendance.log');
        Route::post('/member/pay-fee', [PaymentController::class, 'payFee'])->name('member.pay.fee');
        Route::get('/member/facilities', [FacilityBookingController::class, 'index'])->name('member.facilities.index');
        Route::get('/member/facilities/{facility}', [FacilityBookingController::class, 'show'])->name('member.facilities.show');
        Route::post('/member/facilities/{facility}/book', [FacilityBookingController::class, 'store'])->name('member.facilities.book');

        // Polls / Surveys
        Route::bind('poll', fn($value) => Poll::withoutGlobalScopes()->findOrFail($value));
        Route::get('/member/videos', [VideoController::class, 'memberIndex'])->name('member.videos.index');
        Route::get('/polls', [PollController::class, 'index'])->name('member.polls.index');
        Route::get('/polls/{poll}', [PollController::class, 'show'])->name('member.polls.show');
        Route::post('/polls/{poll}/respond', [PollController::class, 'respond'])->name('member.polls.respond');
        Route::get('/polls/{poll}/results', [PollController::class, 'results'])->name('member.polls.results');
    });

    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index')->middleware('role:Superadmin|Admin');
    Route::get('/info-terkini', [NewsController::class, 'index'])->name('news.index');
    Route::get('/info-terkini/{newsPost}', [NewsController::class, 'show'])->name('news.show');
    Route::post('/info-terkini/{newsPost}/react', [NewsController::class, 'react'])->name('news.react');
    Route::post('/info-terkini/{newsPost}/comments', [NewsController::class, 'storeComment'])->name('news.comments.store');

    // ─── E-Commerce Routes (Inertia, inside dashboard) ───────────────────────
    // Member access: browse products + create/view own orders
    Route::get('products', [App\Http\Controllers\ProductController::class, 'index'])->name('products.index');
    Route::resource('orders', App\Http\Controllers\OrderController::class)->only(['index', 'show', 'store']);
    Route::get('orders/{order}/pay', [App\Http\Controllers\OrderController::class, 'pay'])->name('orders.pay');

    // Admin/Superadmin: manage catalog + manage orders
    Route::middleware('role:Admin|Superadmin')->group(function () {
        Route::resource('products', App\Http\Controllers\ProductController::class)->except(['index', 'show']);

        Route::resource('categories', App\Http\Controllers\CategoryController::class)->except(['show']);
        Route::post('orders/{order}/update-status', [App\Http\Controllers\OrderController::class, 'updateStatus'])->name('orders.updateStatus');

        // Cawangan (Branch) management — Admin manages own org, Superadmin manages all
        Route::get('branches', [App\Http\Controllers\BranchController::class, 'index'])->name('branches.index');
        Route::post('branches', [App\Http\Controllers\BranchController::class, 'store'])->name('branches.store');
        Route::put('branches/{branch}', [App\Http\Controllers\BranchController::class, 'update'])->name('branches.update');
        Route::post('branches/{branch}/logo', [App\Http\Controllers\BranchController::class, 'updateLogo'])->name('branches.logo.update');
        Route::delete('branches/{branch}/logo', [App\Http\Controllers\BranchController::class, 'deleteLogo'])->name('branches.logo.destroy');
        Route::delete('branches/{branch}', [App\Http\Controllers\BranchController::class, 'destroy'])->name('branches.destroy');

        // Branch admin management
        Route::post('branches/{branch}/admins', [App\Http\Controllers\BranchController::class, 'storeAdmin'])->name('branches.admins.store');
        Route::delete('branches/{branch}/admins/{user}', [App\Http\Controllers\BranchController::class, 'destroyAdmin'])->name('branches.admins.destroy');


        // Positions management
        Route::get('positions', [PositionController::class, 'index'])->name('positions.index');
        Route::post('positions', [PositionController::class, 'store'])->name('positions.store');
        Route::post('positions/reorder', [PositionController::class, 'reorder'])->name('positions.reorder');
        Route::put('positions/{position}', [PositionController::class, 'update'])->name('positions.update');
        Route::patch('positions/{position}/toggle', [PositionController::class, 'toggleActive'])->name('positions.toggle');
        Route::delete('positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');

        // Finance Dashboard
        Route::get('/admin/finance', [App\Http\Controllers\AdminFinanceController::class, 'index'])->name('admin.finance.index');
        Route::get('/admin/finance/member/{targetUser}', [App\Http\Controllers\AdminFinanceController::class, 'memberTransactions'])->name('admin.finance.member');
        Route::get('/admin/finance/export/pdf', [App\Http\Controllers\AdminFinanceController::class, 'exportPdf'])->name('admin.finance.export.pdf');
        Route::get('/admin/finance/export/excel', [App\Http\Controllers\AdminFinanceController::class, 'exportExcel'])->name('admin.finance.export.excel');
        Route::get('/admin/finance/export/csv', [App\Http\Controllers\AdminFinanceController::class, 'exportCsv'])->name('admin.finance.export.csv');

        // Member fee management
        Route::get('/admin/fees/members', [MemberFeeController::class, 'index'])->name('admin.fees.members');
        Route::get('/admin/fees/members/payments/{targetUser}', [MemberFeeController::class, 'paymentHistory'])->name('admin.fees.members.payments');
        Route::get('/admin/fees/members/logs/{targetUser}', [MemberFeeController::class, 'activityLog'])->name('admin.fees.members.logs');
        Route::get('/admin/fees/members/fee-detail/{targetUser}/{membershipFee}', [MemberFeeController::class, 'feeDetail'])->name('admin.fees.members.fee-detail');
        Route::post('/admin/fees/members/fee-update/{targetUser}/{membershipFee}', [MemberFeeController::class, 'updateMemberFee'])->name('admin.fees.members.fee-update');
        Route::post('/admin/fees/members/generate', [MemberFeeController::class, 'generateFees'])->name('admin.fees.members.generate');
        Route::get('/admin/fees/members/receipt/{payment}', [MemberFeeController::class, 'downloadReceipt'])->name('admin.fees.members.receipt');
        Route::post('/admin/fees/members/reverse/{payment}', [MemberFeeController::class, 'reversePayment'])->name('admin.fees.members.reverse');
        Route::get('/admin/fees/members/import/template', [MemberFeeController::class, 'downloadTemplate'])->name('admin.fees.members.import.template');
        Route::post('/admin/fees/members/manual-pay', [MemberFeeController::class, 'manualPayment'])->name('admin.fees.members.manual-pay');
        Route::post('/admin/fees/members/import/preview', [MemberFeeController::class, 'previewImport'])->name('admin.fees.members.import.preview');
        Route::post('/admin/fees/members/import/process', [MemberFeeController::class, 'processImport'])->name('admin.fees.members.import.process');
        Route::get('/admin/fees/members/export/csv', [MemberFeeController::class, 'exportCsv'])->name('admin.fees.members.export.csv');
        Route::get('/admin/fees/members/export/excel', [MemberFeeController::class, 'exportExcel'])->name('admin.fees.members.export.excel');
        Route::get('/admin/fees/members/export/pdf', [MemberFeeController::class, 'exportPdf'])->name('admin.fees.members.export.pdf');
        Route::post('/admin/fees/members/{targetUser}/life-member', [MemberFeeController::class, 'toggleLifeMember'])->name('admin.fees.members.life-member');
        Route::post('/admin/fees/members/{targetUser}/exempted', [MemberFeeController::class, 'markExempted'])->name('admin.fees.members.exempted');

        // Admin update member profile
        Route::patch('/admin/members/{user}/update', [InformationHubAdminController::class, 'updateMember'])->name('admin.members.update');

        // Bulk branch change
        Route::get('/admin/bulk-branch', [InformationHubAdminController::class, 'bulkBranch'])->name('admin.bulk-branch');
        Route::patch('/admin/members/bulk-branch', [InformationHubAdminController::class, 'bulkBranchUpdate'])->name('admin.members.bulk-branch');
    });

    Route::get('products/{product}', [App\Http\Controllers\ProductController::class, 'show'])->name('products.show');

    // Branch change requests — Org Admin, Branch Admin
    Route::middleware('role:Admin|Superadmin|Admin Cawangan')->group(function () {
        Route::get('branch-change-requests', [App\Http\Controllers\BranchChangeRequestController::class, 'index'])->name('branch-change-requests.index');
        Route::post('branch-change-requests/{branchChangeRequest}/approve', [App\Http\Controllers\BranchChangeRequestController::class, 'approve'])->name('branch-change-requests.approve');
        Route::post('branch-change-requests/{branchChangeRequest}/reject', [App\Http\Controllers\BranchChangeRequestController::class, 'reject'])->name('branch-change-requests.reject');
    });
});


// ─── Chatbot API ─────────────────────────────────────────────────────────────
Route::post('/api/chat', [App\Http\Controllers\ChatController::class, 'send'])
    ->name('api.chat.send')
    ->middleware('throttle:30,1');

// ─── Authenticated Member Routes ─────────────────────────────────────────────

Route::middleware('auth')->group(function () {

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Force profile update flow (intentionally outside profile_complete middleware blocking)
    Route::get('/member/complete-profile', [ProfileController::class, 'completeProfile'])->name('member.complete-profile');
    Route::post('/member/complete-profile', [ProfileController::class, 'storeCompleteProfile'])->name('member.complete-profile.store');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/journey', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Events — browse + RSVP
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');
    Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp'])->name('events.rsvp');
    Route::post('/events/{event}/comments', [EventController::class, 'storeComment'])->name('events.comments.store');

    // QR Attendance scan endpoint — auth required so we can attribute the scan
    // to the authenticated user.  If user is a guest, Laravel redirects to login
    // and stores this URL as the `intended` destination.
    Route::get('/events/{id}/attend/{token}', [EventController::class, 'recordAttendance'])
         ->name('events.attend');

    // ─── Admin / Staff Only ──────────────────────────────────────────────────
    Route::middleware('role:Admin|Superadmin')->group(function () {
           Route::post('/events', [EventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}/qr', [EventController::class, 'showQr'])
             ->name('events.qr');
        Route::get('/events/{event}/print', [EventController::class, 'printAttendance'])
             ->name('events.print');
    });
});

require __DIR__.'/auth.php';
