<?php

use App\Models\ActivityLog;

return [
    'enabled' => true,
    // No cleaning task is scheduled. Retention rules need school confirmation.
    'delete_records_older_than_days' => 365,
    'default_log_name' => 'learners',
    'default_auth_driver' => null,
    'subject_returns_soft_deleted_models' => false,
    'activity_model' => ActivityLog::class,
    'table_name' => 'activity_log',
    'database_connection' => null,
];
