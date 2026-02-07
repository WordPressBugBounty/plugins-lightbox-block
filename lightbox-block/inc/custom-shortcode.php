<?php
if (!defined('ABSPATH')) {exit;}

class LPBCustomPost{
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
				'name'			=> __( 'Lightbox', 'lightbox' ),
				'singular_name'	=> __( 'Lightbox', 'lightbox' ),
				'menu_name'     => __( 'Lightbox', 'lightbox' ),
				'all_items'     => __( 'All Lightboxes', 'lightbox' ),
				'add_new'		=> __( 'Add New', 'lightbox' ),
				'add_new_item'	=> __( ' &#8627; Add New Lightbox', 'lightbox' ),
				'edit_item'		=> __( 'Edit', 'lightbox' ),
				'new_item'		=> __( 'New', 'lightbox' ),
				'view_item'		=> __( 'View', 'lightbox' ),
				'item_published' => __('Publish Lightbox', 'lightbox'),
				'item_updated'	=> __('Update Lightbox', 'lightbox'),
				'item_trashed'  => __('Lightbox trashed', 'lightbox'),
				'search_items'	=> __( 'Search', 'lightbox'),
				'not_found'		=> __( 'Sorry, we couldn\'t find the that you are looking for.', 'lightbox' )
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
        $post_id = $atts['id'];
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
			echo "<div class='bsbFrontShortcode' id='bsbFrontShortcode-$post_ID'>
				<input value='[lbb-lightbox-block id=$post_ID]' onclick='bsbHandleShortcode( $post_ID )'>
				<span class='tooltip'>Copy To Clipboard</span>
			</div>";
		}
	}

	function useBlockEditorForPost($use, $post){
		if ($this->post_type === $post->post_type) {
			return true;
		}
		return $use;
	}
}
new LPBCustomPost();