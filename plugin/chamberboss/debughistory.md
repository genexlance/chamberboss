Chamberboss Plugin Debugging Summary
Plugin Overview:
Chamberboss is a WordPress plugin designed for comprehensive chamber of commerce management. It includes functionalities for member management, business listings, Stripe payments integration, and MailPoet integration.
Current Status:
The plugin is installed and activated. The Chamberboss admin menu is now visible in the WordPress dashboard.
Key Problem: White Screen of Death (WSOD) on Member/Listing Submission
Description:
When attempting to add a new member (via the custom admin form in Chamberboss -> Members -> Add New Member) or a new business listing (via Chamberboss -> Listings -> Add New Listing), the submission results in a White Screen of Death (WSOD).
Critical Observation:
No errors appear in the debug.log file, even with WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, and @ini_set('display_errors', 1) fully enabled in wp-config.php.
No errors appear in the browser console.
This suggests a fatal PHP error occurring extremely early in the execution of the submission handler, before any logging or display mechanisms can capture it.
Debugging Steps & Findings (Chronological)
Resolved Fatal Errors (Pre-existing):
Redeclaration of Chamberboss\Core\Database::create_tables(): Fixed by renaming the static method to on_activation_create_tables() and updating the activation hook call in chamberboss.php.
Redeclaration of Chamberboss\Core\PostTypes::register_post_types(): Fixed by renaming the static method to on_activation_register_post_types() and updating the activation hook call in chamberboss.php.
Class "Chamberboss\Admin\TransactionsPage" not found: Fixed by creating the missing file includes/Admin/TransactionsPage.php with a basic class structure.
Implemented Admin UI for Member & Listing Addition:
Custom admin forms (render_add_member() in includes/Admin/MembersPage.php and render_add_listing() in includes/Admin/ListingsPage.php) were developed to allow admins to add members/listings directly.
The forms' action attributes were initially incorrect.
Debugging WSOD (Post-Submission, Iteration 1 - Form Action/Hooks):
Initial Hypothesis: Form submission not reaching the processing functions.
Action 1: Added error_log() statements at the very beginning of handle_member_actions() and handle_listing_actions().
Result 1: WSOD persisted on submission, no logs from these functions. (Indicated functions not being hit or crashing immediately).
Action 2: Changed form processing hooks from admin_init to admin_post_add_new_member (for members) and admin_post_add_new_listing (for listings) in includes/Admin/MembersPage.php and includes/Admin/ListingsPage.php respectively. Renamed methods to process_add_member() and process_add_listing().
Result 2: WSOD persisted on submission, still no logs. (Indicated hooks weren't firing or form action was wrong).
Action 3: Corrected the form action attribute in render_add_member() and render_add_listing() to admin-post.php.
Result 3: WSOD persisted on submission, still no logs. (This was unexpected, as admin-post.php is the correct target for admin_post hooks).
Debugging WSOD (Post-Submission, Iteration 2 - Deep Dive into process_add_member()):
Goal: Pinpoint exact line of failure within process_add_member().
Action 1: Commented out all logic inside process_add_member() except a very first error_log() statement.
Result 1: WSOD persisted, no logs. (Indicated error was before the function's first line, or in its loading).
Action 2: Temporarily commented out the add_action('admin_post_add_new_member', ...) call in MembersPage::init().
Result 2: WSOD persisted, no logs. (Indicated error was before even this hook registration).
Action 3: Temporarily commented out all code within MembersPage::init().
Result 3: WSOD persisted, no logs. (Indicated error was before MembersPage::init()).
Action 4: Temporarily commented out the instantiation of MembersPage in AdminMenu.php ($members = new MembersPage();).
Result 4: WSOD was RESOLVED. The Members admin page displayed as blank (expected, as MembersPage wasn't rendering), confirming the fatal error originated within the includes/Admin/MembersPage.php file itself (e.g., a syntax error in class definition, properties, or constants).
Action 5: Re-enabled MembersPage instantiation in AdminMenu.php.
Result 5: The Chamberboss admin menu (and Members page) reappeared without WSOD. This was a critical breakthrough, isolating the problem to within the process_add_member() method itself after it's called.
Debugging WSOD (Post-Submission, Current State - Back to process_add_member()):
Action: Re-enabled initial error_log() statements (process_add_member reached, $_POST data) and the initial if (!isset($_POST['action']) || !isset($_POST['add_member_nonce'])) block, and the nonce verification block in process_add_member(). All other logic remained commented.
Result: WSOD returned upon form submission, and still no debug logs from process_add_member().
Linter Note: During the process of re-enabling code and commenting, there were temporary syntax errors introduced and fixed in MembersPage.php related to comment block (/* ... */) placement.
Current Mystery:
The process_add_member() function is being called by the admin_post_add_new_member hook (as evidenced by the WSOD appearing after submission and after the Admin Menu loads correctly). However, the fatal error is occurring so early within process_add_member() that even the very first error_log() statement (placed at the absolute top of the function) is not writing to debug.log. This is highly unusual and suggests an immediate, uncaught fatal error as soon as the function's PHP is parsed/executed.
Next Steps / Recommendation for Another AI
The problem is isolated to includes/Admin/MembersPage.php and specifically within the process_add_member() function.
Proposed Debugging Strategy:
Systematic Line-by-Line Re-enabling:
Starting with the process_add_member() function in includes/Admin/MembersPage.php, uncomment code line by line (or small blocks at a time).
After each uncomment, attempt to add a member and check the debug.log.
The line that causes the WSOD to reappear (or causes a visible error in debug.log) is the problematic line.
Focus on Dependencies and Early Execution:
Pay close attention to any function calls ($this->sanitize_input(), wp_verify_nonce(), or any object property access) on the very first few lines that are currently uncommented within process_add_member(). Even seemingly innocuous operations can lead to fatal errors if dependencies are missing or incorrectly configured at that exact point in execution.
Consider adding a very simple die('Reached here: 1'); (or similar) at various points before any error_log statements, to force output and narrow down the exact point of failure.