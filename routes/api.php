<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ErrorLogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CmsContentController;
use App\Http\Controllers\Api\CmsContentPublicController;
use App\Http\Controllers\Api\SidebarMenuController;
use App\Http\Controllers\Api\ExistingApplicantController;
use App\Http\Controllers\Api\ExistingApplicantHelperController;
use App\Http\Controllers\Api\ExistingOccupantController;
use App\Http\Controllers\Api\ExistingApplicantVsCsController;
use App\Http\Controllers\Api\ExistingApplicantVsCsHelperController;
use App\Http\Controllers\Api\EstateTreasuryMappingController;
use App\Http\Controllers\Api\EstateTreasuryMappingHelperController;
use App\Http\Controllers\Api\AuthApiServiceController;
use App\Http\Controllers\Api\CommonApplicationController;
use App\Http\Controllers\Api\NewApplicationController;
use App\Http\Controllers\Api\WaitingListController;
use App\Http\Controllers\Api\VacancyListController;
use App\Http\Controllers\Api\RheAllotmentController;
use App\Http\Controllers\Api\AddFlatBlockController;

// Public routes
Route::get('/content/{param}', [CmsContentPublicController::class, 'show']);
Route::get('/cms/{param}', [CmsContentPublicController::class, 'show']);
Route::get('/auth/public-key', [AuthController::class, 'getPublicKey']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/generate-sso-token', [AuthController::class, 'generateSsoToken']);

// Document download (requires authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/document/download', [\App\Http\Controllers\Api\DocumentController::class, 'download']);
});

// Auth API Service (HRMS/DDO Login) - Public endpoints
Route::post('/login-hrms', [AuthApiServiceController::class, 'applicantLogin']);
Route::post('/login-ddo', [AuthApiServiceController::class, 'ddoLogin']);
Route::post('/validate-sso-token', [AuthApiServiceController::class, 'validateSsoToken']);
Route::post('/hrms-login-manual', [AuthApiServiceController::class, 'hrmsLoginManual']);
Route::get('/hrms-log-data/{hrms_id}', [AuthApiServiceController::class, 'getHrmsLogData']);
Route::get('/get-test-info/{hrmsId?}', [AuthApiServiceController::class, 'getTestInfo']);

// User Tagging APIs
Route::get('/user-tagging/check-submission/{uid}', [\App\Http\Controllers\Api\UserTaggingController::class, 'checkSubmission']);
Route::get('/user-tagging/check-hrms/{hrms_id}', [\App\Http\Controllers\Api\UserTaggingController::class, 'checkHrms']);
Route::post('/user-tagging/submit', [\App\Http\Controllers\Api\UserTaggingController::class, 'submit']);
Route::get('/user-tagging/list', [\App\Http\Controllers\Api\UserTaggingController::class, 'getList']);
Route::get('/user-tagging/details/{flat_id}', [\App\Http\Controllers\Api\UserTaggingController::class, 'getDetails']);
Route::post('/user-tagging/update-status', [\App\Http\Controllers\Api\UserTaggingController::class, 'updateStatus']);

// User Tagging Helper APIs
Route::get('/user-tagging/helpers/rhe-list', [\App\Http\Controllers\Api\UserTaggingHelperController::class, 'getRheList']);
Route::get('/user-tagging/helpers/flat-types/{rhe_id}', [\App\Http\Controllers\Api\UserTaggingHelperController::class, 'getFlatTypes']);
Route::get('/user-tagging/helpers/blocks/{rhe_id}/{flat_type_id}', [\App\Http\Controllers\Api\UserTaggingHelperController::class, 'getBlocks']);
Route::get('/user-tagging/helpers/floors/{rhe_id}/{flat_type_id}/{block_id}', [\App\Http\Controllers\Api\UserTaggingHelperController::class, 'getFloors']);
Route::get('/user-tagging/helpers/flats/{rhe_id}/{flat_type_id}/{block_id}/{floor}', [\App\Http\Controllers\Api\UserTaggingHelperController::class, 'getFlats']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Sidebar menus (for authenticated users)
    Route::get('sidebar-menus', [SidebarMenuController::class, 'index']);
});

