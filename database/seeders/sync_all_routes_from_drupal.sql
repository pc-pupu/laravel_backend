-- ============================================================================
-- SQL Script to Sync Routes from Drupal-housing to Laravel
-- This script inserts/updates routes in housing_sidebar_menus table
-- and links them to appropriate roles in housing_sidebar_menu_roles table
-- ============================================================================
-- Generated based on Drupal module analysis
-- Execute this script against your PostgreSQL database
-- ============================================================================

BEGIN;

-- ============================================================================
-- PART 0: Ensure unique constraint on route_name for proper upsert
-- ============================================================================

-- Create unique index on route_name if it doesn't exist (for ON CONFLICT to work)
CREATE UNIQUE INDEX IF NOT EXISTS idx_housing_sidebar_menus_route_name_unique 
ON housing_sidebar_menus(route_name) 
WHERE route_name IS NOT NULL;

-- ============================================================================
-- PART 1: Insert/Update Routes in housing_sidebar_menus
-- ============================================================================

-- ============================================================================
-- Applicant Routes (Role ID: 4 - Applicant)
-- ============================================================================

-- Dashboard
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('Dashboard', 'dashboard', '/dashboard', 'fa fa-home', NULL, 1, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- New Application
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('Application for New Allotment', 'new-application.create', '/new-apply', 'fa fa-file-alt', NULL, 2, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- VS Application
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('Application for Vertical Shifting', 'vertical-shifting.create', '/vs', 'fa fa-arrow-up', NULL, 3, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- CS Application
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('Application for Category Shifting', 'category-shifting.create', '/cs', 'fa fa-exchange-alt', NULL, 4, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- My Applications
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('My Applications', 'application-list.index', '/application-list', 'fa fa-list', NULL, 5, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- Application Status
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('Application Status', 'application-status.index', '/application_status', 'fa fa-info-circle', NULL, 6, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- User Tagging
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('User Tagging', 'user-tagging.create', '/user-tagging', 'fa fa-user-tag', NULL, 7, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- ============================================================================
-- Admin/Official Routes (Role IDs: 6, 7, 8, 10, 11, 13, 17)
-- ============================================================================

-- View Application List (Parent Menu)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
SELECT 'View Application List', NULL, NULL, 'fa fa-eye', NULL, 10, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM housing_sidebar_menus WHERE menu_name = 'View Application List' AND parent_id IS NULL);
UPDATE housing_sidebar_menus SET updated_at = NOW() WHERE menu_name = 'View Application List' AND parent_id IS NULL;

-- Get parent menu ID for View Application List and insert child menus
DO $$
DECLARE
    view_app_list_parent_id BIGINT;
BEGIN
    SELECT sidebar_menu_id INTO view_app_list_parent_id 
    FROM housing_sidebar_menus 
    WHERE menu_name = 'View Application List' AND parent_id IS NULL 
    LIMIT 1;
    
    -- New Application List (child) - Note: Same route_name but different URL
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    SELECT 'New Application List', 'view_application_list.dashboard', '/view_application_list/applied/new-apply', 'fa fa-file', view_app_list_parent_id, 1, 1, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM housing_sidebar_menus WHERE route_name = 'view_application_list.dashboard' AND url = '/view_application_list/applied/new-apply');
    UPDATE housing_sidebar_menus SET menu_name = 'New Application List', url = '/view_application_list/applied/new-apply', icon_class = 'fa fa-file', parent_id = view_app_list_parent_id, order_no = 1, updated_at = NOW() 
    WHERE route_name = 'view_application_list.dashboard' AND url = '/view_application_list/applied/new-apply';
    
    -- VS Application List (child)
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    SELECT 'VS Application List', 'view_application_list.dashboard', '/view_application_list/applied/vs', 'fa fa-arrow-up', view_app_list_parent_id, 2, 1, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM housing_sidebar_menus WHERE route_name = 'view_application_list.dashboard' AND url = '/view_application_list/applied/vs');
    UPDATE housing_sidebar_menus SET menu_name = 'VS Application List', url = '/view_application_list/applied/vs', icon_class = 'fa fa-arrow-up', parent_id = view_app_list_parent_id, order_no = 2, updated_at = NOW() 
    WHERE route_name = 'view_application_list.dashboard' AND url = '/view_application_list/applied/vs';
    
    -- CS Application List (child)
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    SELECT 'CS Application List', 'view_application_list.dashboard', '/view_application_list/applied/cs', 'fa fa-exchange-alt', view_app_list_parent_id, 3, 1, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM housing_sidebar_menus WHERE route_name = 'view_application_list.dashboard' AND url = '/view_application_list/applied/cs');
    UPDATE housing_sidebar_menus SET menu_name = 'CS Application List', url = '/view_application_list/applied/cs', icon_class = 'fa fa-exchange-alt', parent_id = view_app_list_parent_id, order_no = 3, updated_at = NOW() 
    WHERE route_name = 'view_application_list.dashboard' AND url = '/view_application_list/applied/cs';
END $$;

-- Application Status Check (for officials)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('Application Details Check', 'application-status-check.index', '/application_status_check', 'fa fa-search', NULL, 11, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- Allotment List
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('List of Allottees', 'allotment-list.index', '/allotment_list', 'fa fa-users', NULL, 12, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- Allotment List Approve
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('List of Allottees for Approval', 'allotment-list.approve', '/allotment_list_approve', 'fa fa-check-circle', NULL, 13, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- Allotment List Hold
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('List of Allottees for Hold', 'allotment-list.hold', '/allotment_list_hold', 'fa fa-pause-circle', NULL, 14, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- Generate Allotment Letter
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('Generate Allotment Letter', 'generate-allotment-letter.index', '/generate_allotment_letter', 'fa fa-file-pdf', NULL, 15, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- View Allotment Details
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('View Allotment Details', 'view-allotment-details.index', '/view_allotment_details', 'fa fa-eye', NULL, 16, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- View Allotment Letter
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('View Proposed RHE', 'view-allotment-letter.index', '/view_proposed_rhe', 'fa fa-file-alt', NULL, 17, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- Existing Applicant (Parent Menu)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
SELECT 'Existing Applicant', NULL, NULL, 'fa fa-user-friends', NULL, 20, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant' AND parent_id IS NULL);
UPDATE housing_sidebar_menus SET updated_at = NOW() WHERE menu_name = 'Existing Applicant' AND parent_id IS NULL;

-- Get parent menu ID for Existing Applicant and insert child menus
DO $$
DECLARE
    existing_applicant_parent_id BIGINT;
BEGIN
    SELECT sidebar_menu_id INTO existing_applicant_parent_id 
    FROM housing_sidebar_menus 
    WHERE menu_name = 'Existing Applicant' AND parent_id IS NULL 
    LIMIT 1;
    
    -- Existing Applicant Entry
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Existing Applicant Entry', 'existing-applicant.create', '/existing_applicant_entry', 'fa fa-user-plus', existing_applicant_parent_id, 1, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
    
    -- Legacy Applicant List (With HRMS)
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Legacy Applicant List (With HRMS)', 'existing-applicant.with-hrms', '/view-legacy-applicant-list-whrms', 'fa fa-list', existing_applicant_parent_id, 2, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
    
    -- Legacy Applicant List (Without HRMS)
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Legacy Applicant List (Without HRMS)', 'existing-applicant.without-hrms', '/view-legacy-applicant-list-wohrms', 'fa fa-list', existing_applicant_parent_id, 3, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
    
    -- Search with Physical Application No
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Search with Physical Application No', 'existing-applicant.search', '/search-with-physical-application-no', 'fa fa-search', existing_applicant_parent_id, 4, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
END $$;

-- Existing Occupant (Parent Menu)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
SELECT 'Existing Occupant', NULL, NULL, 'fa fa-building', NULL, 21, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM housing_sidebar_menus WHERE menu_name = 'Existing Occupant' AND parent_id IS NULL);
UPDATE housing_sidebar_menus SET updated_at = NOW() WHERE menu_name = 'Existing Occupant' AND parent_id IS NULL;

-- Get parent menu ID for Existing Occupant and insert child menus
DO $$
DECLARE
    existing_occupant_parent_id BIGINT;
BEGIN
    SELECT sidebar_menu_id INTO existing_occupant_parent_id 
    FROM housing_sidebar_menus 
    WHERE menu_name = 'Existing Occupant' AND parent_id IS NULL 
    LIMIT 1;
    
    -- Data Entry For Existing Occupant
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    SELECT 'Data Entry For Existing Occupant', 'existing-occupant.index', '/rhewise_flatlist', 'fa fa-plus', existing_occupant_parent_id, 1, 1, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM housing_sidebar_menus WHERE route_name = 'existing-occupant.index' AND url = '/rhewise_flatlist');
    UPDATE housing_sidebar_menus SET menu_name = 'Data Entry For Existing Occupant', url = '/rhewise_flatlist', icon_class = 'fa fa-plus', parent_id = existing_occupant_parent_id, order_no = 1, updated_at = NOW() 
    WHERE route_name = 'existing-occupant.index' AND url = '/rhewise_flatlist';
    
    -- Data Entry For Existing Occupant Without HRMS ID
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Data Entry For Existing Occupant Without HRMS ID', 'existing-occupant.index-draft', '/rhewise_flatlist_draft', 'fa fa-plus', existing_occupant_parent_id, 2, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
    
    -- Existing Occupant List With HRMS ID
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Existing Occupant List With HRMS ID', 'existing-occupant.with-hrms', '/existing-occupant-list-whrms', 'fa fa-list', existing_occupant_parent_id, 3, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
    
    -- Existing Occupant List Without HRMS ID
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    SELECT 'Existing Occupant List Without HRMS ID', 'existing-occupant.without-hrms', '/existing-occupant-list-wohrms', 'fa fa-list', existing_occupant_parent_id, 4, 1, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM housing_sidebar_menus WHERE route_name = 'existing-occupant.without-hrms' AND url = '/existing-occupant-list-wohrms');
    UPDATE housing_sidebar_menus SET menu_name = 'Existing Occupant List Without HRMS ID', url = '/existing-occupant-list-wohrms', icon_class = 'fa fa-list', parent_id = existing_occupant_parent_id, order_no = 4, updated_at = NOW() 
    WHERE route_name = 'existing-occupant.without-hrms' AND url = '/existing-occupant-list-wohrms';
END $$;

-- Existing Applicant VS/CS (Parent Menu)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
SELECT 'Existing Applicant VS/CS', NULL, NULL, 'fa fa-exchange-alt', NULL, 22, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant VS/CS' AND parent_id IS NULL);
UPDATE housing_sidebar_menus SET updated_at = NOW() WHERE menu_name = 'Existing Applicant VS/CS' AND parent_id IS NULL;

-- Get parent menu ID for Existing Applicant VS/CS and insert child menus
DO $$
DECLARE
    vs_cs_parent_id BIGINT;
BEGIN
    SELECT sidebar_menu_id INTO vs_cs_parent_id 
    FROM housing_sidebar_menus 
    WHERE menu_name = 'Existing Applicant VS/CS' AND parent_id IS NULL 
    LIMIT 1;
    
    -- Legacy VS List (Without HRMS)
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Legacy VS List (Without HRMS)', 'existing-applicant-vs-cs.vs-list-without-hrms', '/legacy-vs-list-wohrms', 'fa fa-list', vs_cs_parent_id, 1, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
    
    -- Legacy VS List (With HRMS)
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Legacy VS List (With HRMS)', 'existing-applicant-vs-cs.vs-list-with-hrms', '/legacy-vs-list-whrms', 'fa fa-list', vs_cs_parent_id, 2, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
    
    -- Legacy CS List (Without HRMS)
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Legacy CS List (Without HRMS)', 'existing-applicant-vs-cs.cs-list-without-hrms', '/legacy-cs-list-wohrms', 'fa fa-list', vs_cs_parent_id, 3, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
    
    -- Legacy CS List (With HRMS)
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Legacy CS List (With HRMS)', 'existing-applicant-vs-cs.cs-list-with-hrms', '/legacy-cs-list-whrms', 'fa fa-list', vs_cs_parent_id, 4, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
END $$;

-- License Management (Parent Menu)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
SELECT 'License Management', NULL, NULL, 'fa fa-certificate', NULL, 23, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM housing_sidebar_menus WHERE menu_name = 'License Management' AND parent_id IS NULL);
UPDATE housing_sidebar_menus SET updated_at = NOW() WHERE menu_name = 'License Management' AND parent_id IS NULL;

-- Get parent menu ID for License Management and insert child menus
DO $$
DECLARE
    license_parent_id BIGINT;
BEGIN
    SELECT sidebar_menu_id INTO license_parent_id 
    FROM housing_sidebar_menus 
    WHERE menu_name = 'License Management' AND parent_id IS NULL 
    LIMIT 1;
    
    -- View Generated License
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('View Generated License', 'license.list', '/view-generated-license', 'fa fa-certificate', license_parent_id, 1, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
    
    -- Flat Possession Taken
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Flat Possession Taken', 'license.flat-possession-taken', '/view-flat-possession-taken-ddo', 'fa fa-key', license_parent_id, 2, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
    
    -- Flat Released
    INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
    VALUES ('Flat Released', 'license.flat-released', '/view-flat-released-ddo', 'fa fa-door-open', license_parent_id, 3, 1, NOW(), NOW())
    ON CONFLICT (route_name) DO UPDATE SET
        menu_name = EXCLUDED.menu_name,
        url = EXCLUDED.url,
        icon_class = EXCLUDED.icon_class,
        parent_id = EXCLUDED.parent_id,
        order_no = EXCLUDED.order_no,
        is_active = EXCLUDED.is_active,
        updated_at = NOW();
END $$;

-- Estate Treasury Mapping
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active, created_at, updated_at)
VALUES ('Estate Treasury Mapping', 'estate-treasury-selection.index', '/estate-treasury-selection', 'fa fa-map-marked-alt', NULL, 24, 1, NOW(), NOW())
ON CONFLICT (route_name) DO UPDATE SET
    menu_name = EXCLUDED.menu_name,
    url = EXCLUDED.url,
    icon_class = EXCLUDED.icon_class,
    parent_id = EXCLUDED.parent_id,
    order_no = EXCLUDED.order_no,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- ============================================================================
-- PART 2: Assign Roles to Menu Items
-- ============================================================================
-- Note: Role IDs are based on Laravel roles table. 
-- Drupal role IDs are stored in roles.drupal_role_id
-- Common mappings: Applicant=4, DDO=11, Housing Official=6, Housing Supervisor=10, Housing Approver=13, etc.

-- Applicant Routes (Role ID: 4 - Applicant)
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id, created_at, updated_at)
SELECT hsm.sidebar_menu_id, r.id, NOW(), NOW()
FROM housing_sidebar_menus hsm
JOIN roles r ON r.drupal_role_id = 4
WHERE hsm.route_name IN ('dashboard', 'new-application.create', 'vertical-shifting.create', 'category-shifting.create', 'application-list.index', 'application-status.index', 'user-tagging.create')
ON CONFLICT (sidebar_menu_id, role_id) DO NOTHING;

-- DDO Routes (Role ID: 11 - DDO)
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id, created_at, updated_at)
SELECT hsm.sidebar_menu_id, r.id, NOW(), NOW()
FROM housing_sidebar_menus hsm
JOIN roles r ON r.drupal_role_id = 11
WHERE hsm.route_name IN (
    'view_application_list.dashboard',
    'view_application',
    'application_detail',
    'application_detail_pdf',
    'update_status',
    'update_status_with_serial',
    'application-approve',
    'reject-application',
    'license.generate',
    'license.list',
    'license.flat-possession-taken',
    'license.flat-released',
    'download_licence_pdf',
    'application-status-check.index',
    'allotment-list.index',
    'allotment-list.approve',
    'allotment-list.hold',
    'allotment-list.detail',
    'generate-allotment-letter.index',
    'view-allotment-details.index',
    'view-allotment-letter.index',
    'existing-applicant.create',
    'existing-applicant.with-hrms',
    'existing-applicant.without-hrms',
    'existing-applicant.search',
    'existing-occupant.index',
    'existing-occupant.index-draft',
    'existing-occupant.with-hrms',
    'existing-occupant.without-hrms',
    'existing-applicant-vs-cs.vs-list-without-hrms',
    'existing-applicant-vs-cs.vs-list-with-hrms',
    'existing-applicant-vs-cs.cs-list-without-hrms',
    'existing-applicant-vs-cs.cs-list-with-hrms',
    'estate-treasury-selection.index'
)
ON CONFLICT (sidebar_menu_id, role_id) DO NOTHING;

-- Housing Official Routes (Role IDs: 6, 7, 8, 10, 13, 17)
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id, created_at, updated_at)
SELECT hsm.sidebar_menu_id, r.id, NOW(), NOW()
FROM housing_sidebar_menus hsm
JOIN roles r ON r.drupal_role_id IN (6, 7, 8, 10, 13, 17)
WHERE hsm.route_name IN (
    'view_application_list.dashboard',
    'view_application',
    'application_detail',
    'application_detail_pdf',
    'update_status',
    'update_status_with_serial',
    'application-approve',
    'reject-application',
    'license.generate',
    'license.list',
    'license.flat-possession-taken',
    'license.flat-released',
    'download_licence_pdf',
    'application-status-check.index',
    'allotment-list.index',
    'allotment-list.approve',
    'allotment-list.hold',
    'allotment-list.detail',
    'generate-allotment-letter.index',
    'view-allotment-details.index',
    'view-allotment-letter.index',
    'existing-applicant.create',
    'existing-applicant.with-hrms',
    'existing-applicant.without-hrms',
    'existing-applicant.search',
    'existing-occupant.index',
    'existing-occupant.index-draft',
    'existing-occupant.with-hrms',
    'existing-occupant.without-hrms',
    'existing-applicant-vs-cs.vs-list-without-hrms',
    'existing-applicant-vs-cs.vs-list-with-hrms',
    'existing-applicant-vs-cs.cs-list-without-hrms',
    'existing-applicant-vs-cs.cs-list-with-hrms',
    'estate-treasury-selection.index'
)
ON CONFLICT (sidebar_menu_id, role_id) DO NOTHING;

-- Assign roles to child menus under "View Application List"
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id, created_at, updated_at)
SELECT m.sidebar_menu_id, r.id, NOW(), NOW()
FROM housing_sidebar_menus m
JOIN roles r ON r.drupal_role_id IN (6, 7, 8, 10, 11, 13, 17)
WHERE m.parent_id = (SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'View Application List' AND parent_id IS NULL LIMIT 1)
ON CONFLICT (sidebar_menu_id, role_id) DO NOTHING;

-- Assign roles to child menus under "Existing Applicant"
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id, created_at, updated_at)
SELECT m.sidebar_menu_id, r.id, NOW(), NOW()
FROM housing_sidebar_menus m
JOIN roles r ON r.drupal_role_id IN (6, 7, 8, 10, 11, 13, 17)
WHERE m.parent_id = (SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant' AND parent_id IS NULL LIMIT 1)
ON CONFLICT (sidebar_menu_id, role_id) DO NOTHING;

-- Assign roles to child menus under "Existing Occupant"
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id, created_at, updated_at)
SELECT m.sidebar_menu_id, r.id, NOW(), NOW()
FROM housing_sidebar_menus m
JOIN roles r ON r.drupal_role_id IN (6, 7, 8, 10, 11, 13, 17)
WHERE m.parent_id = (SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Occupant' AND parent_id IS NULL LIMIT 1)
ON CONFLICT (sidebar_menu_id, role_id) DO NOTHING;

-- Assign roles to child menus under "Existing Applicant VS/CS"
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id, created_at, updated_at)
SELECT m.sidebar_menu_id, r.id, NOW(), NOW()
FROM housing_sidebar_menus m
JOIN roles r ON r.drupal_role_id IN (6, 7, 8, 10, 11, 13, 17)
WHERE m.parent_id = (SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant VS/CS' AND parent_id IS NULL LIMIT 1)
ON CONFLICT (sidebar_menu_id, role_id) DO NOTHING;

-- Assign roles to child menus under "License Management"
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id, created_at, updated_at)
SELECT m.sidebar_menu_id, r.id, NOW(), NOW()
FROM housing_sidebar_menus m
JOIN roles r ON r.drupal_role_id IN (6, 7, 8, 10, 11, 13, 17)
WHERE m.parent_id = (SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'License Management' AND parent_id IS NULL LIMIT 1)
ON CONFLICT (sidebar_menu_id, role_id) DO NOTHING;

COMMIT;

-- ============================================================================
-- Verification Queries (Run these after the script to verify)
-- ============================================================================

-- Check all routes inserted
-- SELECT menu_name, route_name, url, order_no FROM housing_sidebar_menus WHERE route_name IS NOT NULL ORDER BY order_no;

-- Check role assignments
-- SELECT m.menu_name, m.route_name, r.name as role_name, r.drupal_role_id
-- FROM housing_sidebar_menus m
-- JOIN housing_sidebar_menu_roles mr ON m.sidebar_menu_id = mr.sidebar_menu_id
-- JOIN roles r ON mr.role_id = r.id
-- WHERE m.route_name IS NOT NULL
-- ORDER BY m.menu_name, r.name;
