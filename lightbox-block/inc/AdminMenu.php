<?php
if (!defined('ABSPATH')) {exit;}
if(!class_exists('LBB_Lightbox_Admin_Menu')) {
    class LBB_Lightbox_Admin_Menu{

        public function __construct(){
            add_action( 'admin_enqueue_scripts', [$this, 'adminEnqueueScripts'] );
            add_action('admin_menu', [$this, 'adminMenu']);
            add_filter( 'default_content', [ $this, 'starterContent' ], 10, 2 );
            add_filter( 'default_title', [ $this, 'starterTitle' ], 10, 2 );
        }

        // "Try it in a new page" (Settings → Lazy Load): new page opens with a Lightbox gallery block
        private function isStarter( $post ) {
            return isset( $_GET['lbb_starter'] ) && 'page' === ( $post->post_type ?? '' ) && current_user_can( 'edit_pages' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only fills a new draft
        }

        public function starterContent( $content, $post ) {
            return $this->isStarter( $post ) && '' === $content ? '<!-- wp:lbb/lightbox {"lightboxType":"gallery"} /-->' : $content;
        }

        public function starterTitle( $title, $post ) {
            return $this->isStarter( $post ) && '' === $title ? __( 'Lightbox Gallery', 'lightbox-block' ) : $title;
        }

        public function adminMenu(){

            $menuIcon = "<svg xmlns='http://www.w3.org/2000/svg' width='32px' height='32px' viewBox='0 0 32 32'>
            <path fill='#fff' d='M28,4H10A2.0059,2.0059,0,0,0,8,6V20a2.0059,2.0059,0,0,0,2,2H28a2.0059,2.0059,0,0,0,2-2V6A2.0059,2.0059,0,0,0,28,4Zm0,16H10V6H28Z' /><path fill='#fff' d='M18,26H4V16H6V14H4a2.0059,2.0059,0,0,0-2,2V26a2.0059,2.0059,0,0,0,2,2H18a2.0059,2.0059,0,0,0,2-2V24H18Z' /></svg>";
            
                // Settings page: explains the Pro-only site-wide settings (no settings here)
                add_submenu_page(
                    'edit.php?post_type=lbb',
                    __('Settings', 'lightbox-block'),
                    __('Settings', 'lightbox-block'),
                    'manage_options',
                    'lightbox-block-settings',
                    [$this, 'settingsPage']
                );

                add_submenu_page(
                    'edit.php?post_type=lbb',
                    __('Demo & Help', 'lightbox-block'),
                    __('Demo & Help', 'lightbox-block'),
                    'manage_options',
                    'lightbox-block',
                    [$this, 'helpPage']
                );
        }

        function adminEnqueueScripts( $hook ) {

            if( strpos( $hook, 'lightbox-block' ) ){
                wp_enqueue_style( 'lbb-admin-dashboard', LBB_LIGHTBOX_DIR_URL . 'build/admin-dashboard.css', [], LBB_LIGHTBOX_PLUGIN_VERSION );
                $asset = include LBB_LIGHTBOX_DIR_PATH . 'build/admin-dashboard.asset.php';
                $deps = array_unique( array_merge( $asset['dependencies'] ?? [], [ 'react', 'react-dom', 'wp-data', 'wp-api', 'wp-util', 'wp-i18n' ] ) );
                wp_enqueue_script( 'lbb-admin-dashboard', LBB_LIGHTBOX_DIR_URL . 'build/admin-dashboard.js', $deps, $asset['version'] ?? LBB_LIGHTBOX_PLUGIN_VERSION, true );
                wp_set_script_translations( 'lbb-admin-dashboard', 'lightbox-block', LBB_LIGHTBOX_DIR_PATH . 'languages' );
            }

            $screen = get_current_screen();
            if ( $screen && $screen->post_type === 'lbb' ) {
                wp_enqueue_style( 'lbb-shortcode', LBB_LIGHTBOX_ASSETS_DIR . 'css/shortcode.css', [], LBB_LIGHTBOX_PLUGIN_VERSION );
                wp_enqueue_script( 'lbb-shortcode', LBB_LIGHTBOX_ASSETS_DIR . 'js/shortcode.js', [], LBB_LIGHTBOX_PLUGIN_VERSION, true );
            }
	    }

        public function settingsPage() {
            $this->helpPage( 'settings/lazy-load' );
        }

        public function helpPage( $initialRoute = '' )
        {?>
            <div
                id='lbbDashboard'
                data-info='<?php echo esc_attr( wp_json_encode( [
                    'initialRoute' => is_string( $initialRoute ) ? $initialRoute : '',
                    'version' => LBB_LIGHTBOX_PLUGIN_VERSION,
                    'adminUrl' => admin_url(),
                ] ) ); ?>'
            >
            </div>
        <?php } 
    }
    new LBB_Lightbox_Admin_Menu();
}