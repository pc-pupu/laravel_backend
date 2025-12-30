<?php

/**
 * Script to sync routes from Drupal-housing to Laravel
 * This script extracts routes from Drupal modules and generates SQL to insert them into housing_sidebar_menus
 * 
 * Usage: php sync_routes_from_drupal.php
 */

// Define Drupal module paths
$drupalModulesPath = __DIR__ . '/../../../../drupal-housing/sites/all/modules/custom';
$outputFile = __DIR__ . '/sync_routes_output.sql';

// Route mapping: Drupal URL => Laravel route name
$routeMapping = [
    // Dashboard
    'dashboard' => 'dashboard',
    
    // New Application
    'new-apply' => 'new-application.create',
    
    // Application List (for applicants)
    'application-list' => 'application-list.index',
    'view-application/%' => 'application.view',
    
    // View Application List (for admins/officials)
    'view_application_list/%/%' => 'view_application_list.dashboard',
    'view_application/%/%/%' => 'view_application',
    'application_detail/%/%/%' => 'application_detail',
    'application_detail_pdf/%/%' => 'application_detail_pdf',
    'update_status/%/%/%/%' => 'update_status',
    'update_status/%/%/%/%/%' => 'update_status_with_serial',
    'application-approve/%/%/%/%/%/%' => 'application-approve',
    'reject-application' => 'reject-application',
    'generate-license/%/%/%' => 'license.generate',
    'view-generated-license' => 'license.list',
    'view-flat-possession-taken-ddo' => 'license.flat-possession-taken',
    'view-flat-released-ddo' => 'license.flat-released',
    'download_licence_pdf/%' => 'download_licence_pdf',
    
    // Application Status
    'application_status' => 'application-status.index',
    'application_status_check' => 'application-status-check.index',
    'common-application-view/%/%' => 'application-status-check.view-list',
    'common-application-view-det/%/%' => 'application-status-check.view-detail',
    'add-possession-det/%/%' => 'application-status-check.add-possession',
    'add-release-date/%/%' => 'application-status-check.add-release',
    'request-for-license-extension/%/%/%/%' => 'application-status-check.license-extension',
    'request-for-offer-letter-extension/%/%/%/%/%' => 'application-status-check.offer-letter-extension',
    
    // Allotment List
    'allotment_list' => 'allotment-list.index',
    'allotment_list_approve' => 'allotment-list.approve',
    'allotment_list_hold' => 'allotment-list.hold',
    'allotment_details/%' => 'allotment-list.detail',
    
    // Generate Allotment Letter
    'generate_allotment_letter' => 'generate-allotment-letter.index',
    
    // View Allotment Details
    'view_allotment_details' => 'view-allotment-details.index',
    'status_update/%/%' => 'view-allotment-details.update-status',
    
    // View Allotment Letter
    'view_proposed_rhe' => 'view-allotment-letter.index',
    'update_allotment/%/%' => 'view-allotment-letter.update-allotment',
    
    // Existing Applicant
    'existing_applicant_entry' => 'existing-applicant.create',
    'existing-applicant-list' => 'existing-applicant.index',
    'view-legacy-applicant-list-whrms' => 'existing-applicant.with-hrms',
    'view-legacy-applicant-list-wohrms' => 'existing-applicant.without-hrms',
    'search-with-physical-application-no' => 'existing-applicant.search',
    'physical-application-view/%' => 'existing-applicant.view',
    'physical-application-edit/%' => 'existing-applicant.edit',
    
    // Existing Occupant
    'rhewise_flatlist' => 'existing-occupant.index',
    'rhewise_flatlist_draft' => 'existing-occupant.index-draft',
    'rhewise_occupant_data_entry/%' => 'existing-occupant.create',
    'rhewise_occupant_draft_data_entry/%' => 'existing-occupant.create-draft',
    'rhewise_occupant_draft_list' => 'existing-occupant.without-hrms',
    'existing-occupant-list-wohrms' => 'existing-occupant.without-hrms-alt',
    'existing-occupant-list-whrms' => 'existing-occupant.with-hrms',
    'view-occupant-list' => 'existing-occupant.with-hrms-alt',
    'existing-occupant-view-det/%' => 'existing-occupant.view',
    'existing-occupant-view-det-draft/%' => 'existing-occupant.view-draft',
    'existing-occupant-edit/%' => 'existing-occupant.edit',
    'existing-occupant-draft-edit/%' => 'existing-occupant.edit-draft',
    'rhe-wise-flat-occupant-delete/%/%/%' => 'existing-occupant.destroy',
    
    // Existing Applicant VS/CS
    'legacy-vs-cs' => 'existing-applicant-vs-cs.flat-wise-form',
    'legay-vs-or-cs-form/%' => 'existing-applicant-vs-cs.create',
    'legacy-vs-list-wohrms' => 'existing-applicant-vs-cs.vs-list-without-hrms',
    'legacy-vs-wohrms-edit/%' => 'existing-applicant-vs-cs.vs-edit-without-hrms',
    'legacy-vs-list-whrms' => 'existing-applicant-vs-cs.vs-list-with-hrms',
    'legacy-vs-whrms-edit/%' => 'existing-applicant-vs-cs.vs-edit-with-hrms',
    'legacy-cs-list-wohrms' => 'existing-applicant-vs-cs.cs-list-without-hrms',
    'legacy-cs-wohrms-edit/%' => 'existing-applicant-vs-cs.cs-edit-without-hrms',
    'legacy-cs-list-whrms' => 'existing-applicant-vs-cs.cs-list-with-hrms',
    'legacy-cs-whrms-edit/%' => 'existing-applicant-vs-cs.cs-edit-with-hrms',
    
    // User Tagging
    'user-tagging' => 'user-tagging.create',
    'flat-wise-user-info' => 'user-tagging.flat-wise-user-info',
    'flat-wise-user-info-details/%' => 'user-tagging.flat-wise-user-details',
    'tagged-user-list/%' => 'user-tagging.tagged-user-list',
    
    // Estate Treasury Mapping
    'estate-treasury-selection' => 'estate-treasury-selection.index',
    'estate-treasury-selection/add' => 'estate-treasury-selection.create',
    'estate-treasury-selection/edit/%' => 'estate-treasury-selection.edit',
    
    // VS/CS Application
    'vs' => 'vertical-shifting.create',
    'cs' => 'category-shifting.create',
    
    // Online Application
    'online_application/%' => 'online_application',
];

