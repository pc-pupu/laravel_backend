-- PostgreSQL INSERT queries for housing_sidebar_menu_roles table
-- Maps menus to roles based on Drupal access permissions
-- Role IDs: 4=Applicant, 5=Occupant, 6=Housing Official, 7=RHE Division/Subdivision, 8=RHE Division, 10=Housing Supervisor, 11=DDO, 13=Housing Approver, 17=Special Recommendation

-- Dashboard - Available to all authenticated users (roles 4, 5, 6, 7, 8, 10, 11, 13, 17)
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, role_id
FROM housing_sidebar_menus, (VALUES (4), (5), (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE menu_name = 'Dashboard';

-- New Application - Only for Applicants (role 4)
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, 4
FROM housing_sidebar_menus
WHERE menu_name = 'New Application';

-- My Applications - Only for Applicants (role 4)
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, 4
FROM housing_sidebar_menus
WHERE menu_name = 'My Applications';

-- Application Status - Available to all authenticated users
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, role_id
FROM housing_sidebar_menus, (VALUES (4), (5), (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE menu_name = 'Application Status';

-- User Tagging - Only for Applicants (role 4)
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, 4
FROM housing_sidebar_menus
WHERE menu_name = 'User Tagging';

-- View Application List - For Officials (roles 6, 7, 8, 10, 11, 13, 17)
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, role_id
FROM housing_sidebar_menus, (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE menu_name = 'View Application List';

-- View Application List children - Same roles as parent
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT m.menu_id, role_id
FROM housing_sidebar_menus m
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE m.parent_id = (SELECT menu_id FROM housing_sidebar_menus WHERE menu_name = 'View Application List' LIMIT 1);

-- Existing Applicant - For Officials (roles 6, 7, 8, 10, 11, 13, 17)
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, role_id
FROM housing_sidebar_menus, (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE menu_name = 'Existing Applicant';

-- Existing Applicant children - Same roles as parent
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT m.menu_id, role_id
FROM housing_sidebar_menus m
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE m.parent_id = (SELECT menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant' LIMIT 1);

-- Existing Occupant - For RHE Division/Subdivision (roles 7, 8) and other officials (6, 10, 11, 13, 17)
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, role_id
FROM housing_sidebar_menus, (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE menu_name = 'Existing Occupant';

-- Existing Occupant children - Same roles as parent
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT m.menu_id, role_id
FROM housing_sidebar_menus m
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE m.parent_id = (SELECT menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Occupant' LIMIT 1);

-- Existing Applicant VS/CS - For Officials (roles 6, 7, 8, 10, 11, 13, 17)
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, role_id
FROM housing_sidebar_menus, (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE menu_name = 'Existing Applicant VS/CS';

-- Existing Applicant VS/CS children - Same roles as parent
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT m.menu_id, role_id
FROM housing_sidebar_menus m
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE m.parent_id = (SELECT menu_id FROM housing_sidebar_menus WHERE menu_name = 'Existing Applicant VS/CS' LIMIT 1);

-- RHE Allotment - For Officials (roles 6, 7, 8, 10, 11, 13, 17)
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, role_id
FROM housing_sidebar_menus, (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE menu_name = 'RHE Allotment';

-- Estate Treasury Mapping - For Officials (roles 6, 7, 8, 10, 11, 13, 17)
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, role_id
FROM housing_sidebar_menus, (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE menu_name = 'Estate Treasury Mapping';

-- License Management - For Officials (roles 6, 7, 8, 10, 11, 13, 17)
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT menu_id, role_id
FROM housing_sidebar_menus, (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE menu_name = 'License Management';

-- License Management children - Same roles as parent
INSERT INTO housing_sidebar_menu_roles (menu_id, role_id)
SELECT m.menu_id, role_id
FROM housing_sidebar_menus m
CROSS JOIN (VALUES (6), (7), (8), (10), (11), (13), (17)) AS roles(role_id)
WHERE m.parent_id = (SELECT menu_id FROM housing_sidebar_menus WHERE menu_name = 'License Management' LIMIT 1);

