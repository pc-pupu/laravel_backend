# Route Synchronization Instructions

This document provides instructions for synchronizing routes from Drupal-housing to Laravel.

## Overview

The SQL script `sync_all_routes_from_drupal.sql` will:
1. Create a unique index on `route_name` (if it doesn't exist) to enable proper upsert operations
2. Insert/update all routes in the `housing_sidebar_menus` table based on Drupal's `hook_menu()` definitions
3. Assign routes to appropriate roles in the `housing_sidebar_menu_roles` table based on Drupal's `access arguments`

## Prerequisites

1. Ensure you have access to the PostgreSQL database
2. Ensure the `roles` table has `drupal_role_id` column populated with Drupal role IDs
3. Backup your database before running the script

## Execution Steps

### Option 1: Using psql command line

```bash
psql -U your_username -d your_database_name -f laravel_backend/database/seeders/sync_all_routes_from_drupal.sql
```

### Option 2: Using pgAdmin or other GUI tool

1. Open pgAdmin (or your preferred PostgreSQL GUI tool)
2. Connect to your database
3. Open the SQL script file: `laravel_backend/database/seeders/sync_all_routes_from_drupal.sql`
4. Execute the script

### Option 3: Using Laravel Tinker or Artisan

You can also execute the SQL file programmatically, but it's recommended to use direct database access for this migration.

## Verification

After running the script, verify the results using these queries:

```sql
-- Check all routes inserted
SELECT menu_name, route_name, url, order_no 
FROM housing_sidebar_menus 
WHERE route_name IS NOT NULL 
ORDER BY order_no;

-- Check role assignments
SELECT m.menu_name, m.route_name, r.name as role_name, r.drupal_role_id
FROM housing_sidebar_menus m
JOIN housing_sidebar_menu_roles mr ON m.sidebar_menu_id = mr.sidebar_menu_id
JOIN roles r ON mr.role_id = r.id
WHERE m.route_name IS NOT NULL
ORDER BY m.menu_name, r.name;

-- Check for any duplicate route_names (should return empty)
SELECT route_name, COUNT(*) as count
FROM housing_sidebar_menus
WHERE route_name IS NOT NULL
GROUP BY route_name
HAVING COUNT(*) > 1;
```

## Important Notes

1. **Unique Index**: The script creates a unique index on `route_name`. If you have duplicate `route_name` values in your database, the index creation will fail. You'll need to clean up duplicates first.

2. **Role Mapping**: The script uses `roles.drupal_role_id` to map Drupal roles to Laravel roles. Ensure this column is properly populated:
   - Applicant: drupal_role_id = 4
   - DDO: drupal_role_id = 11
   - Housing Official: drupal_role_id = 6
   - Housing Supervisor: drupal_role_id = 10
   - Housing Approver: drupal_role_id = 13
   - etc.

3. **Parent Menus**: Some menus are parent menus (no route_name) and are used for grouping. Child menus reference these parents via `parent_id`.

4. **Route Names**: Route names should match the route names defined in `laravel_frontend/routes/web.php`. If there's a mismatch, update either the SQL script or the Laravel routes file.

## Troubleshooting

### Error: "duplicate key value violates unique constraint"

This means you have duplicate `route_name` values. Clean them up first:
```sql
-- Find duplicates
SELECT route_name, COUNT(*) 
FROM housing_sidebar_menus 
WHERE route_name IS NOT NULL 
GROUP BY route_name 
HAVING COUNT(*) > 1;

-- Remove duplicates (keep the one with the lowest sidebar_menu_id)
DELETE FROM housing_sidebar_menus 
WHERE sidebar_menu_id NOT IN (
    SELECT MIN(sidebar_menu_id) 
    FROM housing_sidebar_menus 
    GROUP BY route_name
) AND route_name IS NOT NULL;
```

### Error: "foreign key constraint violation"

This means the `roles` table doesn't have the expected `drupal_role_id` values. Check and update:
```sql
SELECT id, name, drupal_role_id FROM roles ORDER BY drupal_role_id;
```

## After Running the Script

1. Clear Laravel's route cache (if applicable):
   ```bash
   php artisan route:clear
   ```

2. Test the routes in your application to ensure they're working correctly

3. Verify that menu items appear in the sidebar for the correct user roles
