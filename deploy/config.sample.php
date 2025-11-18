<?php
/**
 * Deployment Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to 'config.php' in the same directory
 * 2. Fill in all the settings below
 * 3. Keep config.php secure (never commit to Git)
 */

// ============================================
// SECURITY SETTINGS
// ============================================

/**
 * Webhook Secret Key
 * Generate a secure random string (min 32 characters)
 * Use this same secret when setting up the GitHub webhook
 * 
 * Generate one at: https://www.random.org/strings/?num=1&len=64&upperalpha=on&loweralpha=on&digits=on
 */
define('DEPLOY_WEBHOOK_SECRET', 'YOUR_SECURE_RANDOM_STRING_HERE');

// ============================================
// NOTIFICATION SETTINGS
// ============================================

/**
 * Email for deployment notifications
 */
define('DEPLOY_NOTIFICATION_EMAIL', 'rob.hudson@the-ria.ca');

/**
 * Enable/disable email notifications
 */
define('DEPLOY_ENABLE_NOTIFICATIONS', true);

// ============================================
// GITHUB SETTINGS
// ============================================

/**
 * GitHub repository (format: username/repo-name)
 */
define('DEPLOY_GITHUB_REPO', 'robhudson-bot/ria-data-manager');

/**
 * Branch to deploy (usually 'main' or 'master')
 */
define('DEPLOY_BRANCH', 'main');

// ============================================
// PATH SETTINGS
// ============================================

/**
 * Absolute path to the plugin directory
 * .deploy folder is INSIDE the plugin, so we go up one level
 */
define('DEPLOY_PLUGIN_PATH', dirname(__DIR__));

/**
 * Temporary directory for downloads and extraction
 */
define('DEPLOY_TEMP_PATH', dirname(__DIR__, 3) . '/uploads/.deploy-temp');

/**
 * Backup directory
 */
define('DEPLOY_BACKUP_PATH', dirname(__DIR__, 3) . '/uploads/.deploy-backups');

/**
 * Log file location
 */
define('DEPLOY_LOG_FILE', dirname(__DIR__, 3) . '/uploads/.deploy-log.txt');

// ============================================
// WORDPRESS INTEGRATION (Optional)
// ============================================

/**
 * Load WordPress if needed (for wp_mail function)
 * Set to true to enable WordPress email functionality
 */
define('DEPLOY_LOAD_WORDPRESS', true);

if (DEPLOY_LOAD_WORDPRESS && file_exists(__DIR__ . '/../../../wp-load.php')) {
    require_once __DIR__ . '/../../../wp-load.php';
}
