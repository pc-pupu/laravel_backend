-- ============================================================================
-- PostgreSQL INSERT queries for housing_sidebar_menus and housing_sidebar_menu_roles
-- Based on Drupal menu structure and role-based access
-- 
-- IMPORTANT: This script will SKIP menus that already exist in the database.
-- It uses WHERE NOT EXISTS clauses to check for existing menus by menu_name
-- and parent_id before inserting. This prevents duplicate entries.
-- 
-- Role IDs Reference:
-- 4 = Applicant
-- 5 = Occupant  
-- 6 = Housing Official
-- 7 = RHE Division/Subdivision
-- 8 = RHE Division
-- 10 = Housing Supervisor
-- 11 = DDO
-- 13 = Housing Approver
-- 17 = Special Recommendation
-- ============================================================================

-- ============================================================================
-- PART 1: Insert into housing_sidebar_menus
-- ============================================================================

-- Insert parent menus (top-level) - Skip if already exists
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active)
SELECT * FROM (VALUES
    ('Dashboard', 'dashboard', '/dashboard', 'fa fa-home', NULL::bigint, 1, true),
    ('New Application', 'new-application.create', '/new-apply', 'fa fa-file-alt', NULL::bigint, 2, true),
    ('My Applications', 'application-list.index', '/application-list', 'fa fa-list', NULL::bigint, 3, true),
    ('Application Status', 'application-status.index', '/application_status', 'fa fa-info-circle', NULL::bigint, 4, true),
    ('User Tagging', 'user-tagging.create', '/user-tagging', 'fa fa-user-tag', NULL::bigint, 5, true),
    ('View Application List', NULL, NULL, 'fa fa-eye', NULL::bigint, 10, true),
    ('Existing Applicant', NULL, NULL, 'fa fa-user-friends', NULL::bigint, 11, true),
    ('Existing Occupant', NULL, NULL, 'fa fa-building', NULL::bigint, 12, true),
    ('Existing Applicant VS/CS', NULL, NULL, 'fa fa-exchange-alt', NULL::bigint, 13, true),
    ('RHE Allotment', NULL, '/rhe_allotment', 'fa fa-key', NULL::bigint, 14, true),
    ('Estate Treasury Mapping', 'estate-treasury-selection.index', '/estate-treasury-selection', 'fa fa-map-marked-alt', NULL::bigint, 15, true),
    ('License Management', NULL, NULL, 'fa fa-certificate', NULL::bigint, 16, true)
) AS v(menu_name, route_name, url, icon_class, parent_id, order_no, is_active)
WHERE NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menus hsm 
    WHERE hsm.menu_name = v.menu_name 
    AND (hsm.parent_id IS NULL AND v.parent_id IS NULL OR hsm.parent_id = v.parent_id)
);

-- Insert child menus under "View Application List" - Skip if already exists
WITH parent_menu AS (
    SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'View Application List' AND parent_id IS NULL LIMIT 1
)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active)
SELECT 
    v.menu_name,
    v.route_name,
    v.url,
    v.icon_class,
    (SELECT sidebar_menu_id FROM parent_menu),
    v.order_no,
    v.is_active
FROM (VALUES
    ('New Application List', 'application-list.admin-list', '/view-application-list/applied/new-apply', 'fa fa-file', 1, true),
    ('VS Application List', 'application-list.admin-list', '/view-application-list/applied/vs', 'fa fa-arrow-up', 2, true),
    ('CS Application List', 'application-list.admin-list', '/view-application-list/applied/cs', 'fa fa-exchange-alt', 3, true),
    ('Generated License List', 'license.list', '/view-generated-license', 'fa fa-certificate', 4, true),
    ('Flat Possession Taken', 'license.flat-possession-taken', '/view-flat-possession-taken-ddo', 'fa fa-key', 5, true),
    ('Flat Released', 'license.flat-released', '/view-flat-released-ddo', 'fa fa-door-open', 6, true)
) AS v(menu_name, route_name, url, icon_class, order_no, is_active)
WHERE NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menus hsm 
    WHERE hsm.menu_name = v.menu_name 
    AND hsm.parent_id = (SELECT sidebar_menu_id FROM parent_menu)
)
AND EXISTS (SELECT 1 FROM parent_menu);

