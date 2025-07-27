## Plan to Resolve Fatal Error

- [x] Create tasks.md file and outline the plan.
- [x] Investigate `includes/Core/Database.php` for redeclaration of `create_tables()`.
- [x] Rename the static `create_tables()` method to `on_activation_create_tables()` in `includes/Core/Database.php`.
- [x] Search the codebase for direct calls to `create_tables()` and update them to `on_activation_create_tables()` if they are related to the activation hook.
- [x] Update the call to `create_tables()` in `chamberboss.php` to `on_activation_create_tables()`.
- [x] Investigate `includes/Core/PostTypes.php` for redeclaration of `register_post_types()`.
- [x] Rename the static `register_post_types()` method to `on_activation_register_post_types()` in `includes/Core/PostTypes.php`.
- [x] Search the codebase for direct calls to `register_post_types()` and update them to `on_activation_register_post_types()` if they are related to the activation hook.
- [x] Update the call to `register_post_types()` in `chamberboss.php` to `on_activation_register_post_types()`.

## New Tasks

- [x] As an admin, be able to add business listings and members.
- [x] Create shortcodes for the business directory and membership signup form.
- [x] As an admin, be able to define business categories.
- [x] Add clear instructions for obtaining Stripe keys/webhook for each step in Stripe setup.
- [x] Fix the fatal error: Class "Chamberboss\Admin\TransactionsPage" not found in `includes/Admin/AdminMenu.php` on line 139.
- [x] Create `includes/Admin/TransactionsPage.php` with a basic class structure and `render()` method.
- [x] Investigate existing code for adding business listings and members to understand current implementation.
- [x] Add a form for creating new members to `includes/Admin/MembersPage.php`.
- [x] Modify the 'Add New Member' button URL in `MembersPage.php` to link to `admin.php?page=chamberboss-members&action=add`.
- [x] Create a new private method `render_add_member()` in `includes/Admin/MembersPage.php` to display a custom form for adding new members.
- [x] Enhance `handle_member_actions()` in `includes/Admin/MembersPage.php` to process the form submission from `render_add_member()` and create the new member post.
- [x] Modify the 'Add New Listing' button URL in `ListingsPage.php` to link to `admin.php?page=chamberboss-listings&action=add`.
- [x] Create a new private method `render_add_listing()` in `includes/Admin/ListingsPage.php` to display a custom form for adding new listings.
- [x] Enhance `handle_listing_actions()` in `includes/Admin/ListingsPage.php` to process the form submission from `render_add_listing()` and create the new listing post.

## Define Business Categories

- [x] Add a new settings section for business categories in `includes/Admin/SettingsPage.php`.
- [x] Add a new navigation tab for 'Categories' in the `render()` method of `includes/Admin/SettingsPage.php`.
- [x] Add a new case in the `switch` statement of the `render()` method to call `render_categories_settings()`.
- [x] Create the `render_categories_settings()` private method in `includes/Admin/SettingsPage.php` to display a form for managing business categories.
- [x] Add a new case to the `handle_settings_save()` method in `includes/Admin/SettingsPage.php` to handle saving category settings.
- [x] Create the `save_categories_settings()` private method in `includes/Admin/SettingsPage.php` to process and save the categories.

## Member Profile Enhancements

- [x] Verify and update the member profile display in `includes/Admin/MembersPage.php` to include Name, phone, email, associated business listings, notes, signup date, and membership renewal date.
- [x] Add a new section in `render_view_member()` to display associated business listings for the member.
- [x] Add a new field for 'Notes' to the member profile display in `render_view_member()`.
- [x] Add a new meta field '_chamberboss_member_notes' to the `save_member_meta` method in `includes/Core/PostTypes.php`.

## Debugging Member & Listing Addition

- [x] Debug why new members and business listings are not being added.
- [x] Add `error_log()` statements to `handle_member_actions()` in `includes/Admin/MembersPage.php` to verify form submission and inspect `$_POST` data.
- [x] Add `error_log()` statements to `handle_listing_actions()` in `includes/Admin/ListingsPage.php` to verify form submission and inspect `$_POST` data.
- [x] Check nonce verification in both `handle_member_actions()` and `handle_listing_actions()`.
- [x] Log the return value of `wp_insert_post()` in both `handle_member_actions()` and `handle_listing_actions()`.
- [x] Review the `sanitize_input()` method for any issues.
- [x] Modify `includes/Admin/MembersPage.php`: Change `admin_init` hook to `admin_post_add_new_member` for member form processing, and rename `handle_member_actions()` to `process_add_member()`.
- [x] Modify `includes/Admin/ListingsPage.php`: Change `admin_init` hook to `admin_post_add_new_listing` for listing form processing, and rename `handle_listing_actions()` to `process_add_listing()`.
- [ ] Verify and correct the form `action` attribute in `render_add_member()` in `includes/Admin/MembersPage.php` to submit to `admin-post.php`.
- [ ] Verify and correct the form `action` attribute in `render_add_listing()` in `includes/Admin/ListingsPage.php` to submit to `admin-post.php`.

## Fix Frontend Signup Form Issues

- [x] Fix user account creation issue - member registration creates ChumberBoss member but not WordPress user
- [x] Update `handle_member_registration()` in `Directory.php` to create WordPress user account so they can login to dashboard
- [x] Integrate Stripe payment processing into signup form before creating member
- [x] Add payment processing to registration flow using existing Stripe integration
- [x] Update frontend JavaScript to handle Stripe payment elements
- [x] Ensure payment is completed successfully before creating member account
- [x] Add proper error handling for payment failures
- [x] Update registration form to show payment section by default
- [x] Test complete signup flow with payment and user creation
- [x] Fixed conditional payment logic - now works with or without Stripe configured

## Implementation Summary

### What Was Fixed:

1. **User Account Creation**: 
   - Modified `handle_member_registration()` to create WordPress user accounts
   - Users now get username/password and can log in to `/members/` dashboard
   - Added `chamberboss_member` role with appropriate capabilities

2. **Stripe Payment Integration**:
   - Added `handle_create_payment_intent()` method for payment processing
   - Payment verification before member creation
   - Only creates member account after successful payment

3. **Frontend JavaScript Updates**:
   - Complete rewrite of registration handling
   - Added Stripe Elements integration
   - Payment processing before registration submission
   - Proper error handling and user feedback

4. **Script Loading**:
   - Added Stripe.js library loading when configured
   - Added Stripe publishable key to JavaScript localization
   - Enhanced error handling when Stripe SDK unavailable

5. **Welcome Email**:
   - Sends email with login credentials to new members
   - Includes link to member dashboard

### To Test:

#### Case 1: With Stripe Configured
1. Configure Stripe in ChumberBoss → Settings → Stripe (add test keys)
2. Visit member registration page
3. Should see payment section with Stripe Elements
4. Fill out form and use test card (4242 4242 4242 4242) 
5. Should process payment, create user account, and send welcome email

#### Case 2: Without Stripe Configured  
1. Leave Stripe settings empty in ChumberBoss → Settings → Stripe
2. Visit member registration page
3. Should see "Payment processing not configured. Registration is currently free."
4. Fill out form and submit
5. Should create user account immediately and send welcome email

Both cases should result in:
- WordPress user account created
- Member can log in at `/members/` dashboard
- Welcome email sent with login credentials 