// Dashboard API - Allow both authenticated and unauthenticated (with uid/username params)
Route::get('/dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index']);

// Category Shifting (CS) APIs
Route::get('/category-shifting/check-draft', [\App\Http\Controllers\Api\CategoryShiftingController::class, 'checkDraftStatus']);
Route::get('/category-shifting/get-application-data', [\App\Http\Controllers\Api\CategoryShiftingController::class, 'getApplicationData']);
Route::get('/category-shifting/get-current-occupation', [\App\Http\Controllers\Api\CategoryShiftingController::class, 'getCurrentOccupation']);
Route::post('/category-shifting/store', [\App\Http\Controllers\Api\CategoryShiftingController::class, 'store']);

// Vertical Shifting (VS) APIs
Route::get('/vertical-shifting/check-draft', [\App\Http\Controllers\Api\VerticalShiftingController::class, 'checkDraftStatus']);
Route::get('/vertical-shifting/get-application-data', [\App\Http\Controllers\Api\VerticalShiftingController::class, 'getApplicationData']);
Route::get('/vertical-shifting/get-current-occupation', [\App\Http\Controllers\Api\VerticalShiftingController::class, 'getCurrentOccupation']);
Route::post('/vertical-shifting/store', [\App\Http\Controllers\Api\VerticalShiftingController::class, 'store']);

// Allotment List APIs
Route::get('/allotment-list/process-dates', [\App\Http\Controllers\Api\AllotmentListController::class, 'getProcessDates']);
Route::get('/allotment-list/process-numbers', [\App\Http\Controllers\Api\AllotmentListController::class, 'getProcessNumbers']);
Route::get('/allotment-list/process-types', [\App\Http\Controllers\Api\AllotmentListController::class, 'getProcessTypes']);
Route::get('/allotment-list/allottees', [\App\Http\Controllers\Api\AllotmentListController::class, 'getAllotteeList']);
Route::get('/allotment-list/allottees-for-approve', [\App\Http\Controllers\Api\AllotmentListController::class, 'getAllotteeListForApprove']);
Route::get('/allotment-list/allottees-on-hold', [\App\Http\Controllers\Api\AllotmentListController::class, 'getAllotteeListOnHold']);
Route::get('/allotment-list/detail/{encrypted_app_id}', [\App\Http\Controllers\Api\AllotmentListController::class, 'getAllotmentDetail']);

// Generate Allotment Letter APIs
Route::get('/generate-allotment-letter/flat-types', [\App\Http\Controllers\Api\GenerateAllotmentLetterController::class, 'getFlatTypes']);
Route::get('/generate-allotment-letter/waiting-list', [\App\Http\Controllers\Api\GenerateAllotmentLetterController::class, 'getWaitingList']);
Route::post('/generate-allotment-letter/generate', [\App\Http\Controllers\Api\GenerateAllotmentLetterController::class, 'generateLetter']);

// View Allotment Details APIs
Route::get('/view-allotment-details', [\App\Http\Controllers\Api\ViewAllotmentDetailsController::class, 'getAllotmentDetails']);
Route::get('/view-allotment-details/documents', [\App\Http\Controllers\Api\ViewAllotmentDetailsController::class, 'getUploadedDocuments']);
Route::post('/view-allotment-details/update-status', [\App\Http\Controllers\Api\ViewAllotmentDetailsController::class, 'updateStatus']);
Route::post('/view-allotment-details/submit-declaration', [\App\Http\Controllers\Api\ViewAllotmentDetailsController::class, 'submitDeclaration']);

// View Allotment Letter APIs
Route::get('/view-allotment-letter/flat-types', [\App\Http\Controllers\Api\ViewAllotmentLetterController::class, 'getFlatTypes']);
Route::get('/view-allotment-letter/list', [\App\Http\Controllers\Api\ViewAllotmentLetterController::class, 'getAllotmentLetterList']);
Route::post('/view-allotment-letter/allot', [\App\Http\Controllers\Api\ViewAllotmentLetterController::class, 'allotApplicant']);
Route::post('/view-allotment-letter/cancel', [\App\Http\Controllers\Api\ViewAllotmentLetterController::class, 'cancelAllotment']);

