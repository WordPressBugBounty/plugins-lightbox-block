<?php

/**
 * Plugin Name: Lightbox block
 * Description: Lightbox block is an excellent choice for your WordPress Lightbox Block.
 * Version: 1.1.39
 * Author: bPlugins
 * Author URI: http://bplugins.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: lightbox
 * @fs_free_only, bsdk_config.json, /freemius-lite
 */
// ABS PATH
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( 'lbb_fs' ) ) {
    register_activation_hook( __FILE__, function () {
        if ( is_plugin_active( 'lightbox-block/index.php' ) ) {
            deactivate_plugins( 'lightbox-block/index.php' );
        }
        if ( is_plugin_active( 'lightbox-block-pro/index.php' ) ) {
            deactivate_plugins( 'lightbox-block-pro/index.php' );
        }
    } );
} else {
    // Constant
    define( 'LBB_PLUGIN_VERSION', ( isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '1.1.39' ) );
    define( 'LBB_ASSETS_DIR', plugin_dir_url( __FILE__ ) . 'assets/' );
    define( 'LBB_DIR_URL', plugin_dir_url( __FILE__ ) );
    define( 'LBB_DIR_PATH', plugin_dir_path( __FILE__ ) );
    define( 'LBB_IS_PRO', file_exists( dirname( __FILE__ ) . '/freemius/start.php' ) );
    // Create a helper function for easy SDK access.
    if ( !function_exists( 'lbb_fs' ) ) {
        function lbb_fs() {
            global $lbb_fs;
            if ( !isset( $lbb_fs ) ) {
                if ( LBB_IS_PRO ) {
                    require_once dirname( __FILE__ ) . '/freemius/start.php';
                } else {
                    require_once dirname( __FILE__ ) . '/freemius-lite/start.php';
                }
                $lbbConfig = array(
                    'id'                  => '13492',
                    'slug'                => 'lightbox-block',
                    'premium_slug'        => 'lightbox-block-pro',
                    'type'                => 'plugin',
                    'public_key'          => 'pk_8346b668170b2e4c33255d896d15c',
                    'is_premium'          => false,
                    'premium_suffix'      => 'Pro',
                    'has_premium_version' => true,
                    'has_addons'          => false,
                    'has_paid_plans'      => true,
                    'trial'               => array(
                        'days'               => 7,
                        'is_require_payment' => false,
                    ),
                    'menu'                => array(
                        'slug'       => 'edit.php?post_type=lbb',
                        'first-path' => 'edit.php?post_type=lbb&page=lightbox-block#/pricing',
                        'support'    => false,
                    ),
                );
                $lbb_fs = ( LBB_IS_PRO ? fs_dynamic_init( $lbbConfig ) : fs_lite_dynamic_init( $lbbConfig ) );
            }
            return $lbb_fs;
        }

        // Init Freemius.
        lbb_fs();
        // Signal that SDK was initiated.
        do_action( 'lbb_fs_loaded' );
        function lbbIsPremium() {
            return ( LBB_IS_PRO ? lbb_fs()->can_use_premium_code() : false );
        }

    }
    // Light Box
    if ( !class_exists( 'LBBPlugin' ) ) {
        class LBBPlugin {
            public function __construct() {
                $this->load_classes();
                add_action( 'init', [$this, 'onInit'] );
                add_action( 'enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets'] );
                add_action( 'enqueue_block_assets', [$this, 'enqueueBlockAssets'], 10 );
                add_action( 'wp_ajax_bpllb_get_image_id', [$this, 'bpllb_get_image_id'] );
                add_action( 'wp_enqueue_scripts', [$this, 'lbb_custom_popup'] );
                add_filter(
                    'plugin_action_links',
                    [$this, 'plugin_action_links'],
                    10,
                    2
                );
            }

            public function lbb_custom_popup() {
                wp_enqueue_script(
                    'lbb-custom-popup',
                    LBB_DIR_URL . 'build/custom-popup.js',
                    [],
                    LBB_PLUGIN_VERSION
                );
                wp_enqueue_style(
                    'lbb-custom-popup',
                    LBB_DIR_URL . 'build/custom-popup.css',
                    [],
                    LBB_PLUGIN_VERSION
                );
            }

            function onInit() {
                register_block_type( __DIR__ . '/build' );
            }

            public function enqueueBlockAssets() {
                wp_enqueue_style(
                    'fontAwesome',
                    LBB_ASSETS_DIR . 'css/font-awesome.min.css',
                    [],
                    '6.5.2'
                );
                // Icon
                wp_register_style(
                    'fancyapps-carousel',
                    LBB_ASSETS_DIR . 'css/carousel.css',
                    [],
                    '5.0'
                );
                wp_register_style(
                    'fancyapps-thum',
                    LBB_ASSETS_DIR . 'css/carousel-thum.css',
                    [],
                    '5.0'
                );
                wp_register_style(
                    'lbb-shortcode',
                    LBB_ASSETS_DIR . 'css/shortcode.css',
                    [],
                    LBB_PLUGIN_VERSION
                );
                wp_register_style(
                    'lbb-plyr-style',
                    LBB_ASSETS_DIR . 'css/plyr.min.css',
                    [],
                    LBB_PLUGIN_VERSION
                );
                wp_register_script(
                    'fancyapps-carousel',
                    LBB_ASSETS_DIR . 'js/carousel.js',
                    [],
                    '5.0'
                );
                wp_register_script(
                    'fancyapps-thum',
                    LBB_ASSETS_DIR . 'js/carousel-thum.js',
                    [],
                    '5.0'
                );
                wp_register_script(
                    'lbb-plyr-script',
                    LBB_ASSETS_DIR . 'js/plyr.min.js',
                    [],
                    LBB_PLUGIN_VERSION
                );
                wp_register_script(
                    'lbb-shortcode',
                    LBB_ASSETS_DIR . 'js/shortcode.js',
                    [],
                    LBB_PLUGIN_VERSION
                );
                wp_localize_script( 'lbb-plyr-script', 'bpllbMediaUrlId', [
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'bpllb_get_image_id' ),
                ] );
            }

            //Class loaded
            public function load_classes() {
                require_once plugin_dir_path( __FILE__ ) . '/inc/AdminMenu.php';
                require_once plugin_dir_path( __FILE__ ) . '/inc/custom-shortcode.php';
            }

            public function bpllb_get_image_id() {
                check_ajax_referer( "bpllb_get_image_id", "nonce" );
                $items = ( isset( $_GET['items'] ) ? wp_unslash( $_GET['items'] ) : '' );
                $decoded_items = json_decode( urldecode( $items ), true );
                if ( !is_array( $decoded_items ) ) {
                    wp_send_json_error( [
                        'message' => 'Invalid data',
                    ] );
                }
                $response = [];
                foreach ( $decoded_items as $item ) {
                    if ( $item['type'] == 'image' ) {
                        $content = ( isset( $item['content'] ) ? esc_url_raw( $item['content'] ) : '' );
                    } else {
                        $content = ( isset( $item['thumbnail'] ) ? esc_url_raw( $item['thumbnail'] ) : '' );
                    }
                    $attachment_id = attachment_url_to_postid( $content );
                    $item['id'] = $attachment_id;
                    $response[] = $item;
                }
                wp_send_json_success( $response );
            }

            public function enqueueBlockEditorAssets() {
                wp_add_inline_script( 'lbb-lightbox-editor-script', "const lbbpipecheck=" . wp_json_encode( lbbIsPremium() ) . ';', 'before' );
                wp_add_inline_script( 'lbb-lightbox-editor-script', "const freemiusFileCheck=" . wp_json_encode( LBB_IS_PRO ) . ';', 'before' );
            }

            public function plugin_action_links( $links, $file ) {
                if ( plugin_basename( __FILE__ ) == $file ) {
                    $dashboardLink = admin_url( 'edit.php?post_type=lbb&page=lightbox-block' );
                    $links['dashboard'] = sprintf(
                        '<a href="%s" style="%s" target="__blank">%s</a>',
                        $dashboardLink,
                        'color:#4527a4;font-weight:bold',
                        __( 'Dashboard!', 'slider' )
                    );
                }
                return $links;
            }

        }

        new LBBPlugin();
    }
}