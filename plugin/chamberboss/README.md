# Chamberboss - WordPress Chamber of Commerce Plugin

**Version:** 1.0.0  
**Author:** Genex Marketing Agency Ltd  
**Requires:** WordPress 5.0+, PHP 7.4+  
**License:** GPL v2 or later

## Overview

Chamberboss is a comprehensive WordPress plugin designed specifically for Chambers of Commerce to manage members, business listings, payments, and communications. The plugin provides a complete solution for membership management with integrated Stripe payments, MailPoet email marketing, and a public business directory.

## Features

### Core Functionality
- **Member Management**: Complete member registration, profile management, and subscription tracking
- **Business Directory**: Public-facing directory with search, filtering, and categorization
- **Stripe Integration**: Secure payment processing for membership fees and renewals
- **MailPoet Integration**: Automated email list management and member communications
- **Admin Dashboard**: Comprehensive admin interface for managing all aspects of the chamber

### Member Features
- Online registration with payment processing
- Member profile management
- Business listing submission and management
- Automated renewal notifications
- Email list integration

### Admin Features
- Member management and approval system
- Business listing moderation
- Payment and subscription tracking
- Email notification customization
- Comprehensive reporting and analytics

## Installation

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- SSL certificate (required for Stripe payments)
- MailPoet plugin (for email integration)

### Step 1: Upload Plugin Files
1. Download the Chamberboss plugin folder
2. Upload the `chamberboss-plugin` folder to `/wp-content/plugins/`
3. Alternatively, zip the plugin folder and upload via WordPress admin

### Step 2: Install Dependencies
The plugin requires Composer for dependency management:

```bash
cd /path/to/wp-content/plugins/chamberboss-plugin
composer install
```

### Step 3: Activate Plugin
1. Go to WordPress Admin → Plugins
2. Find "Chamberboss" in the plugin list
3. Click "Activate"

### Step 4: Database Setup
The plugin will automatically create required database tables upon activation. If you need to manually trigger this:

1. Go to Chamberboss → Settings
2. Click "Reinstall Database Tables"

## Configuration

### Initial Setup

#### 1. Basic Settings
Navigate to **Chamberboss → Settings** and configure:

- **Organization Name**: Your chamber's name
- **Contact Information**: Address, phone, email
- **Membership Price**: Annual membership fee
- **Currency**: Default currency for payments