-- Insert child menus under "Existing Applicant" - Skip if already exists
WITH parent_menu AS (
    SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant' AND parent_id IS NULL LIMIT 1
)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active)
SELECT 
    v.menu_name,
    v.route_name,
    v.url,
    v.icon_class,
    (SELECT sidebar_menu_id FROM parent_menu),
    v.order_no,
    v.is_active
FROM (VALUES
    ('Existing Applicant Entry', 'existing-applicant.create', '/existing_applicant_entry', 'fa fa-user-plus', 1, true),
    ('Legacy Applicant List (With HRMS)', 'existing-applicant.with-hrms', '/view-legacy-applicant-list-whrms', 'fa fa-list', 2, true),
    ('Legacy Applicant List (Without HRMS)', 'existing-applicant.without-hrms', '/view-legacy-applicant-list-wohrms', 'fa fa-list-alt', 3, true),
    ('Search Physical Application', NULL, '/search-with-physical-application-no', 'fa fa-search', 4, true)
) AS v(menu_name, route_name, url, icon_class, order_no, is_active)
WHERE NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menus hsm 
    WHERE hsm.menu_name = v.menu_name 
    AND hsm.parent_id = (SELECT sidebar_menu_id FROM parent_menu)
)
AND EXISTS (SELECT 1 FROM parent_menu);

-- Insert child menus under "Existing Occupant" - Skip if already exists
WITH parent_menu AS (
    SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Occupant' AND parent_id IS NULL LIMIT 1
)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active)
SELECT 
    v.menu_name,
    v.route_name,
    v.url,
    v.icon_class,
    (SELECT sidebar_menu_id FROM parent_menu),
    v.order_no,
    v.is_active
FROM (VALUES
    ('Existing Occupant Entry', 'existing-occupant.create', '/rhewise_flatlist', 'fa fa-building', 1, true),
    ('Existing Occupant Entry (Without HRMS)', 'existing-occupant.create-draft', '/rhewise_flatlist_draft', 'fa fa-building', 2, true),
    ('Existing Occupant List (With HRMS)', 'existing-occupant.with-hrms', '/existing-occupant-list-whrms', 'fa fa-list', 3, true),
    ('Existing Occupant List (Without HRMS)', 'existing-occupant.without-hrms', '/existing-occupant-list-wohrms', 'fa fa-list-alt', 4, true)
) AS v(menu_name, route_name, url, icon_class, order_no, is_active)
WHERE NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menus hsm 
    WHERE hsm.menu_name = v.menu_name 
    AND hsm.parent_id = (SELECT sidebar_menu_id FROM parent_menu)
)
AND EXISTS (SELECT 1 FROM parent_menu);

-- Insert child menus under "Existing Applicant VS/CS" - Skip if already exists
WITH parent_menu AS (
    SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant VS/CS' AND parent_id IS NULL LIMIT 1
)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active)
SELECT 
    v.menu_name,
    v.route_name,
    v.url,
    v.icon_class,
    (SELECT sidebar_menu_id FROM parent_menu),
    v.order_no,
    v.is_active
FROM (VALUES
    ('Flat Wise Applicant Details', 'existing-applicant-vs-cs.flat-wise-form', '/legacy-vs-cs', 'fa fa-th-list', 1, true),
    ('VS List (With HRMS)', 'existing-applicant-vs-cs.vs-list-whrms', '/legacy-vs-list-whrms', 'fa fa-arrow-up', 2, true),
    ('VS List (Without HRMS)', 'existing-applicant-vs-cs.vs-list-wohrms', '/legacy-vs-list-wohrms', 'fa fa-arrow-up', 3, true),
    ('CS List (With HRMS)', 'existing-applicant-vs-cs.cs-list-whrms', '/legacy-cs-list-whrms', 'fa fa-exchange-alt', 4, true),
    ('CS List (Without HRMS)', 'existing-applicant-vs-cs.cs-list-wohrms', '/legacy-cs-list-wohrms', 'fa fa-exchange-alt', 5, true)
) AS v(menu_name, route_name, url, icon_class, order_no, is_active)
WHERE NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menus hsm 
    WHERE hsm.menu_name = v.menu_name 
    AND hsm.parent_id = (SELECT sidebar_menu_id FROM parent_menu)
)
AND EXISTS (SELECT 1 FROM parent_menu);

-- Insert child menus under "License Management" - Skip if already exists
WITH parent_menu AS (
    SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'License Management' AND parent_id IS NULL LIMIT 1
)
INSERT INTO housing_sidebar_menus (menu_name, route_name, url, icon_class, parent_id, order_no, is_active)
SELECT 
    v.menu_name,
    v.route_name,
    v.url,
    v.icon_class,
    (SELECT sidebar_menu_id FROM parent_menu),
    v.order_no,
    v.is_active
