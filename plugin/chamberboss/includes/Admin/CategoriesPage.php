<?php
namespace Chamberboss\Admin;

use Chamberboss\Core\BaseClass;
use Chamberboss\Core\Database;

class CategoriesPage extends BaseClass {
    private $database;

    protected function init() {
        $this->database = new Database();
        add_action('admin_post_add_new_category', [$this, 'add_category']);
        add_action('admin_post_delete_category', [$this, 'delete_category']);
    }

    public function render() {
        $categories = $this->database->get_listing_categories();
        ?>
        <div class="wrap">
            <h1><?php _e('Listing Categories', 'chamberboss'); ?></h1>
            <div id="col-container">
                <div id="col-right">
                    <div class="col-wrap">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th scope="col" class="manage-column"><?php _e('Name', 'chamberboss'); ?></th>
                                    <th scope="col" class="manage-column"><?php _e('Slug', 'chamberboss'); ?></th>
                                    <th scope="col" class="manage-column"><?php _e('Actions', 'chamberboss'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($categories)):
                                    foreach ($categories as $category):
                                        $delete_url = esc_url(wp_nonce_url(admin_url('admin-post.php?action=delete_category&category_id=' . $category->id), 'delete_category_' . $category->id));
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($category->name); ?></td>
                                        <td><?php echo esc_html($category->slug); ?></td>
                                        <td>
                                            <a href="<?php echo $delete_url; ?>" class="button button-small button-danger" onclick="return confirm('Are you sure you want to delete this category?')"><?php _e('Delete', 'chamberboss'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="3"><?php _e('No categories found.', 'chamberboss'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="col-left">
                    <div class="col-wrap">
                        <div class="form-wrap">
                            <h2><?php _e('Add New Category', 'chamberboss'); ?></h2>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('add_new_category_nonce'); ?>
                                <input type="hidden" name="action" value="add_new_category">
                                <div class="form-field">
                                    <label for="name"><?php _e('Name', 'chamberboss'); ?></label>
                                    <input type="text" name="name" id="name" required>
                                </div>
                                <div class="form-field">
                                    <label for="slug"><?php _e('Slug', 'chamberboss'); ?></label>
                                    <input type="text" name="slug" id="slug" required>
                                </div>
                                <?php submit_button('Add Category'); ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function add_category() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }

        check_admin_referer('add_new_category_nonce');

        $name = sanitize_text_field($_POST['name']);
        $slug = sanitize_text_field($_POST['slug']);

        if (empty($name) || empty($slug)) {
            wp_redirect(admin_url('admin.php?page=chamberboss-categories&error=1'));
            exit;
        }

        $this->database->add_listing_category(['name' => $name, 'slug' => $slug]);

        wp_redirect(admin_url('admin.php?page=chamberboss-categories&success=1'));
        exit;
    }

    public function delete_category() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }

        $category_id = intval($_GET['category_id']);
        check_admin_referer('delete_category_' . $category_id);

        $this->database->delete_listing_category($category_id);

        wp_redirect(admin_url('admin.php?page=chamberboss-categories&success=2'));
        exit;
    }
}
