<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_Ajax {

	const MODELS_TRANSIENT_TTL = HOUR_IN_SECONDS;

	public function __construct() {
		add_action( 'wp_ajax_skai_generate_tags', array( $this, 'handle' ) );
		add_action( 'wp_ajax_skai_fetch_models',  array( $this, 'handle_fetch_models' ) );
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

	public function handle_fetch_models() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-keyword-ai' ) ), 403 );
		}
		check_ajax_referer( 'skai_fetch_models', 'nonce' );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_key( (string) wp_unslash( $_POST['provider'] ) ) : '';
		$valid    = array( 'openai', 'anthropic', 'gemini', 'deepseek' );
		if ( ! in_array( $provider, $valid, true ) ) {
			wp_send_json_error( array( 'code' => 'bad_provider', 'message' => __( 'Unknown provider.', 'smart-keyword-ai' ) ) );
		}

		$api_key = (string) Skai_Settings::get( $provider . '_key' );
		if ( $api_key === '' ) {
			wp_send_json_error( array( 'code' => 'no_key', 'message' => __( 'API key is not set for this provider.', 'smart-keyword-ai' ) ) );
		}

		$cache_key = 'skai_models_' . $provider . '_' . md5( $api_key );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			wp_send_json_success( array(
				'models'  => $cached,
				'pricing' => $this->pricing_payload( $provider, $cached ),
				'cached'  => true,
			) );
		}

		$models = $this->fetch_models( $provider, $api_key );
		if ( is_wp_error( $models ) ) {
			wp_send_json_error( array( 'code' => 'fetch_failed', 'message' => $models->get_error_message() ) );
		}

		sort( $models );
		$models = array_values( array_unique( $models ) );

		set_transient( $cache_key, $models, self::MODELS_TRANSIENT_TTL );

		wp_send_json_success( array(
			'models'  => $models,
			'pricing' => $this->pricing_payload( $provider, $models ),
			'cached'  => false,
		) );
	}

	protected function pricing_payload( $provider, $models ) {
		$out = array();
		foreach ( $models as $m ) {
			// Apply the per-model discount correction so the refreshed dropdown matches
			// the real, non-promotional price shown elsewhere (selected-model "Price:"
			// line, Usage-tab cost estimates). set_time_limit(0) above covers the
			// resulting burst of per-model lookups on a cache-cold refresh.
			$p = Skai_Pricing::get_for( $provider, $m, true );
			if ( is_array( $p ) ) {
				$out[ $m ] = array(
					'input'  => $p['input'],
					'output' => $p['output'],
					'label'  => Skai_Pricing::format_price_label( $p ),
				);
			}
		}
		return $out;
	}

	protected function fetch_models( $provider, $api_key ) {
		switch ( $provider ) {
			case 'openai':
				return $this->fetch_openai_models( $api_key );
			case 'anthropic':
				return $this->fetch_anthropic_models( $api_key );
			case 'gemini':
				return $this->fetch_gemini_models( $api_key );
			case 'deepseek':
				return $this->fetch_deepseek_models( $api_key );
		}
		return new WP_Error( 'skai_models', __( 'Unknown provider.', 'smart-keyword-ai' ) );
	}

	protected function fetch_openai_models( $api_key ) {
		$response = wp_remote_get( 'https://api.openai.com/v1/models', array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'skai_models', sprintf(
				/* translators: 1: provider label e.g. OpenAI, 2: HTTP status code */
				__( '%1$s list-models HTTP %2$s', 'smart-keyword-ai' ),
				'OpenAI',
				wp_remote_retrieve_response_code( $response )
			) );
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		$out  = array();
		if ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
			foreach ( $json['data'] as $row ) {
				if ( isset( $row['id'] ) && is_string( $row['id'] ) ) {
					if ( preg_match( '/^(gpt-|o\d|chatgpt)/', $row['id'] ) ) {
						$out[] = $row['id'];
					}
				}
			}
		}
		return $out;
	}

	protected function fetch_anthropic_models( $api_key ) {
		$response = wp_remote_get( 'https://api.anthropic.com/v1/models', array(
			'timeout' => 15,
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			),
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'skai_models', sprintf(
				/* translators: 1: provider label e.g. OpenAI, 2: HTTP status code */
				__( '%1$s list-models HTTP %2$s', 'smart-keyword-ai' ),
				'Anthropic',
				wp_remote_retrieve_response_code( $response )
			) );
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		$out  = array();
		if ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
			foreach ( $json['data'] as $row ) {
				if ( isset( $row['id'] ) && is_string( $row['id'] ) ) {
					$out[] = $row['id'];
				}
			}
		}
		return $out;
	}

	protected function fetch_gemini_models( $api_key ) {
		$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode( $api_key );
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'skai_models', sprintf(
				/* translators: 1: provider label e.g. OpenAI, 2: HTTP status code */
				__( '%1$s list-models HTTP %2$s', 'smart-keyword-ai' ),
				'Gemini',
				wp_remote_retrieve_response_code( $response )
			) );
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		$out  = array();
		if ( isset( $json['models'] ) && is_array( $json['models'] ) ) {
			foreach ( $json['models'] as $row ) {
				if ( ! isset( $row['name'] ) || ! is_string( $row['name'] ) ) {
					continue;
				}
				$methods = isset( $row['supportedGenerationMethods'] ) && is_array( $row['supportedGenerationMethods'] ) ? $row['supportedGenerationMethods'] : array();
				if ( ! in_array( 'generateContent', $methods, true ) ) {
					continue;
				}
				$name = preg_replace( '#^models/#', '', $row['name'] );
				if ( $name !== '' ) {
					$out[] = $name;
				}
			}
		}
		return $out;
	}

	protected function fetch_deepseek_models( $api_key ) {
		$response = wp_remote_get( 'https://api.deepseek.com/models', array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'skai_models', sprintf(
				/* translators: 1: provider label e.g. OpenAI, 2: HTTP status code */
				__( '%1$s list-models HTTP %2$s', 'smart-keyword-ai' ),
				'DeepSeek',
				wp_remote_retrieve_response_code( $response )
			) );
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		$out  = array();
		if ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
			foreach ( $json['data'] as $row ) {
				if ( isset( $row['id'] ) && is_string( $row['id'] ) ) {
					$out[] = $row['id'];
				}
			}
		}
		return $out;
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
