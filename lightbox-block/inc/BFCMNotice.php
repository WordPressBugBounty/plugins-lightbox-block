<?php
/**
 * BFCM 2026 Banner & Admin Notice
 *
 * Handles the Black Friday / Cyber Monday 2026 promotion banner and notice.
 * Expiry: December 5, 2026.
 *
 * @package LightboxBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'LBB_BFCM_Notice' ) ) {
    class LBB_BFCM_Notice {

        private static $instance = null;

        /**
         * End date for BFCM 2026 promotion (December 5, 2026 23:59:59).
         */
        const EXPIRY_TIMESTAMP = 1796515199; // 2026-12-05 23:59:59 UTC

        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function __construct() {
            add_action( 'all_admin_notices', [ $this, 'render_all_lightboxes_banner' ] );
            add_action( 'wp_ajax_lbb_dismiss_bfcm_notice', [ $this, 'ajax_dismiss' ] );
        }

        /**
         * Check if promotion is currently active and not dismissed.
         */
        public function is_active() {
            // Expire after 5th December 2026
            if ( time() > self::EXPIRY_TIMESTAMP ) {
                return false;
            }

            // Check if current user has dismissed it
            if ( get_user_meta( get_current_user_id(), 'lbb_bfcm_2026_dismissed', true ) ) {
                return false;
            }

            // If Freemius / Pro is already active, don't show upgrade notice
            if ( function_exists( 'lbb_fs' ) && lbb_fs()->can_use_premium_code() ) {
                return false;
            }

            return true;
        }

        /**
         * AJAX handler to persist dismissal for the user.
         */
        public function ajax_dismiss() {
            check_ajax_referer( 'lbb_bfcm_dismiss_nonce', 'nonce' );

            if ( ! current_user_can( 'read' ) ) {
                wp_send_json_error( [ 'message' => 'Unauthorized' ] );
            }

            update_user_meta( get_current_user_id(), 'lbb_bfcm_2026_dismissed', 1 );
            wp_send_json_success();
        }

        /**
         * Render the BFCM banner on the All Lightboxes list page (edit.php?post_type=lbb).
         */
        public function render_all_lightboxes_banner() {
            global $pagenow;

            if ( 'edit.php' !== $pagenow || ! isset( $_GET['post_type'] ) || 'lbb' !== $_GET['post_type'] || isset( $_GET['page'] ) ) {
                return;
            }

            $screen = get_current_screen();
            if ( $screen && 'edit-lbb' !== $screen->id ) {
                return;
            }

            if ( ! $this->is_active() ) {
                return;
            }

            $nonce       = wp_create_nonce( 'lbb_bfcm_dismiss_nonce' );
            $pricing_url = 'https://bplugins.com/products/lightbox-block/pricing?utm_source=all_lightboxes&utm_medium=bfcm_banner&utm_campaign=bfcm2026';
            ?>
            <div id="lbbBfcmBannerWrap" style="display:none;">
                <style>
                    /* ===== BFCM Banner: Full-width with 20px right gap ===== */
                    #lbbBfcmBannerWrap {
                        display: none;
                        width: 100%;
                        box-sizing: border-box;
                        padding-right: 20px;
                        margin: 0;
                        clear: both;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmBanner {
                        display: flex;
                        align-items: stretch;
                        background: #ffffff;
                        border: 1px solid #ebd5f5;
                        border-left: none;
                        border-radius: 0;
                        box-shadow: 0 2px 12px rgba(157, 38, 154, 0.08);
                        margin: 0;
                        width: 100%;
                        overflow: hidden;
                        position: relative;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                        box-sizing: border-box;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmBanner * {
                        box-sizing: border-box;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmRibbon {
                        background: linear-gradient(135deg, #FAEFFA 0%, #F6EAFE 50%, #F1DAF7 100%);
                        border-right: 1px solid #ebd5f5;
                        padding: 12px 18px;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        min-width: 140px;
                        flex-shrink: 0;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmRibbonIconWrap {
                        width: 44px;
                        height: 44px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmRibbonIcon,
                    #lbbBfcmBannerWrap .lbbBfcmRibbonIconWrap svg {
                        width: 100%;
                        height: 100%;
                        display: block;
                        object-fit: contain;
                        filter: drop-shadow(0 4px 10px rgba(157, 38, 154, 0.25));
                    }
                    #lbbBfcmBannerWrap .lbbBfcmRibbonTitle {
                        color: #351D42;
                        font-size: 10px;
                        font-weight: 800;
                        letter-spacing: 0.8px;
                        margin-top: 6px;
                        text-transform: uppercase;
                        text-align: center;
                        line-height: 1.2;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmContent {
                        flex: 1;
                        padding: 12px 20px;
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        gap: 6px;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmHeaderRow {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        flex-wrap: wrap;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmTitle {
                        margin: 0;
                        font-size: 15px;
                        font-weight: 700;
                        color: #070127;
                        line-height: 1.3;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmDesc {
                        margin: 3px 0 8px 0;
                        font-size: 12px;
                        line-height: 1.5;
                        color: #4b5563;
                        max-width: 950px;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmDiscountBadge {
                        background: #FFF0E6;
                        color: #E05300;
                        font-size: 11px;
                        font-weight: 700;
                        padding: 2px 10px;
                        border-radius: 9999px;
                        line-height: 1.4;
                        display: inline-block;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmFeaturesRow {
                        display: flex;
                        align-items: center;
                        flex-wrap: wrap;
                        gap: 16px;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmFeatureItem {
                        display: inline-flex;
                        align-items: center;
                        gap: 5px;
                        font-size: 12px;
                        color: #4b5563;
                        font-weight: 500;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmCheckmark {
                        color: #070127;
                        font-weight: 700;
                        font-size: 12px;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmAction {
                        display: flex;
                        align-items: center;
                        padding-right: 22px;
                        padding-left: 10px;
                        position: relative;
                        flex-shrink: 0;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmCloseBtn {
                        position: absolute;
                        top: 8px;
                        right: 10px;
                        background: transparent;
                        border: none;
                        cursor: pointer;
                        color: #a528a2;
                        font-size: 18px;
                        line-height: 1;
                        padding: 2px 6px;
                        border-radius: 4px;
                        transition: color 0.15s ease, background 0.15s ease;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmCloseBtn:hover {
                        color: #721370;
                        background: rgba(165, 40, 162, 0.1);
                    }
                    #lbbBfcmBannerWrap .lbbBfcmCtaBtn {
                        margin-right: 20px;
                        background: #f1daf7;
                        color: #a528a2 !important;
                        text-decoration: none !important;
                        font-size: 13px;
                        font-weight: 600;
                        padding: 9px 18px;
                        border-radius: 6px;
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        transition: all 0.2s ease;
                        box-shadow: none !important;
                        border: 1px solid rgba(165, 40, 162, 0.2);
                        white-space: nowrap;
                    }
                    #lbbBfcmBannerWrap .lbbBfcmCtaBtn:hover {
                        background: #e9c8f2;
                        color: #8c1e89 !important;
                        transform: translateY(-1px);
                    }
                    @media (max-width: 900px) {
                        #lbbBfcmBannerWrap .lbbBfcmBanner { flex-direction: column; }
                        #lbbBfcmBannerWrap .lbbBfcmRibbon { width: 100%; flex-direction: row; gap: 12px; padding: 8px 16px; }
                        #lbbBfcmBannerWrap .lbbBfcmContent { padding: 12px 16px; }
                        #lbbBfcmBannerWrap .lbbBfcmAction { padding: 0 16px 12px; justify-content: flex-start; }
                        #lbbBfcmBannerWrap .lbbBfcmCloseBtn { top: 8px; right: 8px; }
                    }
                </style>
                <div class="lbbBfcmBanner">
                    <div class="lbbBfcmRibbon">
                        <div class="lbbBfcmRibbonIconWrap">
                            <?php echo $this->get_logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                        <span class="lbbBfcmRibbonTitle">LIGHTBOX BLOCK</span>
                    </div>

                    <div class="lbbBfcmContent">
                        <div class="lbbBfcmHeaderRow">
                            <h3 class="lbbBfcmTitle"><?php esc_html_e( "Lightbox Block's Biggest Black Friday Sale is Live – Up to 45% OFF!", 'lightbox-block' ); ?></h3>
                        </div>

                        <p class="lbbBfcmDesc"><?php esc_html_e( 'The biggest deal of the year just arrived early! This is your once-a-year opportunity to upgrade to Lightbox Block Pro at an exclusive price. Unlock powerful features, extended media types, and everything you need to take your lightbox galleries to the next level!', 'lightbox-block' ); ?></p>

                        <div class="lbbBfcmFeaturesRow">
                            <span class="lbbBfcmFeatureItem"><span class="lbbBfcmCheckmark">✓</span> <?php esc_html_e( 'PDF & Video Popups', 'lightbox-block' ); ?></span>
                            <span class="lbbBfcmFeatureItem"><span class="lbbBfcmCheckmark">✓</span> <?php esc_html_e( 'Custom Overlays', 'lightbox-block' ); ?></span>
                            <span class="lbbBfcmFeatureItem"><span class="lbbBfcmCheckmark">✓</span> <?php esc_html_e( 'Advanced Slide Effects', 'lightbox-block' ); ?></span>
                            <span class="lbbBfcmFeatureItem"><span class="lbbBfcmCheckmark">✓</span> <?php esc_html_e( '4+ Audio Players', 'lightbox-block' ); ?></span>
                        </div>
                    </div>

                    <div class="lbbBfcmAction">
                        <button type="button" class="lbbBfcmCloseBtn" id="lbbBfcmCloseBtn" aria-label="Dismiss banner" title="Dismiss">&times;</button>
                        <a href="<?php echo esc_url( $pricing_url ); ?>" target="_blank" rel="noopener noreferrer" class="lbbBfcmCtaBtn">
                            <?php esc_html_e( 'Unlock PRO Features →', 'lightbox-block' ); ?>
                        </a>
                    </div>
                </div>
            </div>
            <script>
            (function($) {
                $(document).ready(function() {
                    if (localStorage.getItem('lbb_bfcm_2026_dismissed')) {
                        return;
                    }

                    // Banner is already in the correct position (all_admin_notices = below Screen Options, above .wrap)
                    $('#lbbBfcmBannerWrap').show();

                    $('#lbbBfcmCloseBtn').on('click', function(e) {
                        e.preventDefault();
                        $('#lbbBfcmBannerWrap').slideUp(200, function() {
                            $(this).remove();
                        });
                        localStorage.setItem('lbb_bfcm_2026_dismissed', '1');
                        $.post(ajaxurl, {
                            action: 'lbb_dismiss_bfcm_notice',
                            nonce: '<?php echo esc_js( $nonce ); ?>'
                        });
                    });
                });
            })(jQuery);
            </script>
            <?php
        }

        /**
         * Return official WordPress.org purple squircle logo SVG markup
         *
         * @return string
         */
        private function get_logo_svg() {
            return '<svg class="lbbBfcmRibbonIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="44" height="44" role="img" aria-label="Lightbox Block">' .
                '<defs>' .
                    '<linearGradient id="lbbNoticeTile" x1="0" y1="0" x2="1" y2="1">' .
                        '<stop offset="0" stop-color="#D630D2"/>' .
                        '<stop offset="1" stop-color="#9D269A"/>' .
                    '</linearGradient>' .
                    '<radialGradient id="lbbNoticeGlow" cx="0.5" cy="0.5" r="0.5">' .
                        '<stop offset="0" stop-color="#ffffff" stop-opacity="0.55"/>' .
                        '<stop offset="1" stop-color="#ffffff" stop-opacity="0"/>' .
                    '</radialGradient>' .
                    '<linearGradient id="lbbNoticeDay" x1="0" y1="0" x2="0" y2="1">' .
                        '<stop offset="0" stop-color="#ffd27a"/>' .
                        '<stop offset="1" stop-color="#ff9ec2"/>' .
                    '</linearGradient>' .
                    '<linearGradient id="lbbNoticeNight" x1="0" y1="0" x2="0" y2="1">' .
                        '<stop offset="0" stop-color="#6a2f9e"/>' .
                        '<stop offset="1" stop-color="#c23ea8"/>' .
                    '</linearGradient>' .
                    '<clipPath id="lbbNoticeTileClip"><rect x="0" y="0" width="256" height="256" rx="56"/></clipPath>' .
                    '<clipPath id="lbbNoticePhotoClip"><rect x="82" y="60" width="92" height="66" rx="9"/></clipPath>' .
                    '<style>' .
                        '.lbb-notice-breathe { transform-box: fill-box; transform-origin: 128px 104px; animation: lbbNoticeBreathe 3.6s ease-in-out infinite; }' .
                        '.lbb-notice-sceneA { animation: lbbNoticeFadeA 3.6s ease-in-out infinite; }' .
                        '.lbb-notice-sceneB { animation: lbbNoticeFadeB 3.6s ease-in-out infinite; }' .
                        '.lbb-notice-hl { animation: lbbNoticeHl 3.6s steps(3) infinite; }' .
                        '@keyframes lbbNoticeBreathe { 0% { transform: scale(0.955); } 18% { transform: scale(1); } 82% { transform: scale(1); } 100% { transform: scale(0.955); } }' .
                        '@keyframes lbbNoticeFadeA { 0%{opacity:1} 40%{opacity:1} 50%{opacity:0} 90%{opacity:0} 100%{opacity:1} }' .
                        '@keyframes lbbNoticeFadeB { 0%{opacity:0} 40%{opacity:0} 50%{opacity:1} 90%{opacity:1} 100%{opacity:0} }' .
                        '@keyframes lbbNoticeHl { 0%{transform:translateX(0)} 100%{transform:translateX(165px)} }' .
                        '@media (prefers-reduced-motion: reduce) { .lbb-notice-breathe{animation:none;transform:scale(1)} .lbb-notice-sceneA{animation:none;opacity:1} .lbb-notice-sceneB{animation:none;opacity:0} .lbb-notice-hl{animation:none;transform:translateX(0)} }' .
                    '</style>' .
                '</defs>' .
                '<g clip-path="url(#lbbNoticeTileClip)">' .
                    '<rect x="0" y="0" width="256" height="256" fill="url(#lbbNoticeTile)"/>' .
                    '<circle cx="70" cy="60" r="120" fill="#ffffff" opacity="0.08"/>' .
                    '<ellipse cx="128" cy="112" rx="96" ry="82" fill="url(#lbbNoticeGlow)" opacity="0.5"/>' .
                    '<rect x="40" y="40" width="176" height="150" rx="16" fill="#3a0e39" opacity="0.28"/>' .
                    '<g>' .
                        '<g>' .
                            '<rect x="50" y="188" width="40" height="34" rx="7" fill="#ffffff" opacity="0.92"/>' .
                            '<circle cx="61" cy="199" r="4" fill="#ffc861"/>' .
                            '<path d="M55 214 L66 203 L74 214 Z" fill="#c23ea8"/>' .
                        '</g>' .
                        '<g>' .
                            '<rect x="105" y="188" width="40" height="34" rx="7" fill="#ffffff" opacity="0.92"/>' .
                            '<path d="M112 214 L120 205 L126 211 L133 200 L138 214 Z" fill="#8f2f9c"/>' .
                            '<circle cx="118" cy="200" r="3.4" fill="#ffc861"/>' .
                        '</g>' .
                        '<g>' .
                            '<rect x="160" y="188" width="40" height="34" rx="7" fill="#ffffff" opacity="0.92"/>' .
                            '<path d="M175 197 L188 205 L175 213 Z" fill="#D630D2"/>' .
                        '</g>' .
                        '<g class="lbb-notice-hl">' .
                            '<rect x="48" y="186" width="44" height="38" rx="9" fill="none" stroke="#ffffff" stroke-width="3"/>' .
                        '</g>' .
                    '</g>' .
                    '<g class="lbb-notice-breathe">' .
                        '<rect x="72" y="50" width="112" height="108" rx="13" fill="#ffffff"/>' .
                        '<g clip-path="url(#lbbNoticePhotoClip)">' .
                            '<rect x="82" y="60" width="92" height="66" fill="#efe7f5"/>' .
                            '<g class="lbb-notice-sceneA">' .
                                '<rect x="82" y="60" width="92" height="66" fill="url(#lbbNoticeDay)"/>' .
                                '<circle cx="150" cy="80" r="11" fill="#fff2c4"/>' .
                                '<path d="M82 126 L104 96 L120 116 L136 90 L160 122 L174 122 L174 126 Z" fill="#b8477f"/>' .
                                '<path d="M82 126 L98 108 L114 126 Z" fill="#8f2f6a"/>' .
                            '</g>' .
                            '<g class="lbb-notice-sceneB">' .
                                '<rect x="82" y="60" width="92" height="66" fill="url(#lbbNoticeNight)"/>' .
                                '<circle cx="150" cy="80" r="10" fill="#f2e6ff"/>' .
                                '<circle cx="146" cy="77" r="10" fill="#6a2f9e"/>' .
                                '<circle cx="98" cy="74" r="1.4" fill="#ffffff"/>' .
                                '<circle cx="118" cy="70" r="1.2" fill="#ffffff"/>' .
                                '<circle cx="132" cy="98" r="1.2" fill="#ffffff"/>' .
                                '<path d="M82 126 L108 98 L128 122 L146 104 L174 126 Z" fill="#4a1f74"/>' .
                            '</g>' .
                        '</g>' .
                        '<path d="M86 66 L86 74 M86 66 L94 66" stroke="#ffffff" stroke-width="2.4" fill="none" stroke-linecap="round"/>' .
                        '<path d="M170 66 L162 66 M170 66 L170 74" stroke="#ffffff" stroke-width="2.4" fill="none" stroke-linecap="round"/>' .
                        '<path d="M86 120 L86 112 M86 120 L94 120" stroke="#ffffff" stroke-width="2.4" fill="none" stroke-linecap="round"/>' .
                        '<path d="M170 120 L170 112 M170 120 L162 120" stroke="#ffffff" stroke-width="2.4" fill="none" stroke-linecap="round"/>' .
                        '<rect x="82" y="134" width="60" height="7" rx="3.5" fill="#D630D2" opacity="0.85"/>' .
                        '<rect x="82" y="146" width="40" height="5" rx="2.5" fill="#c9b8d1"/>' .
                    '</g>' .
                '</g>' .
            '</svg>';
        }
    }

    LBB_BFCM_Notice::get_instance();
}
