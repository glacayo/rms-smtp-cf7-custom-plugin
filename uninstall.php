<?php
/**
 * RMS SMTP for Contact Form 7 - Uninstall
 *
 * @package RMS_SMTP_CF7
 */

// Security: Prevent direct access
defined('WP_UNINSTALL_PLUGIN') || exit;

// Remove plugin options
delete_option('rms_smtp_cf7_options');
