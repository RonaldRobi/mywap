<?php

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
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SharePreviewController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\SuperadminOrganizationController;
use App\Http\Controllers\SuperadminSystemSettingController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\PostcodeController;
use App\Http\Controllers\UsrahController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));
Route::get('/api/postcode/lookup', [PostcodeController::class, 'lookup'])->name('postcode.lookup');
Route::get('/share/info/{newsPost}', [SharePreviewController::class, 'info'])->name('share.info');
Route::get('/share/artikel/{article:slug}', [SharePreviewController::class, 'article'])->name('share.article');
Route::get('/share/infaq/{infaq}', [SharePreviewController::class, 'infaq'])->name('share.infaq');
Route::get('/share/event/{event}', [SharePreviewController::class, 'event'])->name('share.event');

Route::get('/artikel', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/artikel/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::post('/artikel/{article:slug}/react', [ArticleController::class, 'react'])->name('articles.react');
Route::post('/artikel/{article:slug}/comments', [ArticleController::class, 'storeComment'])->name('articles.comments.store');

Route::get('/sumbangan', [InfaqController::class, 'index'])->name('infaq.index');
Route::get('/sumbangan/{year}/{month}/{day}/{infaq:slug}', [InfaqController::class, 'show'])->name('infaq.show');
Route::get('/sumbangan/{year}/{month}/{day}/{infaq:slug}/donate', [InfaqController::class, 'donateForm'])->name('infaq.donate.form');
Route::post('/sumbangan/{year}/{month}/{day}/{infaq:slug}/donate', [InfaqController::class, 'donate'])->name('infaq.donate');
Route::get('/sumbangan/{year}/{month}/{day}/{infaq:slug}/success', [InfaqController::class, 'success'])->name('infaq.success');
Route::get('/sumbangan/{year}/{month}/{day}/{infaq:slug}/qr', [InfaqController::class, 'qrCode'])->name('infaq.qr');

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
        Route::post('/admin/usrah/groups/{usrahGroup}/assign', [UsrahController::class, 'assignMembers'])->name('admin.usrah.groups.assign');
        Route::get('/admin/broadcasts', [BroadcastController::class, 'index'])->name('admin.broadcasts.index');
        Route::post('/admin/broadcasts', [BroadcastController::class, 'store'])->name('admin.broadcasts.store');
        Route::get('/admin/facilities/manage', [FacilityBookingController::class, 'manageFacilities'])->name('admin.facilities.manage');
        Route::post('/admin/facilities', [FacilityBookingController::class, 'storeFacility'])->name('admin.facilities.store');
        Route::put('/admin/facilities/{facility}', [FacilityBookingController::class, 'updateFacility'])->name('admin.facilities.update');
        Route::delete('/admin/facilities/{facility}', [FacilityBookingController::class, 'destroyFacility'])->name('admin.facilities.destroy');
        Route::get('/admin/facility-bookings', [FacilityBookingController::class, 'adminIndex'])->name('admin.facility-bookings.index');
        Route::patch('/admin/facility-bookings/{facilityBooking}', [FacilityBookingController::class, 'updateStatus'])->name('admin.facility-bookings.update');
        Route::get('/admin/info-terkini/manage', [NewsController::class, 'manage'])->name('admin.news.manage');
        Route::post('/admin/info-terkini', [NewsController::class, 'store'])->name('admin.news.store');
        Route::put('/admin/info-terkini/{newsPost}', [NewsController::class, 'update'])->name('admin.news.update');
        Route::delete('/admin/info-terkini/{newsPost}', [NewsController::class, 'destroy'])->name('admin.news.destroy');
        Route::post('/admin/info-terkini/categories', [NewsController::class, 'storeCategory'])->name('admin.news.categories.store');

        Route::get('/admin/articles/manage', [ArticleController::class, 'manage'])->name('admin.articles.manage');
        Route::post('/admin/articles', [ArticleController::class, 'store'])->name('admin.articles.store');
        Route::put('/admin/articles/{article}', [ArticleController::class, 'update'])->name('admin.articles.update');
        Route::delete('/admin/articles/{article}', [ArticleController::class, 'destroy'])->name('admin.articles.destroy');
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
        Route::post('/superadmin/settings/resend-key', [SuperadminSystemSettingController::class, 'updateResendKey'])->name('superadmin.settings.resend-key.update');
        Route::get('/superadmin/email-templates', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'index'])->name('admin.email-templates.index');
        Route::put('/superadmin/email-templates/{emailTemplate}', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'update'])->name('admin.email-templates.update');
        Route::post('/superadmin/members', [InformationHubAdminController::class, 'storeMember'])->name('superadmin.members.store');

        Route::redirect('/superadmin/logo-settings', '/superadmin/organizations');
    });

    Route::middleware('role:Member')->group(function () {
        Route::get('/member/dashboard', [MemberDashboardController::class, 'index'])->name('member.dashboard');
        Route::get('/member/financial/overview', [FinancialController::class, 'memberOverview'])->name('member.financial.overview');
        Route::get('/member/referral', [MemberDashboardController::class, 'referral'])->name('member.referral');
        Route::get('/member/announcements', [InformationHubController::class, 'announcements'])->name('member.announcements');
        Route::get('/member/library', [InformationHubController::class, 'library'])->name('member.library');
        Route::get('/member/information-hub', [InformationHubController::class, 'announcements'])->name('member.hub');
        Route::get('/member/usrah', [UsrahController::class, 'myGroup'])->name('member.usrah');
        Route::get('/member/card', [MemberCardController::class, 'show'])->name('member.card');
        Route::post('/member/usrah/{usrahGroup}/attendance', [UsrahController::class, 'logAttendance'])->name('member.usrah.attendance.log');
        Route::post('/member/pay-fee', [PaymentController::class, 'payFee'])->name('member.pay.fee');
        Route::get('/member/facilities', [FacilityBookingController::class, 'index'])->name('member.facilities.index');
        Route::get('/member/facilities/{facility}', [FacilityBookingController::class, 'show'])->name('member.facilities.show');
        Route::post('/member/facilities/{facility}/book', [FacilityBookingController::class, 'store'])->name('member.facilities.book');
    });

    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
    Route::get('/info-terkini', [NewsController::class, 'index'])->name('news.index');
    Route::get('/info-terkini/{newsPost}', [NewsController::class, 'show'])->name('news.show');
    Route::post('/info-terkini/{newsPost}/react', [NewsController::class, 'react'])->name('news.react');
    Route::post('/info-terkini/{newsPost}/comments', [NewsController::class, 'storeComment'])->name('news.comments.store');

    // ─── E-Commerce Routes (Inertia, inside dashboard) ───────────────────────
    // Member access: browse products + create/view own orders
    Route::get('products', [App\Http\Controllers\ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [App\Http\Controllers\ProductController::class, 'show'])->name('products.show');

    Route::resource('orders', App\Http\Controllers\OrderController::class)->only(['index', 'show', 'store']);

    // Admin/Superadmin: manage catalog + manage orders
    Route::middleware('role:Admin|Superadmin')->group(function () {
        Route::resource('products', App\Http\Controllers\ProductController::class)->except(['index', 'show']);
        Route::resource('categories', App\Http\Controllers\CategoryController::class);
        Route::post('orders/{order}/update-status', [App\Http\Controllers\OrderController::class, 'updateStatus'])->name('orders.updateStatus');

        // Cawangan (Branch) management — Admin manages own org, Superadmin manages all
        Route::get('branches', [App\Http\Controllers\BranchController::class, 'index'])->name('branches.index');
        Route::post('branches', [App\Http\Controllers\BranchController::class, 'store'])->name('branches.store');
        Route::put('branches/{branch}', [App\Http\Controllers\BranchController::class, 'update'])->name('branches.update');
        Route::post('branches/{branch}/logo', [App\Http\Controllers\BranchController::class, 'updateLogo'])->name('branches.logo.update');
        Route::delete('branches/{branch}', [App\Http\Controllers\BranchController::class, 'destroy'])->name('branches.destroy');



        // Positions management
        Route::get('positions', [PositionController::class, 'index'])->name('positions.index');
        Route::post('positions', [PositionController::class, 'store'])->name('positions.store');
        Route::put('positions/{position}', [PositionController::class, 'update'])->name('positions.update');
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
    });
});


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
    Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp'])->name('events.rsvp');

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
