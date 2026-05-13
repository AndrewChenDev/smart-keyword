<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_Gemini extends Skai_Provider {

	protected function request( $prompt ) {
		$url = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $this->model ),
			rawurlencode( $this->api_key )
		);

		$response = wp_remote_post( $url, array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'contents'         => array(
					array( 'parts' => array( array( 'text' => $prompt ) ) ),
				),
				'generationConfig' => array(
					'temperature' => 0.3,
				),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->http_error_from_response( $response, 'Gemini' );
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );

		$text = '';
		if ( isset( $json['candidates'][0]['content']['parts'] ) && is_array( $json['candidates'][0]['content']['parts'] ) ) {
			foreach ( $json['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$text .= $part['text'];
				}
			}
		}

		if ( $text === '' ) {
			return new WP_Error( 'skai_gemini', __( 'Gemini response is missing a text part.', 'smart-keyword-ai' ) );
		}

		return $text;
	}
}
