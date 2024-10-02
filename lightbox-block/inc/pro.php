<?php
if ( ! function_exists( 'lbb_fs' ) ) {
    // Create a helper function for easy SDK access.
    function lbb_fs() {
        global $lbb_fs;

        if ( ! isset( $lbb_fs ) ) {
            // Include Freemius SDK.
            require_once dirname(__FILE__) . '/../freemius/start.php';

            $lbb_fs = fs_dynamic_init( array(
                'id'                  => '13492',
                'slug'                => 'lightbox-block',
                'premium_slug'        => 'lightbox-block-pro',
                'type'                => 'plugin',
                'public_key'          => 'pk_8346b668170b2e4c33255d896d15c',
                'is_premium'          => true,
                'premium_suffix'      => 'Pro',
                // If your plugin is a serviceware, set this option to false.
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'trial'               => array(
                    'days'               => 7,
                    'is_require_payment' => false,
                ),
                'menu'                => array(
                    'slug'           => 'lightbox-block.php',
                    'contact'        => false,
                    'support'        => false,
                ),
            ) );
        }

        return $lbb_fs;
    }

    // Init Freemius.
    lbb_fs();
    // Signal that SDK was initiated.
    do_action( 'lbb_fs_loaded' );
}