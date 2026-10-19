<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Detects other lazy-load plugins, so the editor and the Lazy Load page can warn about them.
 * Our gallery images carry `skip-lazy` + `data-no-lazy`; `honors` says whether the plugin is known to skip them.
 */
if ( ! class_exists( 'LBBLazyConflicts' ) ) {
    class LBBLazyConflicts {

        public function __construct() {
            add_action( 'enqueue_block_editor_assets', [ $this, 'editorData' ] );
        }

        // lazy: true = its lazy load is on, false = installed but off, null = active, setting unknown
        private static function plugins() {
            $option = function ( $name, $key = null ) {
                $value = get_option( $name, null );
                if ( null === $value ) {
                    return null;
                }
                return null === $key ? (bool) $value : ( is_array( $value ) && ! empty( $value[ $key ] ) );
            };

            return [
                [ 'name' => 'WP Rocket', 'files' => [ 'wp-rocket/wp-rocket.php' ], 'honors' => true, 'lazy' => function () use ( $option ) { return $option( 'wp_rocket_settings', 'lazyload' ); } ],
                [ 'name' => 'LiteSpeed Cache', 'files' => [ 'litespeed-cache/litespeed-cache.php' ], 'honors' => true, 'lazy' => function () use ( $option ) { return $option( 'litespeed.conf.media-lazy' ); } ],
                [ 'name' => 'Smush', 'files' => [ 'wp-smushit/wp-smush.php', 'wp-smush-pro/wp-smush.php' ], 'honors' => true, 'lazy' => function () use ( $option ) { return $option( 'wp-smush-settings', 'lazy_load' ); } ],
                [ 'name' => 'Jetpack', 'files' => [ 'jetpack/jetpack.php' ], 'honors' => true, 'lazy' => function () { return in_array( 'lazy-images', (array) get_option( 'jetpack_active_modules', [] ), true ); } ],
                [ 'name' => 'SiteGround Optimizer', 'files' => [ 'sg-cachepress/sg-cachepress.php' ], 'honors' => null, 'lazy' => function () use ( $option ) { return $option( 'siteground_optimizer_lazyload_images' ); } ],
                [ 'name' => 'a3 Lazy Load', 'files' => [ 'a3-lazy-load/a3-lazy-load.php' ], 'honors' => null, 'lazy' => '__return_true' ],
                [ 'name' => 'Lazy Load by WP Rocket', 'files' => [ 'rocket-lazy-load/rocket-lazy-load.php' ], 'honors' => true, 'lazy' => '__return_true' ],
                [ 'name' => 'Autoptimize', 'files' => [ 'autoptimize/autoptimize.php' ], 'honors' => null, 'lazy' => function () use ( $option ) { return $option( 'autoptimize_imgopt_settings', 'autoptimize_imgopt_checkbox_field_3' ); } ],
                [ 'name' => 'Perfmatters', 'files' => [ 'perfmatters/perfmatters.php' ], 'honors' => null, 'lazy' => function () { $o = get_option( 'perfmatters_options', null ); return null === $o ? null : ! empty( $o['lazyload']['lazy_loading'] ); } ],
                [ 'name' => 'EWWW Image Optimizer', 'files' => [ 'ewww-image-optimizer/ewww-image-optimizer.php' ], 'honors' => null, 'lazy' => function () use ( $option ) { return $option( 'ewww_image_optimizer_lazy_load' ); } ],
                [ 'name' => 'Optimole', 'files' => [ 'optimole-wp/optimole-wp.php' ], 'honors' => null, 'lazy' => function () { $o = get_option( 'optml_settings', null ); return null === $o ? null : 'enabled' === ( $o['lazyload'] ?? '' ); } ],
            ];
        }

        // Active lazy-load plugins: [ [ 'name', 'lazy' => true|null, 'honors' => true|null ] ]; plugins with lazy load off are left out
        public static function detect() {
            $active = (array) get_option( 'active_plugins', [] );
            if ( is_multisite() ) {
                $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
            }
            $found = [];
            foreach ( self::plugins() as $plugin ) {
                if ( ! array_intersect( $plugin['files'], $active ) ) {
                    continue;
                }
                $lazy = call_user_func( $plugin['lazy'] );
                if ( false === $lazy ) {
                    continue;
                }
                $found[] = [ 'name' => $plugin['name'], 'lazy' => $lazy, 'honors' => $plugin['honors'] ];
            }

            return $found;
        }

        public function editorData() {
            wp_add_inline_script( 'lbb-lightbox-editor-script', 'window.lbbLazyConflicts = ' . wp_json_encode( self::detect() ) . ';', 'before' );
        }
    }
    new LBBLazyConflicts();
}
