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
    ?>

    <div 
    <?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>
        id="<?php echo esc_attr( $id ); ?>"
        data-attributes='<?php echo esc_attr(wp_json_encode($attributes)); ?>' data-content-indexs="<?php echo esc_attr(wp_json_encode(array_keys($contentBlock))) ?>"></div>

        <?php foreach ($contentBlock as $index => $block) {?>
            <div class="lbbItemContent" id="<?php echo esc_attr($id . '-content-' . $index) ?>">
                <?php echo wp_kses_post($block); ?>
            </div>
        <?php }?>

             
         