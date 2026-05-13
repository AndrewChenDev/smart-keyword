<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_skai_generate_tags', array( $this, 'handle' ) );
	}

	public function handle() {
		check_ajax_referer( 'skai_generate', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-keyword-ai' ) ), 403 );
		}

		$raw = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$raw = is_string( $raw ) ? $raw : '';

		$max     = intval( Skai_Settings::get( 'max_content_chars' ) );
		$content = Skai_Content::prepare( $raw, $max );

		if ( $content === '' ) {
			wp_send_json_error( array( 'message' => __( 'Article content is empty.', 'smart-keyword-ai' ) ) );
		}

		$provider = Skai_Settings::get( 'provider' );
		$api_key  = Skai_Settings::get( $provider . '_key' );
		$model    = Skai_Settings::get( $provider . '_model' );

		if ( ! $api_key ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'API key for the current AI (%s) is not set.', 'smart-keyword-ai' ), $provider ) ) );
		}

		$instance = $this->make_provider( $provider, $api_key, $model );
		if ( ! $instance ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Unknown AI provider: %s', 'smart-keyword-ai' ), $provider ) ) );
		}

		$count = intval( Skai_Settings::get( 'tag_count' ) );
		$tags  = $instance->generate_tags( $content, $count );

		if ( is_wp_error( $tags ) ) {
			wp_send_json_error( array( 'message' => $tags->get_error_message() ) );
		}

		wp_send_json_success( array( 'tags' => array_values( $tags ) ) );
	}

	private function make_provider( $slug, $api_key, $model ) {
		switch ( $slug ) {
			case 'openai':
				return new Skai_OpenAI( $api_key, $model );
			case 'anthropic':
				return new Skai_Anthropic( $api_key, $model );
			case 'gemini':
				return new Skai_Gemini( $api_key, $model );
			case 'deepseek':
				return new Skai_DeepSeek( $api_key, $model );
		}
		return null;
	}
}
