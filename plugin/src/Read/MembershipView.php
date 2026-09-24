<?php
declare(strict_types=1);

namespace Rungud\Read;

/**
 * One person's membership state, derived from the site's GET /access payload.
 * Pure: the only business rule about memberships the CMS holds, in one place.
 *
 * status
 *   active       a paid plan that renews
 *   ending       a paid plan cancelled at period end — access until `until`
 *   free_access  no paid plan, a valid card (free access) until `until`
 *   none         neither
 *
 * `expiring` is true when access stops within 30 days and nothing renews it.
 * A renewing plan is never "expiring", however close its next payment is.
 */
final class MembershipView {

	public const EXPIRING_DAYS = 30;

	/**
	 * @param array<string,mixed> $access GET /inzentive/v1/access/{id}
	 * @return array{status:string, plan:?string, until:?string, renews:bool, expiring:bool, days_left:?int,
	 *               subscription_id:?string, customer_id:?string, free_access:?array{scope:string, until:?string, reason:string, source:string}}
	 */
	public static function from_access( array $access, \DateTimeImmutable $today ): array {
		$membership = (array) ( $access['membership'] ?? array() );
		$subs       = array_values( (array) ( $membership['subscriptions'] ?? array() ) );
		$sub        = self::current_subscription( $subs );
		$card       = is_array( $access['valid_card'] ?? null ) ? $access['valid_card'] : null;
		$card_on    = $card && ! empty( $card['active'] );

		$plan   = isset( $membership['plan'] ) && '' !== $membership['plan'] ? (string) $membership['plan'] : null;
		$active = ! empty( $membership['active'] );
		$renews = $active && ! empty( $membership['billing_continues'] );

		$until = null;
		if ( $active ) {
			$until = self::date( $sub['cancel_at'] ?? null ) ?? self::date( $sub['next_payment'] ?? null );
			$status = $renews ? 'active' : 'ending';
		} elseif ( $card_on ) {
			$until  = self::date( $card['expires_at'] ?? null );
			$status = 'free_access';
		} else {
			$status = 'none';
		}

		$days_left = null === $until ? null : (int) $today->diff( new \DateTimeImmutable( $until ) )->format( '%r%a' );
		$expiring  = ! $renews && null !== $days_left && $days_left >= 0 && $days_left <= self::EXPIRING_DAYS && 'none' !== $status;

		return array(
			'status'          => $status,
			'plan'            => $plan,
			'until'           => $until,
			'renews'          => $renews,
			'expiring'        => $expiring,
			'days_left'       => $days_left,
			'subscription_id' => isset( $sub['stripe_subscription_id'] ) ? (string) $sub['stripe_subscription_id'] : null,
			'customer_id'     => isset( $sub['stripe_customer_id'] ) ? (string) $sub['stripe_customer_id'] : null,
			'free_access'     => $card_on ? array(
				'scope'  => (string) ( $card['scope'] ?? 'all' ),
				'until'  => self::date( $card['expires_at'] ?? null ),
				'reason' => (string) ( $card['reason'] ?? '' ),
				'source' => (string) ( $card['source'] ?? 'coupon' ),
			) : null,
		);
	}

	/** The running subscription, else the most recent one. @param list<array<string,mixed>> $subs */
	private static function current_subscription( array $subs ): array {
		foreach ( $subs as $s ) {
			if ( in_array( (string) ( $s['status'] ?? '' ), array( 'active', 'trialing', 'past_due' ), true ) ) {
				return (array) $s;
			}
		}
		return (array) ( $subs[0] ?? array() );
	}

	/** ISO timestamp, Y-m-d or unix → Y-m-d (site timezone is already applied by the site). */
	public static function date( mixed $value ): ?string {
		if ( null === $value || '' === $value || 0 === $value || '0' === $value ) {
			return null;
		}
		if ( is_int( $value ) || ctype_digit( (string) $value ) ) {
			return gmdate( 'Y-m-d', (int) $value );
		}
		$s = (string) $value;
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}/', $s, $m ) ) {
			return $m[0];
		}
		return null;
	}
}
