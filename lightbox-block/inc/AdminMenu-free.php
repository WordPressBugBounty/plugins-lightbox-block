<?php
if (!defined('ABSPATH')) {exit;}
if(!class_exists('llbAdminMenu')) {
    class llbAdminMenu{

        public function __construct(){
            add_action( 'admin_enqueue_scripts', [$this, 'adminEnqueueScripts'] );
            add_action('admin_menu', [$this, 'adminMenu']);
        }

        public function adminMenu(){

            $menuIcon = "<svg xmlns='http://www.w3.org/2000/svg' width='32px' height='32px' viewBox='0 0 32 32'>
            <path fill='#fff' d='M28,4H10A2.0059,2.0059,0,0,0,8,6V20a2.0059,2.0059,0,0,0,2,2H28a2.0059,2.0059,0,0,0,2-2V6A2.0059,2.0059,0,0,0,28,4Zm0,16H10V6H28Z' /><path fill='#fff' d='M18,26H4V16H6V14H4a2.0059,2.0059,0,0,0-2,2V26a2.0059,2.0059,0,0,0,2,2H18a2.0059,2.0059,0,0,0,2-2V24H18Z' /></svg>";
             
            add_submenu_page(
                'tools.php',
                __('Lightbox Block', 'lightbox'),
                __('Lightbox Block', 'lightbox'),
                'manage_options',
                'lightbox-block-dashboard',
                [$this, 'helpPage']
            );    
        }

        function adminEnqueueScripts( $hook ) {
            if( strpos( $hook, 'lightbox-block-dashboard' ) ){
                wp_enqueue_style( 'lbb-admin-dashboard', LBB_DIR_URL . 'build/admin-dashboard.css', [], LBB_PLUGIN_VERSION );
                wp_enqueue_script( 'lbb-admin-dashboard', LBB_DIR_URL . 'build/admin-dashboard.js', [ 'react', 'react-dom' ], LBB_PLUGIN_VERSION, true );
                wp_set_script_translations( 'lbb-admin-dashboard', 'lightbox', LBB_DIR_PATH . 'languages' );
            }
	    }

        public function helpPage()
        {?>
            <div
                id='lbbDashboard'
                data-info='<?php echo esc_attr( wp_json_encode( [
                    'version' => LBB_PLUGIN_VERSION,
                    'isPremium' => lbbIsPremium(),
                    'hasPro' => LBB_IS_PRO
                ] ) ); ?>'
            >
            </div>
        <?php }  
    }
    new llbAdminMenu();
}