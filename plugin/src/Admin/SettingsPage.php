<?php
declare(strict_types=1);

namespace Rungud\Admin;

use Rungud\Audit\Audit;
use Rungud\Auth\Tokens;
use Rungud\Capabilities;
use Rungud\Config;
use Rungud\Install\Schema;
use Rungud\Settings;

/**
 * Settings → rungud. Admin only (rungud_settings). Four sections, nothing
 * else: connection (keys, CORS, mail), legal entity, VAT profiles, invoice
 * number sequence. Every save writes an audit row.
 */
final class SettingsPage {

	public const SLUG = 'rungud-settings';

	/** Config constant → [settings key, label, secret?] */
	private const CONNECTION_FIELDS = array(
		'RUNGUD_CORS_ORIGINS'   => array( 'cors_origins', 'Allowed frontend origins (one per line; * allowed inside the host for Vercel previews)', false ),
		'STRIPE_SECRET_KEY'     => array( 'stripe_secret_key', 'Stripe restricted key (read invoices, cancel subscriptions, refund)', true ),
		'STRIPE_WEBHOOK_SECRET' => array( 'stripe_webhook_secret', 'Stripe webhook signing secret (rungud endpoint, not LearnDash)', true ),
		'RUNGUD_SMTP_HOST'      => array( 'smtp_host', 'SMTP host', false ),
		'RUNGUD_SMTP_PORT'      => array( 'smtp_port', 'SMTP port', false ),
		'RUNGUD_SMTP_USER'      => array( 'smtp_user', 'SMTP user', false ),
		'RUNGUD_SMTP_PASS'      => array( 'smtp_pass', 'SMTP password', true ),
		'RUNGUD_SMTP_FROM'      => array( 'smtp_from', 'Sender address', false ),
		'RUNGUD_SMTP_FROM_NAME' => array( 'smtp_from_name', 'Sender name', false ),
	);

	private const LEGAL_FIELDS = array(
		'name'        => 'Company name',
		'owner_name'  => 'Owner',
		'street'      => 'Street',
		'postal_code' => 'Postal code',
		'city'        => 'City',
		'country'     => 'Country (ISO 2)',
		'vat_number'  => 'VAT number',
		'tax_number'  => 'Tax number',
		'iban'        => 'IBAN',
		'bic'         => 'BIC',
		'bank_name'   => 'Bank',
		'email'       => 'Email',
		'website'     => 'Website',
		'footer_en'   => 'Invoice footer (English)',
		'footer_de'   => 'Invoice footer (German)',
	);

