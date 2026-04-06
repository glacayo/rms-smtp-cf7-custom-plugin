/**
 * RMS SMTP for Contact Form 7 - Admin JavaScript
 *
 * @package RMS_SMTP_CF7
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * RMS SMTP CF7 Admin Class
     */
    class RmsSmtpCf7Admin {
        
        /**
         * Constructor
         */
        constructor() {
            this.init();
        }
        
        /**
         * Initialize admin functionality
         */
        init() {
            this.bindEvents();
            this.toggleAuthFields();
            this.toggleDebugInfo();
        }
        
        /**
         * Bind DOM events
         */
        bindEvents() {
            // Test connection button
            $('#rms_smtp_test_btn').on('click', (e) => this.testConnection(e));
            
            // Toggle authentication fields
            $('#rms_smtp_auth').on('change', () => this.toggleAuthFields());
            
            // Toggle debug info
            $('#rms_smtp_debug').on('change', () => this.toggleDebugInfo());
            
            // Validate host on blur
            $('#rms_smtp_host').on('blur', () => this.validateHost());
            
            // Validate port on change
            $('#rms_smtp_port').on('change', () => this.validatePort());
            
            // Warn about insecure settings
            $('#rms_smtp_encryption').on('change', () => this.checkEncryption());
        }
        
        /**
         * Toggle authentication fields visibility
         */
        toggleAuthFields() {
            const isChecked = $('#rms_smtp_auth').is(':checked');
            const authFields = $('#rms_smtp_username, #rms_smtp_password').closest('tr');
            
            if (isChecked) {
                authFields.fadeIn(200);
            } else {
                authFields.fadeOut(200);
            }
        }
        
        /**
         * Toggle debug mode information
         */
        toggleDebugInfo() {
            const isChecked = $('#rms_smtp_debug').is(':checked');
            const debugInfo = $('.debug-info');
            
            if (isChecked && debugInfo.length === 0) {
                const warning = $('<div class="debug-info"><p>' + 
                    '<strong>Security Notice:</strong> Debug mode will log sensitive information. ' +
                    'Only enable this for testing and disable it in production.</p></div>');
                $('#rms_smtp_debug').closest('td').append(warning);
            } else if (!isChecked) {
                debugInfo.remove();
            }
        }
        
        /**
         * Validate SMTP host format
         * @return {boolean} Whether the host is valid
         */
        validateHost() {
            const host = $('#rms_smtp_host').val().trim();
            const hostRegex = /^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/;
            const ipRegex = /^(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\.){3}(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)$/;

            if (host === 'localhost') {
                this.clearFieldError('rms_smtp_host');
                return true;
            }

            if (host && !hostRegex.test(host) && !ipRegex.test(host)) {
                this.showFieldError('rms_smtp_host', 'Invalid host format. Please enter a valid domain or IP address.');
                return false;
            }
            
            this.clearFieldError('rms_smtp_host');
            return true;
        }
        
        /**
         * Validate port number
         * @return {boolean} Whether the port is valid
         */
        validatePort() {
            const port = parseInt($('#rms_smtp_port').val(), 10);
            
            if (isNaN(port) || port < 1 || port > 65535) {
                this.showFieldError('rms_smtp_port', 'Port must be between 1 and 65535.');
                return false;
            }
            
            this.clearFieldError('rms_smtp_port');
            return true;
        }
        
        /**
         * Check encryption settings and warn about security
         */
        checkEncryption() {
            const encryption = $('#rms_smtp_encryption').val();
            const existingWarning = $('.encryption-warning');
            
            if (encryption === 'none') {
                existingWarning.remove();
                const warning = $('<div class="security-notice encryption-warning"><p>' +
                    '<strong>⚠️ Security Warning:</strong> No encryption means your SMTP credentials ' +
                    'will be sent in plain text. This is not recommended for production use.</p></div>');
                $('#rms_smtp_encryption').closest('td').append(warning);
            } else {
                existingWarning.remove();
            }
        }
        
        /**
         * Show error for a specific field
         * @param {string} fieldId - The ID of the field
         * @param {string} message - Error message to display
         */
        showFieldError(fieldId, message) {
            const $field = $('#' + fieldId);
            this.clearFieldError(fieldId);
            $field.addClass('error');
            $field.after($('<span class="rms-smtp-error">').text(message).css({color: '#dc3232', fontSize: '12px'}));
        }
        
        /**
         * Clear error for a specific field
         * @param {string} fieldId - The ID of the field
         */
        clearFieldError(fieldId) {
            const $field = $('#' + fieldId);
            $field.removeClass('error');
            $field.siblings('.rms-smtp-error').remove();
        }
        
        /**
         * Test SMTP connection via AJAX
         * @param {Event} e - Click event
         */
        testConnection(e) {
            e.preventDefault();
            
            // Validate before testing
            if (!this.validateHost() || !this.validatePort()) {
                return;
            }
            
            const $btn = $('#rms_smtp_test_btn');
            const $result = $('#rms_smtp_test_result');
            const testEmail = $('#rms_smtp_test_email').val().trim();
            
            // Validate test email
            if (!this.isValidEmail(testEmail)) {
                this.showResult('error', 'Please enter a valid email address for testing.');
                return;
            }
            
            // Disable button during test
            $btn.prop('disabled', true);
            this.showResult('loading', rmsSmtpCf7.testing);
            
            // Make AJAX request
            $.ajax({
                url: rmsSmtpCf7.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rms_smtp_cf7_test',
                    nonce: rmsSmtpCf7.nonce,
                    test_email: testEmail
                },
                success: (response) => {
                    if (response.success) {
                        this.showResult('success', rmsSmtpCf7.success + ' ' + response.data);
                    } else {
                        this.showResult('error', rmsSmtpCf7.error + ' ' + (response.data || ''));
                    }
                },
                error: (xhr, status, error) => {
                    this.showResult('error', rmsSmtpCf7.error + ' (' + error + ')');
                },
                complete: () => {
                    $btn.prop('disabled', false);
                }
            });
        }
        
        /**
         * Show result message
         * @param {string} type - Message type (success, error, loading)
         * @param {string} message - Message to display
         */
        showResult(type, message) {
            const $result = $('#rms_smtp_test_result');
            $result.removeClass('success error loading')
                   .addClass(type)
                   .text(message)
                   .show();
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    $result.fadeOut();
                }, 5000);
            }
        }
        
        /**
         * Validate email format
         * @param {string} email - Email to validate
         * @return {boolean} Whether the email is valid
         */
        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(() => {
        new RmsSmtpCf7Admin();
        
        // Initial checks
        $('#rms_smtp_encryption').trigger('change');
    });
    
})(jQuery);
