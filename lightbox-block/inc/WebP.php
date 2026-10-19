<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WebP copies for gallery images, made with WordPress' own image editor (GD / Imagick) — no external service.
 * Each JPG/PNG size gets a sibling "photo-1024x683.jpg.webp"; originals are never changed.
 * Missing copies are queued from render.php and converted in the background by WP-Cron.
 */
if ( ! class_exists( 'LBBWebP' ) ) {
    class LBBWebP {

        const QUEUE = 'lbb_webp_queue';
        const CRON = 'lbb_webp_convert';
        const META = '_lbb_webp';
        const MIMES = [ 'image/jpeg', 'image/png' ];

        public function __construct() {
            add_action( self::CRON, [ __CLASS__, 'runQueue' ] );
            add_action( 'delete_attachment', [ __CLASS__, 'deleteCopies' ] );
            add_action( 'enqueue_block_editor_assets', [ $this, 'editorData' ] );
            add_action( 'rest_api_init', [ $this, 'routes' ] );
        }

        // Block panel shows a warning when the server can't write WebP
        public function editorData() {
            wp_add_inline_script( 'lbb-lightbox-editor-script', 'window.lbbWebpSupported = ' . wp_json_encode( self::supported() ) . ';', 'before' );
        }

        // Bulk conversion for the Pro settings page
        public function routes() {
            $permission = function () {
                return current_user_can( 'manage_options' ) && function_exists( 'lbbIsPremium' ) && lbbIsPremium();
            };
            register_rest_route( 'lightbox-block/v1', '/webp', [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'status' ],
                'permission_callback' => $permission,
            ] );
            register_rest_route( 'lightbox-block/v1', '/webp/convert', [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'convertBatch' ],
                'permission_callback' => $permission,
            ] );
        }

        // Media Library images used in any Lightbox block (posts, pages, the lbb shortcode type…)
        public static function galleryIds() {
            $cached = get_transient( 'lbb_webp_gallery_ids' );
            if ( is_array( $cached ) ) {
                return $cached;
            }
            global $wpdb;
            $ids = [];
            // No core API searches post_content across every post type; the result is cached in a transient above.
            $posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%wp:lbb/lightbox%' AND post_type <> 'revision' AND post_status NOT IN ('trash', 'auto-draft')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $walk = function ( $blocks ) use ( &$walk, &$ids ) {
                foreach ( $blocks as $block ) {
                    if ( 'lbb/lightbox' === ( $block['blockName'] ?? '' ) ) {
                        foreach ( (array) ( $block['attrs']['items'] ?? [] ) as $item ) {
                            foreach ( [ $item['content'] ?? '', $item['thumbnail'] ?? '' ] as $url ) {
                                $id = is_string( $url ) && '' !== $url ? attachment_url_to_postid( $url ) : 0;
                                if ( $id ) {
                                    $ids[] = $id;
                                }
                            }
                        }
                    }
                    if ( ! empty( $block['innerBlocks'] ) ) {
                        $walk( $block['innerBlocks'] );
                    }
                }
            };
            foreach ( $posts as $postId ) {
                $walk( parse_blocks( get_post_field( 'post_content', $postId ) ) );
            }
            $ids = array_values( array_filter( array_unique( $ids ), function ( $id ) {
                return in_array( get_post_mime_type( $id ), self::MIMES, true );
            } ) );
            set_transient( 'lbb_webp_gallery_ids', $ids, 5 * MINUTE_IN_SECONDS );

            return $ids;
        }

        public static function status() {
            $ids = self::galleryIds();
            $converted = count( array_filter( $ids, [ __CLASS__, 'isConverted' ] ) );

            return [
                'supported' => self::supported(),
                'editor'    => self::supported() ? str_replace( 'WP_Image_Editor_', '', (string) _wp_image_editor_choose( [ 'mime_type' => 'image/webp' ] ) ) : '',
                'total'     => count( $ids ),
                'converted' => $converted,
            ];
        }

        // Converts gallery images for up to ~15s per request; the page keeps calling until all are done
        public static function convertBatch() {
            if ( ! self::supported() ) {
                return new WP_Error( 'lbb_webp_unsupported', __( 'This server cannot create WebP images.', 'lightbox-block' ), [ 'status' => 400 ] );
            }
            $start = microtime( true );
            foreach ( self::galleryIds() as $id ) {
                if ( microtime( true ) - $start > 15 ) {
                    break;
                }
                if ( ! self::isConverted( $id ) ) {
                    self::convert( $id );
                }
            }

            return self::status();
        }

        public static function supported() {
            return wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] );
        }

        public static function quality() {
            // WebP 75 looks about like WordPress' JPEG 82 at ~30% less weight
            $quality = class_exists( 'LBBLazyLoadSettings' ) ? ( LBBLazyLoadSettings::get()['webpQuality'] ?? 75 ) : 75;

            return min( 100, max( 40, (int) $quality ) );
        }

        // Absolute paths of the attachment's original + every generated size (JPG/PNG only)
        public static function files( $id ) {
            if ( ! in_array( get_post_mime_type( $id ), self::MIMES, true ) ) {
                return [];
            }
            $file = get_attached_file( $id );
            if ( ! $file ) {
                return [];
            }
            $dir = trailingslashit( dirname( $file ) );
            $files = [ $file ];
            $meta = wp_get_attachment_metadata( $id );
            foreach ( ( $meta['sizes'] ?? [] ) as $size ) {
                if ( ! empty( $size['file'] ) ) {
                    $files[] = $dir . $size['file'];
                }
            }

            return array_unique( $files );
        }

        public static function isConverted( $id ) {
            return (int) get_post_meta( $id, self::META, true ) === self::quality();
        }

        // Makes the .webp copies; a copy that is not smaller than its source is dropped (the JPG/PNG stays in use)
        public static function convert( $id ) {
            if ( ! self::supported() ) {
                return false;
            }
            $quality = self::quality();
            // set_quality() alone is ignored: WordPress resets quality when the output format changes
            $forceQuality = function () use ( $quality ) {
                return $quality;
            };
            add_filter( 'wp_editor_set_quality', $forceQuality, PHP_INT_MAX );

            foreach ( self::files( $id ) as $src ) {
                $dest = $src . '.webp';
                if ( ! file_exists( $src ) ) {
                    continue;
                }
                $editor = wp_get_image_editor( $src );
                if ( is_wp_error( $editor ) ) {
                    continue;
                }
                $saved = $editor->save( $dest, 'image/webp' );
                if ( ! is_wp_error( $saved ) && file_exists( $dest ) && filesize( $dest ) >= filesize( $src ) ) {
                    wp_delete_file( $dest );
                }
            }
            remove_filter( 'wp_editor_set_quality', $forceQuality, PHP_INT_MAX );
            update_post_meta( $id, self::META, $quality );

            return true;
        }

        public static function deleteCopies( $id ) {
            foreach ( self::files( $id ) as $src ) {
                if ( file_exists( $src . '.webp' ) ) {
                    wp_delete_file( $src . '.webp' );
                }
            }
            delete_post_meta( $id, self::META );
        }

        // Swaps a size URL for its .webp copy when that copy exists
        public static function url( $url ) {
            static $uploads = null;
            $uploads = $uploads ?? wp_get_upload_dir();
            $base = preg_replace( '#^https?:#i', '', $uploads['baseurl'] );
            $plain = preg_replace( '#^https?:#i', '', $url );
            if ( 0 !== strpos( $plain, $base ) ) {
                return $url;
            }
            $path = $uploads['basedir'] . substr( $plain, strlen( $base ) );

            return file_exists( $path . '.webp' ) ? $url . '.webp' : $url;
        }

        public static function srcset( $srcset ) {
            return implode( ', ', array_map( function ( $candidate ) {
                $parts = preg_split( '/\s+/', trim( $candidate ), 2 );
                return self::url( $parts[0] ) . ( isset( $parts[1] ) ? ' ' . $parts[1] : '' );
            }, array_filter( array_map( 'trim', explode( ',', $srcset ) ) ) ) );
        }

        public static function queue( $ids ) {
            $queue = array_values( array_unique( array_merge( (array) get_option( self::QUEUE, [] ), array_map( 'intval', (array) $ids ) ) ) );
            update_option( self::QUEUE, $queue, false );
            if ( ! wp_next_scheduled( self::CRON ) ) {
                wp_schedule_single_event( time() + 5, self::CRON );
            }
        }

        // Background batch: converts for up to ~20s, then reschedules itself for the rest
        public static function runQueue() {
            $queue = (array) get_option( self::QUEUE, [] );
            $start = microtime( true );
            while ( $queue && microtime( true ) - $start < 20 ) {
                $id = (int) array_shift( $queue );
                if ( $id && ! self::isConverted( $id ) ) {
                    self::convert( $id );
                }
            }
            update_option( self::QUEUE, $queue, false );
            if ( $queue ) {
                wp_schedule_single_event( time() + 10, self::CRON );
            }
        }
    }
    new LBBWebP();
}
