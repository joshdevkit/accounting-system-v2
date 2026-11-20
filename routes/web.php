<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CityLedgerController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\IntercompanyJournalController;
use App\Http\Controllers\IntercompanyVoucherController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PosEntryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorLedgerController;
use App\Http\Middleware\EnsureCompanyIsConfigured;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/settings/company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('/settings/company', [CompanyController::class, 'update'])->name('company.update');
    Route::resource('branches', BranchController::class)->except('show');
    Route::get('/contacts/vendor-options', [ContactController::class, 'vendorOptions'])->name('contacts.vendor-options');
    Route::get('/contacts/customer-options', [ContactController::class, 'customerOptions'])->name('contacts.customer-options');
    Route::resource('contacts', ContactController::class);
    Route::post('contacts/import', [ContactController::class, 'import'])->name('contacts.import');
    Route::get('city-ledger', [CityLedgerController::class, 'index'])->name('city-ledger.index');
    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class)->except('show');

    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/settings/password', [ProfileController::class, 'editPassword'])->name('password.edit');
    Route::put('/settings/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::get('/settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');
    /*
    | Everything below needs a company to hang its data on, so the routes are
    | grouped behind a guard that sends the user to settings when there is none.
    */
    Route::middleware(EnsureCompanyIsConfigured::class)->group(function () {
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
        // Fiscal years and their accounting periods.
        Route::post('/fiscal-years/{fiscal_year}/periods', [FiscalYearController::class, 'generatePeriods'])->name('fiscal-years.periods.generate');
        Route::patch('/fiscal-years/{fiscal_year}/status', [FiscalYearController::class, 'updateStatus'])->name('fiscal-years.status');
        Route::patch('/accounting-periods/{accounting_period}/status', [AccountingPeriodController::class, 'updateStatus'])->name('accounting-periods.status');
        Route::resource('fiscal-years', FiscalYearController::class)->except('show');

        // Chart of accounts.
        Route::patch('/accounts/{account}/active', [AccountController::class, 'toggleActive'])->name('accounts.active');
        Route::resource('accounts', AccountController::class)->except('show');

        // Manual journal entries.
        Route::post('/journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');
        Route::post('/journal-entries/{journal_entry}/unpost', [JournalEntryController::class, 'unpost'])->name('journal-entries.unpost');
        Route::post('/journal-entries/{journal_entry}/reverse', [JournalEntryController::class, 'reverse'])->name('journal-entries.reverse');
        Route::resource('journal-entries', JournalEntryController::class);

        // POS day summaries, which post themselves into the ledger.
        Route::post('/pos-entries/{pos_entry}/post', [PosEntryController::class, 'post'])->name('pos-entries.post');
        Route::post('/pos-entries/{pos_entry}/void', [PosEntryController::class, 'void'])->name('pos-entries.void');
        Route::resource('pos-entries', PosEntryController::class);
        // Reports, derived from the ledger.
        Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');
        Route::get('/reports/ledger', [ReportController::class, 'ledger'])->name('reports.ledger');
        // Income statement
        Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement'])->name('reports.income-statement');


        // Expenses.
        Route::post('/expenses/{expense}/post', [ExpenseController::class, 'post'])->name('expenses.post');
        Route::post('/expenses/{expense}/intercompany-journal', [IntercompanyJournalController::class, 'expense'])->name('expenses.intercompany-journal');
        Route::post('/expenses/{expense}/intercompany-voucher', [IntercompanyVoucherController::class, 'expense'])->name('expenses.intercompany-voucher');
        Route::post('/expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');
        Route::resource('expenses', ExpenseController::class);
        Route::post('/expenses/{expense}/pay', [ExpenseController::class, 'pay'])->name('expenses.pay');
        //new route
        Route::get('/expenses/{expense}/check-voucher', [ExpenseController::class, 'checkVoucher'])->middleware('permission:record expenses')->name('expenses.check-voucher');

        /**
         * PURCHASES ROUTE
         */
        Route::post('/purchases/{purchase}/post', [PurchaseController::class, 'post'])->name('purchases.post');
        Route::post('/purchases/{purchase}/intercompany-journal', [IntercompanyJournalController::class, 'purchase'])->name('purchases.intercompany-journal');
        Route::post('/purchases/{purchase}/intercompany-voucher', [IntercompanyVoucherController::class, 'purchase'])->name('purchases.intercompany-voucher');
        Route::post('/intercompany-vouchers/{voucher}/pay', [IntercompanyVoucherController::class, 'pay'])->name('intercompany-vouchers.pay');
        Route::post('/intercompany-vouchers/pay-batch', [IntercompanyVoucherController::class, 'payBatch'])->name('intercompany-vouchers.pay-batch');
        Route::post('/intercompany-vouchers/{voucher}/send-for-cheque', [IntercompanyVoucherController::class, 'sendForCheque'])->name('intercompany-vouchers.send-for-cheque');
        Route::post('/intercompany-vouchers/send-for-cheque-batch', [IntercompanyVoucherController::class, 'sendForChequeBatch'])->name('intercompany-vouchers.send-for-cheque-batch');
        Route::post('/intercompany-vouchers/{voucher}/cheque-details', [IntercompanyVoucherController::class, 'chequeDetails'])->name('intercompany-vouchers.cheque-details');
        Route::post('/intercompany-vouchers/{voucher}/release', [IntercompanyVoucherController::class, 'release'])->name('intercompany-vouchers.release');
        Route::post('/intercompany-vouchers/{voucher}/cancel-payment', [IntercompanyVoucherController::class, 'cancelPayment'])->name('intercompany-vouchers.cancel-payment');
        Route::get('/intercompany-vouchers/{voucher}/voucher', [IntercompanyVoucherController::class, 'voucher'])->name('intercompany-vouchers.voucher');
        Route::post('/purchases/{purchase}/pay', [PurchaseController::class, 'pay'])->name('purchases.pay');
        Route::post('/purchases/{purchase}/void', [PurchaseController::class, 'void'])->name('purchases.void');
        Route::get('/purchases/batch-check-voucher', [PurchaseController::class, 'batchCheckVoucher'])->name('purchases.batch-check-voucher');
        Route::resource('purchases', PurchaseController::class);
        Route::get('/outgoing-payments', [PurchaseController::class, 'outgoing'])->name('banking.outgoing');
        Route::patch('purchases/{purchase}/processing-status', [PurchaseController::class, 'updateProcessingStatus'])->name('purchases.processing-status');
        Route::post('purchases/pay-batch', [PurchaseController::class, 'payBatch'])->name('purchases.pay-batch');
        Route::post('/purchases/{purchase}/for-cheque', [PurchaseController::class, 'forCheque']);
        Route::post('/purchases/{purchase}/cheque-details', [PurchaseController::class, 'chequeDetails']);
        Route::post('/purchases/{purchase}/release', [PurchaseController::class, 'release']);
        Route::post('/purchases/{purchase}/cancel-payment', [PurchaseController::class, 'cancelPayment']);

        /**
         * Accounts Payable - Vendor Ledger
         */
        Route::get('/vendor-ledger', [VendorLedgerController::class, 'index'])->name('vendor-ledger.index');
        
        /**
         * SALES ROUTE
         */
        Route::post('/sales/{sale}/post', [SaleController::class, 'post'])->name('sales.post');
        Route::post('/sales/{sale}/receive', [SaleController::class, 'receive'])->name('sales.receive');
        Route::post('/sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');
        Route::post('/sales/preview', [SaleController::class, 'preview'])->name('sales.preview');
        Route::resource('sales', SaleController::class);


        /**
         * Balance Sheet Route
         */
        Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');

        /**
         * Printing / Export route
         */
        Route::get('/reports/trial-balance/pdf', [ReportController::class, 'trialBalancePdf'])->name('reports.trial-balance.pdf');
        Route::get('/reports/balance-sheet/pdf', [ReportController::class, 'balanceSheetPdf'])->name('reports.balance-sheet.pdf');
        Route::get('/reports/income-statement/pdf', [ReportController::class, 'incomeStatementPdf'])->name('reports.income-statement.pdf');


        Route::get('/purchases/{purchase}/check-voucher', [PurchaseController::class, 'checkVoucher'])->name('purchases.check-voucher');
        Route::get('/expenses/{expense}/check-voucher', [ExpenseController::class, 'checkVoucher'])->name('expenses.check-voucher');


        Route::post('pos-entries/import', [PosEntryController::class, 'import'])->name('Pos.import');
        Route::get('pos-entries/imports/{batch}', [PosEntryController::class, 'importReport'])->whereUuid('batch')->name('pos-entries.import-report');
    });
});
