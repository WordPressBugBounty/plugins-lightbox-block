<?php

/**
 * Plugin Name: Lightbox block
 * Description: Lightbox block is an excellent choice for your WordPress Lightbox Block.
 * Version: 1.1.32
 * Author: bPlugins
 * Author URI: http://bplugins.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: lightbox
 * @fs_free_only, bsdk_config.json, /inc/AdminMenu-free.php
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
    define( 'LBB_PLUGIN_VERSION', ( isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '1.1.32' ) );
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
                    'is_premium'          => true,
                    'premium_suffix'      => 'Pro',
                    'has_premium_version' => true,
                    'has_addons'          => false,
                    'has_paid_plans'      => true,
                    'trial'               => array(
                        'days'               => 7,
                        'is_require_payment' => false,
                    ),
                    'menu'                => ( LBB_IS_PRO ? array(
                        'slug'       => 'lightbox-block-dashboard',
                        'first-path' => 'admin.php?page=lightbox-block-dashboard#/pricing',
                        'support'    => false,
                    ) : array(
                        'slug'       => 'lightbox-block-dashboard',
                        'first-path' => 'tools.php?page=lightbox-block-dashboard#/pricing',
                        'support'    => false,
                        'parent'     => array(
                            'slug' => 'tools.php',
                        ),
                    ) ),
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
                add_action( 'enqueue_block_assets', [$this, 'enqueueBlockAssets'], 10 );
                add_action( 'wp_ajax_lbbPipeChecker', [$this, 'lbbPipeChecker'] );
                add_action( 'wp_ajax_nopriv_lbbPipeChecker', [$this, 'lbbPipeChecker'] );
                add_action( 'admin_init', [$this, 'registerSettings'] );
                add_action( 'rest_api_init', [$this, 'registerSettings'] );
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
            }

            //Class loaded
            public function load_classes() {
                if ( LBB_IS_PRO ) {
                    require_once plugin_dir_path( __FILE__ ) . '/inc/AdminMenu-pro.php';
                } else {
                    require_once plugin_dir_path( __FILE__ ) . '/inc/AdminMenu-free.php';
                }
                if ( LBB_IS_PRO && lbbIsPremium() ) {
                    require_once plugin_dir_path( __FILE__ ) . '/inc/custom-shortcode.php';
                }
            }

            public function lbbPipeChecker() {
                $nonce = $_POST['_wpnonce'];
                if ( !wp_verify_nonce( $nonce, 'wp_ajax' ) ) {
                    wp_send_json_error( 'Invalid Request' );
                }
                wp_send_json_success( [
                    'isPipe' => ( LBB_IS_PRO ? \lbb_fs()->is__premium_only() && \lbb_fs()->can_use_premium_code() : false ),
                ] );
            }

            public function registerSettings() {
                register_setting( 'lbbUtils', 'lbbUtils', [
                    'show_in_rest'      => [
                        'name'   => 'lbbUtils',
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    'type'              => 'string',
                    'default'           => wp_json_encode( [
                        'nonce' => wp_create_nonce( 'wp_ajax' ),
                    ] ),
                    'sanitize_callback' => 'sanitize_text_field',
                ] );
            }

        }

        new LBBPlugin();
    }
}