<?php
/**
 * Tests for building live-check rows.
 *
 * @package EasyPHPSettings
 */

use PHPUnit\Framework\TestCase;

/**
 * Easy_Live_Check::rows().
 */
class EasyLiveCheckTest extends TestCase {

	public function test_rows_combine_saved_live_and_recommended_values() {
		$rows = Easy_Live_Check::rows(
			array( 'memory_limit', 'max_input_vars' ),
			array( 'memory_limit' => '512M' ),
			function ( $key ) {
				return 'memory_limit' === $key ? '256M' : '1000';
			},
			array(
				'memory_limit'   => '256M',
				'max_input_vars' => '10000',
			)
		);

		$this->assertSame(
			array(
				array(
					'key'         => 'memory_limit',
					'saved'       => '512M',
					'live'        => '256M',
					'recommended' => '256M',
					'health'      => 'ok',
					'applied'     => 'pending',
				),
				array(
					'key'         => 'max_input_vars',
					'saved'       => '',
					'live'        => '1000',
					'recommended' => '10000',
					'health'      => 'low',
					'applied'     => 'unset',
				),
			),
			$rows
		);
	}

	public function test_missing_recommendation_gives_null_health() {
		$rows = Easy_Live_Check::rows(
			array( 'memory_limit' ),
			array(),
			function () {
				return '128M';
			},
			array()
		);

		$this->assertNull( $rows[0]['health'] );
		$this->assertSame( '', $rows[0]['recommended'] );
	}

	private function pending( $key, $saved, $live ) {
		return array(
			'key'         => $key,
			'saved'       => $saved,
			'live'        => $live,
			'recommended' => '',
			'health'      => null,
			'applied'     => 'pending',
		);
	}

	public function test_nothing_is_blocked_while_php_may_still_be_caching() {
		$rows = array( $this->pending( 'memory_limit', '512M', '256M' ) );

		$this->assertSame( array(), Easy_Live_Check::blocked_rows( $rows, true, 60, 300 ) );
	}

	public function test_pending_rows_are_blocked_after_the_cache_time() {
		$rows = array(
			$this->pending( 'memory_limit', '512M', '256M' ),
			array_merge( $this->pending( 'max_input_vars', '5000', '5000' ), array( 'applied' => 'applied' ) ),
		);

		$blocked = Easy_Live_Check::blocked_rows( $rows, true, 301, 300 );

		$this->assertSame( array( 'memory_limit' ), array_column( $blocked, 'key' ) );
	}

	public function test_pending_rows_are_blocked_at_once_when_user_ini_is_not_read() {
		$rows = array( $this->pending( 'memory_limit', '512M', '256M' ) );

		$this->assertCount( 1, Easy_Live_Check::blocked_rows( $rows, false, 5, 300 ) );
	}

	public function test_host_request_lists_each_blocked_value_for_the_site() {
		$text = Easy_Live_Check::host_request_text(
			array(
				$this->pending( 'memory_limit', '512M', '256M' ),
				$this->pending( 'max_input_vars', '5000', '1000' ),
			),
			'https://example.com'
		);

		$this->assertStringContainsString( 'https://example.com', $text );
		$this->assertStringContainsString( "memory_limit = 512M\nmax_input_vars = 5000", $text );
	}

	public function test_host_request_is_empty_when_nothing_is_blocked() {
		$this->assertSame( '', Easy_Live_Check::host_request_text( array(), 'https://example.com' ) );
	}
}