	private const TREATMENTS = array( 'standard', 'reverse_charge', 'exempt', 'not_taxable' );

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		foreach ( array( 'connection', 'legal', 'vat', 'sequence' ) as $section ) {
			add_action( 'admin_post_rungud_save_' . $section, array( self::class, 'save_' . $section ) );
		}
	}

	public static function menu(): void {
		add_options_page( 'rungud', 'rungud', Capabilities::SETTINGS, self::SLUG, array( self::class, 'render' ) );
	}

	/* ------------------------------------------------------------ render */

	public static function render(): void {
		if ( ! current_user_can( Capabilities::SETTINGS ) ) {
			wp_die( esc_html__( 'You cannot change rungud settings.', 'rungud-cms' ) );
		}
		$notice = isset( $_GET['rungud_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['rungud_notice'] ) ) : '';
		$error  = isset( $_GET['rungud_error'] ) ? sanitize_text_field( wp_unslash( $_GET['rungud_error'] ) ) : '';
		echo '<div class="wrap"><h1>rungud</h1>';
		if ( $notice ) {
			echo '<div class="notice notice-success"><p>' . esc_html( $notice ) . '</p></div>';
		}
		if ( $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}
		self::render_connection();
		self::render_legal();
		self::render_vat();
		self::render_sequence();
		echo '</div>';
	}

	private static function form_open( string $section ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="rungud_save_' . esc_attr( $section ) . '">';
		wp_nonce_field( 'rungud_save_' . $section );
	}

	private static function render_connection(): void {
		$jwt       = (string) Config::get( 'RUNGUD_JWT_SECRET', '' );
		$jwt_state = '' === $jwt ? 'missing — login is disabled' : ( strlen( $jwt ) < Tokens::MIN_SECRET ? 'too short — login is disabled' : 'set (' . Config::source( 'RUNGUD_JWT_SECRET' ) . ')' );
		$stripe    = (string) Config::get( 'STRIPE_SECRET_KEY', '' );
		$mode      = '' === $stripe ? 'not set' : ( Config::stripe_is_test_mode() ? 'TEST mode' : 'LIVE mode' );

		echo '<h2>Connection</h2>';
		echo '<p>JWT secret: <strong>' . esc_html( $jwt_state ) . '</strong>. It is read from <code>wp-config.php</code> (<code>RUNGUD_JWT_SECRET</code>) only, never stored here.<br>';
		echo 'Stripe: <strong>' . esc_html( $mode ) . '</strong>. Staging must use a test key.</p>';
		self::form_open( 'connection' );
		echo '<table class="form-table">';
		foreach ( self::CONNECTION_FIELDS as $const => [ $key, $label, $secret ] ) {
			$source = Config::source( $const );
			$locked = 'settings' !== $source;
			$value  = Settings::get( $key, '' );
			echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
			if ( $locked ) {
				echo '<em>Set in ' . esc_html( 'constant' === $source ? 'wp-config.php' : 'the environment' ) . ' (' . esc_html( $const ) . ').</em>';
			} elseif ( 'cors_origins' === $key ) {
				echo '<textarea class="large-text code" rows="4" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( str_replace( ',', "\n", (string) $value ) ) . '</textarea>';
			} elseif ( $secret ) {
				$hint = '' === $value ? 'not set' : 'set — leave empty to keep';
				echo '<input type="password" autocomplete="new-password" class="regular-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" placeholder="' . esc_attr( $hint ) . '">';
			} else {
				echo '<input type="text" class="regular-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		submit_button( 'Save connection' );
		echo '</form>';
	}

	private static function render_legal(): void {
		$entity = self::legal_entity();
		echo '<h2>Legal entity</h2><p>Printed on hand-issued invoices and certificates. Changes do not alter invoices already approved.</p>';
		self::form_open( 'legal' );
		echo '<table class="form-table">';
		foreach ( self::LEGAL_FIELDS as $field => $label ) {
			$value = (string) ( $entity[ $field ] ?? '' );
			echo '<tr><th><label for="le_' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th><td>';
			if ( str_starts_with( $field, 'footer_' ) ) {
				echo '<textarea class="large-text" rows="3" id="le_' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '">' . esc_textarea( $value ) . '</textarea>';
			} else {
				echo '<input type="text" class="regular-text" id="le_' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '">';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		submit_button( 'Save legal entity' );
		echo '</form>';
	}

	private static function render_vat(): void {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Schema::table( 'vat_profiles' ) . ' ORDER BY archived_at IS NOT NULL, id', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		echo '<h2>VAT profiles</h2><p>Proposed on hand-issued invoices; Gudrun can change the profile at approval with a note. Profiles are archived, never deleted.</p>';
		self::form_open( 'vat' );
		echo '<table class="widefat striped"><thead><tr><th>Code</th><th>Name (EN)</th><th>Name (DE)</th><th>Country</th><th>Rate %</th><th>Treatment</th><th>Invoice note EN / DE</th><th>Why proposed EN / DE</th><th>Archived</th></tr></thead><tbody>';
		foreach ( array_merge( (array) $rows, array( array( 'id' => 'new' ) ) ) as $r ) {
			$id  = (string) $r['id'];
			$n   = static fn( string $f ): string => 'vat[' . $id . '][' . $f . ']';
			$v   = static fn( string $f ): string => (string) ( $r[ $f ] ?? '' );
			$new = 'new' === $id;
			echo '<tr>';
			echo '<td>' . ( $new ? '<input type="text" size="12" name="' . esc_attr( $n( 'code' ) ) . '" placeholder="new code">' : '<code>' . esc_html( $v( 'code' ) ) . '</code>' ) . '</td>';
			foreach ( array( 'name_en', 'name_de' ) as $f ) {
				echo '<td><input type="text" name="' . esc_attr( $n( $f ) ) . '" value="' . esc_attr( $v( $f ) ) . '"></td>';
			}
			echo '<td><input type="text" size="3" name="' . esc_attr( $n( 'country' ) ) . '" value="' . esc_attr( $v( 'country' ) ) . '"></td>';
			echo '<td><input type="text" size="6" name="' . esc_attr( $n( 'rate' ) ) . '" value="' . esc_attr( $v( 'rate' ) ) . '"></td>';
			echo '<td><select name="' . esc_attr( $n( 'treatment' ) ) . '">';
			foreach ( self::TREATMENTS as $t ) {
				echo '<option value="' . esc_attr( $t ) . '"' . selected( $v( 'treatment' ), $t, false ) . '>' . esc_html( $t ) . '</option>';
			}
			echo '</select></td>';
			echo '<td><textarea rows="2" name="' . esc_attr( $n( 'note_en' ) ) . '">' . esc_textarea( $v( 'note_en' ) ) . '</textarea><textarea rows="2" name="' . esc_attr( $n( 'note_de' ) ) . '">' . esc_textarea( $v( 'note_de' ) ) . '</textarea></td>';
			echo '<td><textarea rows="2" name="' . esc_attr( $n( 'explanation_en' ) ) . '">' . esc_textarea( $v( 'explanation_en' ) ) . '</textarea><textarea rows="2" name="' . esc_attr( $n( 'explanation_de' ) ) . '">' . esc_textarea( $v( 'explanation_de' ) ) . '</textarea></td>';
			echo '<td>' . ( $new ? '' : '<input type="checkbox" name="' . esc_attr( $n( 'archived' ) ) . '" value="1"' . checked( null !== ( $r['archived_at'] ?? null ), true, false ) . '>' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		submit_button( 'Save VAT profiles' );
		echo '</form>';
	}

	private static function render_sequence(): void {
		$seq = self::invoice_sequence();
		echo '<h2>Invoice number sequence</h2>';
		if ( ! $seq ) {
			echo '<p>No sequence found. Deactivate and activate the plugin to create it.</p>';
			return;
		}
		echo '<p>Next hand-issued invoice number: <strong>' . esc_html( $seq['prefix'] . $seq['next_number'] ) . '</strong>. Numbers are assigned only when Gudrun approves an invoice. The next number can be raised, never lowered.</p>';
		self::form_open( 'sequence' );
		echo '<input type="number" min="' . esc_attr( (string) $seq['next_number'] ) . '" name="next_number" value="' . esc_attr( (string) $seq['next_number'] ) . '"> ';
		submit_button( 'Raise next number', 'secondary', 'submit', false );
		echo '</form>';
	}

	/* -------------------------------------------------------------- save */

	private static function guard( string $section ): void {
		if ( ! current_user_can( Capabilities::SETTINGS ) ) {
			wp_die( esc_html__( 'You cannot change rungud settings.', 'rungud-cms' ), 403 );
		}
		check_admin_referer( 'rungud_save_' . $section );
	}

	private static function back( string $notice = '', string $error = '' ): void {
		$args = array( 'page' => self::SLUG );
		if ( $notice ) {
			$args['rungud_notice'] = $notice;
		}
		if ( $error ) {
			$args['rungud_error'] = $error;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function save_connection(): void {
		self::guard( 'connection' );
		foreach ( self::CONNECTION_FIELDS as $const => [ $key, , $secret ] ) {
			if ( 'settings' !== Config::source( $const ) || ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$raw = trim( (string) wp_unslash( $_POST[ $key ] ) );
			if ( $secret && '' === $raw ) {
				continue; // empty secret field = keep
			}
			$value = 'cors_origins' === $key
				? implode( ',', array_filter( array_map( 'trim', preg_split( '/[\s,]+/', $raw ) ?: array() ) ) )
				: sanitize_text_field( $raw );
			Settings::set( $key, $value );
		}
		self::back( 'Connection saved.' );
	}

	public static function save_legal(): void {
		self::guard( 'legal' );
		global $wpdb;
		$entity = self::legal_entity();
		$after  = array();
		foreach ( self::LEGAL_FIELDS as $field => $label ) {
			$raw             = (string) wp_unslash( $_POST[ $field ] ?? '' );
			$after[ $field ] = str_starts_with( $field, 'footer_' ) ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		}
		$after['country'] = strtoupper( substr( $after['country'], 0, 2 ) );
		if ( '' === $after['name'] ) {
			self::back( '', 'The company name cannot be empty.' );
		}
		$before = array_intersect_key( $entity, $after );
		if ( $before == $after ) { // phpcs:ignore Universal.Operators.StrictComparisons
			self::back( 'Nothing changed.' );
		}
		$wpdb->update( Schema::table( 'legal_entities' ), $after, array( 'id' => $entity['id'] ) );
		Audit::record(
			array(
				'entity'     => 'legal_entity',
				'entity_id'  => $entity['id'],
				'action'     => 'update',
				'before'     => $before,
				'after'      => $after,
				'summary_en' => 'Legal entity details changed.',
				'summary_de' => 'Angaben zum Unternehmen geändert.',
			)
		);
		self::back( 'Legal entity saved.' );
	}

	public static function save_vat(): void {
		self::guard( 'vat' );
		global $wpdb;
		$table = Schema::table( 'vat_profiles' );
		$input = (array) wp_unslash( $_POST['vat'] ?? array() );
		foreach ( $input as $id => $fields ) {
			$fields = (array) $fields;
			$clean  = array(
				'name_en'        => sanitize_text_field( (string) ( $fields['name_en'] ?? '' ) ),
				'name_de'        => sanitize_text_field( (string) ( $fields['name_de'] ?? '' ) ),
				'country'        => strtoupper( substr( sanitize_text_field( (string) ( $fields['country'] ?? '' ) ), 0, 2 ) ) ?: null,
				'rate'           => str_replace( ',', '.', trim( (string) ( $fields['rate'] ?? '' ) ) ),
				'treatment'      => (string) ( $fields['treatment'] ?? '' ),
				'note_en'        => sanitize_textarea_field( (string) ( $fields['note_en'] ?? '' ) ),
				'note_de'        => sanitize_textarea_field( (string) ( $fields['note_de'] ?? '' ) ),
				'explanation_en' => sanitize_textarea_field( (string) ( $fields['explanation_en'] ?? '' ) ),
				'explanation_de' => sanitize_textarea_field( (string) ( $fields['explanation_de'] ?? '' ) ),
			);
			if ( 'new' === $id ) {
				$code = sanitize_key( (string) ( $fields['code'] ?? '' ) );
				if ( '' === $code ) {
					continue;
				}
				$clean['code'] = $code;
			}
			if ( ! preg_match( '/^\d{1,2}(\.\d{1,2})?$/', $clean['rate'] ) || ! in_array( $clean['treatment'], self::TREATMENTS, true ) || '' === $clean['name_en'] || '' === $clean['name_de'] ) {
				self::back( '', 'Each VAT profile needs both names, a rate between 0 and 99.99 and a treatment.' );
			}
			if ( 'new' === $id ) {
				if ( false === $wpdb->insert( $table, $clean ) ) {
					self::back( '', 'That code already exists.' );
				}
				Audit::record(
					array(
						'entity'     => 'vat_profile',
						'entity_id'  => $wpdb->insert_id,
						'action'     => 'create',
						'after'      => $clean,
						'summary_en' => "VAT profile “{$clean['name_en']}” created.",
						'summary_de' => "MwSt.-Profil „{$clean['name_de']}“ angelegt.",
					)
				);
				continue;
			}
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
			if ( ! $row ) {
				continue;
			}
			$archive              = ! empty( $fields['archived'] );
			$clean['archived_at'] = $archive ? ( $row['archived_at'] ?? gmdate( 'Y-m-d H:i:s' ) ) : null;
			$clean['rate']        = number_format( (float) $clean['rate'], 2, '.', '' );
			$before               = array_intersect_key( $row, $clean );
			if ( $before == $clean ) { // phpcs:ignore Universal.Operators.StrictComparisons
				continue;
			}
			$wpdb->update( $table, $clean, array( 'id' => (int) $id ) );
			Audit::record(
				array(
					'entity'     => 'vat_profile',
					'entity_id'  => (int) $id,
					'action'     => 'update',
					'before'     => $before,
					'after'      => $clean,
					'summary_en' => "VAT profile “{$clean['name_en']}” changed.",
					'summary_de' => "MwSt.-Profil „{$clean['name_de']}“ geändert.",
				)
			);
		}
		self::back( 'VAT profiles saved.' );
	}

	public static function save_sequence(): void {
		self::guard( 'sequence' );
		$result = self::raise_sequence( (int) ( $_POST['next_number'] ?? 0 ) );
		if ( is_wp_error( $result ) ) {
			self::back( '', $result->get_error_message() );
		}
		self::back( 'Next invoice number saved.' );
	}

	/**
	 * Raises the next invoice number inside a transaction with the sequence row
	 * locked, so it can never race an approval. Lowering is refused.
	 *
	 * @return true|\WP_Error
	 */
	public static function raise_sequence( int $next ) {
		global $wpdb;
		$seq_t = Schema::table( 'number_sequences' );
		$wpdb->query( 'START TRANSACTION' );
		$seq = self::invoice_sequence( true );
		if ( ! $seq ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'rungud_no_sequence', 'No invoice sequence exists.' );
		}
		$current = (int) $seq['next_number'];
		if ( $next <= $current ) {
			$wpdb->query( 'ROLLBACK' );
			return $next === $current ? true : new \WP_Error( 'rungud_sequence_lower', "The next number can only be raised. It is {$current} now." );
		}
		$wpdb->update( $seq_t, array( 'next_number' => $next, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $seq['id'] ) );
		Audit::record(
			array(
				'entity'     => 'number_sequence',
				'entity_id'  => $seq['id'],
				'action'     => 'raise',
				'before'     => array( 'next_number' => $current ),
				'after'      => array( 'next_number' => $next ),
				'summary_en' => "Next invoice number raised from {$current} to {$next}.",
				'summary_de' => "Nächste Rechnungsnummer von {$current} auf {$next} erhöht.",
			)
		);
		$wpdb->query( 'COMMIT' );
		return true;
	}

	/** @return array<string,mixed> */
	private static function legal_entity(): array {
		global $wpdb;
		return (array) $wpdb->get_row( 'SELECT * FROM ' . Schema::table( 'legal_entities' ) . ' WHERE archived_at IS NULL ORDER BY id LIMIT 1', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** @return array<string,mixed>|null */
	private static function invoice_sequence( bool $lock = false ): ?array {
		global $wpdb;
		$entity = self::legal_entity();
		if ( ! $entity ) {
			return null;
		}
		$sql = $wpdb->prepare(
			'SELECT * FROM ' . Schema::table( 'number_sequences' ) . " WHERE doc_type = 'invoice' AND legal_entity_id = %d" . ( $lock ? ' FOR UPDATE' : '' ), // phpcs:ignore WordPress.DB.PreparedSQL
			$entity['id']
		);
		return $wpdb->get_row( $sql, ARRAY_A ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
