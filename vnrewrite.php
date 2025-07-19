<?php

/**
 * Plugin Name: VnRewrite
 * Plugin URI: https://vnrewrite.com/
 * Description: Tự động viết lại bài gốc (post, url, keywword, sub youtube) bằng Gemini, Open AI, Claude...
 * Version: 10.22
 * Author: thienvt
 * Author URI: https://www.facebook.com/thienvt36/
 * License: GPLv2
 */

if (!defined('ABSPATH')) {
    exit;
}

$dir_vnrewrite_data = ABSPATH . 'wp-content/uploads/vnrewrite';
if (!file_exists($dir_vnrewrite_data)) {
    mkdir($dir_vnrewrite_data);
}
define('VNREWRITE_DATA', $dir_vnrewrite_data . '/');
define('VNREWRITE_PATH', plugin_dir_path(__FILE__) . '/');
define('VNREWRITE_URL', plugins_url('/', __FILE__));
define('VNREWRITE_ADMIN_PAGE', admin_url('options-general.php?page=vnrewrite-admin'));

function vnrewrite_script()
{
    if (isset($_GET['page']) && $_GET['page'] == 'vnrewrite-admin') {
        wp_enqueue_script('vnrewrite', VNREWRITE_URL . 'admin/vnrewrite.js', array('jquery'), null, false);
        wp_localize_script('vnrewrite', 'vnrewrite_obj', array(
            'ajaxurl'             => admin_url('admin-ajax.php'),
            'rewrite_urls'        => VNREWRITE_ADMIN_PAGE . '&tab=urls',
            'rewrite_keywords'    => VNREWRITE_ADMIN_PAGE . '&tab=keywords',
            'rewrite_videos'      => VNREWRITE_ADMIN_PAGE . '&tab=videos-yt',
            'config_nonce' => wp_create_nonce('vnrewrite_config_action'),
            'read_log_nonce' => wp_create_nonce('vnrewrite_read_log'),
            'createprompt_nonce' => wp_create_nonce('vnrewrite_createprompt_action'),
            'createprompt_savecat_nonce' => wp_create_nonce('vnrewrite_createprompt_savecat_action'),
            'update_model_nonce' => wp_create_nonce('vnrewrite_update_model'),
            'current_tab' => isset($_GET['tab']) ? $_GET['tab'] : '',
            'debug_enabled' => (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG)
        ));
    }
}
add_action('admin_enqueue_scripts', 'vnrewrite_script');

require_once VNREWRITE_PATH . 'admin/admin.php';
require_once VNREWRITE_PATH . 'admin/cron.php';
require_once VNREWRITE_PATH . 'admin/ajax.php';
require_once VNREWRITE_PATH . 'admin/list-post.php';
require_once VNREWRITE_PATH . 'admin/wp-config-modifi.php';

function vnrewrite_get_options()
{
    static $vnrewrite_options = null;
    if ($vnrewrite_options === null) {
        $vnrewrite_options = get_option('vnrewrite_option');
    }
    return $vnrewrite_options;
}

require_once VNREWRITE_PATH . 'admin/edit-content.php';

//polylang
add_action('plugins_loaded', function () {
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    if (defined('POLYLANG_VERSION') && defined('RANK_MATH_VERSION') && class_exists('PLL_Integrations')) {
        require_once VNREWRITE_PATH . 'lib/rank-math-ppl.php';
        add_action('pll_init', array(PLL_Integrations::instance()->rankmath = new PLL_RankMath(), 'init'));
    }
});

//remove category default "uncategorized-"
if (defined('POLYLANG_VERSION')) {
    add_filter('pll_before_term_translation', function ($translation) {
        if ($translation['taxonomy'] === 'category' && strpos($translation['slug'], 'uncategorized-') === 0) {
            return false;
        }
        return $translation;
    }, 10, 1);

    add_action('created_term', function ($term_id, $tt_id, $taxonomy) {
        if ($taxonomy === 'category') {
            $term = get_term($term_id, 'category');
            if (strpos($term->slug, 'uncategorized-') === 0) {
                wp_delete_term($term_id, 'category');
            }
        }
    }, 10, 3);
}

add_filter('home_url', function ($url, $path = '') {
    if (!defined('POLYLANG_VERSION') || is_admin() || wp_doing_ajax()) {
        return $url;
    }

    $current_lang = function_exists('pll_current_language') ? pll_current_language() : '';

    if ($current_lang) {
        $url = pll_home_url($current_lang);
        if (!empty($path) && $path !== '/') {
            $url = trailingslashit($url) . ltrim($path, '/');
        }
    }

    return $url;
}, 10, 2);

add_action('init', function () {
    if (function_exists('pll_register_string')) {
        $options = vnrewrite_get_options();
        pll_register_string('related_text', $options['text_more'] ?? 'Read more:', 'VnRewrite');
        pll_register_string('toc_text', $options['toc_text'] ?? 'Contents', 'VnRewrite');
        pll_register_string('tab1_text', $options['tab1_text'] ?? 'Description', 'VnRewrite');
        pll_register_string('tab2_text', $options['tab2_text'] ?? 'FAQs', 'VnRewrite');
        pll_register_string('expand_text', $options['expand_text'] ?? 'Expand', 'VnRewrite');
        pll_register_string('collapse_text', $options['collapse_text'] ?? 'Collapse', 'VnRewrite');
        pll_register_string('genre', 'Genre', 'VnRewrite');
        pll_register_string('Recommended for you', 'Recommended for you', 'VnRewrite');
        pll_register_string('You May Also Like', 'You May Also Like', 'VnRewrite');
        pll_register_string('Popular', 'Popular', 'VnRewrite');
        pll_register_string('Latest', 'Latest', 'VnRewrite');
        pll_register_string('Related Posts', 'Related Posts', 'VnRewrite');
    }
});

