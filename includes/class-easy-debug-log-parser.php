<?php
/**
 * Parses and groups wp-content/debug.log entries.
 *
 * @package EasyPHPSettings
 * @since 1.1.9
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Debug_Log_Parser
 *
 * Entries start with "[timestamp] ". Lines without a timestamp (stack traces,
 * "thrown in …") belong to the entry above them.
 */
class Easy_Debug_Log_Parser {

	/**
	 * Levels in display order.
	 *
	 * @var string[]
	 */
	const LEVELS = array( 'fatal', 'warning', 'notice', 'deprecated', 'database', 'other' );

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Split log text into entries.
	 *
	 * @param string $content Log text.
	 * @return array[] Entries with time, level, message and detail.
	 */
	public static function parse( $content ) {
		$entries = array();
		$current = null;

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $content ) as $line ) {
			if ( preg_match( '/^\[([^\]]+)\]\s?(.*)$/', $line, $match ) ) {
				if ( null !== $current ) {
					$entries[] = $current;
				}
				$current = self::entry( $match[1], $match[2] );
				continue;
			}
			// Continuation line; a partial line before the first entry is dropped.
			if ( null !== $current && '' !== trim( $line ) ) {
				$current['detail'] .= ( '' === $current['detail'] ? '' : "\n" ) . $line;
			}
		}

		if ( null !== $current ) {
			$entries[] = $current;
		}
		return $entries;
	}

	/**
	 * Merge repeated entries (same level and message), newest first.
	 *
	 * @param array[] $entries Entries from parse().
	 * @return array[] Groups with level, message, detail, count, first_time, last_time.
	 */
	public static function group( array $entries ) {
		$groups = array();
		foreach ( $entries as $position => $entry ) {
			$key = $entry['level'] . '|' . $entry['message'];
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'level'      => $entry['level'],
					'message'    => $entry['message'],
					'detail'     => $entry['detail'],
					'count'      => 0,
					'first_time' => $entry['time'],
					'last_time'  => $entry['time'],
					'last_seen'  => $position,
				);
			}
			++$groups[ $key ]['count'];
			$groups[ $key ]['last_time'] = $entry['time'];
			$groups[ $key ]['last_seen'] = $position;
			if ( '' !== $entry['detail'] ) {
				$groups[ $key ]['detail'] = $entry['detail'];
			}
		}

		usort(
			$groups,
			function ( $a, $b ) {
				return $b['last_seen'] - $a['last_seen'];
			}
		);

		return array_map(
			function ( $group ) {
				unset( $group['last_seen'] );
				return $group;
			},
			$groups
		);
	}

	/**
	 * Total occurrences per level, in display order.
	 *
	 * @param array[] $groups Groups from group().
	 * @return array<string, int>
	 */
	public static function count_by_level( array $groups ) {
		$counts = array_fill_keys( self::LEVELS, 0 );
		foreach ( $groups as $group ) {
			$counts[ $group['level'] ] += $group['count'];
		}
		return $counts;
	}

	/**
	 * Build one entry from a timestamped line.
	 *
	 * @param string $time Timestamp text.
	 * @param string $text Rest of the line.
	 * @return array
	 */
	private static function entry( $time, $text ) {
		$level   = 'other';
		$message = trim( $text );

		if ( preg_match( '/^PHP ([A-Za-z ]+?):\s*(.*)$/', $message, $match ) ) {
			$level   = self::level_for( $match[1] );
			$message = $match[2];
		}
		if ( 0 === strpos( $message, 'WordPress database error' ) ) {
			$level = 'database';
		}

		return array(
			'time'    => $time,
			'level'   => $level,
			'message' => $message,
			'detail'  => '',
		);
	}

	/**
	 * Map PHP's error label to a level.
	 *
	 * @param string $label Label such as "Fatal error" or "Warning".
	 * @return string
	 */
	private static function level_for( $label ) {
		$label = strtolower( $label );
		if ( false !== strpos( $label, 'fatal' ) || false !== strpos( $label, 'parse' ) ) {
			return 'fatal';
		}
		if ( false !== strpos( $label, 'warning' ) ) {
			return 'warning';
		}
		if ( false !== strpos( $label, 'deprecated' ) ) {
			return 'deprecated';
		}
		if ( false !== strpos( $label, 'notice' ) || false !== strpos( $label, 'strict' ) ) {
			return 'notice';
		}
		return 'other';
	}
}
