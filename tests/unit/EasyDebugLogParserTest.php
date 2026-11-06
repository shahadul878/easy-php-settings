<?php
/**
 * Tests for parsing and grouping debug.log entries.
 *
 * @package EasyPHPSettings
 */

use PHPUnit\Framework\TestCase;

/**
 * Easy_Debug_Log_Parser.
 */
class EasyDebugLogParserTest extends TestCase {

	const LOG = <<<'LOG'
[06-Nov-2026 10:00:00 UTC] PHP Notice:  Undefined index: foo in /var/www/a.php on line 3
[06-Nov-2026 10:01:00 UTC] PHP Warning:  Division by zero in /var/www/b.php on line 9
[06-Nov-2026 10:02:00 UTC] PHP Notice:  Undefined index: foo in /var/www/a.php on line 3
[06-Nov-2026 10:03:00 UTC] PHP Fatal error:  Uncaught Error: Call to undefined function x() in /var/www/c.php:5
Stack trace:
#0 {main}
  thrown in /var/www/c.php on line 5
[06-Nov-2026 10:04:00 UTC] PHP Deprecated:  Creation of dynamic property in /var/www/d.php on line 1
[06-Nov-2026 10:05:00 UTC] WordPress database error Table 'wp_x' doesn't exist for query SELECT 1
LOG;

	public function test_parse_reads_time_level_and_message() {
		$entries = Easy_Debug_Log_Parser::parse( self::LOG );

		$this->assertCount( 6, $entries );
		$this->assertSame( '06-Nov-2026 10:00:00 UTC', $entries[0]['time'] );
		$this->assertSame( 'notice', $entries[0]['level'] );
		$this->assertSame( 'Undefined index: foo in /var/www/a.php on line 3', $entries[0]['message'] );
	}

	public function test_parse_maps_levels() {
		$levels = array_column( Easy_Debug_Log_Parser::parse( self::LOG ), 'level' );

		$this->assertSame( array( 'notice', 'warning', 'notice', 'fatal', 'deprecated', 'database' ), $levels );
	}

	public function test_plain_messages_are_other() {
		$entries = Easy_Debug_Log_Parser::parse( "[a] Automatic updates complete.\n" );

		$this->assertSame( 'other', $entries[0]['level'] );
	}

	public function test_parse_attaches_stack_trace_lines_to_the_entry() {
		$fatal = Easy_Debug_Log_Parser::parse( self::LOG )[3];

		$this->assertStringContainsString( 'Stack trace:', $fatal['detail'] );
		$this->assertStringContainsString( 'thrown in /var/www/c.php on line 5', $fatal['detail'] );
		$this->assertStringNotContainsString( 'Stack trace', $fatal['message'] );
	}

	public function test_parse_ignores_a_partial_first_line_without_a_timestamp() {
		$entries = Easy_Debug_Log_Parser::parse( "tail of an earlier line\n[06-Nov-2026 10:00:00 UTC] PHP Notice:  Hi\n" );

		$this->assertCount( 1, $entries );
		$this->assertSame( 'Hi', $entries[0]['message'] );
	}

	public function test_parse_handles_crlf_and_empty_input() {
		$this->assertSame( array(), Easy_Debug_Log_Parser::parse( '' ) );
		$this->assertCount( 2, Easy_Debug_Log_Parser::parse( "[a] PHP Notice:  One\r\n[b] PHP Notice:  Two\r\n" ) );
	}

	public function test_group_merges_repeats_and_counts_them() {
		$groups = Easy_Debug_Log_Parser::group( Easy_Debug_Log_Parser::parse( self::LOG ) );
		$notice = array_values(
			array_filter(
				$groups,
				function ( $group ) {
					return 'notice' === $group['level'];
				}
			)
		)[0];

		$this->assertCount( 5, $groups );
		$this->assertSame( 2, $notice['count'] );
		$this->assertSame( '06-Nov-2026 10:00:00 UTC', $notice['first_time'] );
		$this->assertSame( '06-Nov-2026 10:02:00 UTC', $notice['last_time'] );
	}

	public function test_group_orders_newest_first() {
		$groups = Easy_Debug_Log_Parser::group( Easy_Debug_Log_Parser::parse( self::LOG ) );

		$this->assertSame( array( 'database', 'deprecated', 'fatal', 'notice', 'warning' ), array_column( $groups, 'level' ) );
	}

	public function test_count_by_level() {
		$counts = Easy_Debug_Log_Parser::count_by_level( Easy_Debug_Log_Parser::group( Easy_Debug_Log_Parser::parse( self::LOG ) ) );

		$this->assertSame(
			array(
				'fatal'      => 1,
				'warning'    => 1,
				'notice'     => 2,
				'deprecated' => 1,
				'database'   => 1,
				'other'      => 0,
			),
			$counts
		);
	}
}
