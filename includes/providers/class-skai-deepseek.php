<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_DeepSeek extends Skai_Provider {

	protected function request( $prompt ) {
		$response = wp_remote_post( 'https://api.deepseek.com/chat/completions', array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'       => $this->model,
				'temperature' => 0.3,
				'messages'    => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->http_error_from_response( $response, 'DeepSeek' );
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $json['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'skai_deepseek', __( 'DeepSeek response is missing message.content.', 'smart-keyword-ai' ) );
		}

		return (string) $json['choices'][0]['message']['content'];
	}
}
