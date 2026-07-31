<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Skai_Settings {

	const OPTION = 'skai_options';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'on_options_updated' ), 10, 2 );
		add_action( 'admin_post_skai_reset_usage_month', array( $this, 'handle_reset_usage_month' ) );
		add_action( 'admin_post_skai_reset_usage_all',   array( $this, 'handle_reset_usage_all' ) );
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
			'max_content_chars'  => 0,
			'custom_instruction' => '',
		);
	}

	public static function providers() {
		return array(
			'openai'    => 'OpenAI',
			'anthropic' => 'Anthropic',
			'gemini'    => 'Gemini',
			'deepseek'  => 'DeepSeek',
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

		// --- General tab ---
		add_settings_section( 'skai_general', '', '__return_false', 'skai-settings' );

		add_settings_field( 'provider', __( 'Active AI Provider', 'smart-keyword-ai' ), array( $this, 'field_provider' ), 'skai-settings', 'skai_general' );

		add_settings_field(
			'custom_instruction',
			__( 'Custom instruction', 'smart-keyword-ai' ),
			array( $this, 'field_textarea' ),
			'skai-settings',
			'skai_general',
			array(
				'key'         => 'custom_instruction',
				'rows'        => 5,
				'class'       => 'large-text',
				'description' => __( 'Optional extra guidance appended to the AI prompt. Output format stays JSON regardless of what you write here.', 'smart-keyword-ai' ),
			)
		);

		add_settings_field( 'tag_count', __( 'Tags per generation', 'smart-keyword-ai' ), array( $this, 'field_text' ), 'skai-settings', 'skai_general', array(
			'key'   => 'tag_count',
			'type'  => 'number',
			'class' => 'small-text',
			'min'   => 1,
			'max'   => 20,
		) );

		add_settings_field( 'max_content_chars', __( 'Max characters sent to AI', 'smart-keyword-ai' ), array( $this, 'field_text' ), 'skai-settings', 'skai_general', array(
			'key'         => 'max_content_chars',
			'type'        => 'number',
			'class'       => 'regular-text',
			'description' => __( '0 = no limit (send the full article to the AI). Otherwise, this caps how many characters of the article are sent — no minimum or maximum enforced.', 'smart-keyword-ai' ),
		) );

		// --- API & Models tab ---
		add_settings_section( 'skai_api', '', '__return_false', 'skai-settings' );

		foreach ( self::providers() as $slug => $label ) {
			add_settings_field(
				$slug . '_key',
				sprintf( __( '%s API Key', 'smart-keyword-ai' ), $label ),
				array( $this, 'field_text' ),
				'skai-settings',
				'skai_api',
				array(
					'key'   => $slug . '_key',
					'type'  => 'password',
					'class' => 'regular-text',
				)
			);
			add_settings_field(
				$slug . '_model',
				sprintf( __( '%s Model', 'smart-keyword-ai' ), $label ),
				array( $this, 'field_model_select' ),
				'skai-settings',
				'skai_api',
				array(
					'key'      => $slug . '_model',
					'provider' => $slug,
				)
			);
		}
	}

	public function field_provider() {
		$current = self::get( 'provider' );
		$opts    = self::providers();
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

	public function field_textarea( $args ) {
		$key   = $args['key'];
		$rows  = isset( $args['rows'] ) ? intval( $args['rows'] ) : 5;
		$class = isset( $args['class'] ) ? $args['class'] : 'large-text';
		$value = self::get( $key );

		printf(
			'<textarea name="%s[%s]" rows="%d" class="%s">%s</textarea>',
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			$rows,
			esc_attr( $class ),
			esc_textarea( (string) $value )
		);

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	public function field_model_select( $args ) {
		$key      = $args['key'];
		$provider = $args['provider'];
		$current  = (string) self::get( $key );
		$default  = self::default_model( $provider );

		$options = array();
		if ( $default !== '' ) {
			$options[] = $default;
		}
		if ( $current !== '' && $current !== $default ) {
			$options[] = $current;
		}
		$options = array_values( array_unique( $options ) );

		$is_custom = false;

		echo '<select class="skai-model-select" data-provider="' . esc_attr( $provider ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']">';
		foreach ( $options as $opt ) {
			$price       = Skai_Pricing::get_for( $provider, $opt );
			$price_label = is_array( $price ) ? ' ' . Skai_Pricing::format_price_label( $price ) : '';
			printf(
				'<option value="%s" data-price-input="%s" data-price-output="%s"%s>%s</option>',
				esc_attr( $opt ),
				is_array( $price ) ? esc_attr( $price['input'] )  : '',
				is_array( $price ) ? esc_attr( $price['output'] ) : '',
				selected( $current, $opt, false ),
				esc_html( $opt . $price_label )
			);
		}
		printf( '<option value="__custom__"%s>%s</option>', selected( $is_custom, true, false ), esc_html__( 'Custom…', 'smart-keyword-ai' ) );
		echo '</select> ';

		printf(
			'<input type="text" class="skai-model-custom regular-text" data-provider="%s" value="%s" placeholder="%s" style="display:none;margin-left:6px;" />',
			esc_attr( $provider ),
			esc_attr( $current ),
			esc_attr__( 'Type a model ID', 'smart-keyword-ai' )
		);

		printf(
			' <button type="button" class="button skai-refresh-models" data-provider="%s">%s</button>',
			esc_attr( $provider ),
			esc_html__( 'Refresh from API', 'smart-keyword-ai' )
		);

		echo ' <span class="skai-model-status" data-provider="' . esc_attr( $provider ) . '" style="margin-left:6px;color:#646970;"></span>';

		$current_price = Skai_Pricing::get_for( $provider, $current );
		$price_text    = is_array( $current_price )
			? sprintf( __( 'Price: %s', 'smart-keyword-ai' ), Skai_Pricing::format_price_label( $current_price ) )
			: __( 'Price: unknown for this model', 'smart-keyword-ai' );
		echo '<p class="description skai-model-price" data-provider="' . esc_attr( $provider ) . '">' . esc_html( $price_text ) . '</p>';

		echo '<p class="description">' . sprintf(
			/* translators: %s: default model id */
			esc_html__( 'Default: %s', 'smart-keyword-ai' ),
			'<code>' . esc_html( $default ) . '</code>'
		) . '</p>';
	}

	public function sanitize( $input ) {
		$out      = self::defaults();
		$existing = get_option( self::OPTION, array() );
		if ( is_array( $existing ) ) {
			$out = array_merge( $out, $existing );
		}

		$valid_p  = array_keys( self::providers() );

		if ( isset( $input['provider'] ) && in_array( $input['provider'], $valid_p, true ) ) {
			$out['provider'] = $input['provider'];
		}

		foreach ( $valid_p as $p ) {
			if ( isset( $input[ $p . '_key' ] ) ) {
				$out[ $p . '_key' ] = trim( sanitize_text_field( $input[ $p . '_key' ] ) );
			}
			if ( isset( $input[ $p . '_model' ] ) ) {
				$m = trim( sanitize_text_field( $input[ $p . '_model' ] ) );
				if ( $m === '__custom__' || $m === '' ) {
					$m = self::default_model( $p );
				}
				$out[ $p . '_model' ] = $m;
			}
		}

		if ( isset( $input['tag_count'] ) ) {
			$out['tag_count'] = max( 1, min( 20, intval( $input['tag_count'] ) ) );
		}
		if ( isset( $input['max_content_chars'] ) ) {
			$out['max_content_chars'] = max( 0, intval( $input['max_content_chars'] ) );
		}

		if ( isset( $input['custom_instruction'] ) ) {
			$custom = sanitize_textarea_field( wp_unslash( $input['custom_instruction'] ) );
			if ( strlen( $custom ) > 2000 ) {
				$custom = substr( $custom, 0, 2000 );
			}
			$out['custom_instruction'] = $custom;
		}

		return $out;
	}

	public function on_options_updated( $old, $new ) {
		if ( ! is_array( $old ) || ! is_array( $new ) ) {
			return;
		}
		foreach ( array_keys( self::providers() ) as $p ) {
			$old_key = isset( $old[ $p . '_key' ] ) ? $old[ $p . '_key' ] : '';
			$new_key = isset( $new[ $p . '_key' ] ) ? $new[ $p . '_key' ] : '';
			if ( $old_key !== $new_key ) {
				if ( $old_key !== '' ) {
					delete_transient( 'skai_models_' . $p . '_' . md5( (string) $old_key ) );
				}
				if ( $new_key !== '' ) {
					delete_transient( 'skai_models_' . $p . '_' . md5( (string) $new_key ) );
				}
			}
		}
	}

	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== 'settings_page_skai-settings' ) {
			return;
		}
		wp_enqueue_style(
			'skai-settings',
			SKAI_URL . 'assets/settings.css',
			array(),
			SKAI_VERSION
		);
		wp_enqueue_script(
			'skai-settings',
			SKAI_URL . 'assets/settings.js',
			array( 'jquery' ),
			SKAI_VERSION,
			true
		);
		wp_localize_script( 'skai-settings', 'SKAI_SETTINGS', array(
			'ajaxurl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'skai_fetch_models' ),
			'i18n'             => array(
				'loading'        => __( 'Loading…', 'smart-keyword-ai' ),
				'loaded'         => __( 'Loaded %d models', 'smart-keyword-ai' ),
				'no_key'         => __( 'No API key set — type a model name below.', 'smart-keyword-ai' ),
				'error'          => __( 'Could not fetch models.', 'smart-keyword-ai' ),
				'confirm_month'  => __( 'Reset usage counts for the current month?', 'smart-keyword-ai' ),
				'confirm_all'    => __( 'Reset all usage history? This cannot be undone.', 'smart-keyword-ai' ),
				'price_known'    => __( 'Price: %s', 'smart-keyword-ai' ),
				'price_unknown'  => __( 'Price: unknown for this model', 'smart-keyword-ai' ),
			),
		) );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = array(
			'general' => __( 'General', 'smart-keyword-ai' ),
			'api'     => __( 'API & Models', 'smart-keyword-ai' ),
			'usage'   => __( 'Usage', 'smart-keyword-ai' ),
		);

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'general';
		if ( ! isset( $tabs[ $current_tab ] ) ) {
			$current_tab = 'general';
		}

		$base_url = admin_url( 'options-general.php?page=skai-settings' );
		?>
		<div class="wrap skai-settings">
			<h1><?php esc_html_e( 'Smart Keyword AI', 'smart-keyword-ai' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) :
					$active = ( $slug === $current_tab ) ? ' nav-tab-active' : '';
					$url    = esc_url( add_query_arg( 'tab', $slug, $base_url ) );
					?>
					<a href="<?php echo $url; ?>" class="nav-tab<?php echo esc_attr( $active ); ?>" data-tab="<?php echo esc_attr( $slug ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'skai_group' ); ?>

				<div class="skai-tab-pane" data-tab="general" <?php echo $current_tab === 'general' ? '' : 'style="display:none;"'; ?>>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'skai-settings', 'skai_general' ); ?>
					</table>
				</div>

				<div class="skai-tab-pane" data-tab="api" <?php echo $current_tab === 'api' ? '' : 'style="display:none;"'; ?>>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'skai-settings', 'skai_api' ); ?>
					</table>
				</div>

				<div class="skai-submit-wrap" <?php echo $current_tab === 'usage' ? 'style="display:none;"' : ''; ?>>
					<?php submit_button(); ?>
				</div>
			</form>

			<div class="skai-tab-pane" data-tab="usage" <?php echo $current_tab === 'usage' ? '' : 'style="display:none;"'; ?>>
				<?php $this->render_usage_pane(); ?>
			</div>
		</div>
		<?php
	}

	protected function render_usage_pane() {
		$all          = Skai_Usage::get_all();
		$current_ym   = Skai_Usage::current_month();
		$current_data = isset( $all[ $current_ym ] ) ? $all[ $current_ym ] : array();

		krsort( $all );
		$history = $all;
		unset( $history[ $current_ym ] );

		echo '<h2>' . esc_html( sprintf(
			/* translators: %s: month label like "May 2026" */
			__( 'Token usage — %s', 'smart-keyword-ai' ),
			$this->format_month_label( $current_ym )
		) ) . '</h2>';

		$this->render_usage_table( $current_data );

		echo '<p class="description">' . esc_html__( 'Token counts come from each provider\'s API response. Buckets reset automatically on the 1st of each month.', 'smart-keyword-ai' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Est. cost uses OpenRouter pricing for the specific model each request hit (cached daily).', 'smart-keyword-ai' ) . '</p>';

		$reset_url = admin_url( 'admin-post.php' );
		?>
		<p>
			<form method="post" action="<?php echo esc_url( $reset_url ); ?>" style="display:inline-block;margin-right:8px;">
				<?php wp_nonce_field( 'skai_reset_usage' ); ?>
				<input type="hidden" name="action" value="skai_reset_usage_month" />
				<button type="submit" class="button skai-reset-month"><?php esc_html_e( 'Reset current month', 'smart-keyword-ai' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( $reset_url ); ?>" style="display:inline-block;">
				<?php wp_nonce_field( 'skai_reset_usage' ); ?>
				<input type="hidden" name="action" value="skai_reset_usage_all" />
				<button type="submit" class="button skai-reset-all"><?php esc_html_e( 'Reset all history', 'smart-keyword-ai' ); ?></button>
			</form>
		</p>
		<?php

		if ( ! empty( $history ) ) {
			echo '<details class="skai-usage-history"><summary>' . esc_html__( 'Previous months', 'smart-keyword-ai' ) . '</summary>';
			foreach ( $history as $ym => $data ) {
				echo '<h3>' . esc_html( $this->format_month_label( $ym ) ) . '</h3>';
				$this->render_usage_table( $data );
			}
			echo '</details>';
		}
	}

	protected function render_usage_table( $month_data ) {
		$active_providers = array();
		foreach ( self::providers() as $slug => $label ) {
			if ( Skai_Usage::has_usage( $month_data, $slug ) ) {
				$active_providers[ $slug ] = $label;
			}
		}

		if ( empty( $active_providers ) ) {
			echo '<p><em>' . esc_html__( 'No usage recorded for this month.', 'smart-keyword-ai' ) . '</em></p>';
			return;
		}

		echo '<table class="widefat striped skai-usage-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Provider', 'smart-keyword-ai' ) . '</th>';
		echo '<th>' . esc_html__( 'Requests', 'smart-keyword-ai' ) . '</th>';
		echo '<th>' . esc_html__( 'Input tokens', 'smart-keyword-ai' ) . '</th>';
		echo '<th>' . esc_html__( 'Output tokens', 'smart-keyword-ai' ) . '</th>';
		echo '<th>' . esc_html__( 'Total tokens', 'smart-keyword-ai' ) . '</th>';
		echo '<th>' . esc_html__( 'Est. cost (USD)', 'smart-keyword-ai' ) . '</th>';
		echo '</tr></thead><tbody>';

		$grand_total_cost = 0.0;
		$any_known_price  = false;

		foreach ( $active_providers as $slug => $label ) {
			$models = Skai_Usage::models_for( $month_data, $slug );

			$provider_input    = 0;
			$provider_output   = 0;
			$provider_requests = 0;
			$provider_cost     = 0.0;
			$provider_has_cost = false;
			$model_costs       = array();

			foreach ( $models as $model_id => $counts ) {
				$provider_input    += $counts['input'];
				$provider_output   += $counts['output'];
				$provider_requests += $counts['requests'];

				$price = Skai_Pricing::get_for( $slug, $model_id );
				$cost  = Skai_Pricing::estimate_cost_usd( $price, $counts['input'], $counts['output'] );
				$model_costs[ $model_id ] = $cost;
				if ( null !== $cost ) {
					$provider_cost   += $cost;
					$provider_has_cost = true;
				}
			}

			if ( $provider_has_cost ) {
				$any_known_price   = true;
				$grand_total_cost += $provider_cost;
			}

			$provider_total = $provider_input + $provider_output;

			echo '<tr class="skai-provider-row">';
			echo '<td><button type="button" class="skai-toggle" aria-expanded="false" data-label="' . esc_attr( $label ) . '">' . esc_html( '▶ ' . $label ) . '</button></td>';
			echo '<td>' . esc_html( number_format_i18n( $provider_requests ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $provider_input ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $provider_output ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $provider_total ) ) . '</td>';
			echo '<td>' . ( $provider_has_cost ? esc_html( '$' . number_format_i18n( $provider_cost, 4 ) ) : '—' ) . '</td>';
			echo '</tr>';

			echo '<tr class="skai-model-rows" hidden><td colspan="6">';
			echo '<table class="skai-model-subtable"><tbody>';
			foreach ( $models as $model_id => $counts ) {
				$mtotal = $counts['input'] + $counts['output'];
				$mcost  = isset( $model_costs[ $model_id ] ) ? $model_costs[ $model_id ] : null;
				echo '<tr>';
				echo '<td><code>' . esc_html( $model_id ) . '</code></td>';
				echo '<td>' . esc_html( number_format_i18n( $counts['requests'] ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $counts['input'] ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $counts['output'] ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $mtotal ) ) . '</td>';
				echo '<td>' . ( null === $mcost ? '—' : esc_html( '$' . number_format_i18n( $mcost, 4 ) ) ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '</td></tr>';
		}

		if ( $any_known_price ) {
			echo '<tr><td colspan="5" style="text-align:right;font-weight:600;">' . esc_html__( 'Estimated total', 'smart-keyword-ai' ) . '</td>';
			echo '<td style="font-weight:600;">' . esc_html( '$' . number_format_i18n( $grand_total_cost, 4 ) ) . '</td></tr>';
		}

		echo '</tbody></table>';
	}

	protected function format_month_label( $ym ) {
		$ts = strtotime( $ym . '-01' );
		if ( ! $ts ) {
			return $ym;
		}
		return date_i18n( 'F Y', $ts );
	}

	public function handle_reset_usage_month() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'smart-keyword-ai' ) );
		}
		check_admin_referer( 'skai_reset_usage' );
		Skai_Usage::reset_month();
		wp_safe_redirect( add_query_arg( array( 'page' => 'skai-settings', 'tab' => 'usage', 'reset' => 'month' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public function handle_reset_usage_all() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'smart-keyword-ai' ) );
		}
		check_admin_referer( 'skai_reset_usage' );
		Skai_Usage::reset_all();
		wp_safe_redirect( add_query_arg( array( 'page' => 'skai-settings', 'tab' => 'usage', 'reset' => 'all' ), admin_url( 'options-general.php' ) ) );
		exit;
	}
}