//img
function rewrite_disable_scaled_images($threshold)
{
    return 0;
}
add_filter('big_image_size_threshold', 'rewrite_disable_scaled_images');

function rewrite_only_thumbnail_size($sizes)
{
    foreach ($sizes as $size => $details) {
        if ($size !== 'thumbnail') {
            unset($sizes[$size]);
        }
    }
    return $sizes;
}
add_filter('intermediate_image_sizes_advanced', 'rewrite_only_thumbnail_size');

function rewrite_remove_all_custom_image_sizes()
{
    global $_wp_additional_image_sizes;

    if (isset($_wp_additional_image_sizes) && count($_wp_additional_image_sizes)) {
        foreach ($_wp_additional_image_sizes as $size => $details) {
            if ($size !== 'thumbnail') {
                remove_image_size($size);
            }
        }
    }
}
add_action('init', 'rewrite_remove_all_custom_image_sizes');

function rewrite_remove_image_sizes_theme()
{
    foreach (get_intermediate_image_sizes() as $size) {
        if ($size !== 'thumbnail') {
            remove_image_size($size);
        }
    }
}
add_action('after_setup_theme', 'rewrite_remove_image_sizes_theme', 11);

//update model
function vnrewrite_update_model()
{
    if (isset($_GET['cmd']) && $_GET['cmd'] === 'vnrewrite-update-model') {
        if (!current_user_can('manage_options')) {
            wp_die('Bạn không có quyền thực hiện hành động này.');
        }

        $url = 'https://vngpt.pro/model/';
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            $message = 'Không thể kết nối đến server để cập nhật model.';
            $type = 'error';
        } else {
            $body = wp_remote_retrieve_body($response);
            update_option('vnrewrite_model', $body);
            $message = 'Cập nhật model thành công.';
            $type = 'success';
        }

        set_transient('vnrewrite_admin_notice', array('message' => $message, 'type' => $type), 30);

        wp_redirect(add_query_arg('model-updated', '1', VNREWRITE_ADMIN_PAGE));
        exit;
    }
}
add_action('admin_init', 'vnrewrite_update_model');

function vnrewrite_admin_notices()
{
    $notice = get_transient('vnrewrite_admin_notice');
    if ($notice) {
        $class = 'notice notice-' . $notice['type'] . ' is-dismissible';
?>
        <div class="<?php echo esc_attr($class); ?>">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
<?php
        delete_transient('vnrewrite_admin_notice');
    }
}
add_action('admin_notices', 'vnrewrite_admin_notices');

function vnrewrite_admin_init()
{
    if (isset($_GET['page']) && $_GET['page'] === 'vnrewrite-admin') {
        add_action('admin_notices', 'vnrewrite_admin_notices');
    }
}
add_action('admin_init', 'vnrewrite_admin_init');

function vnrewrite_update_model_from_remote()
{
    $url = 'https://vngpt.pro/model/';
    $response = wp_remote_get($url);

    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        update_option('vnrewrite_model', $body);
        return true;
    }
    return false;
}

function vnrewrite_activation()
{
    vnrewrite_update_model_from_remote();
}
register_activation_hook(__FILE__, 'vnrewrite_activation');

function vnrewrite_upgrade($upgrader_object, $options)
{
    $current_plugin_path_name = plugin_basename(__FILE__);

    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        foreach ($options['plugins'] as $each_plugin) {
            if ($each_plugin == $current_plugin_path_name) {
                vnrewrite_update_model_from_remote();
            }
        }
    }
}
add_action('upgrader_process_complete', 'vnrewrite_upgrade', 10, 2);

function vnrewrite_install($upgrader_object, $options)
{
    if ($options['action'] == 'install' && $options['type'] == 'plugin') {
        $current_plugin_path_name = plugin_basename(__FILE__);
        $installed_plugin = $upgrader_object->plugin_info();
        if ($installed_plugin == $current_plugin_path_name) {
            vnrewrite_update_model_from_remote();
        }
    }
}
add_action('upgrader_process_complete', 'vnrewrite_install', 10, 2);

//admin bar
add_action('admin_bar_menu', 'vnrewrite_admin_bar', 999);
function vnrewrite_admin_bar($wp_admin_bar)
{
    $wp_admin_bar->add_menu(array(
        'id'    => 'vnrewrite',
        'title' => 'VnRewrite',
        'href'  => admin_url('options-general.php?page=vnrewrite-admin')
    ));
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links_vnrewrite');
function add_action_links_vnrewrite($actions)
{
    $mylinks = array(
        '<a href="' . admin_url('options-general.php?page=vnrewrite-admin') . '">Settings</a>',
    );
    $actions = array_merge($actions, $mylinks);
    return $actions;
}

$options = vnrewrite_get_options();
require_once VNREWRITE_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (!empty($options['user_key'])) {
    $myUpdateChecker = PucFactory::buildUpdateChecker(
        'https://vnrewrite.com/update/?token=' . ($options['user_key'] ?? ''),
        __FILE__,
        'vnrewrite'
    );
}
?>