<?php
/**
 * Compares setting values with recommendations.
 *
 * @package EasyPHPSettings
 * @since 1.1.9
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Limit_Health
 *
 * Shared by the General tab, the REST live check and Site Health so they
 * always agree on what "low" or "applied" means.
 */
class Easy_Limit_Health {

	const LOW  = 'low';
	const OK   = 'ok';
	const HIGH = 'high';

	const UNSET_VALUE = 'unset';
	const APPLIED     = 'applied';
	const PENDING     = 'pending';

	/**
	 * How far above the recommendation counts as "high": sizes, then counts.
	 */
	const SIZE_HIGH_FACTOR  = 1.5;
	const COUNT_HIGH_FACTOR = 2;

	/**
	 * Settings measured in bytes (K/M/G suffixes).
	 *
	 * @var string[]
	 */
	private static $size_keys = array( 'memory_limit', 'upload_max_filesize', 'post_max_size', 'wp_memory_limit', 'wp_max_memory_limit' );

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Compare a value with its recommendation.
	 *
	 * @param string $key         Setting key.
	 * @param string $value       Value to judge.
	 * @param string $recommended Recommended value ('' when there is none).
	 * @return string|null low, ok, high, or null when there is no recommendation.
	 */
	public static function status( $key, $value, $recommended ) {
		if ( '' === (string) $recommended ) {
			return null;
		}
		if ( self::is_unlimited( $key, $value ) ) {
			return self::OK;
		}

		$current = self::to_number( $key, $value );
		$target  = self::to_number( $key, $recommended );
		if ( $current < $target ) {
			return self::LOW;
		}

		$factor = self::is_size( $key ) ? self::SIZE_HIGH_FACTOR : self::COUNT_HIGH_FACTOR;
		return ( $target > 0 && $current > $target * $factor ) ? self::HIGH : self::OK;
	}

	/**
	 * Whether the server is using the saved value.
	 *
	 * @param string $key   Setting key.
	 * @param string $saved Saved value ('' when not set).
	 * @param string $live  Value in effect.
	 * @return string unset, applied or pending.
	 */
	public static function applied_state( $key, $saved, $live ) {
		if ( '' === (string) $saved ) {
			return self::UNSET_VALUE;
		}
		return self::to_number( $key, $saved ) === self::to_number( $key, $live ) ? self::APPLIED : self::PENDING;
	}

	/**
	 * Whether the value means "no limit" (-1 memory, 0 execution time).
	 *
	 * @param string $key   Setting key.
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_unlimited( $key, $value ) {
		$value = trim( (string) $value );
		if ( 'max_execution_time' === $key ) {
			return '0' === $value;
		}
		return self::is_size( $key ) && '-1' === $value;
	}

	/**
	 * Whether the setting is measured in bytes.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	private static function is_size( $key ) {
		return in_array( $key, self::$size_keys, true );
	}

	/**
	 * Convert a value to a comparable number (bytes for sizes).
	 *
	 * @param string $key   Setting key.
	 * @param string $value Value.
	 * @return int
	 */
	private static function to_number( $key, $value ) {
		$value = trim( (string) $value );
		if ( ! self::is_size( $key ) ) {
			return (int) $value;
		}

		$number = (int) $value;
		switch ( strtolower( substr( $value, -1 ) ) ) {
			case 'g':
				$number *= 1024;
				// Fall through.
			case 'm':
				$number *= 1024;
				// Fall through.
			case 'k':
				$number *= 1024;
		}
		return $number;
	}
}
