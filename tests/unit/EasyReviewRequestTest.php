<?php
/**
 * Tests for when the review request may be shown.
 *
 * @package EasyPHPSettings
 */

use PHPUnit\Framework\TestCase;

/**
 * Easy_Module_Review_Request::may_ask().
 */
class EasyReviewRequestTest extends TestCase {

	const NOW = 1800000000;

	private function state( array $changes = array() ) {
		return array_merge(
			array(
				'dismissed'    => false,
				'rated'        => false,
				'snooze_until' => 0,
			),
			$changes
		);
	}

	public function test_asks_when_nothing_has_been_chosen_yet() {
		$this->assertTrue( Easy_Module_Review_Request::may_ask( $this->state(), self::NOW ) );
	}

	public function test_never_asks_after_rating() {
		$this->assertFalse( Easy_Module_Review_Request::may_ask( $this->state( array( 'rated' => true ) ), self::NOW ) );
	}

	public function test_never_asks_after_dont_ask_again() {
		$this->assertFalse( Easy_Module_Review_Request::may_ask( $this->state( array( 'dismissed' => true ) ), self::NOW ) );
	}

	public function test_waits_while_snoozed() {
		$this->assertFalse( Easy_Module_Review_Request::may_ask( $this->state( array( 'snooze_until' => self::NOW + 60 ) ), self::NOW ) );
	}

	public function test_asks_again_when_snooze_ends() {
		$this->assertTrue( Easy_Module_Review_Request::may_ask( $this->state( array( 'snooze_until' => self::NOW - 1 ) ), self::NOW ) );
	}

	public function test_missing_keys_count_as_not_chosen() {
		$this->assertTrue( Easy_Module_Review_Request::may_ask( array(), self::NOW ) );
	}
}
