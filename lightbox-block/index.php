<?php
/**
 * Plugin Name: All In One Lightbox – Display Images, Audio, and Video in Popups
 * Description: Lightbox block is an excellent choice for your WordPress Lightbox Block.
 * Version: 1.1.43
 * Author: bPlugins
 * Author URI: http://bplugins.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lightbox-block
 */

// ABS PATH
if (!defined('ABSPATH')) {exit;}

    // Constant
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        define('LBB_LIGHTBOX_PLUGIN_VERSION', time());
    } else {
        define('LBB_LIGHTBOX_PLUGIN_VERSION', '1.1.43');
    }
    
    define('LBB_LIGHTBOX_ASSETS_DIR', plugin_dir_url(__FILE__) . 'assets/');
    define('LBB_LIGHTBOX_DIR_URL', plugin_dir_url(__FILE__));
    define('LBB_LIGHTBOX_DIR_PATH', plugin_dir_path(__FILE__));
    
    // Create a helper function for easy SDK access.
    if (!function_exists('lbb_fs')) {
        function lbb_fs()
        {
            global $lbb_fs;

            if (!isset($lbb_fs)) {
                 
                require_once dirname( __FILE__ ) . '/vendor/freemius-lite/start.php';

                $lbbConfig = (array(
                    'id' => '13492',
                    'slug' => 'lightbox-block',
                    'type' => 'plugin',
                    'public_key' => 'pk_8346b668170b2e4c33255d896d15c',
                    'is_premium' => false,
                    'menu' => array(
                        'slug'           => 'edit.php?post_type=lbb',
                        'first-path'  =>  'edit.php?post_type=lbb&page=lightbox-block#/pricing',
                        'support'     => false,
                    )
                    
                ));
                $lbb_fs = fs_lite_dynamic_init( $lbbConfig );
            }
            return $lbb_fs;
        }

        // Init Freemius.
        lbb_fs();
        // Signal that SDK was initiated.
        do_action('lbb_fs_loaded');
    }

    // Light Box
    if (!class_exists('LBB_Lightbox_Plugin')) {
        class LBB_Lightbox_Plugin
        {
            public function __construct(){ 
                $this->load_classes();
                add_action('init', [$this, 'onInit']);
                add_action('enqueue_block_assets', [$this, 'enqueueBlockAssets'], 10);
                add_action('wp_ajax_bpllb_get_image_id', [$this, 'bpllb_get_image_id']);
                add_action('wp_enqueue_scripts', [$this, 'lbb_custom_popup']);
                add_filter( 'plugin_action_links', [$this, 'plugin_action_links'], 10, 2 ); 
            }

            public function lbb_custom_popup(){
                global $post;

                if ( ! is_singular() || ! ( $post instanceof WP_Post ) ) {
                    return;
                }

                if ( ! has_block( 'lbb/lightbox', $post ) && ! has_shortcode( $post->post_content, 'lbb-lightbox-block' ) ) {
                    return;
                }

                wp_enqueue_script('lbb-custom-popup',  LBB_LIGHTBOX_DIR_URL . 'build/custom-popup.js', [], LBB_LIGHTBOX_PLUGIN_VERSION, true);
                wp_enqueue_style( 'lbb-custom-popup', LBB_LIGHTBOX_DIR_URL. 'build/custom-popup.css', [], LBB_LIGHTBOX_PLUGIN_VERSION );
            }

            function onInit(){
                register_block_type( __DIR__ . '/build' );
            }

            public function enqueueBlockAssets(){
                wp_register_style( 'fontAwesome', LBB_LIGHTBOX_ASSETS_DIR . 'css/font-awesome.min.css', [], '6.5.2' ); // Icon — enqueued via block.json when block is present
                
                wp_register_style('fancyapps-carousel', LBB_LIGHTBOX_ASSETS_DIR . 'css/carousel.css', [], '5.0');
                wp_register_style('fancyapps-thum', LBB_LIGHTBOX_ASSETS_DIR . 'css/carousel-thum.css', [], '5.0');
                wp_register_style('lbb-shortcode', LBB_LIGHTBOX_ASSETS_DIR . 'css/shortcode.css', [], LBB_LIGHTBOX_PLUGIN_VERSION);

                wp_register_style('lbb-plyr-style', LBB_LIGHTBOX_ASSETS_DIR . 'css/plyr.min.css', [], LBB_LIGHTBOX_PLUGIN_VERSION);

                wp_register_script('fancyapps-carousel', LBB_LIGHTBOX_ASSETS_DIR . 'js/carousel.js', [], '5.0', true);
                wp_register_script('fancyapps-thum', LBB_LIGHTBOX_ASSETS_DIR . 'js/carousel-thum.js', [], '5.0', true);

                wp_register_script('lbb-plyr-script', LBB_LIGHTBOX_ASSETS_DIR . 'js/plyr.min.js', [], LBB_LIGHTBOX_PLUGIN_VERSION, true);
                wp_register_script('lbb-shortcode', LBB_LIGHTBOX_ASSETS_DIR . 'js/shortcode.js', [], LBB_LIGHTBOX_PLUGIN_VERSION, true);

                wp_localize_script('lbb-plyr-script', 'bpllbMediaUrlId', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('bpllb_get_image_id'),
                ]);
            }

            //Class loaded
            public function load_classes () {
                 
                require_once plugin_dir_path(__FILE__) . '/inc/AdminMenu.php';
                require_once plugin_dir_path(__FILE__) . '/inc/custom-shortcode.php';
            }

            public function bpllb_get_image_id(){

                check_ajax_referer("bpllb_get_image_id", "nonce" );

                if ( ! current_user_can( 'edit_posts' ) ) {
                    wp_send_json_error( ['message' => 'Insufficient permissions'] );
                }

                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded first, then each field is sanitized individually below.
                $raw_items = isset($_POST['items']) ? wp_unslash($_POST['items']) : '';
                $decoded_items = json_decode($raw_items, true);

                if (!is_array($decoded_items)) {
                    wp_send_json_error(['message' => 'Invalid data']);
                }

                $response = [];

                foreach ($decoded_items as $item) {
                    $type = isset($item['type']) ? sanitize_text_field($item['type']) : '';

                    if ( 'image' === $type ) {
                        $content = isset($item['content']) ? esc_url_raw($item['content']) : '';
                    } else {
                        $content = isset($item['thumbnail']) ? esc_url_raw($item['thumbnail']) : '';
                    }

                    $attachment_id = attachment_url_to_postid($content);
                    $item['type'] = $type;
                    $item['id'] = $attachment_id;
                    $response[] = $item;
                }
                wp_send_json_success($response);
            }

            public function plugin_action_links($links, $file) {
            
            if( plugin_basename( __FILE__ ) == $file ) {

                $dashboardLink = admin_url( 'edit.php?post_type=lbb&page=lightbox-block' );

                $links['dashboard'] = sprintf( '<a href="%s" style="%s" target="_blank">%s</a>', $dashboardLink, 'color:#4527a4;font-weight:bold', __( 'Dashboard!', 'lightbox-block' ) );
            }
 
            return $links;
        }
        }
        new LBB_Lightbox_Plugin;
    }
