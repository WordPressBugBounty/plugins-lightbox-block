<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Auto Lightbox (Lightbox → Settings → Auto Lightbox, Free).
 * Opens core Image and Gallery block images in our lightbox site-wide, old posts included, without inserting our block.
 * Images added with the Classic editor (Classic block, classic posts, inline in a paragraph) count as Image blocks.
 */
if ( ! class_exists( 'LBBAutoLightbox' ) ) {
    class LBBAutoLightbox {

        const OPTION  = 'lbb_auto_lightbox';
        const HANDLE  = 'lbb-auto-lightbox';
        const KEY     = '__lbbAuto';          // per-block decision, set in render_block_data and read when the block renders
        const PENDING = 'lbb-auto-pending';   // gallery images wait for the gallery to give them one group
        const IMAGE_EXT = '/\.(jpe?g|png|gif|webp|avif|bmp|svg)$/i';

        // > 0 while our own block renders its "content" items (src/render.php), so their images are left alone
        public static $paused = 0;

        private static $group = 0;

        private static $settings = [];

        public function __construct() {
            add_action( 'init', [ $this, 'register' ] );
            add_action( 'init', [ $this, 'hook' ], 20 );
        }

        public static function defaults() {
            return [
                'enabled' => false, // off by default: an update never changes a live site on its own
                'image'   => true,
                'gallery' => true,
                'wooDesc' => true,  // WooCommerce product description images (Free)
            ];
        }

        public static function get() {
            return self::sanitize( get_option( self::OPTION, [] ) );
        }

        public static function sanitize( $value ) {
            $value = wp_parse_args( is_array( $value ) ? $value : [], self::defaults() );

            return [
                'enabled' => rest_sanitize_boolean( $value['enabled'] ),
                'image'   => rest_sanitize_boolean( $value['image'] ),
                'gallery' => rest_sanitize_boolean( $value['gallery'] ),
                'wooDesc' => rest_sanitize_boolean( $value['wooDesc'] ),
            ];
        }

        // Saved through /wp/v2/settings from the dashboard page (manage_options)
        public function register() {
            register_setting( 'lbb_settings', self::OPTION, [
                'type'              => 'object',
                'default'           => self::defaults(),
                'sanitize_callback' => [ __CLASS__, 'sanitize' ],
                'show_in_rest'      => [
                    'schema' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'properties'           => [
                            'enabled' => [ 'type' => 'boolean' ],
                            'image'   => [ 'type' => 'boolean' ],
                            'gallery' => [ 'type' => 'boolean' ],
                            'wooDesc' => [ 'type' => 'boolean' ],
                        ],
                    ],
                ],
            ] );
        }

        // Nothing is hooked while the feature is off
        public function hook() {
            $settings = self::$settings = self::get();
            if ( ! $settings['enabled'] || ( ! $settings['image'] && ! $settings['gallery'] && ! $settings['wooDesc'] ) || is_admin() ) {
                return;
            }
            add_filter( 'render_block_data', [ $this, 'decide' ], 10, 3 );
            add_filter( 'render_block_core/image', [ $this, 'renderImage' ], 20, 2 );
            add_filter( 'render_block_core/gallery', [ $this, 'renderGallery' ], 20, 2 );
            if ( $settings['image'] || $settings['wooDesc'] ) {
                add_filter( 'render_block', [ $this, 'renderClassic' ], 20, 2 );
            }
        }

        /**
         * Decides per block, before it renders, whether we take it over: 'single' / 'gallery' on core/image, 'yes' on core/gallery.
         * Taken-over images get core's own "Expand on click" turned off, so two lightboxes never open.
         */
        public function decide( $parsed, $source = null, $parent = null ) {
            $name = $parsed['blockName'] ?? '';
            if ( 'core/image' !== $name && 'core/gallery' !== $name ) {
                return $parsed;
            }
            unset( $parsed['attrs'][ self::KEY ] ); // never trust a value stored in post content

            if ( self::$paused || is_feed() || self::excluded( $parsed['attrs'] ?? [] ) ) {
                return $parsed;
            }

            if ( 'core/gallery' === $name ) {
                if ( self::on( 'gallery' ) ) {
                    $parsed['attrs'][ self::KEY ] = 'yes';
                }
                return $parsed;
            }

            $inGallery = $parent instanceof WP_Block && 'core/gallery' === $parent->name;
            if ( $inGallery ) {
                $mode = empty( $parent->parsed_block['attrs'][ self::KEY ] ) ? '' : 'gallery';
            } else {
                $mode = self::on( 'image' ) ? 'single' : '';
            }
            if ( $mode ) {
                $parsed['attrs'][ self::KEY ] = $mode;
                $parsed['attrs']['lightbox'] = [ 'enabled' => false ];
            }

            return $parsed;
        }

        public function renderImage( $html, $block ) {
            $mode = $block['attrs'][ self::KEY ] ?? '';
            if ( ! $mode || '' === trim( $html ) ) {
                return $html;
            }
            $group = 'gallery' === $mode ? self::PENDING : 'lbb-auto-' . ( ++self::$group );

            return self::tag( $html, $group );
        }

        /**
         * Classic editor images: a classic post or Classic block (no block name / core/freeform), or an image inline in a paragraph.
         * Each image opens on its own, like an Image block. Only Media Library images (wp-image-{id}) and links to image files.
         */
        public function renderClassic( $html, $block ) {
            $name = $block['blockName'] ?? null;
            if ( null !== $name && 'core/freeform' !== $name && 'core/paragraph' !== $name ) {
                return $html;
            }
            if ( self::$paused || is_feed() || ! self::on( 'image' ) || ! is_string( $html ) || ( false === stripos( $html, '<img' ) && false === stripos( $html, '<a' ) ) ) {
                return $html;
            }

            return self::tag( $html, null, true );
        }

        // Gives all images of one gallery one group, so Next/Prev walks the gallery
        public function renderGallery( $html, $block ) {
            if ( empty( $block['attrs'][ self::KEY ] ) ) {
                return $html;
            }
            $group = 'lbb-auto-gallery-' . ( ++self::$group );

            // Galleries saved before WP 5.9 have no inner image blocks: their <img>/<a> are tagged here
            if ( empty( $block['innerBlocks'] ) ) {
                return self::tag( $html, $group );
            }

            $p = new WP_HTML_Tag_Processor( $html );
            while ( $p->next_tag() ) {
                if ( self::PENDING === $p->get_attribute( 'data-fancybox' ) ) {
                    $p->set_attribute( 'data-fancybox', $group );
                }
            }

            return $p->get_updated_html();
        }

        /**
         * Adds data-fancybox to what opens the image:
         * - <a> linking to an image file → the link (lightbox shows its href)
         * - <img> with no link → the image, full-size URL in data-lbb-src (not data-src, which lazy-load plugins use)
         * Images inside any other link (attachment page, custom URL) keep their link.
         * $group null: each image its own group. $classic: an unlinked <img> must be a Media Library image (not an icon, emoji or pixel).
         */
        private static function tag( $html, $group, $classic = false ) {
            $next = function () use ( $group ) {
                return $group ?? 'lbb-auto-' . ( ++self::$group );
            };
            $p = new WP_HTML_Tag_Processor( $html );
            $link = null; // null = not inside <a>, true = image-file link, false = other link
            $found = false;

            while ( $p->next_tag( [ 'tag_closers' => 'visit' ] ) ) {
                $tag = $p->get_tag();

                if ( 'A' === $tag ) {
                    if ( $p->is_tag_closer() ) {
                        $link = null;
                        continue;
                    }
                    $link = self::isImageUrl( $p->get_attribute( 'href' ) ) && ! ( $classic && self::hasNoLightbox( $p ) );
                    if ( $link && null === $p->get_attribute( 'data-fancybox' ) ) {
                        $p->set_attribute( 'data-fancybox', $next() );
                        $found = true;
                    }
                    continue;
                }

                if ( 'IMG' !== $tag || null !== $link || null !== $p->get_attribute( 'data-fancybox' ) ) {
                    continue;
                }
                if ( $classic && ( ! preg_match( '/\bwp-image-\d+\b/', (string) $p->get_attribute( 'class' ) ) || self::hasNoLightbox( $p ) ) ) {
                    continue;
                }
                $src = self::fullUrl( $p );
                if ( $src ) {
                    $p->set_attribute( 'data-fancybox', $next() );
                    $p->set_attribute( 'data-lbb-src', $src );
                    $found = true;
                }
            }

            if ( $found ) {
                self::enqueue();
            }

            return $p->get_updated_html();
        }

        // Full-size URL of an <img>: legacy gallery data-full-url, then the attachment (wp-image-{id}), then its own src
        private static function fullUrl( WP_HTML_Tag_Processor $p ) {
            $url = $p->get_attribute( 'data-full-url' );

            if ( ! is_string( $url ) || '' === $url ) {
                $url = '';
                $class = (string) $p->get_attribute( 'class' );
                if ( preg_match( '/\bwp-image-(\d+)\b/', $class, $m ) ) {
                    $url = (string) wp_get_attachment_image_url( (int) $m[1], 'full' );
                }
            }
            if ( '' === $url ) {
                $url = (string) $p->get_attribute( 'src' );
            }

            return esc_url_raw( $url, [ 'http', 'https' ] );
        }

        private static function isImageUrl( $href ) {
            if ( ! is_string( $href ) || '' === $href ) {
                return false;
            }
            $path = (string) wp_parse_url( $href, PHP_URL_PATH );

            return (bool) preg_match( self::IMAGE_EXT, $path );
        }

        // "Post Images" / "Post Galleries" decide for posts and pages; on a WooCommerce product, "Product Description Images" decides both
        private static function on( $key ) {
            return ! empty( self::$settings[ 'product' === get_post_type() ? 'wooDesc' : $key ] );
        }

        // "no-lightbox" class on a classic <img> or <a>
        private static function hasNoLightbox( WP_HTML_Tag_Processor $p ) {
            return true === $p->has_class( 'no-lightbox' );
        }

        // "no-lightbox" class on the image or gallery (Block → Advanced → Additional CSS class)
        private static function excluded( $attrs ) {
            $class = is_string( $attrs['className'] ?? null ) ? $attrs['className'] : '';

            return in_array( 'no-lightbox', preg_split( '/\s+/', $class ), true );
        }

        // Loaded only on pages that have a tagged image
        private static function enqueue() {
            if ( wp_script_is( self::HANDLE, 'enqueued' ) ) {
                return;
            }
            $dir = plugin_dir_path( __DIR__ ) . 'build/';
            if ( ! file_exists( $dir . 'auto-lightbox.asset.php' ) ) {
                return;
            }
            $asset = include $dir . 'auto-lightbox.asset.php';
            $url = plugin_dir_url( __DIR__ ) . 'build/';

            wp_enqueue_script( self::HANDLE, $url . 'auto-lightbox.js', $asset['dependencies'], $asset['version'], [ 'in_footer' => true, 'strategy' => 'defer' ] );
            // Screen-reader labels for images made keyboard-openable
            wp_add_inline_script( self::HANDLE, 'window.lbbAutoLightboxL10n = ' . wp_json_encode( [
                'open'      => __( 'Open image in lightbox', 'lightbox-block' ),
                /* translators: %s: image alt text */
                'openNamed' => __( '%s, open in lightbox', 'lightbox-block' ),
            ] ) . ';', 'before' );
            if ( file_exists( $dir . 'auto-lightbox.css' ) ) {
                wp_enqueue_style( self::HANDLE, $url . 'auto-lightbox.css', [], $asset['version'] );
            }
        }
    }
    new LBBAutoLightbox();
}
