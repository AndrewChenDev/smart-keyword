<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_Anthropic extends Skai_Provider {

	protected function request( $prompt ) {
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'timeout' => 30,
			'headers' => array(
				'x-api-key'         => $this->api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'      => $this->model,
				'max_tokens' => 512,
				'messages'   => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->http_error_from_response( $response, 'Anthropic' );
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );

		// content is an array of blocks; concatenate text blocks.
		$text = '';
		if ( isset( $json['content'] ) && is_array( $json['content'] ) ) {
			foreach ( $json['content'] as $block ) {
				if ( isset( $block['type'] ) && 'text' === $block['type'] && isset( $block['text'] ) ) {
					$text .= $block['text'];
				}
			}
		}

		if ( $text === '' ) {
			return new WP_Error( 'skai_anthropic', __( 'Anthropic response is missing a text block.', 'smart-keyword-ai' ) );
		}

		return $text;
	}
}
