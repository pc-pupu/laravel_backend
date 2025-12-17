-- PostgreSQL INSERT queries for housing_sidebar_menus table
-- Based on Drupal menu structure and role-based access
-- Execute this script in PostgreSQL

-- First, insert parent menus (top-level)
-- Note: menu_id will be auto-generated if it's a serial/auto-increment field
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_id, active) VALUES
('Dashboard', 'dashboard', '/dashboard', 'fa fa-home', NULL, 1, 1),
('New Application', 'new-application.create', '/new-apply', 'fa fa-file-alt', NULL, 2, 1),
('My Applications', 'application-list.index', '/application-list', 'fa fa-list', NULL, 3, 1),
('Application Status', 'application-status.index', '/application_status', 'fa fa-info-circle', NULL, 4, 1),
('User Tagging', 'user-tagging.create', '/user-tagging', 'fa fa-user-tag', NULL, 5, 1),
('View Application List', NULL, NULL, 'fa fa-eye', NULL, 10, 1),
('Existing Applicant', NULL, NULL, 'fa fa-user-friends', NULL, 11, 1),
('Existing Occupant', NULL, NULL, 'fa fa-building', NULL, 12, 1),
('Existing Applicant VS/CS', NULL, NULL, 'fa fa-exchange-alt', NULL, 13, 1),
('RHE Allotment', NULL, '/rhe_allotment', 'fa fa-key', NULL, 14, 1),
('Estate Treasury Mapping', 'estate-treasury-selection.index', '/estate-treasury-selection', 'fa fa-map-marked-alt', NULL, 15, 1),
('License Management', NULL, NULL, 'fa fa-certificate', NULL, 16, 1);

-- Get the parent menu IDs (assuming they're inserted in order)
-- For PostgreSQL, we'll use CTEs or subqueries to get parent IDs

-- Insert child menus under "View Application List"
WITH parent_menu AS (
    SELECT menu_id FROM housing_sidebar_menus WHERE menu_name = 'View Application List' LIMIT 1
)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_id, active)
SELECT 
    'New Application List',
    'application-list.admin-list',
    '/view-application-list/applied/new-apply',
    'fa fa-file',
    (SELECT menu_id FROM parent_menu),
    1,
    1
UNION ALL
SELECT 
    'VS Application List',
    'application-list.admin-list',
    '/view-application-list/applied/vs',
    'fa fa-arrow-up',
    (SELECT menu_id FROM parent_menu),
    2,
    1
UNION ALL
SELECT 
    'CS Application List',
    'application-list.admin-list',
    '/view-application-list/applied/cs',
    'fa fa-exchange-alt',
    (SELECT menu_id FROM parent_menu),
    3,
    1
UNION ALL
SELECT 
    'Generated License List',
    'license.list',
    '/view-generated-license',
    'fa fa-certificate',
    (SELECT menu_id FROM parent_menu),
    4,
    1
UNION ALL
SELECT 
    'Flat Possession Taken',
    'license.flat-possession-taken',
    '/view-flat-possession-taken-ddo',
    'fa fa-key',
    (SELECT menu_id FROM parent_menu),
    5,
    1
UNION ALL
SELECT 
    'Flat Released',
    'license.flat-released',
    '/view-flat-released-ddo',
    'fa fa-door-open',
    (SELECT menu_id FROM parent_menu),
    6,
    1;

-- Insert child menus under "Existing Applicant"
WITH parent_menu AS (
    SELECT menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant' LIMIT 1
)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_id, active)
SELECT 
    'Existing Applicant Entry',
    'existing-applicant.create',
    '/existing_applicant_entry',
    'fa fa-user-plus',
    (SELECT menu_id FROM parent_menu),
    1,
    1
UNION ALL
SELECT 
    'Legacy Applicant List (With HRMS)',
    'existing-applicant.with-hrms',
    '/view-legacy-applicant-list-whrms',
    'fa fa-list',
    (SELECT menu_id FROM parent_menu),
    2,
    1
UNION ALL
SELECT 
    'Legacy Applicant List (Without HRMS)',
    'existing-applicant.without-hrms',
    '/view-legacy-applicant-list-wohrms',
    'fa fa-list-alt',
    (SELECT menu_id FROM parent_menu),
    3,
    1
UNION ALL
SELECT 
    'Search Physical Application',
    NULL,
    '/search-with-physical-application-no',
    'fa fa-search',
    (SELECT menu_id FROM parent_menu),
    4,
    1;

-- Insert child menus under "Existing Occupant"
WITH parent_menu AS (
    SELECT menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Occupant' LIMIT 1
)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_id, active)
SELECT 
    'Existing Occupant Entry',
    'existing-occupant.create',
    '/rhewise_flatlist',
    'fa fa-building',
    (SELECT menu_id FROM parent_menu),
    1,
    1
UNION ALL
SELECT 
    'Existing Occupant Entry (Without HRMS)',
    'existing-occupant.create-draft',
    '/rhewise_flatlist_draft',
    'fa fa-building',
    (SELECT menu_id FROM parent_menu),
    2,
    1
UNION ALL
SELECT 
    'Existing Occupant List (With HRMS)',
    'existing-occupant.with-hrms',
    '/existing-occupant-list-whrms',
    'fa fa-list',
    (SELECT menu_id FROM parent_menu),
    3,
    1
UNION ALL
SELECT 
    'Existing Occupant List (Without HRMS)',
    'existing-occupant.without-hrms',
    '/existing-occupant-list-wohrms',
    'fa fa-list-alt',
    (SELECT menu_id FROM parent_menu),
    4,
    1;

-- Insert child menus under "Existing Applicant VS/CS"
WITH parent_menu AS (
    SELECT menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant VS/CS' LIMIT 1
)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_id, active)
SELECT 
    'Flat Wise Applicant Details',
    'existing-applicant-vs-cs.flat-wise-form',
    '/legacy-vs-cs',
    'fa fa-th-list',
    (SELECT menu_id FROM parent_menu),
    1,
    1
UNION ALL
SELECT 
    'VS List (With HRMS)',
    'existing-applicant-vs-cs.vs-list-whrms',
    '/legacy-vs-list-whrms',
    'fa fa-arrow-up',
    (SELECT menu_id FROM parent_menu),
    2,
    1
UNION ALL
SELECT 
    'VS List (Without HRMS)',
    'existing-applicant-vs-cs.vs-list-wohrms',
    '/legacy-vs-list-wohrms',
    'fa fa-arrow-up',
    (SELECT menu_id FROM parent_menu),
    3,
    1
UNION ALL
SELECT 
    'CS List (With HRMS)',
    'existing-applicant-vs-cs.cs-list-whrms',
    '/legacy-cs-list-whrms',
    'fa fa-exchange-alt',
    (SELECT menu_id FROM parent_menu),
    4,
    1
UNION ALL
SELECT 
    'CS List (Without HRMS)',
    'existing-applicant-vs-cs.cs-list-wohrms',
    '/legacy-cs-list-wohrms',
    'fa fa-exchange-alt',
    (SELECT menu_id FROM parent_menu),
    5,
    1;

-- Insert child menus under "License Management"
WITH parent_menu AS (
    SELECT menu_id FROM housing_sidebar_menus WHERE menu_name = 'License Management' LIMIT 1
)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_id, active)
SELECT 
    'Generate License',
    NULL,
    NULL,
    'fa fa-certificate',
    (SELECT menu_id FROM parent_menu),
    1,
    1
UNION ALL
SELECT 
    'License List',
    'license.list',
    '/view-generated-license',
    'fa fa-list',
    (SELECT menu_id FROM parent_menu),
    2,
    1;

