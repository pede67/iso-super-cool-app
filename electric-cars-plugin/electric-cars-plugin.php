<?php
/*
Plugin Name: Electric Cars Database
Description: Manage a searchable database of electric cars with customizable parameters.
Version: 1.1
Author: Codex
*/

if (!defined('ABSPATH')) exit;

/**
 * Retrieve saved car parameters
 */
function ecdb_get_parameters() {
    $params = get_option('ecdb_parameters', array());
    if (!is_array($params)) {
        $params = array();
    }
    return $params;
}

/**
 * Admin menu
 */
add_action('admin_menu', 'ecdb_admin_menu');
function ecdb_admin_menu() {
    add_menu_page('Electric Cars', 'Electric Cars', 'manage_options', 'ecdb_parameters', 'ecdb_parameters_page');
}

/**
 * Parameter management page
 */
function ecdb_parameters_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $params = ecdb_get_parameters();

    // Handle add parameter
    if (isset($_POST['ecdb_add_param']) && check_admin_referer('ecdb_params')) {
        $slug = sanitize_title($_POST['param_slug']);
        $label = sanitize_text_field($_POST['param_label']);
        if ($slug && $label) {
            $params[$slug] = $label;
            update_option('ecdb_parameters', $params);
        }
    }

    // Handle delete parameter
    if (isset($_POST['ecdb_delete']) && check_admin_referer('ecdb_params')) {
        $del = sanitize_title($_POST['ecdb_delete']);
        unset($params[$del]);
        update_option('ecdb_parameters', $params);
    }

    ?>
    <div class="wrap">
        <h1>Electric Car Parameters</h1>
        <form method="post">
            <?php wp_nonce_field('ecdb_params'); ?>
            <table class="form-table" role="presentation">
                <tr><th>Label</th><th>Slug</th><th>Action</th></tr>
                <?php foreach ($params as $slug => $label) : ?>
                    <tr>
                        <td><?php echo esc_html($label); ?></td>
                        <td><?php echo esc_html($slug); ?></td>
                        <td>
                            <button type="submit" name="ecdb_delete" value="<?php echo esc_attr($slug); ?>" class="button">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <h2>Add Parameter</h2>
            <p>
                <label>Label: <input type="text" name="param_label" required></label>
            </p>
            <p>
                <label>Slug: <input type="text" name="param_slug" required></label>
            </p>
            <p><button type="submit" name="ecdb_add_param" class="button button-primary">Add Parameter</button></p>
        </form>
    </div>
    <?php
}

/**
 * Register custom post type
 */
add_action('init', 'ecdb_register_post_type');
function ecdb_register_post_type() {
    register_post_type('electric_car', array(
        'labels' => array(
            'name' => 'Electric Cars',
            'singular_name' => 'Electric Car'
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail'),
        'taxonomies' => array('car_brand', 'car_model', 'car_variant'),
    ));
}

/**
 * Register taxonomies for brand, model and variant
 */
add_action('init', 'ecdb_register_taxonomies');
function ecdb_register_taxonomies() {
    $args = array(
        'public' => true,
        'hierarchical' => false,
        'show_admin_column' => true,
    );
    register_taxonomy('car_brand', 'electric_car', array_merge(
        $args,
        array('labels' => array('name' => 'Brands', 'singular_name' => 'Brand'))
    ));
    register_taxonomy('car_model', 'electric_car', array_merge(
        $args,
        array('labels' => array('name' => 'Models', 'singular_name' => 'Model'))
    ));
    register_taxonomy('car_variant', 'electric_car', array_merge(
        $args,
        array('labels' => array('name' => 'Variants', 'singular_name' => 'Variant'))
    ));
}

/**
 * Add meta box for parameters
 */
add_action('add_meta_boxes', 'ecdb_meta_boxes');
function ecdb_meta_boxes() {
    add_meta_box('ecdb_params', 'Car Parameters', 'ecdb_meta_box', 'electric_car', 'normal', 'default');
}

function ecdb_meta_box($post) {
    $params = ecdb_get_parameters();
    wp_nonce_field('ecdb_save_params', 'ecdb_nonce');
    foreach ($params as $slug => $label) {
        $value = get_post_meta($post->ID, '_ecdb_' . $slug, true);
        echo '<p><label>' . esc_html($label) . ': ';
        echo '<input type="text" name="ecdb_param[' . esc_attr($slug) . ']" value="' . esc_attr($value) . '"></label></p>';
    }
}

/**
 * Save meta
 */
add_action('save_post_electric_car', 'ecdb_save_car_meta');
function ecdb_save_car_meta($post_id) {
    if (!isset($_POST['ecdb_nonce']) || !wp_verify_nonce($_POST['ecdb_nonce'], 'ecdb_save_params')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $params = ecdb_get_parameters();
    if (!isset($_POST['ecdb_param'])) return;

    foreach ($params as $slug => $label) {
        if (isset($_POST['ecdb_param'][$slug])) {
            update_post_meta($post_id, '_ecdb_' . $slug, sanitize_text_field($_POST['ecdb_param'][$slug]));
        }
    }
}

/**
 * Shortcode output
 */
add_shortcode('electric_cars', 'ecdb_shortcode');
function ecdb_shortcode($atts) {
    $params = ecdb_get_parameters();

    $search = isset($_GET['ecdb_search']) ? sanitize_text_field($_GET['ecdb_search']) : '';

    $query = new WP_Query(array(
        'post_type' => 'electric_car',
        's' => $search,
        'posts_per_page' => -1,
    ));

    ob_start();
    ?>
    <form method="get" action="">
        <input type="text" name="ecdb_search" value="<?php echo esc_attr($search); ?>" placeholder="Search cars">
        <button type="submit">Search</button>
    </form>
    <table class="ecdb-table">
        <tr>
            <th>Name</th>
            <th>Brand</th>
            <th>Model</th>
            <th>Variant</th>
            <?php foreach ($params as $label) : ?>
                <th><?php echo esc_html($label); ?></th>
            <?php endforeach; ?>
        </tr>
        <?php if ($query->have_posts()) : ?>
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <tr>
                    <td><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></td>
                    <?php $brand = wp_get_post_terms(get_the_ID(), 'car_brand', array('fields' => 'names')); ?>
                    <td><?php echo esc_html(implode(', ', $brand)); ?></td>
                    <?php $model = wp_get_post_terms(get_the_ID(), 'car_model', array('fields' => 'names')); ?>
                    <td><?php echo esc_html(implode(', ', $model)); ?></td>
                    <?php $variant = wp_get_post_terms(get_the_ID(), 'car_variant', array('fields' => 'names')); ?>
                    <td><?php echo esc_html(implode(', ', $variant)); ?></td>
                    <?php foreach ($params as $slug => $label) : ?>
                        <td><?php echo esc_html(get_post_meta(get_the_ID(), '_ecdb_' . $slug, true)); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endwhile; ?>
            <?php wp_reset_postdata(); ?>
        <?php else : ?>
            <tr><td colspan="<?php echo count($params) + 4; ?>">No cars found.</td></tr>
        <?php endif; ?>
    </table>
    <?php
    return ob_get_clean();
}

