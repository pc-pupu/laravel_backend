# Error Logging Guide

## When Errors Are Inserted into `error_logs` Table

Errors are automatically inserted into the `error_logs` table in the following scenarios:

### 1. **Automatic Logging (Unhandled Exceptions)**
All unhandled exceptions are automatically logged via the global exception handler in `bootstrap/app.php`. This includes:
- PHP fatal errors
- Uncaught exceptions
- Database connection errors
- Missing files/classes
- Any exception that bubbles up without being caught

**Example:**
```php
// This will be automatically logged
public function someMethod() {
    throw new \Exception('Something went wrong');
}
```

### 2. **Manual Logging in Controllers**
Errors caught in try-catch blocks need to be manually logged. The following controllers already log errors:
- `ErrorLogController` - Logs errors when loading/deleting/clearing logs
- Other controllers should use `ErrorLogService` in their catch blocks

**Example:**
```php
try {
    // Some code that might fail
} catch (\Exception $e) {
    ErrorLogService::logException($e, 'error');
    // Handle error
}
```

### 3. **Using Helper Functions**
You can manually log errors anywhere in your code using helper functions:

```php
// Log an exception
log_exception($exception, 'error');

// Log a simple error message
log_error('Something went wrong', 'error', ['context' => 'value']);

// Log a warning
log_warning('This is a warning message');

// Log info
log_info('Informational message');
```

## Error Levels

- **error**: Critical errors that need attention (default)
- **warning**: Warnings that should be monitored
- **info**: Informational messages
- **debug**: Debug messages

## What Gets Logged

Each error log entry includes:
- **level**: Error level (error, warning, info, debug)
- **message**: Error message
- **exception_type**: Exception/error class (e.g. `Illuminate\Database\QueryException`)
- **file**: File where error occurred
- **line**: Line number where error occurred
- **trace**: Full stack trace
- **user_id**: ID of authenticated user (if available)
- **url**: Full URL where error occurred
- **method**: HTTP method (GET, POST, etc.)
- **ip_address**: IP address of the request
- **context**: Additional context data (request data, headers, etc.)
- **created_at**: Timestamp when error occurred

## Testing Error Logging

### Test 1: Automatic Logging
Create a test route that throws an exception:
```php
Route::get('/test-error', function() {
    throw new \Exception('Test error for logging');
});
```
Visit the route - the error should appear in the error_logs table.

### Test 2: Manual Logging
Use the helper function:
```php
log_error('Test error message', 'error', ['test' => true]);
```

### Test 3: Check Admin Panel
Go to `/admin/error-logs` in the admin panel to view all logged errors.

## Important Notes

1. **Database Connection Errors**: If the database is unavailable, errors will still be logged to Laravel's default log file (`storage/logs/laravel.log`).

2. **Performance**: Error logging is designed to be non-blocking. If logging fails, it won't crash your application.

3. **Sensitive Data**: Be careful not to log sensitive information like passwords, credit card numbers, etc. The service automatically excludes password fields from request data.

4. **Table Existence**: The `error_logs` table must exist. Run migrations if needed:
   ```bash
   php artisan migrate
   ```

## Viewing Errors

All errors can be viewed in the admin panel at:
- **URL**: `/admin/error-logs`
- **Features**: 
  - Filter by level
  - Search by message
  - Filter by date range
  - View detailed error information (error type, user, time)
  - Delete individual errors
  - **Clear by time**: Delete errors older than X days, or between two dates
  - Clear all errors

