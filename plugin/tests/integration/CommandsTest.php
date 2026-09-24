<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Audit\Audit;
use Rungud\Capabilities as C;
use Rungud\Tests\Fixtures\SiteStore;

/** Phase 3: commands through the site double and WooCommerce. */
final class CommandsTest extends TestCase {

	private \WP_User $gudrun;
	private \WP_User $robert;
	private \WP_User $jonas;

	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( SiteStore::class ) ) {
			$this->markTestSkipped( 'Runs against the site double only.' );
		}
		$this->gudrun = $this->make_user( C::ROLE_BACKOFFICE );
		$this->robert = $this->make_user( C::ROLE_OWNER );
		$this->jonas  = get_userdata( self::factory()->user->create( array( 'role' => 'customer', 'first_name' => 'Jonas', 'last_name' => 'Weber', 'user_email' => 'jonas@example.com' ) ) );
		wp_set_current_user( $this->gudrun->ID );
	}

	private function rid(): string {
		return wp_generate_uuid4();
	}

	public function test_licence_verify_reaches_the_site_with_a_fresh_idempotency_key_and_is_audited(): void {
		$res = $this->call( 'POST', "/people/{$this->jonas->ID}/licences/breath-coach/verify", array( 'request_id' => $this->rid() ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( '/licences/verdict', SiteStore::$received[0]['route'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', SiteStore::$received[0]['key'] );
		$row = Audit::find( $res->get_data()['audit_id'] );
		$this->assertSame( 'ok', $row['status'] );
		$this->assertStringContainsString( 'Jonas Weber', $row['summary_de'] );
		$this->assertStringContainsString( 'verifiziert', $row['summary_de'] );
	}

	public function test_reject_without_reason_is_stopped_before_anything_is_sent(): void {
		$res = $this->call( 'POST', "/people/{$this->jonas->ID}/licences/breath-coach/reject", array( 'request_id' => $this->rid() ) );
		$this->assertError( $res, 400, 'rungud_invalid' );
		$this->assertSame( array(), SiteStore::$received );
	}

	public function test_same_request_id_runs_once(): void {
		$rid = $this->rid();
		$this->call( 'POST', "/people/{$this->jonas->ID}/licences", array( 'request_id' => $rid, 'program' => 'breath-coach', 'number' => 'bw-1044' ) );
		$again = $this->call( 'POST', "/people/{$this->jonas->ID}/licences", array( 'request_id' => $rid, 'program' => 'breath-coach', 'number' => 'bw-1044' ) );
		$this->assertTrue( $again->get_data()['replay'] );
		$this->assertCount( 1, SiteStore::$received );
		$this->assertSame( 'BW-1044', SiteStore::$received[0]['params']['number'] );
	}

	public function test_site_down_is_failed_on_today_and_a_manual_retry_resolves_it(): void {
		SiteStore::$fail['/access/grant'] = new \WP_Error( 'inzentive_api_no_group', 'Access cannot be opened right now.', array( 'status' => 503 ) );
		$until = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
		$res   = $this->call( 'POST', "/people/{$this->jonas->ID}/access/grant", array( 'request_id' => $this->rid(), 'scope' => 'b2c', 'expires_at' => $until, 'reason' => 'Goodwill' ) );
		$this->assertError( $res, 502, 'rungud_command_failed' );
		$failed = $res->get_data()['data']['audit_id'];
		$this->assertStringStartsWith( 'Nicht ausgeführt:', Audit::find( $failed )['summary_de'] );

		$today = $this->call( 'GET', '/today' )->get_data()['items'];
		$this->assertSame( 'commands_failed', $today[0]['key'] );

		// Website is back; Gudrun retries by hand. The site gets a NEW key (a stored refusal must not be replayed).
		unset( SiteStore::$fail['/access/grant'] );
		$retry = $this->call( 'POST', "/audit/{$failed}/retry", array( 'request_id' => $this->rid() ) );
		$this->assertSame( 200, $retry->get_status(), wp_json_encode( $retry->get_data() ) );
		$this->assertNotSame( SiteStore::$received[0]['key'], SiteStore::$received[1]['key'] );
		$this->assertSame( $failed, (int) Audit::find( $retry->get_data()['audit_id'] )['retry_of'] );
		$this->assertSame( array(), $this->call( 'GET', '/today' )->get_data()['items'] );
	}

	public function test_a_refusal_by_the_site_is_logged_but_not_on_today(): void {
		SiteStore::$fail['/access/grant'] = new \WP_Error( 'inzentive_api_shorter', 'This account already has access until 2026-12-31.', array( 'status' => 409 ) );
		$res = $this->call( 'POST', "/people/{$this->jonas->ID}/access/grant", array( 'request_id' => $this->rid(), 'scope' => 'all', 'expires_at' => gmdate( 'Y-m-d', strtotime( '+3 days' ) ), 'reason' => 'x' ) );
		$this->assertError( $res, 422, 'rungud_refused' );
		$this->assertStringContainsString( '2026-12-31', $res->get_data()['message'] );
		$this->assertSame( 0, Audit::count_unresolved_failures() );
	}

	public function test_membership_commands_report_that_the_site_lacks_them(): void {
		$res = $this->call( 'POST', "/people/{$this->jonas->ID}/membership/cancel-now", array( 'request_id' => $this->rid() ) );
		$this->assertError( $res, 501, 'rungud_site_missing' );
		$caps = $this->call( 'GET', '/site/capabilities' )->get_data();
		$this->assertFalse( $caps['membership']['cancel-now'] );
		$this->assertTrue( $caps['licences'] );
		$this->assertSame( 'log', $caps['mail_mode'] );
	}

	public function test_redeem_code_is_a_woo_coupon_with_the_site_meta(): void {
		$res = $this->call( 'POST', "/people/{$this->jonas->ID}/redeem-codes", array( 'request_id' => $this->rid(), 'days' => 7, 'scope' => 'b2c', 'restrict' => true, 'note' => 'trial' ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$data   = $res->get_data()['result'];
		$this->assertMatchesRegularExpression( '/^TRY-[A-HJ-NP-Z2-9]{4}$/', $data['code'] );
		$coupon = new \WC_Coupon( $data['code'] );
		$this->assertSame( 'yes', $coupon->get_meta( '_inzentive_valid_card' ) );
		$this->assertSame( 7, (int) $coupon->get_meta( '_inzentive_card_days' ) );
		$this->assertSame( 'b2c', $coupon->get_meta( '_inzentive_card_scope' ) );
		$this->assertSame( array( 'jonas@example.com' ), $coupon->get_email_restrictions() );
		$this->assertSame( 1, $coupon->get_usage_limit_per_user() );
		$list = $this->call( 'GET', "/people/{$this->jonas->ID}/redeem-codes" )->get_data()['items'];
		$this->assertSame( $data['code'], $list[0]['code'] );
		$this->assertSame( 'open', $list[0]['state'] );
	}

	private function company_order( int $quantity ): array {
		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_name( 'Immersion' );
		$item->set_quantity( $quantity );
		$item->set_total( '1600.00' );
		$order->add_item( $item );
		$order->set_billing_first_name( 'Petra' );
		$order->set_billing_last_name( 'Schmidt' );
		$order->set_total( '1600.00' );
		$order->save();
		return array( $order, $item );
	}

	public function test_attendees_on_the_line_item_length_equals_quantity(): void {
		[ $order, $item ] = $this->company_order( 2 );
		$bad = $this->call( 'POST', "/orders/{$order->get_id()}/attendees", array( 'request_id' => $this->rid(), 'item_id' => $item->get_id(), 'attendees' => array( array( 'name' => 'Only one' ) ) ) );
		$this->assertError( $bad, 422, 'rungud_refused' );

		$ok = $this->call( 'POST', "/orders/{$order->get_id()}/attendees", array( 'request_id' => $this->rid(), 'item_id' => $item->get_id(), 'attendees' => array( array( 'name' => 'Anna', 'email' => 'anna@x.de' ), array( 'name' => 'Peter', 'email' => '' ) ) ) );
		$this->assertSame( 200, $ok->get_status(), wp_json_encode( $ok->get_data() ) );
		$stored = wc_get_order( $order->get_id() )->get_item( $item->get_id() )->get_meta( '_inzentive_attendees' );
		$this->assertSame( array( array( 'name' => 'Anna', 'email' => 'anna@x.de' ), array( 'name' => 'Peter', 'email' => '' ) ), $stored );
	}

	public function test_refund_through_woocommerce_never_above_what_is_left(): void {
		[ $order ] = $this->company_order( 1 );
		$order->set_status( 'processing' );
		$order->save();
		$this->assertError( $this->call( 'POST', "/orders/{$order->get_id()}/refund", array( 'request_id' => $this->rid(), 'amount' => '2000', 'reason' => 'x' ) ), 422, 'rungud_refused' );
		$ok = $this->call( 'POST', "/orders/{$order->get_id()}/refund", array( 'request_id' => $this->rid(), 'amount' => '100,50', 'reason' => 'Goodwill' ) );
		$this->assertSame( 200, $ok->get_status(), wp_json_encode( $ok->get_data() ) );
		$this->assertSame( '100.50', $ok->get_data()['result']['amount'] );
		$this->assertFalse( $ok->get_data()['result']['automatic'], 'no gateway → recorded, sent by bank transfer' );
		$this->assertEqualsWithDelta( 100.50, (float) wc_get_order( $order->get_id() )->get_total_refunded(), 0.001 );
	}

	public function test_documents_without_a_pdf_are_refused_and_nothing_is_mailed(): void {
		$sent = 0;
		$count = static function ( $r ) use ( &$sent ) { ++$sent; return $r; };
		add_filter( 'pre_wp_mail', $count );
		$res = $this->call( 'POST', '/documents/send', array( 'request_id' => $this->rid(), 'doc_type' => 'woo_invoice', 'doc_ref' => '1', 'doc_label' => '#1', 'to' => 'x@example.com', 'lang' => 'de', 'subject' => 's', 'body' => 'b' ) );
		remove_filter( 'pre_wp_mail', $count );
		$this->assertError( $res, 422, 'rungud_refused' );
		$this->assertSame( 0, $sent );
	}

	public function test_robert_is_refused_on_every_write_route(): void {
		wp_set_current_user( $this->robert->ID );
		[ $order, $item ] = $this->company_order( 1 );
		$failed = Audit::record( array( 'entity' => 'x', 'action' => 'y', 'status' => Audit::FAILED, 'summary_en' => 'x', 'summary_de' => 'x', 'command' => array( 'kind' => 'access_grant', 'params' => array() ) ) );
		$u = $this->jonas->ID;
		$routes = array(
			"/people/{$u}/licences"                 => array( 'program' => 'p', 'number' => 'N-1' ),
			"/people/{$u}/licences/p/verify"        => array(),
			"/people/{$u}/access/grant"             => array( 'scope' => 'b2c', 'expires_at' => '2030-01-01', 'reason' => 'r' ),
			"/people/{$u}/membership/cancel-now"    => array(),
			"/people/{$u}/checkout-link"            => array( 'plan' => 'membership-annual', 'lang' => 'de', 'subject' => 's', 'body' => 'b' ),
			"/people/{$u}/redeem-codes"             => array( 'days' => 7, 'scope' => 'b2c' ),
			"/orders/{$order->get_id()}/attendees"  => array( 'item_id' => $item->get_id(), 'attendees' => array() ),
			"/orders/{$order->get_id()}/refund"     => array( 'amount' => '1', 'reason' => 'r' ),
			'/documents/send'                       => array( 'doc_type' => 'woo_invoice', 'doc_ref' => '1', 'doc_label' => '1', 'to' => 'x@example.com', 'lang' => 'de', 'subject' => 's', 'body' => 'b' ),
			"/audit/{$failed}/retry"                => array(),
		);
		foreach ( $routes as $route => $params ) {
			$res = $this->call( 'POST', $route, $params + array( 'request_id' => $this->rid() ) );
			$this->assertSame( 403, $res->get_status(), $route );
		}
		$this->assertSame( array(), SiteStore::$received );
	}

	public function test_licence_number_proposal_is_the_next_free_one(): void {
		SiteStore::$programs = array(
			array( 'id' => 1, 'slug' => 'breath-coach', 'title' => 'Breath Coach', 'audience' => 'b2b', 'takes_licence' => true, 'licence_prefix' => 'BW', 'certification_numbers' => array( 'BW-0758', 'BW-1043' ) ),
		);
		update_user_meta( $this->jonas->ID, '_inzentive_licenses', array( 1 => array( 'certificate_number' => 'BW-2210' ) ) );
		$items = $this->call( 'GET', '/programs' )->get_data()['items'];
		$this->assertSame( 'BW-2211', $items[0]['next_number'] );
	}
}
