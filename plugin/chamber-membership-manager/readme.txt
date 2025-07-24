# Chamber Boss

A WordPress plugin for Chambers of Commerce to manage memberships, process payments via Stripe, and maintain a business directory.

## Description

This plugin is designed for Chambers of Commerce to manage memberships, process payments via Stripe, and maintain a business directory. Members can create and manage their own business listings, which are featured in a searchable directory.

## Features

1. **Membership Management**
   - User roles: Integrate with WordPress user system (e.g., create custom roles like "Chamber Member")
   - Signup flow: New users register via a form, pay via Stripe, and get auto-approved as members upon successful payment
   - Membership tiers: Configurable in plugin settings
   - Renewal reminders: Email notifications for expiring memberships

2. **Business Listings**
   - Creation/Editing: Logged-in members can create listings via a frontend form or WordPress dashboard
   - Approval Workflow: Admins review and approve listings before they go live (toggleable in settings)
   - Listing Management: Members can edit/delete their own listings; admins can manage all

3. **Business Directory**
   - Public-facing page/shortcode: A searchable directory (e.g., [chamber-directory]) displaying all approved listings
   - Search/Filter: By keywords
   - Display: Grid view with pagination
   - SEO Optimization: Use WordPress custom post types (CPT) for listings to ensure search engine friendliness

4. **Stripe Integration**
   - Payment Processing: Handle one-time or recurring memberships via Stripe Checkout
   - Secure API Key Storage: Use WordPress options API with encryption
   - Webhooks: Handle Stripe events (e.g., payment succeeded → grant membership; payment failed → notify user)
   - Compliance: Ensure PCI compliance by not storing card details; use Stripe's tokenization

5. **MailPoet Integration**
   - Newsletter Lists: Automatically manage two separate lists in MailPoet
   - Subscription Flow: Auto-subscribe members to the members' list upon successful signup
   - Unsubscription/Removal: Auto-remove from lists on membership expiration
   - Admin Controls: Settings page to select/configure MailPoet lists

## Installation

1. Download the plugin ZIP file
2. Go to Plugins > Add New in your WordPress admin
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate Plugin"
5. Go to Chamber Members > Settings to configure your Stripe API keys and MailPoet lists

## Usage

1. Configure your Stripe API keys and MailPoet list IDs in the plugin settings
2. Create membership tiers as needed
3. Members can register and create business listings
4. Use the [chamber-directory] shortcode to display the business directory on any page

## Changelog

### 1.0.0
* Initial release