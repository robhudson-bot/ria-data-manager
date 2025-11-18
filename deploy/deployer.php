<?php
/**
 * RIA Data Manager Deployment Handler
 * 
 * Handles the actual deployment process:
 * - Downloads plugin from GitHub
 * - Creates backup
 * - Extracts and syncs files
 * - Preserves config.php
 * - Handles rollback on failure
 * 
 * @version 1.0.0
 */

class RIA_Deployer {
    
    private $plugin_path;
    private $temp_path;
    private $backup_path;
    private $github_zip_url;
    
    public function __construct() {
        $this->plugin_path = DEPLOY_PLUGIN_PATH;
        $this->temp_path = DEPLOY_TEMP_PATH;
        $this->backup_path = DEPLOY_BACKUP_PATH;
        $this->github_zip_url = sprintf(
            'https://github.com/%s/archive/refs/heads/%s.zip',
            DEPLOY_GITHUB_REPO,
            DEPLOY_BRANCH
        );
        
        // Ensure directories exist
        $this->ensure_directory($this->temp_path);
        $this->ensure_directory($this->backup_path);
    }
    
    /**
     * Main deployment function
     */
    public function deploy() {
        try {
            $this->log('Starting deployment process...');
            
            // Step 1: Validate environment
            $this->validate_environment();
            
            // Step 2: Create backup
            $backup_file = $this->create_backup();
            
            // Step 3: Download from GitHub
            $this->log('Downloading from GitHub...');
            $zip_file = $this->download_github_zip();
            
            // Step 4: Extract files
            $this->log('Extracting files...');
            $extracted_path = $this->extract_zip($zip_file);
            
            // Step 5: Preserve config.php
            $config_backup = $this->preserve_config();
            
            // Step 6: Sync files
            $this->log('Syncing files to plugin directory...');
            $this->sync_files($extracted_path);
            
            // Step 7: Restore config.php
            if ($config_backup) {
                $this->restore_config($config_backup);
            }
            
            // Step 8: Cleanup
            $this->cleanup($zip_file, $extracted_path);
            
            $this->log('Deployment completed successfully!');
            
            return [
                'success' => true,
                'message' => 'Plugin updated successfully',
                'backup' => basename($backup_file),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->log('Deployment failed: ' . $e->getMessage(), 'ERROR');
            
            // Attempt rollback
            if (isset($backup_file) && file_exists($backup_file)) {
                $this->log('Attempting rollback...');
                try {
                    $this->rollback($backup_file);
                    $this->log('Rollback completed successfully');
                } catch (Exception $rollback_error) {
                    $this->log('Rollback failed: ' . $rollback_error->getMessage(), 'ERROR');
                }
            }
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Validate deployment environment
     */
    private function validate_environment() {
        // Check if plugin directory exists
        if (!is_dir($this->plugin_path)) {
            throw new Exception("Plugin directory not found: {$this->plugin_path}");
        }
        
        // Check write permissions
        if (!is_writable($this->plugin_path)) {
            throw new Exception("Plugin directory is not writable: {$this->plugin_path}");
        }
        
        // Check if ZipArchive is available
        if (!class_exists('ZipArchive')) {
            throw new Exception("ZipArchive class not available. Please enable zip extension.");
        }
        
        $this->log('Environment validation passed');
    }
    
    /**
     * Create backup of current plugin
     */
    private function create_backup() {
        $this->log('Creating backup...');
        
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = $this->backup_path . "/ria-data-manager_backup_{$timestamp}.zip";
        
        $zip = new ZipArchive();
        if ($zip->open($backup_file, ZipArchive::CREATE) !== true) {
            throw new Exception("Failed to create backup file: {$backup_file}");
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->plugin_path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($this->plugin_path) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }
        
        $zip->close();
        
        // Keep only last 5 backups
        $this->cleanup_old_backups();
        
        $this->log("Backup created: " . basename($backup_file));
        return $backup_file;
    }
    
    /**
     * Download plugin ZIP from GitHub
     */
    private function download_github_zip() {
        $zip_file = $this->temp_path . '/plugin-download.zip';
        
        // Use cURL for better control
        $ch = curl_init($this->github_zip_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception("Download failed: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Download failed with HTTP code: {$http_code}");
        }
        
        if (file_put_contents($zip_file, $data) === false) {
            throw new Exception("Failed to save downloaded file");
        }
        
        $this->log("Downloaded " . $this->format_bytes(filesize($zip_file)));
        return $zip_file;
    }
    
    /**
     * Extract ZIP file
     */
    private function extract_zip($zip_file) {
        $extract_path = $this->temp_path . '/extracted';
        
        // Remove old extraction if exists
        if (is_dir($extract_path)) {
            $this->delete_directory($extract_path);
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== true) {
            throw new Exception("Failed to open ZIP file");
        }
        
        $zip->extractTo($extract_path);
        $zip->close();
        
        // GitHub creates a subdirectory named "repo-branch"
        $subdirs = glob($extract_path . '/*', GLOB_ONLYDIR);
        if (empty($subdirs)) {
            throw new Exception("No directory found in extracted ZIP");
        }
        
        $this->log("Extracted to temporary directory");
        return $subdirs[0]; // Return the actual plugin directory
    }
    
    /**
     * Preserve config.php before deployment
     */
    private function preserve_config() {
        $config_file = $this->plugin_path . '/config/config.php';
        
        if (!file_exists($config_file)) {
            $this->log('No config.php found (will use config.sample.php)');
            return false;
        }
        
        $backup_config = $this->temp_path . '/config.php.backup';
        if (!copy($config_file, $backup_config)) {
            throw new Exception("Failed to backup config.php");
        }
        
        $this->log('Config.php preserved');
        return $backup_config;
    }
    
    /**
     * Restore config.php after deployment
     */
    private function restore_config($backup_config) {
        $config_file = $this->plugin_path . '/config/config.php';
        
        if (!copy($backup_config, $config_file)) {
            throw new Exception("Failed to restore config.php");
        }
        
        unlink($backup_config);
        $this->log('Config.php restored');
    }
    
    /**
     * Sync files from extracted directory to plugin directory
     */
    private function sync_files($source_dir) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source_dir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $file_count = 0;
        foreach ($files as $file) {
            $relative_path = substr($file->getPathname(), strlen($source_dir) + 1);
            
            // Skip . and ..
            if ($relative_path === '.' || $relative_path === '..') {
                continue;
            }
            
            // Skip preserved files
            if ($relative_path === 'config/config.php' || $relative_path === 'deploy/config.php') {
                continue;
            }
            
            $target_path = $this->plugin_path . '/' . $relative_path;
            
            if ($file->isDir()) {
                if (!is_dir($target_path)) {
                    mkdir($target_path, 0755, true);
                }
            } else {
                // Ensure parent directory exists
                $target_dir = dirname($target_path);
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                
                // Copy file
                if (!copy($file->getPathname(), $target_path)) {
                    throw new Exception("Failed to copy: {$relative_path}");
                }
                
                $file_count++;
            }
        }
        
        $this->log("Synced {$file_count} files");
    }
    
    /**
     * Rollback to previous backup
     */
    private function rollback($backup_file) {
        // Clear current plugin directory (except config.php)
        $config_backup = $this->preserve_config();
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->plugin_path),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->getFilename() === '.' || $file->getFilename() === '..') {
                continue;
            }
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        // Extract backup
        $zip = new ZipArchive();
        if ($zip->open($backup_file) !== true) {
            throw new Exception("Failed to open backup file");
        }
        
        $zip->extractTo($this->plugin_path);
        $zip->close();
        
        // Restore config if we backed it up
        if ($config_backup) {
            $this->restore_config($config_backup);
        }
        
        $this->log('Rollback completed');
    }
    
    /**
     * Cleanup temporary files
     */
    private function cleanup($zip_file, $extracted_path) {
        if (file_exists($zip_file)) {
            unlink($zip_file);
        }
        
        if (is_dir($extracted_path)) {
            $this->delete_directory(dirname($extracted_path));
        }
        
        $this->log('Cleanup completed');
    }
    
    /**
     * Keep only last N backups
     */
    private function cleanup_old_backups($keep = 5) {
        $backups = glob($this->backup_path . '/ria-data-manager_backup_*.zip');
        
        if (count($backups) > $keep) {
            // Sort by modification time (oldest first)
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Delete oldest backups
            $to_delete = array_slice($backups, 0, count($backups) - $keep);
            foreach ($to_delete as $backup) {
                unlink($backup);
            }
            
            $this->log('Cleaned up ' . count($to_delete) . ' old backup(s)');
        }
    }
    
    /**
     * Recursively delete a directory
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($dir);
    }
    
    /**
     * Ensure directory exists
     */
    private function ensure_directory($path) {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new Exception("Failed to create directory: {$path}");
            }
        }
    }
    
    /**
     * Format bytes to human readable
     */
    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Log messages
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents(DEPLOY_LOG_FILE, $log_entry, FILE_APPEND);
    }
}
