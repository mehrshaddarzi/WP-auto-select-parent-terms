<?php
/**
 * Plugin Name: فرآیند انتخاب اتوماتیک دسته های والد
 * Description: ابزارک فرآیند انتخاب اتوماتیک دسته های والد
 * Plugin URI:  https://realwp.net
 * Version:    1.0.0
 * Author:      مهرشاد درزی
 * Author URI:  https://realwp.net
 * License:     MIT
 */

class WP_Auto_Parent_Terms
{

    public static $plugin_url;

    public static $plugin_path;

    public static $plugin_version;

    public function __construct()
    {
        // Define Variable
        $this->define_constants();

        // Loader
        add_action('plugins_loaded', [$this, 'plugins_loaded']);
    }

    /* @Hook */
    public function plugins_loaded()
    {
        add_action('admin_init', [$this, 'import_excel'], 30);
    }

    /* @tested */
    public function define_constants()
    {

        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_data = get_plugin_data(__FILE__);

        /*
         * Set Plugin Version
         */
        self::$plugin_version = $plugin_data['Version'];

        /*
         * Set Plugin Url
         */
        self::$plugin_url = plugins_url('', __FILE__);

        /*
         * Set Plugin Path
         */
        self::$plugin_path = plugin_dir_path(__FILE__);
    }

    /* @Hook */
    public function import_excel()
    {
        global $wpdb;

        if (!class_exists('\Import_Excel_Widget')) {
            require_once WP_Auto_Parent_Terms::$plugin_path . '/widget-import-excel/Import_Excel_Widget.php';
        }

        // Setup Html
        ob_start();
        include WP_Auto_Parent_Terms::$plugin_path . '/form.php';
        $html = ob_get_contents();
        ob_end_clean();

        // Return
        new \Import_Excel_Widget([
            'id' => 'auto-parent-terms',
            'title' => 'انتخاب اتوماتیک دسته های والد پست',
            'html' => $html,
            'number_per_process' => 50,
            'require_excel_file' => false,
            'get_excel_content' => function ($array, $args) {
                global $wpdb;

                // Get Data
                $post_type = trim($_REQUEST['post_type']);
                $taxonomy = trim($_REQUEST['taxonomy']);

                // Check Params
                if (empty($post_type) || empty($taxonomy)) {
                    return ['status' => false, 'message' => 'پارامترها اشتباه می باشد'];
                }

                // Get Posts
                $query = new \WP_Query(array(
                    'post_type' => $post_type,
                    'post_status' => 'any',
                    'posts_per_page' => '-1',
                    'order' => 'ASC',
                    'fields' => 'ids',
                    'cache_results' => false,
                    'no_found_rows' => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                    'suppress_filters' => true,
                    'tax_query' => array(
                        array(
                            'taxonomy' => $taxonomy,
                            'operator' => 'EXISTS'
                        )
                    ),
                ));

                // Check Empty Posts
                if (empty($query->posts)) {
                    return ['status' => false, 'message' => 'هیچ رکورد یافت نشد'];
                }

                // Init List
                $list = [];

                // Init Error Data List
                $errors = [];

                // For Loop
                foreach ($query->posts as $ID) {
                    // Row
                    $row = [];

                    // Set Post ID
                    $row['ID'] = $ID;

                    // Set Tax
                    $row['tax'] = $taxonomy;

                    // Push To List
                    $list[] = $row;
                }

                // Check Has Error
                if (!empty($errors)) {
                    return ['status' => false, 'message' => '<br />' . implode('<br />', $errors)];
                }

                // Return Data
                return [
                    'status' => true,
                    'message' => '',
                    'list' => $list,
                    'table' => [
                        'تعداد کل محتواها' => count($list),
                    ]
                ];
            },
            'process_item' => function ($item, $key) {

                // Get Data
                $post_id = $item['ID'];
                $taxonomy = $item['tax'];

                // Get Terms Ids
                $db_terms = wp_get_post_terms($post_id, $taxonomy);
                $db_terms_ids = array_map('intval', wp_list_pluck($db_terms, 'term_id'));
                if (!empty($db_terms_ids)) {

                    // Get Parent Ids
                    $prepare_terms_ids = array_map('intval', self::wp_prepare_terms_ids_with_parent($db_terms_ids, $taxonomy));
                    // error_log( print_r( $post_id, true ) );

                    // Check If Changes
                    // @see https://stackoverflow.com/questions/38629056/how-to-check-if-two-arrays-contain-the-same-values
                    $areEqual = array_diff($db_terms_ids, $prepare_terms_ids) === array_diff($prepare_terms_ids, $db_terms_ids);
                    if (!$areEqual) {
                        wp_set_post_terms($post_id, $prepare_terms_ids, $taxonomy, false);
                    }
                }
            },
            'after_completed_process' => function () {
                global $wpdb;
                // Do Work
            }
        ]);
    }

    public static function wp_prepare_terms_ids_with_parent($ids, $taxonomy)
    {
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        $list = $ids;
        foreach ($ids as $term_id) {
            $list[] = $term_id;
            $ancestors = get_ancestors($term_id, $taxonomy);
            if (!empty($ancestors)) {
                foreach ($ancestors as $anc_id) {
                    if (!in_array($anc_id, $list)) {
                        $list[] = $anc_id;
                    }
                }
            }
        }

        return array_filter(array_values(array_unique($list)));
    }

    public static function wp_prepare_term_children_id_for_field($post_id, $taxonomy)
    {
        // Prepare Term Id
        $terms = wp_get_post_terms($post_id, $taxonomy);
        $terms_ids = wp_list_pluck($terms, 'term_id');

        // Check One Term
        if (count($terms_ids) < 2) {
            return $terms_ids[0];
        }

        // Setup ancestors number array
        $list = [];
        foreach ($terms_ids as $term_id) {
            $ancestors = get_ancestors($term_id, $taxonomy);
            $list[$term_id] = count($ancestors);
        }
        arsort($list);

        return array_key_first($list);
    }

}

new WP_Auto_Parent_Terms();