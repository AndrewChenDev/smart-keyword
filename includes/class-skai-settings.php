<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_Settings {

	const OPTION = 'skai_options';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	public static function on_activate() {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}

	public static function defaults() {
		return array(
			'provider'           => 'openai',
			'openai_key'         => '',
			'openai_model'       => 'gpt-4o-mini',
			'anthropic_key'      => '',
			'anthropic_model'    => 'claude-haiku-4-5',
			'gemini_key'         => '',
			'gemini_model'       => 'gemini-3.1-flash-lite',
			'deepseek_key'       => '',
			'deepseek_model'     => 'deepseek-chat',
			'tag_count'          => 6,
			'max_content_chars'  => 4000,
		);
	}

	public static function default_model( $provider ) {
		$d = self::defaults();
		$k = $provider . '_model';
		return isset( $d[ $k ] ) ? $d[ $k ] : '';
	}

	public static function get( $key ) {
		$opts = get_option( self::OPTION, array() );
		$opts = is_array( $opts ) ? array_merge( self::defaults(), $opts ) : self::defaults();
		return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
	}

	public function add_menu() {
		add_options_page(
			__( 'Smart Keyword AI', 'smart-keyword-ai' ),
			__( 'Smart Keyword AI', 'smart-keyword-ai' ),
			'manage_options',
			'skai-settings',
			array( $this, 'render_page' )
		);
	}

	public function register() {
		register_setting( 'skai_group', self::OPTION, array( $this, 'sanitize' ) );

		add_settings_section( 'skai_main', __( 'AI Settings', 'smart-keyword-ai' ), '__return_false', 'skai-settings' );

		add_settings_field( 'provider', __( 'Active AI Provider', 'smart-keyword-ai' ), array( $this, 'field_provider' ), 'skai-settings', 'skai_main' );

		foreach ( array(
			'openai'    => 'OpenAI',
			'anthropic' => 'Anthropic',
			'gemini'    => 'Gemini',
			'deepseek'  => 'DeepSeek',
		) as $slug => $label ) {
			add_settings_field(
				$slug . '_key',
				sprintf( __( '%s API Key', 'smart-keyword-ai' ), $label ),
				array( $this, 'field_text' ),
				'skai-settings',
				'skai_main',
				array(
					'key'      => $slug . '_key',
					'type'     => 'password',
					'class'    => 'regular-text',
				)
			);
			add_settings_field(
				$slug . '_model',
				sprintf( __( '%s Model', 'smart-keyword-ai' ), $label ),
				array( $this, 'field_text' ),
				'skai-settings',
				'skai_main',
				array(
					'key'         => $slug . '_model',
					'type'        => 'text',
					'class'       => 'regular-text',
					'placeholder' => self::default_model( $slug ),
					'description' => sprintf( __( 'Default: %s', 'smart-keyword-ai' ), self::default_model( $slug ) ),
				)
			);
		}

		add_settings_field( 'tag_count', __( 'Tags per generation', 'smart-keyword-ai' ), array( $this, 'field_text' ), 'skai-settings', 'skai_main', array(
			'key'   => 'tag_count',
			'type'  => 'number',
			'class' => 'small-text',
			'min'   => 1,
			'max'   => 20,
		) );

		add_settings_field( 'max_content_chars', __( 'Max characters sent to AI', 'smart-keyword-ai' ), array( $this, 'field_text' ), 'skai-settings', 'skai_main', array(
			'key'         => 'max_content_chars',
			'type'        => 'number',
			'class'       => 'small-text',
			'min'         => 500,
			'max'         => 20000,
			'description' => __( 'Content exceeding this length is truncated to reduce token cost.', 'smart-keyword-ai' ),
		) );
	}

	public function field_provider() {
		$current = self::get( 'provider' );
		$opts    = array(
			'openai'    => 'OpenAI',
			'anthropic' => 'Anthropic',
			'gemini'    => 'Gemini',
			'deepseek'  => 'DeepSeek',
		);
		echo '<select name="' . esc_attr( self::OPTION ) . '[provider]">';
		foreach ( $opts as $v => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $v ),
				selected( $current, $v, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	public function field_text( $args ) {
		$key         = $args['key'];
		$type        = isset( $args['type'] ) ? $args['type'] : 'text';
		$class       = isset( $args['class'] ) ? $args['class'] : 'regular-text';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		$min         = isset( $args['min'] ) ? ' min="' . intval( $args['min'] ) . '"' : '';
		$max         = isset( $args['max'] ) ? ' max="' . intval( $args['max'] ) . '"' : '';
		$value       = self::get( $key );

		printf(
			'<input type="%s" class="%s" name="%s[%s]" value="%s" placeholder="%s"%s%s />',
			esc_attr( $type ),
			esc_attr( $class ),
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_attr( (string) $value ),
			esc_attr( $placeholder ),
			$min,
			$max
		);

		if ( ! empty( $args['description'] ) ) {
			echo ' <span class="description">' . esc_html( $args['description'] ) . '</span>';
		}
	}

	public function sanitize( $input ) {
		$out      = self::defaults();
		$valid_p  = array( 'openai', 'anthropic', 'gemini', 'deepseek' );

		if ( isset( $input['provider'] ) && in_array( $input['provider'], $valid_p, true ) ) {
			$out['provider'] = $input['provider'];
		}

		foreach ( $valid_p as $p ) {
			if ( isset( $input[ $p . '_key' ] ) ) {
				$out[ $p . '_key' ] = trim( sanitize_text_field( $input[ $p . '_key' ] ) );
			}
			if ( isset( $input[ $p . '_model' ] ) ) {
				$m = trim( sanitize_text_field( $input[ $p . '_model' ] ) );
				$out[ $p . '_model' ] = $m !== '' ? $m : self::default_model( $p );
			}
		}

		if ( isset( $input['tag_count'] ) ) {
			$out['tag_count'] = max( 1, min( 20, intval( $input['tag_count'] ) ) );
		}
		if ( isset( $input['max_content_chars'] ) ) {
			$out['max_content_chars'] = max( 500, min( 20000, intval( $input['max_content_chars'] ) ) );
		}

		return $out;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Smart Keyword AI', 'smart-keyword-ai' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'skai_group' );
				do_settings_sections( 'skai-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
