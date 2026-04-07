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
- Passwords are encrypted using AES-256-GCM encryption
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

### Admin Panel Testing

1. Go to **Tools → RMS SMTP**
2. Enter your email in the "Test Email Address" field
3. Click "Send Test Email"
4. Check your inbox for the test message

### CLI SMTP Connection Test Suite

The `tests/` directory contains a standalone test suite that validates SMTP connections **without WordPress**. Useful for diagnosing connectivity issues before activating the plugin.

#### Setup

```bash
# Copy the example env and fill in your credentials
cp .env.example .env

# Edit .env with your SMTP credentials (nano, vim, etc.)
nano .env
```

#### Running Tests

```bash
# Test all configured servers (connection only)
php tests/test-smtp-connection.php

# Test with verbose SMTP protocol output
php tests/test-smtp-connection.php --verbose

# Test a single server (e.g. server 1 = Gmail)
php tests/test-smtp-connection.php --server=1

# Also send a test email (uses TEST_RECIPIENT from .env)
php tests/test-smtp-connection.php --send-email

# Override timeout (default: 10s)
php tests/test-smtp-connection.php --timeout=5
```

#### What Each Test Checks

| Step | Description |
|------|-------------|
| DNS Resolve | Resolves SMTP hostname to IP |
| TCP Connect | Opens socket to host:port |
| SMTP Banner | Reads server greeting (220) |
| EHLO | Sends EHLO, checks 250 response |
| AUTH Support | Verifies AUTH methods advertised |
| STARTTLS | TLS handshake (if encryption=tls) |
| AUTH LOGIN | Authenticates with credentials |
| Test Email | Sends email via SMTP protocol (optional) |

#### Test Results

Results are saved as JSON in `tests/results/` with timestamps. Example:

```json
{
  "timestamp": "2026-04-06 19:30:00",
  "servers": {
    "1": {
      "host": "smtp.gmail.com",
      "success": true,
      "steps": {
        "dns": { "success": true, "detail": "Resolved to 142.250.x.x" },
        "connect": { "success": true },
        "auth_login": { "success": true }
      }
    }
  }
}
```

#### Security Notes

- The test suite is **CLI-only** — it refuses to run in a web context
- Passwords are **never echoed** in output
- `.env` is in `.gitignore` — credentials are never committed
- Only run tests in development/local environments

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

Yes. Passwords are encrypted using AES-256-GCM with keys derived from your WordPress salt keys. They are never stored in plain text.

### Can I use this on multisite?

This plugin is designed for single-site installations. Multisite support may be added in future versions.

## Support

For issues, feature requests, or contributions:
- GitHub: [https://github.com/glacayo/rms-smtp-cf7-custom-plugin](https://github.com/glacayo/rms-smtp-cf7-custom-plugin)

## License

This plugin is licensed under the GPL-2.0-or-later license.

## Credits

Developed by RMS Development

---

**Security Note**: Always use strong passwords and keep your WordPress installation updated. Enable SSL/TLS encryption for SMTP connections whenever possible.