FROM (VALUES
    ('Generate License', NULL, NULL, 'fa fa-certificate', 1, true),
    ('License List', 'license.list', '/view-generated-license', 'fa fa-list', 2, true)
) AS v(menu_name, route_name, url, icon_class, order_no, is_active)
WHERE NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menus hsm 
    WHERE hsm.menu_name = v.menu_name 
    AND hsm.parent_id = (SELECT sidebar_menu_id FROM parent_menu)
)
AND EXISTS (SELECT 1 FROM parent_menu);

-- ============================================================================
-- PART 2: Insert into housing_sidebar_menu_roles
-- ============================================================================

-- Dashboard - Available to all authenticated users (roles 4, 5, 6, 7, 8, 10, 11, 13, 17) - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus hsm
CROSS JOIN (VALUES (4), (5), (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE hsm.menu_name = 'Dashboard' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- New Application - Only for Applicants (role 4) - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, 4
FROM housing_sidebar_menus hsm
WHERE hsm.menu_name = 'New Application' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = 4
);

-- My Applications - Only for Applicants (role 4) - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, 4
FROM housing_sidebar_menus hsm
WHERE hsm.menu_name = 'My Applications' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = 4
);

-- Application Status - Available to all authenticated users - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus hsm
CROSS JOIN (VALUES (4), (5), (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE hsm.menu_name = 'Application Status' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- User Tagging - Only for Applicants (role 4) - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, 4
FROM housing_sidebar_menus hsm
WHERE hsm.menu_name = 'User Tagging' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = 4
);

-- View Application List - For Officials (roles 6, 7, 8, 10, 11, 13, 17) - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus hsm
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE hsm.menu_name = 'View Application List' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- View Application List children - Same roles as parent - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT m.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus m
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE m.parent_id = (SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'View Application List' AND parent_id IS NULL LIMIT 1)
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = m.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- Existing Applicant - For Officials (roles 6, 7, 8, 10, 11, 13, 17) - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus hsm
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE hsm.menu_name = 'Existing Applicant' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- Existing Applicant children - Same roles as parent - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT m.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus m
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE m.parent_id = (SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant' AND parent_id IS NULL LIMIT 1)
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = m.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- Existing Occupant - For Officials (roles 6, 7, 8, 10, 11, 13, 17) - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus hsm
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE hsm.menu_name = 'Existing Occupant' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- Existing Occupant children - Same roles as parent - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT m.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus m
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE m.parent_id = (SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Occupant' AND parent_id IS NULL LIMIT 1)
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = m.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- Existing Applicant VS/CS - For Officials (roles 6, 7, 8, 10, 11, 13, 17) - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus hsm
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE hsm.menu_name = 'Existing Applicant VS/CS' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- Existing Applicant VS/CS children - Same roles as parent - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT m.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus m
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE m.parent_id = (SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant VS/CS' AND parent_id IS NULL LIMIT 1)
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = m.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- RHE Allotment - For Officials (roles 6, 7, 8, 10, 11, 13, 17) - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus hsm
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE hsm.menu_name = 'RHE Allotment' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- Estate Treasury Mapping - For Officials (roles 6, 7, 8, 10, 11, 13, 17) - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus hsm
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE hsm.menu_name = 'Estate Treasury Mapping' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- License Management - For Officials (roles 6, 7, 8, 10, 11, 13, 17) - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT hsm.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus hsm
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE hsm.menu_name = 'License Management' AND hsm.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = hsm.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- License Management children - Same roles as parent - Skip if already exists
INSERT INTO housing_sidebar_menu_roles (sidebar_menu_id, role_id)
SELECT m.sidebar_menu_id, roles.role_id
FROM housing_sidebar_menus m
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE m.parent_id = (SELECT sidebar_menu_id FROM housing_sidebar_menus WHERE menu_name = 'License Management' AND parent_id IS NULL LIMIT 1)
AND NOT EXISTS (
    SELECT 1 FROM housing_sidebar_menu_roles hsmr 
    WHERE hsmr.sidebar_menu_id = m.sidebar_menu_id 
    AND hsmr.role_id = roles.role_id
);

-- ============================================================================
-- END OF SCRIPT
-- ============================================================================

