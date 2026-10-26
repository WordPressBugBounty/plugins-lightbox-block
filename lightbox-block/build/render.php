<?php
    if ( ! defined( 'ABSPATH' ) ) exit;
    $id = wp_unique_id( 'lbbLightBox-' );
    extract($attributes);

    $lightboxType = $attributes['lightboxType'] ?? '';
    $items        = $attributes['items'] ?? []; 
     
    if ($attributes['layout'] == 'slider') {
        wp_enqueue_script('fancyapps-carousel');
        wp_enqueue_script('fancyapps-thum');
        wp_enqueue_style('fancyapps-carousel');
        wp_enqueue_style('fancyapps-thum');
    }

    $contentBlock = [];

    foreach ($items as $index => $item) {
        if ($item['type'] === 'content' || $lightboxType === "content") {
            $blocks = parse_blocks($item['content']);
            $content = '';
            foreach ($blocks as $block) {
                $content .= render_block($block);
            }
            $contentBlock[$index] = $content;
        }
    }

    // Item URLs come from post content (Contributor+), so only http(s) or relative URLs reach view.js
    foreach ( $items as $index => $item ) {
        $type = in_array( $lightboxType, [ 'gallery', 'button' ], true ) ? ( $item['type'] ?? '' ) : $lightboxType;
        if ( in_array( $type, [ 'image', 'video', 'youtube', 'audio', 'pdf', 'iframe' ], true ) && isset( $item['content'] ) ) {
            $items[ $index ]['content'] = esc_url_raw( (string) $item['content'], [ 'http', 'https' ] );
        }
        if ( isset( $item['thumbnail'] ) ) {
            $items[ $index ]['thumbnail'] = esc_url_raw( (string) $item['thumbnail'], [ 'http', 'https' ] );
        }
    }
    $attributes['items'] = $items;

    // Responsive images map: grid thumbnail URL => smaller sizes + srcset, read by view.js
    $lazySettings = wp_parse_args( is_array( $lazyLoad ?? null ) ? $lazyLoad : [], [ 'enabled' => true, 'thumbSize' => 'large' ] );
    $lazyEnabled = (bool) apply_filters( 'lbb_lazy_load_enabled', rest_sanitize_boolean( $lazySettings['enabled'] ), $attributes );
    $thumbSize = in_array( $lazySettings['thumbSize'], array_merge( get_intermediate_image_sizes(), [ 'full' ] ), true ) ? $lazySettings['thumbSize'] : 'large';
    $responsive = [ 'lazy' => $lazyEnabled, 'images' => [] ];
    $useWebp = ! empty( $lazySettings['webp'] ) && class_exists( 'LBBWebP' ) && LBBWebP::supported();
    $webpQueue = [];

    if ( 'button' !== $lightboxType ) {
        $cols = wp_parse_args( is_array( $columns ?? null ) ? $columns : [], [ 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ] );
        $vw = function ( $count ) {
            return (int) ceil( 100 / max( 1, (int) $count ) ) . 'vw';
        };
        $sizes = 'slider' === $layout ? '100vw' : sprintf( '(max-width: 576px) %s, (max-width: 768px) %s, %s', $vw( $cols['mobile'] ), $vw( $cols['tablet'] ), $vw( $cols['desktop'] ) );
        $eagerCount = 'slider' === $layout ? 1 : max( 1, (int) $cols['desktop'] );
        // Default Large is too small for full-width galleries (slider, one column): their srcset may go up to the original
        $srcsetCap = ( 'large' === $thumbSize && ( 'slider' === $layout || 1 === (int) $cols['desktop'] ) ) ? 'full' : $thumbSize;
        $stripScheme = function ( $url ) {
            return preg_replace( '#^https?:#i', '', (string) $url );
        };

        foreach ( $items as $index => $item ) {
            $type = in_array( $lightboxType, [ 'gallery', 'button' ], true ) ? ( $item['type'] ?? '' ) : $lightboxType;
            $url = 'image' === $type ? ( $item['content'] ?? '' ) : ( $item['thumbnail'] ?? '' );

            if ( ! is_string( $url ) || '' === $url ) {
                continue;
            }

            if ( isset( $responsive['images'][ $url ] ) ) {
                $responsive['images'][ $url ]['eager'] = $responsive['images'][ $url ]['eager'] || $index < $eagerCount;
                // Same file seen first as another item's thumbnail: an image item still needs its WebP lightbox copy
                if ( 'image' === $type && $useWebp && empty( $responsive['images'][ $url ]['full'] ) && LBBWebP::url( $url ) !== $url ) {
                    $responsive['images'][ $url ]['full'] = LBBWebP::url( $url );
                }
                continue;
            }

            // Saved item id can be stale when the image URL was changed later, so verify it
            $attachmentId = ! empty( $item['id'] ) ? (int) $item['id'] : 0;
            if ( ! $attachmentId || $stripScheme( wp_get_attachment_url( $attachmentId ) ) !== $stripScheme( $url ) ) {
                $attachmentId = attachment_url_to_postid( $url );
            }

            if ( ! $attachmentId || ! wp_attachment_is_image( $attachmentId ) ) {
                continue;
            }

            $main = wp_get_attachment_image_src( $attachmentId, $thumbSize );
            if ( ! $main ) {
                continue;
            }
            $thumb = wp_get_attachment_image_src( $attachmentId, 'thumbnail' );

            // Chosen size is the largest candidate (WP's srcset otherwise goes up to 2048px)
            $srcset = (string) wp_get_attachment_image_srcset( $attachmentId, $thumbSize );
            if ( $srcset && 'full' !== $srcsetCap ) {
                $srcset = implode( ', ', array_filter( array_map( 'trim', explode( ',', $srcset ) ), function ( $candidate ) use ( $main ) {
                    return preg_match( '/\s(\d+)w$/', $candidate, $w ) && (int) $w[1] <= (int) $main[1];
                } ) );
            }

            $srcset = false !== strpos( $srcset, ',' ) ? $srcset : '';
            $thumbUrl = $thumb ? $thumb[0] : $main[0];
            $mainUrl = $main[0];
            $fullUrl = '';

            // WebP copies once converted; until then the JPG/PNG is used and the image is queued for conversion
            if ( $useWebp ) {
                if ( LBBWebP::isConverted( $attachmentId ) ) {
                    $mainUrl = LBBWebP::url( $mainUrl );
                    $thumbUrl = LBBWebP::url( $thumbUrl );
                    $srcset = $srcset ? LBBWebP::srcset( $srcset ) : '';
                    // Image items open this file in the lightbox, so it gets the WebP copy too
                    if ( 'image' === $type && LBBWebP::url( $url ) !== $url ) {
                        $fullUrl = LBBWebP::url( $url );
                    }
                } else {
                    $webpQueue[] = $attachmentId;
                }
            }

            $responsive['images'][ $url ] = [
                'src'    => $mainUrl,
                'srcset' => $srcset,
                'sizes'  => $sizes,
                'width'  => (int) $main[1],
                'height' => (int) $main[2],
                'thumb'  => $thumbUrl,
                'eager'  => $index < $eagerCount,
            ];
            if ( $fullUrl ) {
                $responsive['images'][ $url ]['full'] = $fullUrl;
            }
        }
    }

    if ( $webpQueue ) {
        LBBWebP::queue( $webpQueue );
    }
    ?>

    <div 
    <?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>
        id="<?php echo esc_attr( $id ); ?>"
        data-attributes='<?php echo esc_attr(wp_json_encode($attributes)); ?>' data-content-indexs="<?php echo esc_attr(wp_json_encode(array_keys($contentBlock))) ?>" data-responsive='<?php echo esc_attr( wp_json_encode( $responsive ) ); ?>'></div>

        <?php foreach ($contentBlock as $index => $block) {?>
            <div class="lbbItemContent" id="<?php echo esc_attr($id . '-content-' . $index) ?>">
                <?php echo wp_kses_post($block); ?>
            </div>
        <?php }?>

             
         