// Permission to Role mapping (based on Drupal role IDs)
// Note: This needs to be verified against actual Drupal role-permission assignments
$permissionToRoles = [
    // Applicant permissions (role_id: 4)
    'administer Application List' => [4],
    'administer View Applicant Application' => [4],
    'administer Application Status' => [4],
    'administer New Application' => [4],
    
    // DDO permissions (role_id: 11)
    'administer View Application List' => [11, 6, 7, 8, 10, 13, 17],
    'administer View Application' => [11, 6, 7, 8, 10, 13, 17],
    'administer View Application PDF' => [11, 6, 7, 8, 10, 13, 17],
    'administer approve applicaion' => [11, 6, 7, 8, 10, 13, 17],
    'administer Download Licence PDF' => [11, 6, 7, 8, 10, 13, 17],
    'administer License Generation Application' => [11, 6, 7, 8, 10, 13, 17],
    'administer Flat Released List View' => [11, 6, 7, 8, 10, 13, 17],
    'common_allotment_view' => [6, 7, 8, 10, 13, 17],
    
    // Allotment List permissions
    'administer Allotment List' => [6, 7, 8, 10, 13, 17],
    'administer List of Allottees for New Allotment PDF' => [6, 7, 8, 10, 13, 17],
    'administer View Allotment' => [6, 7, 8, 10, 13, 17],
    'administer Allotment List Approve' => [6, 7, 8, 10, 13, 17],
    'administer Allotment List for Hold' => [6, 7, 8, 10, 13, 17],
    
    // Existing Applicant permissions
    'administer Existing Applicant Form' => [6, 7, 8, 10, 13, 17],
    'administer Physical Applicant List Display' => [6, 7, 8, 10, 13, 17],
    
    // Existing Occupant permissions
    'administer Existing Occupant' => [6, 7, 8, 10, 13, 17],
    'administer Existing Occupant List' => [6, 7, 8, 10, 13, 17],
    'administer Existing Occupant Edit' => [6, 7, 8, 10, 13, 17],
    'administer RHE wise flat list Master' => [6, 7, 8, 10, 13, 17],
    
    // User Tagging permissions
    'administer_user_tagging' => [6, 7, 8, 10, 13, 17],
    
    // Estate Treasury Mapping permissions (assuming similar to other admin permissions)
    'access content' => [4, 5, 6, 7, 8, 10, 11, 13, 17],
];

