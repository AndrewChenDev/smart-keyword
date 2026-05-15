<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_Pricing {

	const TRANSIENT_KEY = 'skai_openrouter_pricing';
	const TTL           = DAY_IN_SECONDS;
	const ENDPOINT      = 'https://openrouter.ai/api/v1/models';

	public static function provider_prefix( $provider ) {
		switch ( $provider ) {
			case 'openai':
				return 'openai';
			case 'anthropic':
				return 'anthropic';
			case 'gemini':
				return 'google';
			case 'deepseek':
				return 'deepseek';
		}
		return '';
	}

	public static function get_all( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( is_array( $cached ) && isset( $cached['by_provider'] ) ) {
				return $cached;
			}
		}
		return self::refresh();
	}

	public static function refresh() {
		$response = wp_remote_get( self::ENDPOINT, array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$empty = array( 'by_provider' => array(), 'fetched_at' => 0 );
			set_transient( self::TRANSIENT_KEY, $empty, MINUTE_IN_SECONDS * 15 );
			return $empty;
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		$by_provider = array(
			'openai'    => array(),
			'anthropic' => array(),
			'gemini'    => array(),
			'deepseek'  => array(),
		);

		if ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
			$prefix_to_provider = array(
				'openai'    => 'openai',
				'anthropic' => 'anthropic',
				'google'    => 'gemini',
				'deepseek'  => 'deepseek',
			);

			foreach ( $json['data'] as $row ) {
				if ( ! isset( $row['id'] ) || ! is_string( $row['id'] ) ) {
					continue;
				}
				$parts = explode( '/', $row['id'], 2 );
				if ( count( $parts ) !== 2 ) {
					continue;
				}
				$prefix = $parts[0];
				$bare   = $parts[1];
				if ( ! isset( $prefix_to_provider[ $prefix ] ) ) {
					continue;
				}
				$prompt_per_token     = isset( $row['pricing']['prompt'] )     ? floatval( $row['pricing']['prompt'] )     : 0.0;
				$completion_per_token = isset( $row['pricing']['completion'] ) ? floatval( $row['pricing']['completion'] ) : 0.0;
				$by_provider[ $prefix_to_provider[ $prefix ] ][ $bare ] = array(
					'input'  => $prompt_per_token * 1000000.0,
					'output' => $completion_per_token * 1000000.0,
				);
			}
		}

		$payload = array(
			'by_provider' => $by_provider,
			'fetched_at'  => time(),
		);
		set_transient( self::TRANSIENT_KEY, $payload, self::TTL );
		return $payload;
	}

	public static function get_for( $provider, $model_id ) {
		$data = self::get_all();
		$bp   = isset( $data['by_provider'][ $provider ] ) ? $data['by_provider'][ $provider ] : array();
		if ( isset( $bp[ $model_id ] ) ) {
			return $bp[ $model_id ];
		}
		// Loose match: strip common suffixes / latest tags.
		$stripped = preg_replace( '/-latest$|-\d{8}$|-\d{4}-\d{2}-\d{2}$/', '', $model_id );
		if ( $stripped !== $model_id && isset( $bp[ $stripped ] ) ) {
			return $bp[ $stripped ];
		}
		return null;
	}

	public static function map_for_provider( $provider ) {
		$data = self::get_all();
		return isset( $data['by_provider'][ $provider ] ) ? $data['by_provider'][ $provider ] : array();
	}

	public static function fetched_at() {
		$data = self::get_all();
		return isset( $data['fetched_at'] ) ? intval( $data['fetched_at'] ) : 0;
	}

	public static function format_usd_per_m( $value ) {
		$value = floatval( $value );
		if ( $value <= 0.0 ) {
			return '$0';
		}
		$s = number_format( $value, 4, '.', '' );
		$s = rtrim( rtrim( $s, '0' ), '.' );
		return '$' . $s;
	}

	public static function format_price_label( $price ) {
		if ( ! is_array( $price ) ) {
			return '';
		}
		return sprintf(
			'%s/M in | %s/M out',
			self::format_usd_per_m( isset( $price['input'] )  ? $price['input']  : 0 ),
			self::format_usd_per_m( isset( $price['output'] ) ? $price['output'] : 0 )
		);
	}

	public static function estimate_cost_usd( $price, $input_tokens, $output_tokens ) {
		if ( ! is_array( $price ) ) {
			return null;
		}
		$in_rate  = isset( $price['input'] )  ? floatval( $price['input'] )  : 0.0;
		$out_rate = isset( $price['output'] ) ? floatval( $price['output'] ) : 0.0;
		return ( intval( $input_tokens ) * $in_rate + intval( $output_tokens ) * $out_rate ) / 1000000.0;
	}
}
