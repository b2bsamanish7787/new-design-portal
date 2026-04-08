<?php
/**
 * Application Constants
 */

// App info
define('APP_NAME', 'Design Portal');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '');  // Set to your base URL if in a subdirectory e.g. '/design-portal'

// Session settings
define('SESSION_TIMEOUT', 1800);     // 30 minutes in seconds
define('SESSION_NAME', 'design_portal_session');

// Pagination
define('ITEMS_PER_PAGE', 10);

// Password policy
define('MIN_PASSWORD_LENGTH', 8);

// File paths
define('ROOT_PATH', dirname(__DIR__));
define('ASSETS_PATH', BASE_URL . '/assets');

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_CLIENT', 'client');

// Project statuses
define('STATUS_PENDING', 'pending');
define('STATUS_ONGOING', 'ongoing');
define('STATUS_COMPLETED', 'completed');

// Days threshold for highlighting
define('DAYS_WARNING_THRESHOLD', 3);

// Login rate limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