// Extract routes from Drupal modules
function extractDrupalRoutes($modulesPath) {
    $routes = [];
    $moduleFiles = glob($modulesPath . '/*/*.module');
    
    foreach ($moduleFiles as $moduleFile) {
        $content = file_get_contents($moduleFile);
        
        // Extract menu function
        if (preg_match('/function\s+(\w+)_menu\s*\([^)]*\)\s*\{([^}]+)\}/s', $content, $menuMatch)) {
            $menuFunction = $menuMatch[0];
            
            // Extract menu items
            if (preg_match_all('/\$items\[[\'"]([^\'"]+)[\'"]\]\s*=\s*array\s*\(([^)]+)\)/s', $menuFunction, $itemMatches, PREG_SET_ORDER)) {
                foreach ($itemMatches as $match) {
                    $path = $match[1];
                    $itemContent = $match[2];
                    
                    // Extract title
                    $title = '';
                    if (preg_match("/'title'\s*=>\s*['\"]([^'\"]+)['\"]/", $itemContent, $titleMatch)) {
                        $title = $titleMatch[1];
                    }
                    
                    // Extract access arguments (permissions)
                    $permissions = [];
                    if (preg_match("/'access arguments'\s*=>\s*array\s*\(([^)]+)\)/", $itemContent, $accessMatch)) {
                        $accessContent = $accessMatch[1];
                        if (preg_match_all("/['\"]([^'\"]+)['\"]/", $accessContent, $permMatches)) {
                            $permissions = $permMatches[1];
                        }
                    }
                    
                    $routes[] = [
                        'path' => $path,
                        'title' => $title,
                        'permissions' => $permissions,
                        'module' => basename(dirname($moduleFile)),
                    ];
                }
            }
        }
    }
    
    return $routes;
}

