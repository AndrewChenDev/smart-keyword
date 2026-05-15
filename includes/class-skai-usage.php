<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_Usage {

	const OPTION         = 'skai_usage';
	const MONTHS_TO_KEEP = 12;
	const LEGACY_MODEL   = '(legacy)';
	const UNKNOWN_MODEL  = '(unknown)';

	public static function providers() {
		return array( 'openai', 'anthropic', 'gemini', 'deepseek' );
	}

	public static function current_month() {
		return gmdate( 'Y-m' );
	}

	public static function get_all() {
		$data = get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	public static function get_month( $ym = null ) {
		if ( null === $ym ) {
			$ym = self::current_month();
		}
		$all = self::get_all();
		return isset( $all[ $ym ] ) && is_array( $all[ $ym ] ) ? $all[ $ym ] : array();
	}

	public static function record( $provider, $model, $input_tokens, $output_tokens ) {
		$provider = (string) $provider;
		if ( ! in_array( $provider, self::providers(), true ) ) {
			return;
		}

		$model = trim( (string) $model );
		if ( $model === '' ) {
			$model = self::UNKNOWN_MODEL;
		}

		$input  = max( 0, intval( $input_tokens ) );
		$output = max( 0, intval( $output_tokens ) );

		$all = self::get_all();
		$ym  = self::current_month();

		if ( ! isset( $all[ $ym ] ) || ! is_array( $all[ $ym ] ) ) {
			$all[ $ym ] = array();
		}
		if ( ! isset( $all[ $ym ][ $provider ] ) || ! is_array( $all[ $ym ][ $provider ] ) ) {
			$all[ $ym ][ $provider ] = array();
		}

		// If the existing bucket is in the legacy shape (direct input/output/requests keys),
		// migrate it to a nested (legacy) entry before writing the new model entry.
		if ( isset( $all[ $ym ][ $provider ]['input'] ) || isset( $all[ $ym ][ $provider ]['output'] ) || isset( $all[ $ym ][ $provider ]['requests'] ) ) {
			$legacy = array(
				'input'    => intval( isset( $all[ $ym ][ $provider ]['input'] )    ? $all[ $ym ][ $provider ]['input']    : 0 ),
				'output'   => intval( isset( $all[ $ym ][ $provider ]['output'] )   ? $all[ $ym ][ $provider ]['output']   : 0 ),
				'requests' => intval( isset( $all[ $ym ][ $provider ]['requests'] ) ? $all[ $ym ][ $provider ]['requests'] : 0 ),
			);
			$all[ $ym ][ $provider ] = array( self::LEGACY_MODEL => $legacy );
		}

		if ( ! isset( $all[ $ym ][ $provider ][ $model ] ) || ! is_array( $all[ $ym ][ $provider ][ $model ] ) ) {
			$all[ $ym ][ $provider ][ $model ] = array( 'input' => 0, 'output' => 0, 'requests' => 0 );
		}

		$all[ $ym ][ $provider ][ $model ]['input']    += $input;
		$all[ $ym ][ $provider ][ $model ]['output']   += $output;
		$all[ $ym ][ $provider ][ $model ]['requests'] += 1;

		$all = self::prune( $all );

		update_option( self::OPTION, $all, false );
	}

	public static function reset_month( $ym = null ) {
		if ( null === $ym ) {
			$ym = self::current_month();
		}
		$all = self::get_all();
		if ( isset( $all[ $ym ] ) ) {
			unset( $all[ $ym ] );
			update_option( self::OPTION, $all, false );
		}
	}

	public static function reset_all() {
		update_option( self::OPTION, array(), false );
	}

	protected static function prune( $all ) {
		if ( count( $all ) <= self::MONTHS_TO_KEEP ) {
			return $all;
		}
		krsort( $all );
		return array_slice( $all, 0, self::MONTHS_TO_KEEP, true );
	}

	public static function models_for( $month_data, $provider ) {
		if ( ! isset( $month_data[ $provider ] ) || ! is_array( $month_data[ $provider ] ) ) {
			return array();
		}
		$bucket = $month_data[ $provider ];

		// Detect legacy shape: direct {input,output,requests} keys at the provider level.
		if ( isset( $bucket['input'] ) || isset( $bucket['output'] ) || isset( $bucket['requests'] ) ) {
			return array(
				self::LEGACY_MODEL => array(
					'input'    => intval( isset( $bucket['input'] )    ? $bucket['input']    : 0 ),
					'output'   => intval( isset( $bucket['output'] )   ? $bucket['output']   : 0 ),
					'requests' => intval( isset( $bucket['requests'] ) ? $bucket['requests'] : 0 ),
				),
			);
		}

		$out = array();
		foreach ( $bucket as $model => $counts ) {
			if ( ! is_array( $counts ) ) {
				continue;
			}
			$out[ (string) $model ] = array(
				'input'    => intval( isset( $counts['input'] )    ? $counts['input']    : 0 ),
				'output'   => intval( isset( $counts['output'] )   ? $counts['output']   : 0 ),
				'requests' => intval( isset( $counts['requests'] ) ? $counts['requests'] : 0 ),
			);
		}
		return $out;
	}

	public static function row_for( $month_data, $provider ) {
		$totals = array( 'input' => 0, 'output' => 0, 'requests' => 0 );
		foreach ( self::models_for( $month_data, $provider ) as $counts ) {
			$totals['input']    += $counts['input'];
			$totals['output']   += $counts['output'];
			$totals['requests'] += $counts['requests'];
		}
		return $totals;
	}

	public static function has_usage( $month_data, $provider ) {
		$row = self::row_for( $month_data, $provider );
		return ( $row['requests'] > 0 || $row['input'] > 0 || $row['output'] > 0 );
	}
}
