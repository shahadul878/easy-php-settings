<?php
/**
 * Tests for the Site Health test results.
 *
 * @package EasyPHPSettings
 */

use PHPUnit\Framework\TestCase;

/**
 * Easy_Site_Health result builders.
 */
class EasySiteHealthTest extends TestCase {

	private function row( $key, $saved, $live, $health, $applied, $recommended = '256M' ) {
		return compact( 'key', 'saved', 'live', 'health', 'applied', 'recommended' );
	}

	/* ─── Limits ─── */

	public function test_limits_good_when_nothing_is_low() {
		$result = Easy_Site_Health::limits_result(
			array(
				$this->row( 'memory_limit', '', '512M', 'high', 'unset' ),
				$this->row( 'max_input_vars', '', '10000', 'ok', 'unset', '10000' ),
			)
		);

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'easy_php_settings_limits', $result['test'] );
		$this->assertSame( 'blue', $result['badge']['color'] );
	}

	public function test_limits_recommended_when_a_limit_is_low_and_lists_it() {
		$result = Easy_Site_Health::limits_result(
			array(
				$this->row( 'memory_limit', '', '128M', 'low', 'unset' ),
				$this->row( 'max_input_vars', '', '10000', 'ok', 'unset', '10000' ),
			)
		);

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'memory_limit', $result['description'] );
		$this->assertStringContainsString( '128M', $result['description'] );
		$this->assertStringNotContainsString( 'max_input_vars', $result['description'] );
		$this->assertStringContainsString( 'page=easy-php-settings', $result['actions'] );
	}

	/* ─── Applied ─── */

	public function test_applied_good_when_nothing_is_saved() {
		$result = Easy_Site_Health::applied_result(
			array( $this->row( 'memory_limit', '', '256M', 'ok', 'unset' ) ),
			true,
			10000,
			300
		);

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'easy_php_settings_applied', $result['test'] );
	}

	public function test_applied_good_when_every_saved_value_is_in_use() {
		$result = Easy_Site_Health::applied_result(
			array( $this->row( 'memory_limit', '512M', '512M', 'high', 'applied' ) ),
			true,
			10000,
			300
		);

		$this->assertSame( 'good', $result['status'] );
	}

	public function test_applied_good_while_php_may_still_be_caching() {
		$result = Easy_Site_Health::applied_result(
			array( $this->row( 'memory_limit', '512M', '256M', 'ok', 'pending' ) ),
			true,
			60,
			300
		);

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( '300', $result['description'] );
	}

	public function test_applied_recommended_when_host_keeps_the_old_value() {
		$result = Easy_Site_Health::applied_result(
			array( $this->row( 'memory_limit', '512M', '256M', 'ok', 'pending' ) ),
			true,
			900,
			300
		);

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'memory_limit', $result['description'] );
		$this->assertStringContainsString( '512M', $result['description'] );
		$this->assertStringContainsString( 'host', $result['description'] );
	}

	public function test_applied_explains_when_server_ignores_user_ini() {
		$result = Easy_Site_Health::applied_result(
			array( $this->row( 'memory_limit', '512M', '256M', 'ok', 'pending' ) ),
			false,
			900,
			300
		);

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'does not read .user.ini', $result['description'] );
	}

	public function test_output_is_escaped() {
		$result = Easy_Site_Health::limits_result(
			array( $this->row( 'memory_limit', '', '<b>1M</b>', 'low', 'unset' ) )
		);

		$this->assertStringNotContainsString( '<b>1M</b>', $result['description'] );
	}
}
