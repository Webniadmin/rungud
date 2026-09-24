<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Audit\Audit;
use Rungud\Capabilities as C;
use Rungud\Tests\Fixtures\SiteStore;

/**
 * Phase 2: read-only resources against the site double. Robert reads all of
 * them; nothing here writes to the site.
 */
final class ReadApiTest extends TestCase {

	private \WP_User $gudrun;
	private \WP_User $robert;

	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( SiteStore::class ) ) {
			$this->markTestSkipped( 'Runs against the site double only.' );
		}
		$this->gudrun = $this->make_user( C::ROLE_BACKOFFICE );
		$this->robert = $this->make_user( C::ROLE_OWNER );
	}

	private function member( string $email, string $first, string $last ): \WP_User {
		$id = self::factory()->user->create( array( 'user_email' => $email, 'first_name' => $first, 'last_name' => $last, 'display_name' => "$first $last", 'role' => 'customer' ) );
		return get_userdata( $id );
	}

	public function test_site_capability_is_widened_only_during_our_call(): void {
		$anna = $this->member( 'anna@example.com', 'Anna', 'Schmidt' );
		wp_set_current_user( $this->gudrun->ID );
		$this->assertSame( 200, $this->call( 'GET', '/people/' . $anna->ID . '/access' )->get_status() );

		// Directly, Gudrun still cannot use the site's back-office API.
		$direct = rest_do_request( new \WP_REST_Request( 'GET', '/inzentive/v1/events' ) );
		$this->assertSame( 403, $direct->get_status() );
		$this->assertFalse( has_filter( 'inzentive/api/capability' ) );
	}

	public function test_people_search_and_list_state(): void {
		$anna = $this->member( 'anna@smithfitness.de', 'Anna', 'Schmidt' );
		$this->member( 'peter@smithfitness.de', 'Peter', 'Novak' );
		SiteStore::$active[ $anna->ID ] = 'instructor-annual';

		wp_set_current_user( $this->robert->ID );
		$data = $this->call( 'GET', '/people', array( 'search' => 'smithfitness' ) )->get_data();
		$this->assertSame( 2, $data['total'] );
		$byEmail = array_column( $data['items'], null, 'email' );
		$this->assertSame( 'active', $byEmail['anna@smithfitness.de']['membership'] );
		$this->assertSame( 'instructor-annual', $byEmail['anna@smithfitness.de']['plan'] );
		$this->assertSame( 'none', $byEmail['peter@smithfitness.de']['membership'] );
		$this->assertSame( 'Anna Schmidt', $byEmail['anna@smithfitness.de']['name'] );

		$everyone = array_column( $this->call( 'GET', '/people' )->get_data()['items'], 'email' );
		$this->assertNotContains( $this->gudrun->user_email, $everyone, 'staff are not people' );
		$this->assertNotContains( $this->robert->user_email, $everyone );
	}

	public function test_person_detail_derives_membership_and_lists_orders(): void {
		$beata = $this->member( 'beata@example.sk', 'Beata', 'Tothova' );
		SiteStore::$access[ $beata->ID ] = array(
			'user'       => array( 'id' => $beata->ID, 'professional' => true ),
			'membership' => array(
				'active' => true, 'billing_continues' => false, 'plan' => 'instructor-annual',
				'subscriptions' => array( array( 'id' => 9, 'status' => 'active', 'price' => 200.0, 'next_payment' => null, 'cancel_at' => gmdate( 'Y-m-d', strtotime( '+10 days' ) ) . 'T00:00:00+00:00', 'stripe_subscription_id' => 'sub_X', 'stripe_customer_id' => null ) ),
			),
			'licences'   => array( array( 'program_slug' => 'breath-coach-basic', 'status' => 'verified', 'number' => 'BC-1044' ) ),
			'valid_card' => null,
		);
		$order = wc_create_order( array( 'customer_id' => $beata->ID ) );
		$order->set_billing_first_name( 'Beata' );
		$order->set_billing_last_name( 'Tothova' );
		$order->set_billing_email( 'beata@example.sk' );
		$order->set_total( '583.10' );
		$order->set_currency( 'EUR' );
		$order->save();

		wp_set_current_user( $this->robert->ID );
		$data = $this->call( 'GET', '/people/' . $beata->ID )->get_data();
		$this->assertSame( 'ending', $data['membership']['status'] );
		$this->assertTrue( $data['membership']['expiring'] );
		$this->assertTrue( $data['professional'] );
		$this->assertSame( 'BC-1044', $data['licences'][0]['number'] );
		$this->assertCount( 1, $data['orders'] );
		$this->assertSame( '583.10', $data['orders'][0]['total'] );
		$this->assertNull( $data['orders'][0]['invoice_pdf'] );
		$this->assertNull( $data['site_error'] );
	}

	public function test_documents_say_when_stripe_is_not_configured(): void {
		$beata = $this->member( 'b2@example.sk', 'Beata', 'T' );
		SiteStore::$access[ $beata->ID ] = array(
			'membership' => array( 'active' => true, 'billing_continues' => true, 'subscriptions' => array( array( 'status' => 'active', 'stripe_subscription_id' => 'sub_Y' ) ) ),
		);
		wp_set_current_user( $this->gudrun->ID );
		$data = $this->call( 'GET', '/people/' . $beata->ID . '/documents' )->get_data();
		$this->assertSame( 'not_configured', $data['stripe_state'] );
		$this->assertSame( array(), $data['stripe'] );
	}

	public function test_documents_read_stripe_invoices_through_the_reader(): void {
		$beata = $this->member( 'b3@example.sk', 'Beata', 'T' );
		SiteStore::$access[ $beata->ID ] = array(
			'membership' => array( 'active' => true, 'billing_continues' => true, 'subscriptions' => array( array( 'status' => 'active', 'stripe_subscription_id' => 'sub_Z' ) ) ),
		);
		$seen = new \ArrayObject();
		\Rungud\Stripe\StripeReader::set_factory(
			static function () use ( $seen ) {
				return (object) array(
					'invoices' => new class( $seen ) {
						public function __construct( private \ArrayObject $seen ) {}
						public function all( array $params ) {
							$this->seen->exchangeArray( $params );
							return (object) array( 'data' => array( (object) array( 'id' => 'in_1', 'number' => 'INV-7F3A-0009', 'created' => 1758326400, 'total' => 20000, 'currency' => 'eur', 'status' => 'paid', 'invoice_pdf' => 'https://pay.stripe.com/invoice/x/pdf' ) ) );
						}
					},
				);
			}
		);
		try {
			wp_set_current_user( $this->gudrun->ID );
			$data = $this->call( 'GET', '/people/' . $beata->ID . '/documents' )->get_data();
		} finally {
			\Rungud\Stripe\StripeReader::set_factory( null );
		}
		$this->assertSame( 'ok', $data['stripe_state'] );
		$this->assertSame( 'sub_Z', $seen['subscription'] );
		$this->assertSame( '200.00', $data['stripe'][0]['amount'] );
		$this->assertSame( 'EUR', $data['stripe'][0]['currency'] );
		$this->assertSame( 'INV-7F3A-0009', $data['stripe'][0]['number'] );
	}

	public function test_events_upcoming_with_booked_and_waiting(): void {
		$future = gmdate( 'Y-m-d', strtotime( '+20 days' ) );
		$past   = gmdate( 'Y-m-d', strtotime( '-20 days' ) );
		SiteStore::$events = array(
			array( 'id' => 11, 'slug' => 'immersion-09-26', 'title' => 'Immersion Breath Teacher Training', 'start' => $future, 'end' => $future, 'price' => 583.1, 'member_price' => 408.17, 'seats_total' => 15, 'seats_left' => 2, 'product_id' => 501 ),
			array( 'id' => 12, 'slug' => 'old', 'title' => 'Old event', 'start' => $past, 'end' => $past, 'price' => 100, 'member_price' => null, 'seats_total' => null, 'seats_left' => null, 'product_id' => 502 ),
		);
		SiteStore::$waiting['immersion-09-26'] = 2;

		wp_set_current_user( $this->robert->ID );
		$items = $this->call( 'GET', '/events' )->get_data()['items'];
		$this->assertCount( 1, $items );
		$this->assertSame( 13, $items[0]['booked'] );
		$this->assertSame( 2, $items[0]['waiting'] );
		$this->assertSame( '583.10', $items[0]['price'] );
		$this->assertSame( '408.17', $items[0]['member_price'] );

		$past_items = $this->call( 'GET', '/events', array( 'when' => 'past' ) )->get_data()['items'];
		$this->assertSame( 12, $past_items[0]['id'] );
	}

	public function test_event_participants_come_from_the_site_one_row_per_order(): void {
		$anna = $this->member( 'anna@x.de', 'Anna', 'S' );
		SiteStore::$active[ $anna->ID ] = 'membership-annual';
		$order = wc_create_order( array( 'customer_id' => $anna->ID ) );
		$order->set_billing_company( 'Smith Fitness GmbH' );
		$order->set_currency( 'EUR' );
		$order->save();
		SiteStore::$events    = array( array( 'id' => 11, 'slug' => 'imm', 'title' => 'Immersion', 'start' => '2030-01-01', 'end' => '2030-01-03' ) );
		SiteStore::$attendees = array( 11 => array( array( 'order_id' => $order->get_id(), 'order_number' => (string) $order->get_id(), 'name' => 'Anna S', 'email' => 'anna@x.de', 'quantity' => 4, 'total' => 1636.4, 'status' => 'processing', 'paid' => true, 'date' => '2026-07-02 10:00' ) ) );

		wp_set_current_user( $this->gudrun->ID );
		$data = $this->call( 'GET', '/events/11' )->get_data();
		$p    = $data['participants'][0];
		$this->assertSame( 4, $p['quantity'] );
		$this->assertSame( '1636.40', $p['total'] );
		$this->assertSame( 'Smith Fitness GmbH', $p['company'] );
		$this->assertSame( 'active', $p['membership'] );
		$this->assertNull( $p['health_declaration'] );
		$this->assertSame( '2026-07-02', $p['date'] );
	}

	public function test_participant_amount_is_the_gross_line_like_everywhere_else(): void {
		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_name( 'Zen Flow Retreat, Lake Bled' );
		$item->set_quantity( 4 );
		$item->set_subtotal( '3330.25' );
		$item->set_total( '3330.25' );
		$item->set_taxes( array( 'total' => array( 1 => '269.75' ), 'subtotal' => array( 1 => '269.75' ) ) );
		$item->add_meta_data( '_inzentive_event_id', 21, true );
		$order->add_item( $item );
		$order->set_currency( 'EUR' );
		$order->save();
		$item->save();
		$this->assertSame( '269.75', wc_format_decimal( wc_get_order( $order->get_id() )->get_item( $item->get_id() )->get_total_tax(), 2 ), 'fixture: tax stored on the line' );
		SiteStore::$events    = array( array( 'id' => 21, 'slug' => 'zen', 'title' => 'Zen Flow Retreat', 'start' => '2030-11-23', 'end' => '2030-11-27' ) );
		// The site's own row carries the net line total.
		SiteStore::$attendees = array( 21 => array( array( 'order_id' => $order->get_id(), 'order_number' => (string) $order->get_id(), 'name' => 'Petra Schmidt', 'email' => 'p@x', 'quantity' => 4, 'total' => 3330.25, 'status' => 'processing', 'paid' => true, 'date' => '2026-09-24 12:00' ) ) );

		wp_set_current_user( $this->gudrun->ID );
		$p = $this->call( 'GET', '/events/21' )->get_data()['participants'][0];
		$this->assertSame( '3600.00', $p['total'] );
		$order_view = $this->call( 'GET', '/orders/' . $order->get_id() )->get_data();
		$this->assertSame( '3600.00', $order_view['items'][0]['total'] );
	}

	public function test_unknown_event_is_404(): void {
		wp_set_current_user( $this->gudrun->ID );
		$this->assertError( $this->call( 'GET', '/events/999' ), 404, 'rungud_not_found' );
	}

	public function test_orders_list_and_search(): void {
		$o1 = wc_create_order();
		$o1->set_billing_email( 'findme@example.com' );
		$o1->set_billing_last_name( 'Findme' );
		$o1->save();
		$o2 = wc_create_order();
		$o2->set_billing_email( 'other@example.com' );
		$o2->save();

		wp_set_current_user( $this->robert->ID );
		$this->assertGreaterThanOrEqual( 2, $this->call( 'GET', '/orders' )->get_data()['total'] );
		$found = $this->call( 'GET', '/orders', array( 'search' => 'findme' ) )->get_data();
		$this->assertSame( 1, $found['total'] );
		$this->assertSame( $o1->get_id(), $found['items'][0]['id'] );
		$this->assertSame( 200, $this->call( 'GET', '/orders/' . $o1->get_id() )->get_status() );
	}

	public function test_memberships_filters_and_counts(): void {
		$soon  = $this->member( 'soon@x.de', 'Soon', 'Ending' );
		$renew = $this->member( 'renew@x.de', 'Always', 'Renews' );
		SiteStore::$plan_members = array( 'membership-annual' => array( $soon->ID, $renew->ID ) );
		SiteStore::$access[ $soon->ID ]  = array( 'membership' => array( 'active' => true, 'billing_continues' => false, 'plan' => 'membership-annual', 'subscriptions' => array( array( 'status' => 'active', 'cancel_at' => gmdate( 'Y-m-d', strtotime( '+5 days' ) ) ) ) ) );
		SiteStore::$access[ $renew->ID ] = array( 'membership' => array( 'active' => true, 'billing_continues' => true, 'plan' => 'membership-annual', 'subscriptions' => array( array( 'status' => 'active', 'next_payment' => gmdate( 'Y-m-d', strtotime( '+5 days' ) ) ) ) ) );

		wp_set_current_user( $this->robert->ID );
		$data = $this->call( 'GET', '/memberships' )->get_data();
		$this->assertSame( array( 'soon@x.de' ), array_column( $data['items'], 'email' ) );
		$this->assertSame( 1, $data['counts']['expiring'] );
		$this->assertSame( 1, $data['counts']['active'] );
		$this->assertSame( 1, $data['counts']['ending'] );
		$this->assertSame( 2, $data['counts']['all'] );
		$this->assertCount( 2, $this->call( 'GET', '/memberships', array( 'filter' => 'all' ) )->get_data()['items'] );
	}

	public function test_audit_log_hides_logins_and_marks_open_failures(): void {
		$this->login( $this->gudrun );
		wp_set_current_user( $this->gudrun->ID );
		$failed = Audit::record( array( 'entity' => 'licence', 'entity_id' => '1', 'action' => 'verify', 'status' => Audit::FAILED, 'error' => 'timeout', 'summary_en' => 'x', 'summary_de' => 'x' ) );
		$data = $this->call( 'GET', '/audit' )->get_data();
		$this->assertNotContains( 'session', array_column( $data['items'], 'entity' ) );
		$this->assertTrue( $data['items'][0]['open'] );
		$this->assertSame( $failed, $data['items'][0]['id'] );
		$this->assertSame( 1, $data['open_failures'] );
		$this->assertSame( 1, $data['last_72h'], 'logins are not counted' );
	}

	public function test_robert_reads_every_phase_2_resource(): void {
		SiteStore::$events = array();
		wp_set_current_user( $this->robert->ID );
		foreach ( array( '/people', '/events', '/orders', '/memberships', '/audit', '/people/' . $this->robert->ID, '/people/' . $this->robert->ID . '/documents' ) as $route ) {
			$this->assertSame( 200, $this->call( 'GET', $route )->get_status(), $route );
		}
	}
}
