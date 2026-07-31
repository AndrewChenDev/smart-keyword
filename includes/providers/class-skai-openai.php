<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_OpenAI extends Skai_Provider {

	protected $slug = 'openai';

	protected function request( $prompt ) {
		$body = array(
			'model'       => $this->model,
			'temperature' => 0.3,
			'messages'    => array(
				array( 'role' => 'user', 'content' => $prompt ),
			),
		);

		$response = $this->post_chat_completion( $body );

		// Reasoning/GPT-5-family models reject any non-default temperature. Since a
		// 400 here is rejected before inference runs (no cost incurred), retry once
		// without it instead of maintaining a model-name allowlist.
		if ( $this->is_temperature_unsupported( $response ) ) {
			unset( $body['temperature'] );
			$response = $this->post_chat_completion( $body );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->http_error_from_response( $response, 'OpenAI' );
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $json['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'skai_openai', __( 'OpenAI response is missing message.content.', 'smart-keyword-ai' ) );
		}

		return array(
			'text'  => (string) $json['choices'][0]['message']['content'],
			'usage' => array(
				'input'  => isset( $json['usage']['prompt_tokens'] )     ? intval( $json['usage']['prompt_tokens'] )     : 0,
				'output' => isset( $json['usage']['completion_tokens'] ) ? intval( $json['usage']['completion_tokens'] ) : 0,
			),
		);
	}

	private function post_chat_completion( array $body ) {
		return wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );
	}

	private function is_temperature_unsupported( $response ) {
		if ( is_wp_error( $response ) || 400 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $json['error']['param'], $json['error']['code'] )
			&& 'temperature' === $json['error']['param']
			&& 'unsupported_value' === $json['error']['code'];
	}
}
