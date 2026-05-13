<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Skai_Provider {

	protected $api_key;
	protected $model;

	public function __construct( $api_key, $model ) {
		$this->api_key = (string) $api_key;
		$this->model   = (string) $model;
	}

	/**
	 * Each subclass implements the HTTP call. Must return the raw text response from the model
	 * (the content portion only — not the wrapping JSON) as a string, or a WP_Error on failure.
	 *
	 * @param string $prompt
	 * @return string|WP_Error
	 */
	abstract protected function request( $prompt );

	public function generate_tags( $content, $count ) {
		$count  = max( 1, intval( $count ) );
		$prompt = $this->build_prompt( $content, $count );
		$raw    = $this->request( $prompt );

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$tags = $this->parse_tags( $raw );
		if ( empty( $tags ) ) {
			return new WP_Error(
				'skai_parse',
				sprintf( __( 'AI returned content that could not be parsed as tags: %s', 'smart-keyword-ai' ), mb_substr( $raw, 0, 200 ) )
			);
		}

		// Dedupe (mb-safe, case-insensitive), trim, slice.
		$seen = array();
		$out  = array();
		foreach ( $tags as $t ) {
			$t = trim( (string) $t, " \t\n\r\0\x0B\"'`,，。、" );
			if ( $t === '' ) {
				continue;
			}
			$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $t, 'UTF-8' ) : strtolower( $t );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $t;
			if ( count( $out ) >= $count ) {
				break;
			}
		}

		return $out;
	}

	protected function build_prompt( $content, $count ) {
		return "You are an SEO expert. Generate exactly {$count} high-value SEO tag keywords for the article below.\n\n"
			. "Rules:\n"
			. "- Output language MUST match the article's language. If the article is in Chinese, return Chinese tags; if English, return English tags. Mixed-language articles use the dominant language.\n"
			. "- Each tag is a concise searchable keyword (Chinese: 2–8 characters; English: 1–4 words).\n"
			. "- Focus on entities, topics, and search intent — not generic filler.\n"
			. "- Return ONLY a JSON array of strings. No prose, no markdown fences, no keys.\n\n"
			. "Article:\n\"\"\"\n{$content}\n\"\"\"";
	}

	protected function parse_tags( $raw ) {
		$s = trim( (string) $raw );

		// Strip ```json ... ``` or ``` ... ``` fences if present.
		if ( preg_match( '/^```(?:json)?\s*(.*?)\s*```$/is', $s, $m ) ) {
			$s = $m[1];
		}

		// Try strict JSON first.
		$decoded = json_decode( $s, true );
		if ( is_array( $decoded ) ) {
			return array_values( array_filter( $decoded, 'is_string' ) );
		}

		// Try to find an array literal anywhere in the string.
		if ( preg_match( '/\[[\s\S]*\]/', $s, $m ) ) {
			$decoded = json_decode( $m[0], true );
			if ( is_array( $decoded ) ) {
				return array_values( array_filter( $decoded, 'is_string' ) );
			}
		}

		// Fallback: split by newlines / commas / Chinese commas.
		$parts = preg_split( '/[\r\n,，、;；]+/u', $s );
		if ( is_array( $parts ) ) {
			return $parts;
		}

		return array();
	}

	protected function http_error_from_response( $response, $context ) {
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$msg  = sprintf(
			/* translators: 1: provider name, 2: HTTP status code, 3: response body excerpt */
			__( '%1$s HTTP %2$s: %3$s', 'smart-keyword-ai' ),
			$context,
			$code,
			mb_substr( (string) $body, 0, 300 )
		);
		return new WP_Error( 'skai_http', $msg );
	}
}
