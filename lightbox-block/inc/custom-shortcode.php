<?php
if (!defined('ABSPATH')) {exit;}

class LBB_Lightbox_Custom_Post{
	public $post_type = 'lbb';

	public function __construct(){
		add_action( 'init', [$this, 'onInit'], 20 );
		add_shortcode( 'lbb-lightbox-block', [$this, 'onAddShortcode'], 20 );
		add_filter( 'manage_lbb_posts_columns', [$this, 'manageLPBPostsColumns'], 10 );
		add_action( 'manage_lbb_posts_custom_column', [$this, 'manageBSBPostsCustomColumns'], 10, 2 );
		add_action( 'use_block_editor_for_post', [$this, 'useBlockEditorForPost'], 999, 2 );
	}

	function onInit(){
		$menuIcon = "<svg xmlns='http://www.w3.org/2000/svg' width='32px' height='32px' viewBox='0 0 32 32'>
            <path fill='#fff' d='M28,4H10A2.0059,2.0059,0,0,0,8,6V20a2.0059,2.0059,0,0,0,2,2H28a2.0059,2.0059,0,0,0,2-2V6A2.0059,2.0059,0,0,0,28,4Zm0,16H10V6H28Z' /><path fill='#fff' d='M18,26H4V16H6V14H4a2.0059,2.0059,0,0,0-2,2V26a2.0059,2.0059,0,0,0,2,2H18a2.0059,2.0059,0,0,0,2-2V24H18Z' /></svg>";

		register_post_type( $this->post_type, [
			'labels'				=> [
				'name'			=> __( 'Lightbox', 'lightbox-block' ),
				'singular_name'	=> __( 'Lightbox', 'lightbox-block' ),
				'menu_name'     => __( 'Lightbox', 'lightbox-block' ),
				'all_items'     => __( 'All Lightboxes', 'lightbox-block' ),
				'add_new'		=> __( 'Add New', 'lightbox-block' ),
				'add_new_item'	=> __( ' &#8627; Add New Lightbox', 'lightbox-block' ),
				'edit_item'		=> __( 'Edit', 'lightbox-block' ),
				'new_item'		=> __( 'New', 'lightbox-block' ),
				'view_item'		=> __( 'View', 'lightbox-block' ),
				'item_published' => __('Publish Lightbox', 'lightbox-block'),
				'item_updated'	=> __('Update Lightbox', 'lightbox-block'),
				'item_trashed'  => __('Lightbox trashed', 'lightbox-block'),
				'search_items'	=> __( 'Search', 'lightbox-block'),
				'not_found'		=> __( 'Sorry, we couldn\'t find the that you are looking for.', 'lightbox-block' )
			],
			'public'				=> false,
			'show_ui'				=> true, 		
			'show_in_rest'			=> true,							
			'publicly_queryable'	=> false,
			'exclude_from_search'	=> true,
			'menu_position'			=> 14,
			'menu_icon'				=> 'data:image/svg+xml;base64,' . base64_encode( $menuIcon ),		
			'has_archive'			=> false,
			'hierarchical'			=> false,
			'capability_type'		=> 'page',
			'rewrite'				=> [ 'slug' => 'lbb' ],
			'supports'				=> [ 'title', 'editor' ],
			'template'				=> [ ['lbb/lightbox'] ],
			'template_lock'			=> 'all',
		]); // Register Post Type
	}

	public function onAddShortcode( $atts ) {
        $atts = shortcode_atts( array(
            'id' => 0,
        ), $atts, 'lbb-lightbox-block' );

        $post_id = absint( $atts['id'] );
        if ( ! $post_id ) {
            return '';
        }

        $post = get_post( $post_id );
        if ( !$post ) {
            return '';
        }
        if ( post_password_required( $post ) ) {
            return get_the_password_form( $post );
        }
        switch ( $post->post_status ) {
            case 'publish':
                return $this->displayContent( $post );
            case 'private':
                if (current_user_can('read_private_posts')) {
                    return $this->displayContent( $post );
                }
                return '';
            case 'draft':
            case 'pending':
            case 'future':
                if ( current_user_can( 'edit_post', $post_id ) ) {
                    return $this->displayContent( $post );
                }
                return '';
            default:
                return '';
        }
    }
	
    public function displayContent( $post ){
        $blocks = parse_blocks( $post->post_content );
        if ( empty( $blocks ) ) {
            return '';
        }
        return render_block( $blocks[0] );
    }

	function manageLPBPostsColumns( $defaults ) {
		unset( $defaults['date'] );
		$defaults['shortcode'] = 'ShortCode';
		$defaults['date'] = 'Date';
		return $defaults;
	}

	function manageBSBPostsCustomColumns( $column_name, $post_ID ) {
		if ( $column_name == 'shortcode' ) {
			$safe_id = intval( $post_ID );
			echo "<div class='bsbFrontShortcode' id='bsbFrontShortcode-" . esc_attr( $safe_id ) . "'>
				<input value='[lbb-lightbox-block id=" . esc_attr( $safe_id ) . "]' onclick='bsbHandleShortcode( " . esc_attr( $safe_id ) . " )'>
				<span class='tooltip'>Copy To Clipboard</span>
			</div>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic values are escaped with esc_attr() and intval().
		}
	}

	function useBlockEditorForPost($use, $post){
		if ($this->post_type === $post->post_type) {
			return true;
		}
		return $use;
	}
}
new LBB_Lightbox_Custom_Post();