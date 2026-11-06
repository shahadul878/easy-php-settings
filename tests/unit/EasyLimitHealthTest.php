<?php
/**
 * Tests for comparing setting values with recommendations.
 *
 * @package EasyPHPSettings
 */

use PHPUnit\Framework\TestCase;

/**
 * Easy_Limit_Health.
 */
class EasyLimitHealthTest extends TestCase {

	public function test_size_below_recommended_is_low() {
		$this->assertSame( 'low', Easy_Limit_Health::status( 'memory_limit', '128M', '256M' ) );
	}

	public function test_size_equal_to_recommended_is_ok() {
		$this->assertSame( 'ok', Easy_Limit_Health::status( 'memory_limit', '256M', '256M' ) );
	}

	public function test_size_well_above_recommended_is_high() {
		$this->assertSame( 'high', Easy_Limit_Health::status( 'memory_limit', '1G', '256M' ) );
	}

	public function test_size_units_are_compared_in_bytes() {
		$this->assertSame( 'ok', Easy_Limit_Health::status( 'upload_max_filesize', '131072K', '128M' ) );
	}

	public function test_count_below_recommended_is_low() {
		$this->assertSame( 'low', Easy_Limit_Health::status( 'max_input_vars', '1000', '10000' ) );
	}

	public function test_count_up_to_double_is_ok() {
		$this->assertSame( 'ok', Easy_Limit_Health::status( 'max_execution_time', '600', '300' ) );
	}

	public function test_unlimited_memory_is_ok() {
		$this->assertSame( 'ok', Easy_Limit_Health::status( 'memory_limit', '-1', '256M' ) );
	}

	public function test_unlimited_execution_time_is_ok() {
		$this->assertSame( 'ok', Easy_Limit_Health::status( 'max_execution_time', '0', '300' ) );
	}

	public function test_no_recommendation_gives_null() {
		$this->assertNull( Easy_Limit_Health::status( 'memory_limit', '256M', '' ) );
	}

	public function test_applied_state_is_unset_without_a_saved_value() {
		$this->assertSame( 'unset', Easy_Limit_Health::applied_state( 'memory_limit', '', '256M' ) );
	}

	public function test_applied_state_matches_equivalent_sizes() {
		$this->assertSame( 'applied', Easy_Limit_Health::applied_state( 'memory_limit', '1G', '1024M' ) );
	}

	public function test_applied_state_matches_counts() {
		$this->assertSame( 'applied', Easy_Limit_Health::applied_state( 'max_input_vars', '5000', '5000' ) );
	}

	public function test_applied_state_is_pending_when_values_differ() {
		$this->assertSame( 'pending', Easy_Limit_Health::applied_state( 'memory_limit', '512M', '256M' ) );
	}
}
