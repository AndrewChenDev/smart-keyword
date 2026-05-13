<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_Content {

	public static function prepare( $raw, $max_chars ) {
		if ( ! is_string( $raw ) || $raw === '' ) {
			return '';
		}

		$c = $raw;

		// Drop media-bearing shortcodes (with and without closing tags).
		$c = preg_replace( '#\[(caption|gallery|embed|video|audio|playlist)[^\]]*\].*?\[/\1\]#is', ' ', $c );
		$c = preg_replace( '#\[(caption|gallery|embed|video|audio|playlist|img)[^\]]*\]#i', ' ', $c );

		// Remove remaining shortcodes.
		if ( function_exists( 'strip_shortcodes' ) ) {
			$c = strip_shortcodes( $c );
		}

		// Kill <img>, <picture>, <figure> blocks before strip_all_tags so their alt/src text doesn't leak.
		$c = preg_replace( '#<figure\b[^>]*>.*?</figure>#is', ' ', $c );
		$c = preg_replace( '#<picture\b[^>]*>.*?</picture>#is', ' ', $c );
		$c = preg_replace( '#<img\b[^>]*/?>#i', ' ', $c );

		// Strip remaining HTML and collapse whitespace.
		$c = wp_strip_all_tags( $c, true );

		// Remove stray image URLs.
		$c = preg_replace( '#https?://\S+\.(?:jpe?g|png|gif|webp|svg|bmp|tiff?)(?:\?\S*)?#i', ' ', $c );

		// Collapse whitespace runs (mb-safe enough for ascii whitespace).
		$c = preg_replace( '/\s+/u', ' ', $c );
		$c = trim( $c );

		$max_chars = max( 100, intval( $max_chars ) );
		if ( function_exists( 'mb_substr' ) ) {
			$c = mb_substr( $c, 0, $max_chars, 'UTF-8' );
		} else {
			$c = substr( $c, 0, $max_chars );
		}

		return $c;
	}
}
