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

// Public routes
Route::get('/content/{param}', [CmsContentPublicController::class, 'show']);
Route::get('/cms/{param}', [CmsContentPublicController::class, 'show']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Sidebar menus (for authenticated users)
    Route::get('sidebar-menus', [SidebarMenuController::class, 'index']);
    
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
        
    });
        // CMS Content
        Route::get('cms-content', [CmsContentController::class, 'index']);
        Route::get('cms-content/meta/stats', [CmsContentController::class, 'stats']);
        Route::post('cms-content', [CmsContentController::class, 'store']);
        Route::get('cms-content/{id}', [CmsContentController::class, 'show']);
        Route::put('cms-content/{id}', [CmsContentController::class, 'update']);
        Route::delete('cms-content/{id}', [CmsContentController::class, 'destroy']);

        

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
    
});


 