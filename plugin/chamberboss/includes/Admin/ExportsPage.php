<?php
namespace Chamberboss\Admin;

use Chamberboss\Core\BaseClass;

class ExportsPage extends BaseClass {

    protected function init() {
        add_action('admin_init', [$this, 'handle_exports']);
    }

    public function render() {
        ?>
        <div class="wrap">
            <h1><?php _e('Export Data', 'chamberboss'); ?></h1>
            <p><?php _e('Download your Chamberboss data in CSV format.', 'chamberboss'); ?></p>

            <div class="chamberboss-export-buttons">
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=chamberboss-exports&action=export_members'), 'export_members_nonce'); ?>" class="button button-primary">
                    <?php _e('Export Members', 'chamberboss'); ?>
                </a>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=chamberboss-exports&action=export_listings'), 'export_listings_nonce'); ?>" class="button button-primary">
                    <?php _e('Export Listings', 'chamberboss'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    public function handle_exports() {
        if (empty($_GET['action'])) {
            return;
        }

        if ($_GET['action'] === 'export_members') {
            $this->export_members_csv();
        }

        if ($_GET['action'] === 'export_listings') {
            $this->export_listings_csv();
        }
    }

    private function export_members_csv() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'export_members_nonce')) {
            return;
        }

        $members = get_posts([
            'post_type' => 'chamberboss_member',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=chamberboss-members-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            __('Name', 'chamberboss'),
            __('Email', 'chamberboss'),
            __('Company', 'chamberboss'),
            __('Phone', 'chamberboss'),
            __('Address', 'chamberboss'),
            __('Website', 'chamberboss'),
            __('Status', 'chamberboss'),
            __('Membership Start', 'chamberboss'),
            __('Membership End', 'chamberboss'),
        ]);

        foreach ($members as $member) {
            $row = [
                $member->post_title,
                get_post_meta($member->ID, '_chamberboss_member_email', true),
                get_post_meta($member->ID, '_chamberboss_member_company', true),
                get_post_meta($member->ID, '_chamberboss_member_phone', true),
                get_post_meta($member->ID, '_chamberboss_member_address', true),
                get_post_meta($member->ID, '_chamberboss_member_website', true),
                get_post_meta($member->ID, '_chamberboss_subscription_status', true),
                get_post_meta($member->ID, '_chamberboss_subscription_start', true),
                get_post_meta($member->ID, '_chamberboss_subscription_end', true),
            ];
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    private function export_listings_csv() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'export_listings_nonce')) {
            return;
        }

        $listings = get_posts([
            'post_type' => 'chamberboss_listing',
            'post_status' => ['publish', 'pending', 'draft'],
            'posts_per_page' => -1,
        ]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=chamberboss-listings-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            __('Business Name', 'chamberboss'),
            __('Description', 'chamberboss'),
            __('Category', 'chamberboss'),
            __('Phone', 'chamberboss'),
            __('Address', 'chamberboss'),
            __('Website', 'chamberboss'),
            __('Status', 'chamberboss'),
            __('Featured', 'chamberboss'),
            __('Author', 'chamberboss'),
            __('Date', 'chamberboss'),
        ]);

        foreach ($listings as $listing) {
            $author = get_user_by('id', $listing->post_author);
            $row = [
                $listing->post_title,
                $listing->post_content,
                get_post_meta($listing->ID, '_chamberboss_listing_category', true),
                get_post_meta($listing->ID, '_chamberboss_listing_phone', true),
                get_post_meta($listing->ID, '_chamberboss_listing_address', true),
                get_post_meta($listing->ID, '_chamberboss_listing_website', true),
                $listing->post_status,
                get_post_meta($listing->ID, '_chamberboss_listing_featured', true) ? 'Yes' : 'No',
                $author ? $author->display_name : '',
                $listing->post_date,
            ];
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