#### 2. Stripe Payment Setup
1. Create a Stripe account at [stripe.com](https://stripe.com)
2. Get your API keys from Stripe Dashboard → Developers → API keys
3. In WordPress admin, go to **Chamberboss → Settings → Payments**
4. Enter your Stripe keys:
   - **Test Mode**: Use test keys for development
   - **Live Mode**: Use live keys for production
5. Configure webhook endpoint: `https://yoursite.com/wp-json/chamberboss/v1/stripe/webhook`

#### 3. MailPoet Integration
1. Install and activate the MailPoet plugin
2. Go to **Chamberboss → Settings → Email**
3. Enable MailPoet integration
4. Select the email list for new members
5. Configure email templates and automation settings

#### 4. Email Notifications
Configure automated email templates:
- Welcome emails for new members
- Payment confirmation emails
- Renewal reminder emails
- Membership expiration notices

### Page Setup

#### Required Pages
Create the following pages and add the specified shortcodes:

1. **Member Dashboard Page**
   ```
   [chamberboss_member_dashboard]
   ```

2. **Member Registration Page**
   ```
   [chamberboss_member_registration]
   ```

2. **Business Directory Page**
   ```
   [chamberboss_directory per_page="12" show_search="true" show_filters="true"]
   ```

3. **Submit Business Listing Page**
   ```
   [chamberboss_listing_form require_membership="true"]
   ```

#### Optional Shortcode Parameters

**Directory Shortcode:**
```
[chamberboss_directory 
    per_page="12" 
    category="retail" 
    featured_only="false" 
    show_search="true" 
    show_filters="true" 
    layout="grid"]
```

**Registration Form:**
```
[chamberboss_member_registration 
    redirect_url="/welcome/" 
    show_payment="true"]
```

**Listing Form:**
```
[chamberboss_listing_form 
    redirect_url="/thank-you/" 
    require_membership="true"]
```

## Usage Guide

### For Administrators

#### Managing Members
1. Go to **Chamberboss → Members**
2. View all members, their status, and subscription details
3. Manually add members or approve registrations
4. Process payments and manage subscriptions
5. Export member data for reporting

#### Managing Business Listings
1. Go to **Chamberboss → Listings**
2. Review and approve submitted listings
3. Edit listing details and manage categories
4. Feature important listings
5. Bulk actions for multiple listings

#### Payment Management
1. Go to **Chamberboss → Transactions**
2. View all payment history
3. Process refunds through Stripe
4. Generate financial reports
5. Manage failed payments and renewals

#### Email Management
1. Configure email templates in **Settings → Email**
2. View notification history
3. Manually send emails to members
4. Manage MailPoet list synchronization

### For Members

#### Registration Process
1. Visit the member registration page
2. Fill out personal and business information
3. Complete payment via Stripe
4. Receive welcome email and account details
5. Access member benefits immediately

#### Managing Business Listings
1. Log in to WordPress (if user accounts are enabled)
2. Visit the listing submission page
3. Submit business information and images
4. Wait for admin approval
5. Listings appear in public directory once approved

#### Membership Renewal
1. Receive automated renewal reminders
2. Click renewal link in email
3. Complete payment process
4. Membership automatically extended

## Customization

### Styling
The plugin includes comprehensive CSS that can be customized:

- **Frontend Styles**: `/assets/css/frontend.css`
- **Admin Styles**: `/assets/css/admin.css`

Override styles in your theme's CSS file or create a child theme.

### Templates
Create custom templates in your theme:

```
/wp-content/themes/your-theme/chamberboss/
├── directory.php
├── listing-single.php
├── member-registration.php
└── listing-form.php
```

### Hooks and Filters

#### Action Hooks
```php
// Member events
do_action('chamberboss_member_registered', $member_id);
do_action('chamberboss_membership_activated', $member_id);
do_action('chamberboss_membership_expired', $member_id);

// Payment events
do_action('chamberboss_payment_succeeded', $member_id, $payment_data);
do_action('chamberboss_payment_failed', $member_id, $payment_data);

// Listing events
do_action('chamberboss_listing_submitted', $listing_id);
do_action('chamberboss_listing_approved', $listing_id);
```

#### Filter Hooks
```php
// Customize email content
add_filter('chamberboss_email_welcome_message', 'custom_welcome_message');
add_filter('chamberboss_email_renewal_subject', 'custom_renewal_subject');

// Modify directory query
add_filter('chamberboss_directory_query_args', 'custom_directory_query');

// Customize member capabilities
add_filter('chamberboss_member_capabilities', 'custom_member_caps');
```

## Troubleshooting

### Common Issues

#### Stripe Payments Not Working
1. Verify SSL certificate is installed and working
2. Check Stripe API keys are correct
3. Ensure webhook endpoint is configured
4. Check error logs for detailed messages

#### MailPoet Integration Issues
1. Verify MailPoet plugin is active and updated
2. Check API permissions and list IDs
3. Review email sending limits and quotas
4. Test with a small group first

#### Database Errors
1. Check database user permissions
2. Verify table creation during activation
3. Run database repair if needed
4. Contact hosting provider for MySQL issues

#### Performance Issues
1. Enable WordPress caching
2. Optimize database queries
3. Use CDN for static assets
4. Consider upgrading hosting plan

### Debug Mode
Enable debug mode for detailed error logging:

```php
// Add to wp-config.php
define('CHAMBERBOSS_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Support
For technical support and customization requests:
- Email: support@genexmarketing.com
- Documentation: [Plugin documentation site]
- GitHub: [Repository URL if applicable]

## Security

### Best Practices
1. Keep WordPress and all plugins updated
2. Use strong passwords and two-factor authentication
3. Regular database backups
4. Monitor for suspicious activity
5. Use security plugins for additional protection

### Data Protection
The plugin handles sensitive member and payment data:
- All payment processing is handled by Stripe (PCI compliant)
- Member data is stored securely in WordPress database
- Email addresses are protected and not publicly visible
- GDPR compliance features included

## Changelog

### Version 1.0.0
- Initial release
- Complete member management system
- Stripe payment integration
- MailPoet email integration
- Business directory functionality
- Admin dashboard and reporting
- Responsive frontend design
- Comprehensive notification system

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Credits

**Developed by:** Genex Marketing Agency Ltd  
**Contributors:** [List any contributors]  
**Special Thanks:** WordPress community, Stripe, MailPoet

---

For more information about Genex Marketing Agency Ltd, visit [your website].

