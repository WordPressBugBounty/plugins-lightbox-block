<?php
if(!class_exists('LBBBlock')) { 
    class LBBBlock
    {
        public function __construct()
        {
            add_action('enqueue_block_assets', [$this, 'enqueueBlockAssets'], 10);
            add_action('init', [$this, 'onInit']);
        }

        public function enqueueBlockAssets()
        {
            wp_enqueue_style( 'fontAwesome', LBB_ASSETS_DIR . 'css/font-awesome.min.css', [], '6.5.2' ); // Icon
            
            wp_register_style('fancyapps-carousel', LBB_ASSETS_DIR . 'css/carousel.css', [], '5.0');
            wp_register_style('fancyapps-thum', LBB_ASSETS_DIR . 'css/carousel-thum.css', [], '5.0');

            wp_register_style('lbb-plyr-style', LBB_ASSETS_DIR . 'css/plyr.min.css', [], LBB_PLUGIN_VERSION);

            wp_register_script('fancyapps-carousel', LBB_ASSETS_DIR . 'js/carousel.js', [], '5.0');
            wp_register_script('fancyapps-thum', LBB_ASSETS_DIR . 'js/carousel-thum.js', [], '5.0');

            wp_register_script('lbb-plyr-script', LBB_ASSETS_DIR . 'js/plyr.min.js', [], LBB_PLUGIN_VERSION);

            wp_register_script('lbb-script', LBB_DIR_URL . 'dist/script.js', [ 'wp-util', 'react', 'react-dom', 'lbb-plyr-script',], LBB_PLUGIN_VERSION);

            wp_register_style('lbb-style', LBB_DIR_URL . 'dist/style.css', [ 'fontAwesome', 'lbb-plyr-style'], LBB_PLUGIN_VERSION);
        }

        public function onInit()
        {
            wp_register_style('lbb-lightbox-editor-style', LBB_DIR_URL . 'dist/editor.css', ['wp-edit-blocks', 'lbb-style', 'fancyapps-carousel', 'fancyapps-thum'], LBB_PLUGIN_VERSION); // Backend Style

            register_block_type(__DIR__, [
                'editor_style' => 'lbb-lightbox-editor-style',
                'render_callback' => [$this, 'render'],
            ]); // Register Block

            wp_set_script_translations('lbb-lightbox-editor-script', 'lightbox', plugin_dir_path(__FILE__) . 'languages'); // Translate

        }

        public function render($attributes)
        {
            extract($attributes);

            $className = $className ?? '';
            $lbbBlockClassName = 'wp-block-lbb-lightbox ' . $className . ' align' . $align;
            if ($layout == 'slider') {
                wp_enqueue_script('fancyapps-carousel');
                wp_enqueue_script('fancyapps-thum');
                wp_enqueue_style('fancyapps-carousel');
                wp_enqueue_style('fancyapps-thum');
            }

            wp_enqueue_style('lbb-style');
            wp_enqueue_script('lbb-script');

            ob_start();

            $contentBlock = [];

            foreach ($attributes['items'] as $index => $item) {
                if ($item['type'] === 'content') {
                    $blocks = parse_blocks($item['content']);
                    $content = '';
                    foreach ($blocks as $block) {
                        $content .= render_block($block);
                    }
                    $contentBlock[$index] = $content;
                }
            }
            ?>

            <div class='<?php echo esc_attr($lbbBlockClassName); ?>' id='lbbLightBox-<?php echo esc_attr($cId) ?>' data-attributes='<?php echo esc_attr(wp_json_encode($attributes)); ?>' data-content-indexs="<?php echo esc_attr(wp_json_encode(array_keys($contentBlock))) ?>" data-nonce='<?php echo esc_attr(wp_json_encode(wp_create_nonce('wp_ajax'))); ?>'></div>

                <?php foreach ($contentBlock as $index => $block) {?>
                    <div class="lbbItemContent" id="content-<?php echo esc_attr($cId . '-' . $index) ?>">
                        <?php echo wp_kses_post($block); ?>
                    </div>
                <?php }?>

            <?php return ob_get_clean();
        } // Render
    }
    new LBBBlock();
}