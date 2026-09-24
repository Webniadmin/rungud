<?php
declare(strict_types=1);

namespace Rungud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rungud\Read\MembershipView;

final class MembershipViewTest extends TestCase {

	private \DateTimeImmutable $today;

	protected function setUp(): void {
		$this->today = new \DateTimeImmutable( '2026-08-20' );
	}

	private function access( array $membership = array(), ?array $card = null ): array {
		return array( 'membership' => $membership + array( 'active' => false, 'billing_continues' => false, 'plan' => null, 'subscriptions' => array() ), 'valid_card' => $card );
	}

	public function test_renewing_plan_is_active_and_never_expiring(): void {
		$v = MembershipView::from_access( $this->access( array(
			'active' => true, 'billing_continues' => true, 'plan' => 'instructor-annual',
			'subscriptions' => array( array( 'status' => 'active', 'next_payment' => '2026-08-25T00:00:00+00:00', 'cancel_at' => null, 'stripe_subscription_id' => 'sub_1' ) ),
		) ), $this->today );
		$this->assertSame( 'active', $v['status'] );
		$this->assertSame( '2026-08-25', $v['until'] );
		$this->assertSame( 5, $v['days_left'] );
		$this->assertFalse( $v['expiring'] );
		$this->assertSame( 'sub_1', $v['subscription_id'] );
	}

	public function test_cancelled_at_period_end_is_ending_and_expiring_inside_30_days(): void {
		$v = MembershipView::from_access( $this->access( array(
			'active' => true, 'billing_continues' => false, 'plan' => 'membership-annual',
			'subscriptions' => array( array( 'status' => 'active', 'next_payment' => null, 'cancel_at' => '2026-09-07T00:00:00+00:00' ) ),
		) ), $this->today );
		$this->assertSame( 'ending', $v['status'] );
		$this->assertSame( '2026-09-07', $v['until'] );
		$this->assertTrue( $v['expiring'] );
	}

	public function test_ending_later_than_30_days_is_not_expiring(): void {
		$v = MembershipView::from_access( $this->access( array(
			'active' => true, 'billing_continues' => false,
			'subscriptions' => array( array( 'status' => 'active', 'cancel_at' => '2026-10-01' ) ),
		) ), $this->today );
		$this->assertFalse( $v['expiring'] );
	}

	public function test_free_access_card(): void {
		$v = MembershipView::from_access( $this->access( array(), array( 'active' => true, 'scope' => 'b2c', 'expires_at' => '2026-08-27T10:00:00+00:00', 'reason' => 'goodwill', 'source' => 'cms' ) ), $this->today );
		$this->assertSame( 'free_access', $v['status'] );
		$this->assertSame( '2026-08-27', $v['until'] );
		$this->assertTrue( $v['expiring'] );
		$this->assertSame( 'b2c', $v['free_access']['scope'] );
	}

	public function test_expired_card_and_no_plan_is_none(): void {
		$v = MembershipView::from_access( $this->access( array(), array( 'active' => false, 'expires_at' => '2026-07-01' ) ), $this->today );
		$this->assertSame( 'none', $v['status'] );
		$this->assertFalse( $v['expiring'] );
		$this->assertNull( $v['free_access'] );
	}

	public function test_dates_in_any_site_format(): void {
		$this->assertSame( '2026-09-07', MembershipView::date( '2026-09-07' ) );
		$this->assertSame( '2026-09-07', MembershipView::date( '2026-09-07T13:00:00+02:00' ) );
		$this->assertSame( '1970-01-01', MembershipView::date( 1 ) );
		$this->assertNull( MembershipView::date( 0 ) );
		$this->assertNull( MembershipView::date( '' ) );
	}
}
