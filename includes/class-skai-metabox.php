<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_Metabox {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_footer-post.php', array( $this, 'inject_link' ) );
		add_action( 'admin_footer-post-new.php', array( $this, 'inject_link' ) );
	}

	public function enqueue( $hook ) {
		if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! is_object_in_taxonomy( $screen->post_type, 'post_tag' ) ) {
			return;
		}

		wp_enqueue_style( 'skai-admin', SKAI_URL . 'assets/admin.css', array(), SKAI_VERSION );
		wp_enqueue_script( 'skai-admin', SKAI_URL . 'assets/admin.js', array( 'jquery' ), SKAI_VERSION, true );

		wp_localize_script( 'skai-admin', 'SKAI', array(
			'ajaxurl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'skai_generate' ),
			'post_id'   => isset( $GLOBALS['post']->ID ) ? intval( $GLOBALS['post']->ID ) : 0,
			'i18n'      => array(
				'link'         => __( 'Pick from AI tags', 'smart-keyword-ai' ),
				'loading'      => __( 'Generating…', 'smart-keyword-ai' ),
				'error'        => __( 'Generation failed.', 'smart-keyword-ai' ),
				'emptyContent' => __( 'Article content is empty.', 'smart-keyword-ai' ),
			),
		) );
	}

	public function inject_link() {
		$screen = get_current_screen();
		if ( ! $screen || ! is_object_in_taxonomy( $screen->post_type, 'post_tag' ) ) {
			return;
		}
		$label = esc_js( __( 'Pick from AI tags', 'smart-keyword-ai' ) );
		?>
		<script>
		jQuery(function($){
			var $box = $('#tagsdiv-post_tag .inside');
			if (!$box.length) { return; }
			if ($box.find('.skai-link-wrap').length) { return; }
			var label = (window.SKAI && SKAI.i18n && SKAI.i18n.link) || '<?php echo $label; ?>';
			var html = '<p class="skai-link-wrap">' +
				'<a href="#" class="skai-generate-link">' + label + '</a>' +
				' <span class="skai-spinner spinner"></span>' +
				' <span class="skai-message"></span>' +
				'</p>';
			$box.append(html);
		});
		</script>
		<?php
	}
}