// RHE Allotment APIs
Route::get('/rhe-allotment/flat-types', [RheAllotmentController::class, 'getFlatTypes']);
Route::get('/rhe-allotment/show-vacancy', [RheAllotmentController::class, 'showVacancy']);
Route::post('/rhe-allotment/process', [RheAllotmentController::class, 'processAllotment']);

Route::post('/block/add', [AddFlatBlockController::class, 'storeFlatBlock']); // Added by Subham dt.20-01-2026
Route::get('/block/list', [AddFlatBlockController::class, 'fetchFlatBlock']); // Added by Subham dt.12-03-2026

// Protected routes (continued)
Route::middleware('auth:sanctum')->group(function () {
    
    Route::prefix('admin')->group(function () {

        // Users
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{id}', [UserController::class, 'show']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);

        // Roles
        Route::get('roles', [RoleController::class, 'index']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::get('roles/{id}', [RoleController::class, 'show']);
        Route::put('roles/{id}', [RoleController::class, 'update']);
        Route::delete('roles/{id}', [RoleController::class, 'destroy']);

        // Permissions
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::post('permissions', [PermissionController::class, 'store']);
        Route::get('permissions/{id}', [PermissionController::class, 'show']);
        Route::put('permissions/{id}', [PermissionController::class, 'update']);
        Route::delete('permissions/{id}', [PermissionController::class, 'destroy']);

        // Sidebar Menus Management
        Route::get('sidebar-menus/all', [SidebarMenuController::class, 'all']);
        Route::post('sidebar-menus', [SidebarMenuController::class, 'store']);
        Route::get('sidebar-menus/{id}', [SidebarMenuController::class, 'show']);
        Route::put('sidebar-menus/{id}', [SidebarMenuController::class, 'update']);
        Route::delete('sidebar-menus/{id}', [SidebarMenuController::class, 'destroy']);

        // Error Logs (statistics and clear-by-time before {id} so they are matched first)
        Route::get('error-logs/statistics', [ErrorLogController::class, 'statistics']);
        Route::get('error-logs', [ErrorLogController::class, 'index']);
        Route::get('error-logs/{id}', [ErrorLogController::class, 'show']);
        Route::delete('error-logs/clear-by-time', [ErrorLogController::class, 'clearByTime']);
        Route::delete('error-logs/{id}', [ErrorLogController::class, 'destroy']);
        Route::delete('error-logs', [ErrorLogController::class, 'clear']);

        // Cache clear (for admin panel: clear backend cache)
        Route::post('cache/clear', [\App\Http\Controllers\Api\CacheClearController::class, 'clear']);
        
    });
        // CMS Content
        Route::get('cms-content', [CmsContentController::class, 'index']);
        Route::get('cms-content/meta/stats', [CmsContentController::class, 'stats']);
        Route::post('cms-content', [CmsContentController::class, 'store']);
        Route::get('cms-content/{id}', [CmsContentController::class, 'show']);
        Route::put('cms-content/{id}', [CmsContentController::class, 'update']);
        Route::delete('cms-content/{id}', [CmsContentController::class, 'destroy']);

        // DDO Management
        Route::get('ddo/by-application/{online_application_id}', [\App\Http\Controllers\Api\DdoController::class, 'getDdoByApplication']);
        Route::post('ddo/update-change', [\App\Http\Controllers\Api\DdoController::class, 'updateDdoChange']);
        Route::get('ddo/treasury-by-district/{district_code}', [\App\Http\Controllers\Api\DdoController::class, 'getTreasuryByDistrict']);
        Route::get('ddo', [\App\Http\Controllers\Api\DdoController::class, 'index']);
        Route::post('ddo', [\App\Http\Controllers\Api\DdoController::class, 'store']);
        Route::get('ddo/{id}', [\App\Http\Controllers\Api\DdoController::class, 'show']);
        Route::put('ddo/{id}', [\App\Http\Controllers\Api\DdoController::class, 'update']);
        Route::delete('ddo/{id}', [\App\Http\Controllers\Api\DdoController::class, 'destroy']);

        // Generate License
        Route::get('generate-license/list', [\App\Http\Controllers\Api\GenerateLicenseController::class, 'index']);
        Route::post('generate-license', [\App\Http\Controllers\Api\GenerateLicenseController::class, 'generate']);

        // Existing Applicant (Legacy/Physical Applicants)
        Route::get('existing-applicants', [ExistingApplicantController::class, 'index']);
        Route::get('existing-applicants/with-hrms', [ExistingApplicantController::class, 'withHrms']);
        Route::get('existing-applicants/without-hrms', [ExistingApplicantController::class, 'withoutHrms']);
        Route::get('existing-applicants/search', [ExistingApplicantController::class, 'search']);
        Route::post('existing-applicants', [ExistingApplicantController::class, 'store']);
        Route::get('existing-applicants/{id}', [ExistingApplicantController::class, 'show']);
        Route::put('existing-applicants/{id}', [ExistingApplicantController::class, 'update']);
        Route::post('existing-applicants/{id}/accept-declaration', [ExistingApplicantController::class, 'acceptDeclaration']);
        
        // Existing Applicant Helper Endpoints
        Route::get('existing-applicants-helpers/districts', [ExistingApplicantHelperController::class, 'districts']);
        Route::get('existing-applicants-helpers/pay-bands', [ExistingApplicantHelperController::class, 'payBands']);
        Route::get('existing-applicants-helpers/rhe-flat-type', [ExistingApplicantHelperController::class, 'rheFlatType']);
        Route::get('existing-applicants-helpers/allotment-categories', [ExistingApplicantHelperController::class, 'allotmentCategories']);
        Route::get('existing-applicants-helpers/ddo-designations', [ExistingApplicantHelperController::class, 'ddoDesignations']);
        Route::get('existing-applicants-helpers/ddo-address', [ExistingApplicantHelperController::class, 'ddoAddress']);
        Route::get('existing-applicants-helpers/flat-type-id', [ExistingApplicantHelperController::class, 'flatTypeId']);

        // Existing Occupant
        Route::get('existing-occupants', [ExistingOccupantController::class, 'index']);
        Route::get('existing-occupants/with-hrms', [ExistingOccupantController::class, 'withHrms']);
        Route::get('existing-occupants/without-hrms', [ExistingOccupantController::class, 'withoutHrms']);
        Route::get('existing-occupants/flat/{flat_id}', [ExistingOccupantController::class, 'getByFlat']);
        Route::get('existing-occupants/flat/{flat_id}/check', [ExistingOccupantController::class, 'checkFlatOccupancy']);
        Route::get('existing-occupants/flat/{flat_id}/details', [ExistingOccupantController::class, 'getFlatDetails']);
        Route::get('existing-occupants/draft', [ExistingOccupantController::class, 'listDrafts']);
        Route::get('existing-occupants/meta/rhe-list', [ExistingOccupantController::class, 'getRheList']);
        Route::get('existing-occupants/meta/flat-types/{rhe_id}', [ExistingOccupantController::class, 'getFlatTypes']);
        Route::get('existing-occupants/meta/blocks/{rhe_id}/{flat_type_id}', [ExistingOccupantController::class, 'getBlocks']);
        Route::get('existing-occupants/meta/flats/{rhe_id}/{flat_type_id}/{block_id}', [ExistingOccupantController::class, 'getAvailableFlats']);
        Route::get('existing-occupants/meta/districts', [ExistingOccupantController::class, 'getDistricts']);
        Route::get('existing-occupants/meta/ddo-list', [ExistingOccupantController::class, 'getDdoList']);
        Route::get('existing-occupants/meta/pay-bands', [ExistingOccupantController::class, 'getPayBands']);
        Route::post('existing-occupants', [ExistingOccupantController::class, 'store']);
        Route::get('existing-occupants/{id}', [ExistingOccupantController::class, 'show']);
        Route::put('existing-occupants/{id}', [ExistingOccupantController::class, 'update']);
        Route::delete('existing-occupants/{id}', [ExistingOccupantController::class, 'destroy']);

        // Existing Applicant VS/CS (Floor Shifting / Category Shifting)
        Route::get('existing-applicant-vs-cs/flat-details', [ExistingApplicantVsCsController::class, 'getFlatApplicantDetails']);
        Route::post('existing-applicant-vs-cs', [ExistingApplicantVsCsController::class, 'store']);
        Route::get('existing-applicant-vs-cs/vs-list-with-hrms', [ExistingApplicantVsCsController::class, 'vsListWithHrms']);
        Route::get('existing-applicant-vs-cs/vs-list-without-hrms', [ExistingApplicantVsCsController::class, 'vsListWithoutHrms']);
        Route::get('existing-applicant-vs-cs/cs-list-with-hrms', [ExistingApplicantVsCsController::class, 'csListWithHrms']);
        Route::get('existing-applicant-vs-cs/cs-list-without-hrms', [ExistingApplicantVsCsController::class, 'csListWithoutHrms']);
        Route::get('existing-applicant-vs-cs/{id}', [ExistingApplicantVsCsController::class, 'show']);
        Route::put('existing-applicant-vs-cs/{id}', [ExistingApplicantVsCsController::class, 'update']);

        // Existing Applicant VS/CS Helper Endpoints
        Route::get('existing-applicant-vs-cs-helpers/rhe-list', [ExistingApplicantVsCsHelperController::class, 'getRheList']);
        Route::get('existing-applicant-vs-cs-helpers/flat-types', [ExistingApplicantVsCsHelperController::class, 'getFlatTypesUnderRhe']);
        Route::get('existing-applicant-vs-cs-helpers/blocks', [ExistingApplicantVsCsHelperController::class, 'getBlocksUnderRhe']);
        Route::get('existing-applicant-vs-cs-helpers/flats', [ExistingApplicantVsCsHelperController::class, 'getFlatsUnderRhe']);
        Route::get('existing-applicant-vs-cs-helpers/housing-estates', [ExistingApplicantVsCsHelperController::class, 'getHousingEstates']);
        Route::get('existing-applicant-vs-cs-helpers/housing-blocks', [ExistingApplicantVsCsHelperController::class, 'getHousingBlocks']);
        Route::get('existing-applicant-vs-cs-helpers/housing-flats', [ExistingApplicantVsCsHelperController::class, 'getHousingFlats']);
        Route::get('existing-applicant-vs-cs-helpers/possession-date', [ExistingApplicantVsCsHelperController::class, 'getPossessionDate']);

        // Estate Treasury Mapping
        Route::get('estate-treasury-mapping', [EstateTreasuryMappingController::class, 'index']);
        Route::post('estate-treasury-mapping', [EstateTreasuryMappingController::class, 'store']);
        Route::get('estate-treasury-mapping/{id}', [EstateTreasuryMappingController::class, 'show']);
        Route::put('estate-treasury-mapping/{id}', [EstateTreasuryMappingController::class, 'update']);
        Route::delete('estate-treasury-mapping/{id}', [EstateTreasuryMappingController::class, 'destroy']);

        // Estate Treasury Mapping Helper Endpoints
        Route::get('estate-treasury-mapping-helpers/estates', [EstateTreasuryMappingHelperController::class, 'getEstates']);
        Route::get('estate-treasury-mapping-helpers/treasuries', [EstateTreasuryMappingHelperController::class, 'getTreasuries']);

        // Common Application (shared form for new applications)
        Route::post('common-application', [CommonApplicationController::class, 'store']);
        Route::get('common-application/personal-info', [CommonApplicationController::class, 'getApplicantPersonalInfo']);
        Route::get('common-application/official-info', [CommonApplicationController::class, 'getApplicantOfficialInfo']);

        
        // New Application (extends common application with additional features)
        Route::get('new-application/check-draft', [NewApplicationController::class, 'checkDraftStatus']);
        Route::post('new-application', [NewApplicationController::class, 'store']);
        Route::get('new-application/data', [NewApplicationController::class, 'getApplicationData']);
        Route::get('new-application/housing-estate-preferences', [NewApplicationController::class, 'getHousingEstatePreferences']);
        Route::get('new-application/flat-type-by-payband', [NewApplicationController::class, 'getFlatTypeByPayBand']);
        Route::post('new-application/supporting-doc-upload', [NewApplicationController::class, 'uploadSupportingDocument']);

        // Waiting List APIs
        Route::get('waiting-list/flat-type', [WaitingListController::class, 'flatTypeWaitingList']);

        // Vacancy List APIs
        Route::get('vacancy-list/district-wise', [VacancyListController::class, 'districtWise']);
        Route::get('vacancy-list/rhe-wise', [VacancyListController::class, 'rheWise']);

        // Application List
        Route::get('application-list', [\App\Http\Controllers\Api\ApplicationListController::class, 'index']);
        Route::get('application-list/admin', [\App\Http\Controllers\Api\ApplicationListController::class, 'adminList']);
        Route::get('application-list/{id}', [\App\Http\Controllers\Api\ApplicationListController::class, 'show']);
        Route::post('application-list/{id}/update-status', [\App\Http\Controllers\Api\ApplicationListController::class, 'updateStatus']);
        
        // View Application List Module APIs
        Route::get('view-application-list/dashboard', [\App\Http\Controllers\Api\ApplicationListController::class, 'getDashboardCounts']);
        Route::post('view-application-list/approve', [\App\Http\Controllers\Api\ApplicationListController::class, 'approveApplication']);
        Route::get('view-application-list/{id}/documents', [\App\Http\Controllers\Api\ApplicationListController::class, 'getApplicantDocuments']);
        Route::get('view-application-list/{id}/entity-type', [\App\Http\Controllers\Api\ApplicationListController::class, 'getApplicationEntityType']);

        // License Management
        Route::post('license/generate', [\App\Http\Controllers\Api\LicenseController::class, 'generate']);
        Route::get('license/list', [\App\Http\Controllers\Api\LicenseController::class, 'list']);
        Route::get('license/download-pdf/{online_application_id}', [\App\Http\Controllers\Api\LicenseController::class, 'downloadPdf']);
        Route::get('license/flat-possession-taken', [\App\Http\Controllers\Api\LicenseController::class, 'flatPossessionTaken']);
        Route::get('license/flat-released', [\App\Http\Controllers\Api\LicenseController::class, 'flatReleased']);

        // VS/CS License Management
        Route::prefix('generate-vs-cs-license')->group(function () {
            Route::get('list', [\App\Http\Controllers\Api\GenerateVsCsLicenseController::class, 'index']);
            Route::post('generate', [\App\Http\Controllers\Api\GenerateVsCsLicenseController::class, 'generate']);
            Route::get('details', [\App\Http\Controllers\Api\GenerateVsCsLicenseController::class, 'getLicenseDetailsForPdf']);
            Route::post('upload-signed', [\App\Http\Controllers\Api\GenerateVsCsLicenseController::class, 'uploadSignedLicense']);
            Route::get('download-signed/{occupant_license_id}', [\App\Http\Controllers\Api\GenerateVsCsLicenseController::class, 'downloadSignedLicense']);
        });

        // License List Management
        Route::prefix('license-list')->group(function () {
            Route::get('list', [\App\Http\Controllers\Api\LicenseListController::class, 'index']);
            Route::get('rhe-list', [\App\Http\Controllers\Api\LicenseListController::class, 'getRheList']);
        });

        // View License Details (for applicants)
        Route::prefix('view-license-details')->group(function () {
            Route::get('list', [\App\Http\Controllers\Api\ViewLicenseDetailsController::class, 'index']);
            Route::get('details', [\App\Http\Controllers\Api\ViewLicenseDetailsController::class, 'getLicenseDetailsForPdf']);
        });

        // New License Application
        Route::prefix('new-license')->group(function () {
            Route::get('check-draft', [\App\Http\Controllers\Api\NewLicenseController::class, 'checkDraft']);
            Route::get('allotment-details', [\App\Http\Controllers\Api\NewLicenseController::class, 'getAllotmentDetails']);
            Route::post('store', [\App\Http\Controllers\Api\NewLicenseController::class, 'store']);
        });

        // VS License Application
        Route::prefix('vs-license')->group(function () {
            Route::get('check-draft', [\App\Http\Controllers\Api\VsLicenseController::class, 'checkDraft']);
            Route::get('allotment-details', [\App\Http\Controllers\Api\VsLicenseController::class, 'getAllotmentDetails']);
            Route::post('store', [\App\Http\Controllers\Api\VsLicenseController::class, 'store']);
        });

        // CS License Application
        Route::prefix('cs-license')->group(function () {
            Route::get('check-draft', [\App\Http\Controllers\Api\CsLicenseController::class, 'checkDraft']);
            Route::get('allotment-details', [\App\Http\Controllers\Api\CsLicenseController::class, 'getAllotmentDetails']);
            Route::post('store', [\App\Http\Controllers\Api\CsLicenseController::class, 'store']);
        });

        // Renew License Application
        Route::prefix('renew-license')->group(function () {
            Route::get('check-draft', [\App\Http\Controllers\Api\RenewLicenseController::class, 'checkDraft']);
            Route::get('license-details', [\App\Http\Controllers\Api\RenewLicenseController::class, 'getLicenseDetails']);
            Route::post('store', [\App\Http\Controllers\Api\RenewLicenseController::class, 'store']);
        });

        // Allotment List Management (Protected)
        Route::post('allotment-list/approve', [\App\Http\Controllers\Api\AllotmentListController::class, 'approveAllotments']);
        Route::post('allotment-list/reject', [\App\Http\Controllers\Api\AllotmentListController::class, 'rejectAllotments']);
        Route::post('allotment-list/hold', [\App\Http\Controllers\Api\AllotmentListController::class, 'holdAllotments']);

        // Online Application landing statuses
        Route::get('online-application/statuses', [\App\Http\Controllers\Api\OnlineApplicationController::class, 'statuses']);

        // Application Status
        Route::get('application-status/{application_no}', [\App\Http\Controllers\Api\ApplicationStatusController::class, 'getStatusHistory']);
        
        // Application Status Check
        Route::post('application-status-check/search', [\App\Http\Controllers\Api\ApplicationStatusController::class, 'search']);
        Route::get('application-status-check/{id}', [\App\Http\Controllers\Api\ApplicationStatusController::class, 'getApplicationDetail']);
        Route::post('application-status-check/{id}/add-possession', [\App\Http\Controllers\Api\ApplicationStatusController::class, 'addPossessionDate']);
        Route::post('application-status-check/{id}/add-release-date', [\App\Http\Controllers\Api\ApplicationStatusController::class, 'addReleaseDate']);
        Route::post('application-status-check/{id}/request-license-extension', [\App\Http\Controllers\Api\ApplicationStatusController::class, 'requestLicenseExtension']);
        Route::post('application-status-check/{id}/request-offer-letter-extension', [\App\Http\Controllers\Api\ApplicationStatusController::class, 'requestOfferLetterExtension']);
        Route::get('application-status-check/{id}/extension-count', [\App\Http\Controllers\Api\ApplicationStatusController::class, 'getExtensionCount']);
    
        // Special Recommendation APIs
        Route::prefix('special-recommendation')->group(function () {
            Route::get('housing-approver-list', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'getHousingApproverList']);
            Route::post('add', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'addToSpecialRecommendation']);
            Route::post('remove', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'removeFromSpecialRecommendation']);
            Route::get('list-view', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'getSpecialRecommendationListView']);
            Route::post('update-priority', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'updatePriorityOrder']);
            Route::get('final-list', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'getFinalSpecialRecommendedList']);
            Route::get('view-details/{encrypted_online_application_id}', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'getApplicationDetails'])
                ->where('encrypted_online_application_id', '.*');
            Route::post('convert-to-general', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'convertToGeneralCategory']);
            Route::post('manual-allotment', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'manualAllotment']);
            
            // Helper endpoints for manual allotment
            Route::prefix('helpers')->group(function () {
                Route::get('rhe-list', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'getRheList']);
                Route::get('flat-types/{rhe_id}', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'getFlatTypesUnderRhe']);
                Route::get('blocks/{rhe_id}/{flat_type_id}', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'getBlocksUnderRhe']);
                Route::get('floors/{rhe_id}/{flat_type_id}/{block_id}', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'getFloorsUnderRhe']);
                Route::get('flats/{rhe_id}/{flat_type_id}/{block_id}/{floor_no}', [\App\Http\Controllers\Api\SpecialRecommendationController::class, 'getFlatsUnderRhe']);
            });
        });

        // Shifting Allotment APIs
        Route::prefix('shifting-allotment')->group(function () {
            // VS Allotment
            Route::get('vs/rhe-list', [\App\Http\Controllers\Api\ShiftingAllotmentController::class, 'getVsRheList']);
            Route::get('vs/vacancy-count', [\App\Http\Controllers\Api\ShiftingAllotmentController::class, 'getVsVacancyCount']);
            Route::get('vs/applicant-count', [\App\Http\Controllers\Api\ShiftingAllotmentController::class, 'getVsApplicantCount']);
            Route::post('vs/process', [\App\Http\Controllers\Api\ShiftingAllotmentController::class, 'processVsAllotment']);
            
            // CS Allotment
            Route::get('cs/rhe-list', [\App\Http\Controllers\Api\ShiftingAllotmentController::class, 'getCsRheList']);
            Route::get('cs/vacancy-count', [\App\Http\Controllers\Api\ShiftingAllotmentController::class, 'getCsVacancyCount']);
            Route::get('cs/applicant-count', [\App\Http\Controllers\Api\ShiftingAllotmentController::class, 'getCsApplicantCount']);
            Route::post('cs/process', [\App\Http\Controllers\Api\ShiftingAllotmentController::class, 'processCsAllotment']);
        });

        // Shifting Allotment List APIs
        Route::prefix('shifting-allotment-list')->group(function () {
            // VS Allotment List
            Route::get('vs/process-dates', [\App\Http\Controllers\Api\ShiftingAllotmentListController::class, 'getVsProcessDates']);
            Route::get('vs/process-numbers', [\App\Http\Controllers\Api\ShiftingAllotmentListController::class, 'getVsProcessNumbers']);
            Route::get('vs/allottees', [\App\Http\Controllers\Api\ShiftingAllotmentListController::class, 'getVsAllotteeList']);
            
            // CS Allotment List
            Route::get('cs/process-dates', [\App\Http\Controllers\Api\ShiftingAllotmentListController::class, 'getCsProcessDates']);
            Route::get('cs/process-numbers', [\App\Http\Controllers\Api\ShiftingAllotmentListController::class, 'getCsProcessNumbers']);
            Route::get('cs/allottees', [\App\Http\Controllers\Api\ShiftingAllotmentListController::class, 'getCsAllotteeList']);
        });

        // View Shifting Allotment Details APIs
        Route::prefix('view-shifting-allotment-details')->group(function () {
            // VS Allotment Details
            Route::get('vs', [\App\Http\Controllers\Api\ViewShiftingAllotmentDetailsController::class, 'getVsAllotmentDetails']);
            Route::post('vs/update-status', [\App\Http\Controllers\Api\ViewShiftingAllotmentDetailsController::class, 'updateVsStatus']);
            
            // CS Allotment Details
            Route::get('cs', [\App\Http\Controllers\Api\ViewShiftingAllotmentDetailsController::class, 'getCsAllotmentDetails']);
            Route::post('cs/update-status', [\App\Http\Controllers\Api\ViewShiftingAllotmentDetailsController::class, 'updateCsStatus']);
            Route::get('cs/documents', [\App\Http\Controllers\Api\ViewShiftingAllotmentDetailsController::class, 'getCsDocuments']);
        });
});


