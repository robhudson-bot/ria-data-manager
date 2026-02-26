/**
 * Quarry Admin Scripts
 */

(function($) {
    'use strict';
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        QRY_Admin.init();
    });
    
    /**
     * Main Admin Object
     */
    var QRY_Admin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initFileInput();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // File input change
            $('#csv_file').on('change', this.handleFileChange);
            
            // Form validation
            $('#qry-export-form').on('submit', this.validateExportForm);
            $('#qry-import-form').on('submit', this.validateImportForm);
            
            // Post type change - could trigger ACF field detection
            $('#post_type').on('change', this.handlePostTypeChange);
        },
        
        /**
         * Initialize file input
         */
        initFileInput: function() {
            var $fileInput = $('#csv_file');
            if ($fileInput.length) {
                // Add file size validation
                $fileInput.on('change', function() {
                    var file = this.files[0];
                    if (file) {
                        var maxSize = 50 * 1024 * 1024; // 50MB
                        if (file.size > maxSize) {
                            alert(quarry.strings.error + ': File size exceeds 50MB limit');
                            $(this).val('');
                            return false;
                        }
                        
                        // Show file name
                        var fileName = file.name;
                        if (fileName.length > 50) {
                            fileName = fileName.substring(0, 47) + '...';
                        }
                        $(this).next('.file-name').remove();
                        $(this).after('<span class="file-name">' + fileName + '</span>');
                    }
                });
            }
        },
        
        /**
         * Handle file change
         */
        handleFileChange: function() {
            var file = this.files[0];
            if (file) {
                // Validate file extension
                var fileName = file.name;
                var fileExt = fileName.split('.').pop().toLowerCase();
                
                if (fileExt !== 'csv') {
                    alert('Please select a CSV file');
                    $(this).val('');
                }
            }
        },
        
        /**
         * Handle post type change
         */
        handlePostTypeChange: function() {
            var postType = $(this).val();
            // Could add dynamic field detection here
            console.log('Post type changed to:', postType);
        },
        
        /**
         * Validate export form
         */
        validateExportForm: function(e) {
            var postStatus = $('input[name="post_status[]"]:checked').length;
            
            if (postStatus === 0) {
                e.preventDefault();
                alert('Please select at least one post status');
                return false;
            }
            
            return true;
        },
        
        /**
         * Validate import form
         */
        validateImportForm: function(e) {
            var fileInput = $('#csv_file')[0];
            
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Please select a CSV file to import');
                return false;
            }
            
            return true;
        },
        
        /**
         * Show progress
         */
        showProgress: function(container, message) {
            var $progress = $(container).find('.qry-progress');
            if ($progress.length) {
                $progress.find('.qry-status-text').text(message || 'Processing...');
                $progress.show();
            }
        },
        
        /**
         * Hide progress
         */
        hideProgress: function(container) {
            var $progress = $(container).find('.qry-progress');
            if ($progress.length) {
                $progress.hide();
            }
        },
        
        /**
         * Update progress bar
         */
        updateProgress: function(container, percent) {
            var $fill = $(container).find('.qry-progress-fill');
            if ($fill.length) {
                $fill.css('width', percent + '%');
            }
        },
        
        /**
         * Show success message
         */
        showSuccess: function(container, message) {
            var $result = $(container).find('.qry-result');
            if ($result.length) {
                $result
                    .removeClass('notice-error')
                    .addClass('notice notice-success')
                    .html('<p>' + message + '</p>')
                    .show();
                
                // Scroll to result
                $('html, body').animate({
                    scrollTop: $result.offset().top - 100
                }, 500);
            }
        },
        
        /**
         * Show error message
         */
        showError: function(container, message) {
            var $result = $(container).find('.qry-result');
            if ($result.length) {
                $result
                    .removeClass('notice-success')
                    .addClass('notice notice-error')
                    .html('<p><strong>' + quarry.strings.error + ':</strong> ' + message + '</p>')
                    .show();
                
                // Scroll to result
                $('html, body').animate({
                    scrollTop: $result.offset().top - 100
                }, 500);
            }
        },
        
        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };
    
    /**
     * Export functionality enhancement
     */
    var ExportManager = {
        init: function() {
            // Auto-enable ACF checkbox when post type with ACF fields is selected
            $('#post_type').on('change', function() {
                var postType = $(this).val();
                // Could check if post type has ACF fields and auto-check the box
            });
        }
    };
    
    /**
     * Import functionality enhancement
     */
    var ImportManager = {
        init: function() {
            this.setupDragDrop();
        },
        
        /**
         * Setup drag and drop for file upload
         */
        setupDragDrop: function() {
            var $fileInput = $('#csv_file');
            var $form = $('#qry-import-form');
            
            if (!$fileInput.length) return;
            
            // Prevent default drag behaviors
            $(document).on('drag dragstart dragend dragover dragenter dragleave drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
            
            // Add visual feedback
            $form.on('dragover dragenter', function() {
                $(this).addClass('drag-over');
            });
            
            $form.on('dragleave dragend drop', function() {
                $(this).removeClass('drag-over');
            });
            
            // Handle drop
            $form.on('drop', function(e) {
                var files = e.originalEvent.dataTransfer.files;
                if (files.length) {
                    $fileInput[0].files = files;
                    $fileInput.trigger('change');
                }
            });
        }
    };
    
    /**
     * Initialize sub-modules
     */
    $(document).ready(function() {
        ExportManager.init();
        ImportManager.init();
    });
    
    // Make admin object globally available
    window.QRY_Admin = QRY_Admin;
    
})(jQuery);
