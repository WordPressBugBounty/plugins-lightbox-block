<?php
namespace LBB\Inc;

class AdminMenu
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
        add_action('admin_menu', [$this, 'adminMenu']);
    }

    public function adminEnqueueScripts($hook)
    {
        if ('toplevel_page_lightbox-block' === $hook) {
            wp_enqueue_style('lbb-admin-style', LBB_DIR_URL . 'dist/admin.css', false, LBB_PLUGIN_VERSION);
            wp_enqueue_script('lbb-admin-script', LBB_DIR_URL . 'dist/admin.js', ['react', 'react-dom'], LBB_PLUGIN_VERSION);
        }
    }

    public function adminMenu()
    {
        $menuIcon = "<svg xmlns='http://www.w3.org/2000/svg' width='32px' height='32px' viewBox='0 0 32 32'>
		<path fill='#fff' d='M28,4H10A2.0059,2.0059,0,0,0,8,6V20a2.0059,2.0059,0,0,0,2,2H28a2.0059,2.0059,0,0,0,2-2V6A2.0059,2.0059,0,0,0,28,4Zm0,16H10V6H28Z' /><path fill='#fff' d='M18,26H4V16H6V14H4a2.0059,2.0059,0,0,0-2,2V26a2.0059,2.0059,0,0,0,2,2H18a2.0059,2.0059,0,0,0,2-2V24H18Z' />
	</svg>";

        add_menu_page(
            __('Lightbox Block', 'Lightbox-block'),
            __('Lightbox Block', 'Lightbox-block'),
            'manage_options',
            'lightbox-block.php',
            [$this, 'helpPage'],
            'data:image/svg+xml;base64,' . base64_encode($menuIcon),
            6
        );
    }

    public function helpPage()
    {?>
		<div class='bplAdminHelpPage'></div>
	<?php }
}
new AdminMenu();