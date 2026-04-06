# RMS SMTP for Contact Form 7

A secure WordPress plugin that enables SMTP email delivery for Contact Form 7 and all WordPress emails.

## Features

- **Secure SMTP Configuration**: Configure SMTP settings directly from the WordPress admin
- **Contact Form 7 Integration**: Works seamlessly with CF7 email submissions
- **Security First**: Encrypted password storage, input sanitization, nonce verification
- **Easy Setup**: Simple configuration under Tools menu
- **Test Connection**: Built-in SMTP connection testing
- **Multiple Encryption Options**: Support for SSL/TLS encryption

## Security Features

This plugin implements multiple layers of security:

### 1. **Encrypted Password Storage**
- Passwords are encrypted using AES-256-CBC encryption
- Encryption key derived from WordPress salts
- Never stored in plain text

### 2. **Input Validation & Sanitization**
- Host validation: Only valid domain names and IP addresses
- Port validation: Range 1-65535
- Email validation: Proper email format checking
- Text sanitization: All user inputs sanitized

### 3. **Access Control**
- Only administrators (`manage_options` capability) can access settings
- Nonce verification on all AJAX requests
- Capability checks on activation/deactivation

### 4. **XSS Prevention**
- All output properly escaped (`esc_attr`, `esc_html`, `esc_url`)
- No direct echo of user input

### 5. **CSRF Protection**
- WordPress Settings API handles form nonces
- Custom AJAX handlers verify nonces

## Installation

1. Upload the `rms-smtp-cf7-custom-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Tools → RMS SMTP** to configure settings

## Configuration

### SMTP Settings

| Setting | Description | Example |
|---------|-------------|---------|
| **Enable SMTP** | Turn on SMTP for all emails | ☑️ |
| **SMTP Host** | Your SMTP server address | `smtp.gmail.com` |
| **SMTP Port** | Server port (25, 465, 587) | `587` |
| **Encryption** | Connection security | `TLS` |
| **Authentication** | Enable SMTP login | ☑️ |
| **Username** | SMTP account username | `user@example.com` |
| **Password** | SMTP account password | •••••••• |
| **From Email** | Sender email address | `noreply@example.com` |
| **From Name** | Sender display name | `My Website` |
| **Debug Mode** | Enable SMTP debugging | ☐ |

### Common SMTP Providers

#### Gmail
```
Host: smtp.gmail.com
Port: 587
Encryption: TLS
Authentication: Yes
```
*Note: Requires App Password if 2FA is enabled*

#### Outlook/Office 365
```
Host: smtp.office365.com
Port: 587
Encryption: TLS
Authentication: Yes
```

#### SendGrid
```
Host: smtp.sendgrid.net
Port: 587
Encryption: TLS
Authentication: Yes
Username: apikey
Password: your_sendgrid_api_key
```

## Usage with Contact Form 7

This plugin automatically hooks into WordPress's `wp_mail()` function, which Contact Form 7 uses. No additional configuration needed in CF7 forms.

1. Configure SMTP settings in **Tools → RMS SMTP**
2. Your Contact Form 7 emails will now be sent via SMTP
3. Use the "Send Test Email" feature to verify configuration

## Testing

1. Go to **Tools → RMS SMTP**
2. Enter your email in the "Test Email Address" field
3. Click "Send Test Email"
4. Check your inbox for the test message

## Debug Mode

Enable debug mode to troubleshoot SMTP connection issues:

1. Check "Enable SMTP Debug Mode"
2. Save settings
3. Check WordPress debug log (`wp-content/debug.log`) for SMTP connection details
4. **Remember to disable debug mode in production!**

## Troubleshooting

### Emails Not Sending

1. Verify SMTP is enabled
2. Check host and port settings
3. Ensure authentication credentials are correct
4. Check if your hosting provider blocks SMTP ports
5. Enable debug mode and check logs

### Gmail Issues

- If using Gmail with 2FA, generate an App Password:
  1. Go to Google Account → Security
  2. Enable 2-Step Verification if not already
  3. Go to App Passwords
  4. Generate password for "Mail"
  5. Use this password in plugin settings

### Connection Timeout

- Try different ports: 465 (SSL) or 587 (TLS)
- Check if your host blocks outgoing SMTP
- Contact your hosting provider

## Requirements

- WordPress 5.8 or later
- PHP 7.4 or later
- Contact Form 7 (recommended but not required)

## Frequently Asked Questions

### Does this work without Contact Form 7?

Yes! This plugin configures SMTP for ALL WordPress emails, including:
- WordPress core emails
- WooCommerce notifications
- Any plugin using `wp_mail()`

### Is my password secure?

Yes. Passwords are encrypted using AES-256-CBC with keys derived from your WordPress salt keys. They are never stored in plain text.

### Can I use this on multisite?

This plugin is designed for single-site installations. Multisite support may be added in future versions.

## Support

For issues, feature requests, or contributions:
- GitHub: [https://github.com/RMS-Dev/smtp-cf7](https://github.com/RMS-Dev/smtp-cf7)

## License

This plugin is licensed under the GPL-2.0-or-later license.

## Credits

Developed by RMS Development

---

**Security Note**: Always use strong passwords and keep your WordPress installation updated. Enable SSL/TLS encryption for SMTP connections whenever possible.
