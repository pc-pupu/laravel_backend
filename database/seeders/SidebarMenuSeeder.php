<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SidebarMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder inserts all the sidebar menus from the previous static sidebar
     * Make sure to run this AFTER roles are created in your database
     */
    public function run(): void
    {
        // First, get role IDs (adjust role names if they differ in your database)
        $roles = DB::table('roles')->pluck('id', 'name')->toArray();
        
        // Helper function to get role ID
        $getRoleId = function($roleName) use ($roles) {
            return $roles[$roleName] ?? null;
        };

        // Helper function to insert menu and link to roles
        $insertMenu = function($menuData, $roleNames = []) use ($getRoleId) {
            $menuId = DB::table('housing_sidebar_menus')->insertGetId($menuData);
            
            foreach ($roleNames as $roleName) {
                $roleId = $getRoleId($roleName);
                if ($roleId) {
                    DB::table('housing_sidebar_menu_roles')->insert([
                        'sidebar_menu_id' => $menuId,
                        'role_id' => $roleId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            return $menuId;
        };

        // ============================================
        // ADMIN ROLE MENUS
        // ============================================
        if (isset($roles['Admin'])) {
            // Dashboard
            $adminDashboardId = $insertMenu([
                'menu_name' => 'Dashboard',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-tachometer fa-lg',
                'parent_id' => null,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Admin']);

            // Role Management (Parent)
            $roleMgmtId = $insertMenu([
                'menu_name' => 'Role Management',
                'route_name' => null,
                'icon_class' => 'fa fa-user fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Admin']);

            // View Role List (Child)
            $insertMenu([
                'menu_name' => 'View Role List',
                'route_name' => 'displayRoleList',
                'icon_class' => 'fa fa-list-ul fa-lg mx-1',
                'parent_id' => $roleMgmtId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Admin']);

            // User Management (Parent)
            $userMgmtId = $insertMenu([
                'menu_name' => 'User Management',
                'route_name' => null,
                'icon_class' => 'fa fa-user-circle-o fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Admin']);

            // View User List (Child)
            $insertMenu([
                'menu_name' => 'View User List',
                'route_name' => 'displayUserList',
                'icon_class' => 'fa fa-list-ul fa-lg mx-1',
                'parent_id' => $userMgmtId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Admin']);

            // View User-Role Mapping (Child)
            $insertMenu([
                'menu_name' => 'View User-Role Mapping',
                'route_name' => 'displayUserRoleList',
                'icon_class' => 'fa fa-list-alt fa-lg mx-1',
                'parent_id' => $userMgmtId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Admin']);
        }

        // ============================================
        // DDO ROLE MENUS
        // ============================================
        if (isset($roles['DDO'])) {
            // Dashboard
            $ddoDashboardId = $insertMenu([
                'menu_name' => 'Dashboard',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-tachometer fa-lg',
                'parent_id' => null,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['DDO']);

            // New Application (Parent)
            $ddoNewAppId = $insertMenu([
                'menu_name' => 'New Application',
                'route_name' => null,
                'icon_class' => 'fa fa-user-circle-o fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['DDO']);

            // New Application (Child)
            $insertMenu([
                'menu_name' => 'New Application',
                'route_name' => 'new-application',
                'icon_class' => 'fa fa-clipboard fa-lg mx-1',
                'parent_id' => $ddoNewAppId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['DDO']);

            // Vertical Shifting (Child)
            $insertMenu([
                'menu_name' => 'Vertical Shifting',
                'route_name' => null,
                'icon_class' => 'fa fa-retweet fa-lg mx-1',
                'parent_id' => $ddoNewAppId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['DDO']);

            // Category Shifting (Child)
            $insertMenu([
                'menu_name' => 'Category Shifting',
                'route_name' => null,
                'icon_class' => 'fa fa-check-square-o fa-lg mx-1',
                'parent_id' => $ddoNewAppId,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['DDO']);

            // Allotted Application (Parent)
            $ddoAllottedId = $insertMenu([
                'menu_name' => 'Allotted Application',
                'route_name' => null,
                'icon_class' => 'fa fa-file-text-o fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['DDO']);

            // Allotted Application children (same structure)
            $insertMenu([
                'menu_name' => 'New Application',
                'route_name' => null,
                'icon_class' => 'fa fa-clipboard fa-lg mx-1',
                'parent_id' => $ddoAllottedId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['DDO']);

            $insertMenu([
                'menu_name' => 'Vertical Shifting',
                'route_name' => null,
                'icon_class' => 'fa fa-retweet fa-lg mx-1',
                'parent_id' => $ddoAllottedId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['DDO']);

            $insertMenu([
                'menu_name' => 'Category Shifting',
                'route_name' => null,
                'icon_class' => 'fa fa-check-square-o fa-lg mx-1',
                'parent_id' => $ddoAllottedId,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['DDO']);

            // Applicant List With Flat Possession
            $insertMenu([
                'menu_name' => 'Applicant List With Flat Possession',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-list fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 4,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['DDO']);

            // Applicant List With Flat Release
            $insertMenu([
                'menu_name' => 'Applicant List With Flat Release',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-share-square-o fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 5,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['DDO']);
        }

        // ============================================
        // HOUSING SUPERVISOR ROLE MENUS
        // ============================================
        if (isset($roles['Housing Supervisor'])) {
            $insertMenu([
                'menu_name' => 'Dashboard',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-tachometer fa-lg',
                'parent_id' => null,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Supervisor']);

            $insertMenu([
                'menu_name' => 'Search Application Details',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-search fa-lg',
                'parent_id' => null,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Supervisor']);

            // New Application (Parent) - same structure as DDO
            $hsNewAppId = $insertMenu([
                'menu_name' => 'New Application',
                'route_name' => null,
                'icon_class' => 'fa fa-user-circle-o fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Supervisor']);

            $insertMenu([
                'menu_name' => 'New Application',
                'url' => 'view-application-list-dashboard',
                'icon_class' => 'fa fa-clipboard fa-lg mx-1',
                'parent_id' => $hsNewAppId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Supervisor']);

            $insertMenu([
                'menu_name' => 'Vertical Shifting',
                'route_name' => null,
                'icon_class' => 'fa fa-retweet fa-lg mx-1',
                'parent_id' => $hsNewAppId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Supervisor']);

            $insertMenu([
                'menu_name' => 'Category Shifting',
                'route_name' => null,
                'icon_class' => 'fa fa-check-square-o fa-lg mx-1',
                'parent_id' => $hsNewAppId,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Supervisor']);

            // Allotted Application (Parent) - same structure
            $hsAllottedId = $insertMenu([
                'menu_name' => 'Allotted Application',
                'route_name' => null,
                'icon_class' => 'fa fa-file-text-o fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 4,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Supervisor']);

            // Add children for Allotted Application
            for ($i = 1; $i <= 3; $i++) {
                $childNames = ['New Application', 'Vertical Shifting', 'Category Shifting'];
                $childIcons = ['fa fa-clipboard fa-lg mx-1', 'fa fa-retweet fa-lg mx-1', 'fa fa-check-square-o fa-lg mx-1'];
                $insertMenu([
                    'menu_name' => $childNames[$i - 1],
                    'route_name' => null,
                    'icon_class' => $childIcons[$i - 1],
                    'parent_id' => $hsAllottedId,
                    'order_no' => $i,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], ['Housing Supervisor']);
            }

            // Occupant & Applicant List (Parent)
            $hsOccAppId = $insertMenu([
                'menu_name' => 'Occupant & Applicant List',
                'route_name' => null,
                'icon_class' => 'fa fa-list fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 5,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Supervisor']);

            $insertMenu([
                'menu_name' => 'Physical Applicant List',
                'route_name' => null,
                'icon_class' => 'fa fa-list-alt fa-lg mx-1',
                'parent_id' => $hsOccAppId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Supervisor']);

            $insertMenu([
                'menu_name' => 'Existing Occupant List',
                'route_name' => null,
                'icon_class' => 'fa fa-home fa-lg mx-1',
                'parent_id' => $hsOccAppId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Supervisor']);
        }

        // ============================================
        // HOUSING APPROVER ROLE MENUS
        // ============================================
        if (isset($roles['Housing Approver'])) {
            $insertMenu([
                'menu_name' => 'Dashboard',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-tachometer fa-lg',
                'parent_id' => null,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Approver']);

            $insertMenu([
                'menu_name' => 'Search Application Details',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-search fa-lg',
                'parent_id' => null,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Approver']);

            // Same structure as Housing Supervisor for New Application, Allotted Application, and Occupant & Applicant List
            // (You can copy the same logic from Housing Supervisor section)
        }

        // ============================================
        // HOUSING OFFICIAL ROLE MENUS
        // ============================================
        if (isset($roles['Housing Official'])) {
            $insertMenu([
                'menu_name' => 'Dashboard',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-tachometer fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $insertMenu([
                'menu_name' => 'Search Application Details',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-search fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $insertMenu([
                'menu_name' => 'Auto-Cancellation List',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-calendar-times-o fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            // Special Recommendation (Parent)
            $hoSpecialRecId = $insertMenu([
                'menu_name' => 'Special Recommendation',
                'route_name' => null,
                'icon_class' => 'fa fa-hand-pointer-o fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 4,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $insertMenu([
                'menu_name' => 'Add to Special Recommendation List',
                'route_name' => null,
                'icon_class' => 'fa fa-plus fa-lg mx-1',
                'parent_id' => $hoSpecialRecId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $insertMenu([
                'menu_name' => 'Edit Special Recommendation',
                'route_name' => null,
                'icon_class' => 'fa fa-pencil-square-o fa-lg mx-1',
                'parent_id' => $hoSpecialRecId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $insertMenu([
                'menu_name' => 'Final List of Special Recommendation',
                'route_name' => null,
                'icon_class' => 'fa fa-file-text fa-lg mx-1',
                'parent_id' => $hoSpecialRecId,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            // Allottment (Parent)
            $hoAllotmentId = $insertMenu([
                'menu_name' => 'Allottment',
                'route_name' => null,
                'icon_class' => 'fa fa-pie-chart fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 5,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $allotmentChildren = [
                ['Run Allotment', 'fa fa-clipboard fa-lg mx-1'],
                ['Approve Allotment', 'fa fa-home fa-lg mx-1'],
                ['Flat Type Wise Waiting List', 'fa fa-list-alt fa-lg mx-1'],
                ['Vacancy List', 'fa fa-building-o fa-lg mx-1'],
                ['Allotment List', 'fa fa-file-text fa-lg mx-1'],
            ];

            foreach ($allotmentChildren as $index => $child) {
                $insertMenu([
                    'menu_name' => $child[0],
                    'route_name' => null,
                    'icon_class' => $child[1],
                    'parent_id' => $hoAllotmentId,
                    'order_no' => $index + 1,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], ['Housing Official']);
            }

            // Licenses (Parent)
            $hoLicensesId = $insertMenu([
                'menu_name' => 'Licenses',
                'route_name' => null,
                'icon_class' => 'fa fa-id-card fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 6,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $insertMenu([
                'menu_name' => 'Generate License',
                'route_name' => null,
                'icon_class' => 'fa fa-plus-circle fa-lg mx-1',
                'parent_id' => $hoLicensesId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $insertMenu([
                'menu_name' => 'View Generated Licenses',
                'route_name' => null,
                'icon_class' => 'fa fa-list-alt fa-lg mx-1',
                'parent_id' => $hoLicensesId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            // Legacy Data (Parent)
            $hoLegacyId = $insertMenu([
                'menu_name' => 'Legacy Data',
                'route_name' => null,
                'icon_class' => 'fa fa-database fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 7,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $insertMenu([
                'menu_name' => 'Legacy Applicant Entry',
                'route_name' => null,
                'icon_class' => 'fa fa-address-card-o fa-lg mx-1',
                'parent_id' => $hoLegacyId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $insertMenu([
                'menu_name' => 'Legacy Applicant List',
                'route_name' => null,
                'icon_class' => 'fa fa-list-alt fa-lg mx-1',
                'parent_id' => $hoLegacyId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            // Treasury Estate Mapping
            $insertMenu([
                'menu_name' => 'Treasury Estate Mapping',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-building fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 8,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            // Occupant & Applicant List (Parent)
            $hoOccAppId = $insertMenu([
                'menu_name' => 'Occupant & Applicant List',
                'route_name' => null,
                'icon_class' => 'fa fa-list fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 9,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $insertMenu([
                'menu_name' => 'Physical Applicant List',
                'route_name' => null,
                'icon_class' => 'fa fa-list-alt fa-lg mx-1',
                'parent_id' => $hoOccAppId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);

            $insertMenu([
                'menu_name' => 'Existing Occupant List',
                'route_name' => null,
                'icon_class' => 'fa fa-home fa-lg mx-1',
                'parent_id' => $hoOccAppId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Housing Official']);
        }

        // ============================================
        // APPLICANT ROLE MENUS
        // ============================================
        if (isset($roles['Applicant'])) {
            $insertMenu([
                'menu_name' => 'Dashboard',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-tachometer fa-lg',
                'parent_id' => null,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Applicant']);

            // Online Application (Parent)
            $appOnlineAppId = $insertMenu([
                'menu_name' => 'Online Application',
                'route_name' => null,
                'icon_class' => 'bi bi-buildings-fill fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Applicant']);

            $insertMenu([
                'menu_name' => 'New Application',
                'route_name' => 'new-application',
                'icon_class' => 'fa fa-building fa-lg mx-1',
                'parent_id' => $appOnlineAppId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Applicant']);

            $insertMenu([
                'menu_name' => 'Vertical Shifting',
                'route_name' => null,
                'icon_class' => 'fa fa-th-list fa-lg mx-1',
                'parent_id' => $appOnlineAppId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Applicant']);

            $insertMenu([
                'menu_name' => 'Category Shifting',
                'route_name' => null,
                'icon_class' => 'fa fa-plus fa-lg mx-1',
                'parent_id' => $appOnlineAppId,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Applicant']);
        }

        // ============================================
        // DIVISION ROLE MENUS
        // ============================================
        if (isset($roles['Division'])) {
            $insertMenu([
                'menu_name' => 'Dashboard',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-tachometer fa-lg',
                'parent_id' => null,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Division']);

            // RHE Data (Parent)
            $divRheId = $insertMenu([
                'menu_name' => 'RHE Data',
                'route_name' => null,
                'icon_class' => 'bi bi-buildings-fill fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Division']);

            $insertMenu([
                'menu_name' => 'RHE Wise Flat List',
                'route_name' => null,
                'icon_class' => 'fa fa-building fa-lg mx-1',
                'parent_id' => $divRheId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Division']);

            $insertMenu([
                'menu_name' => 'RHE Flat Master',
                'route_name' => null,
                'icon_class' => 'fa fa-th-list fa-lg mx-1',
                'parent_id' => $divRheId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Division']);

            $insertMenu([
                'menu_name' => 'Add RHE Block',
                'route_name' => null,
                'icon_class' => 'fa fa-plus fa-lg mx-1',
                'parent_id' => $divRheId,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Division']);

            // Occupant Data (Parent)
            $divOccDataId = $insertMenu([
                'menu_name' => 'Occupant Data',
                'route_name' => null,
                'icon_class' => 'fa fa-address-card-o fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Division']);

            $insertMenu([
                'menu_name' => 'Occupant Data Approve',
                'route_name' => null,
                'icon_class' => 'fa fa-clipboard fa-lg mx-1',
                'parent_id' => $divOccDataId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Division']);

            $insertMenu([
                'menu_name' => 'Occupant List',
                'route_name' => null,
                'icon_class' => 'fa fa-list-alt fa-lg mx-1',
                'parent_id' => $divOccDataId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Division']);
        }

        // ============================================
        // SUB-DIVISION ROLE MENUS
        // ============================================
        if (isset($roles['Sub-Division'])) {
            $insertMenu([
                'menu_name' => 'Dashboard',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-tachometer fa-lg',
                'parent_id' => null,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Sub-Division']);

            $insertMenu([
                'menu_name' => 'Search Application Details',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-search fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Sub-Division']);

            $insertMenu([
                'menu_name' => 'Auto-Cancellation List',
                'route_name' => 'dashboard',
                'icon_class' => 'fa fa-calendar-times-o fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Sub-Division']);

            // RHE Data (Parent) - same as Division
            $subDivRheId = $insertMenu([
                'menu_name' => 'RHE Data',
                'route_name' => null,
                'icon_class' => 'bi bi-buildings-fill fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 4,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Sub-Division']);

            $insertMenu([
                'menu_name' => 'RHE Wise Flat List',
                'route_name' => null,
                'icon_class' => 'fa fa-building fa-lg mx-1',
                'parent_id' => $subDivRheId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Sub-Division']);

            $insertMenu([
                'menu_name' => 'RHE Flat Master',
                'route_name' => null,
                'icon_class' => 'fa fa-th-list fa-lg mx-1',
                'parent_id' => $subDivRheId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Sub-Division']);

            $insertMenu([
                'menu_name' => 'Add RHE Block',
                'route_name' => null,
                'icon_class' => 'fa fa-plus fa-lg mx-1',
                'parent_id' => $subDivRheId,
                'order_no' => 3,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Sub-Division']);

            // Occupant Data (Parent)
            $subDivOccDataId = $insertMenu([
                'menu_name' => 'Occupant Data',
                'route_name' => null,
                'icon_class' => 'fa fa-address-card-o fa-lg mx-1',
                'parent_id' => null,
                'order_no' => 5,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Sub-Division']);

            $insertMenu([
                'menu_name' => 'Occupant Data Entry',
                'route_name' => 'existing-occupant-add',
                'icon_class' => 'fa fa-clipboard fa-lg mx-1',
                'parent_id' => $subDivOccDataId,
                'order_no' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Sub-Division']);

            $insertMenu([
                'menu_name' => 'Occupant List',
                'route_name' => null,
                'icon_class' => 'fa fa-list-alt fa-lg mx-1',
                'parent_id' => $subDivOccDataId,
                'order_no' => 2,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['Sub-Division']);
        }

        $this->command->info('Sidebar menus seeded successfully!');
    }
}