// Generate SQL for routes
function generateRouteSQL($routes, $routeMapping, $permissionToRoles) {
    $sql = "-- SQL script to sync routes from Drupal to Laravel\n";
    $sql .= "-- Generated automatically - DO NOT EDIT MANUALLY\n\n";
    
    $sql .= "BEGIN;\n\n";
    
    // First, delete existing routes (optional - comment out if you want to keep existing)
    // $sql .= "DELETE FROM housing_sidebar_menu_roles;\n";
    // $sql .= "DELETE FROM housing_sidebar_menus WHERE route_name IS NOT NULL;\n\n";
    
    $menuId = 1;
    $insertedRoutes = [];
    
    foreach ($routes as $route) {
        $drupalPath = $route['path'];
        $title = $route['title'] ?: ucfirst(str_replace(['-', '_'], ' ', $drupalPath));
        $laravelRoute = $routeMapping[$drupalPath] ?? null;
        
        if (!$laravelRoute) {
            // Try to find a matching route
            $drupalPathClean = preg_replace('/\/%.*$/', '', $drupalPath);
            foreach ($routeMapping as $drupalKey => $laravelKey) {
                $drupalKeyClean = preg_replace('/\/%.*$/', '', $drupalKey);
                if ($drupalPathClean === $drupalKeyClean) {
                    $laravelRoute = $laravelKey;
                    break;
                }
            }
        }
        
        if (!$laravelRoute) {
            continue; // Skip routes that don't have Laravel equivalents
        }
        
        // Generate URL from Drupal path
        $url = '/' . str_replace('%', '', $drupalPath);
        $url = preg_replace('/\/+/', '/', $url); // Remove duplicate slashes
        
        // Determine icon class based on route type
        $iconClass = 'fa fa-file';
        if (strpos($title, 'List') !== false) {
            $iconClass = 'fa fa-list';
        } elseif (strpos($title, 'View') !== false || strpos($title, 'Detail') !== false) {
            $iconClass = 'fa fa-eye';
        } elseif (strpos($title, 'Create') !== false || strpos($title, 'Add') !== false || strpos($title, 'Entry') !== false) {
            $iconClass = 'fa fa-plus';
        } elseif (strpos($title, 'Edit') !== false) {
            $iconClass = 'fa fa-edit';
        } elseif (strpos($title, 'Application') !== false) {
            $iconClass = 'fa fa-file-alt';
        } elseif (strpos($title, 'User') !== false) {
            $iconClass = 'fa fa-user';
        } elseif (strpos($title, 'License') !== false) {
            $iconClass = 'fa fa-certificate';
        }
        
        // Insert menu item
        $menuName = addslashes($title);
        $routeName = addslashes($laravelRoute);
        $urlEscaped = addslashes($url);
        $iconEscaped = addslashes($iconClass);
        
        $sql .= "-- Route: $drupalPath\n";
        $sql .= "INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)\n";
        $sql .= "VALUES ('$menuName', '$routeName', '$urlEscaped', '$iconEscaped', NULL, $menuId, 1, NOW(), NOW())\n";
        $sql .= "ON CONFLICT DO NOTHING;\n\n";
        
        // Get menu_id (we'll use a subquery in the role assignment)
        $sql .= "-- Assign roles for: $menuName\n";
        
        // Get roles based on permissions
        $roleIds = [];
        foreach ($route['permissions'] as $permission) {
            if (isset($permissionToRoles[$permission])) {
                $roleIds = array_merge($roleIds, $permissionToRoles[$permission]);
            }
        }
        
        // If no specific permissions, use default (access content)
        if (empty($roleIds)) {
            $roleIds = $permissionToRoles['access content'] ?? [4, 5, 6, 7, 8, 10, 11, 13, 17];
        }
        
        $roleIds = array_unique($roleIds);
        
        if (!empty($roleIds)) {
            $roleIdsStr = implode(',', $roleIds);
            $sql .= "INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id, created_at, updated_at)\n";
            $sql .= "SELECT sidebar_menu_id, role_id, NOW(), NOW()\n";
            $sql .= "FROM housing_sidebar_menus, (VALUES ($roleIdsStr)) AS roles(role_id)\n";
            $sql .= "WHERE route_name = '$routeName'\n";
            $sql .= "ON CONFLICT (sidebar_menu_id, role_id) DO NOTHING;\n\n";
        }
        
        $menuId++;
        $insertedRoutes[] = $laravelRoute;
    }
    
    $sql .= "COMMIT;\n";
    
    return $sql;
}

// Main execution
if (!is_dir($drupalModulesPath)) {
    echo "Error: Drupal modules path not found: $drupalModulesPath\n";
    exit(1);
}

echo "Extracting routes from Drupal modules...\n";
$routes = extractDrupalRoutes($drupalModulesPath);
echo "Found " . count($routes) . " routes\n";

echo "Generating SQL...\n";
$sql = generateRouteSQL($routes, $routeMapping, $permissionToRoles);

file_put_contents($outputFile, $sql);
echo "SQL script generated: $outputFile\n";
echo "Please review and execute the SQL script against your database.\n";

