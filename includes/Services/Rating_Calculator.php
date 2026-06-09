<?php
/**
 * Rating calculations.
 *
 * @package ReviewRating
 */

namespace ReviewRating\Services;

use ReviewRating\Repositories\Review_Repository;
use ReviewRating\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rating_Calculator {
	/**
	 * Repository.
	 *
	 * @var Review_Repository
	 */
	private $repository;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Review_Repository $repository Repository.
	 * @param Settings          $settings   Settings.
	 */
	public function __construct( Review_Repository $repository, Settings $settings ) {
		$this->repository = $repository;
		$this->settings   = $settings;
	}

	/**
	 * Calculate one review average.
	 *
	 * @param array $criteria Criteria ratings.
	 * @return float
	 */
	public function calculate_review_average( array $criteria ) {
		$criteria = array_filter(
			array_map( 'absint', $criteria ),
			static function ( $value ) {
				return $value >= 1 && $value <= 5;
			}
		);

		if ( empty( $criteria ) ) {
			return 0.0;
		}

		return round( array_sum( $criteria ) / count( $criteria ), 1 );
	}

	/**
	 * Calculate aggregate data for a post.
	 *
	 * @param int $post_id Reviewed post ID.
	 * @return array
	 */
	public function calculate_for_post( $post_id ) {
		$reviews        = $this->repository->get_reviews_for_post( $post_id );
		$total_reviews  = count( $reviews );
		$total_average  = 0;
		$criteria_sums  = array();
		$criteria_count = array();

		foreach ( $this->settings->get_enabled_criteria() as $key => $label ) {
			$criteria_sums[ $key ]  = 0;
			$criteria_count[ $key ] = 0;
		}

		foreach ( $reviews as $review ) {
			$total_average += $this->repository->get_review_average( $review->ID );
			$criteria      = $this->repository->get_review_criteria( $review->ID );

			foreach ( $criteria_sums as $key => $sum ) {
				$value = isset( $criteria[ $key ] ) ? absint( $criteria[ $key ] ) : 0;

				if ( $value > 0 ) {
					$criteria_sums[ $key ] += $value;
					$criteria_count[ $key ]++;
				}
			}
		}

		$criteria_average = array();

		foreach ( $criteria_sums as $key => $sum ) {
			$criteria_average[ $key ] = $criteria_count[ $key ] > 0 ? round( $sum / $criteria_count[ $key ], 1 ) : 0;
		}

		return array(
			'average'          => $total_reviews > 0 ? round( $total_average / $total_reviews, 1 ) : 0,
			'count'            => $total_reviews,
			'criteria_average' => $criteria_average,
		);
	}

	/**
	 * Recalculate and store aggregate data.
	 *
	 * @param int $post_id Reviewed post ID.
	 * @return array
	 */
	public function recalculate_post_cache( $post_id ) {
		$data = $this->calculate_for_post( $post_id );

		update_post_meta( $post_id, Settings::META_AVERAGE, $data['average'] );
		update_post_meta( $post_id, Settings::META_COUNT, $data['count'] );
		update_post_meta( $post_id, Settings::META_CRITERIA_AVG, $data['criteria_average'] );

		return $data;
	}

	/**
	 * Get cached aggregate data.
	 *
	 * @param int $post_id Reviewed post ID.
	 * @return array
	 */
	public function get_cached_or_calculate( $post_id ) {
		$average = get_post_meta( $post_id, Settings::META_AVERAGE, true );
		$count   = get_post_meta( $post_id, Settings::META_COUNT, true );
		$criteria_average = get_post_meta( $post_id, Settings::META_CRITERIA_AVG, true );

		if ( '' === $average || '' === $count || ! is_array( $criteria_average ) ) {
			return $this->recalculate_post_cache( $post_id );
		}

		return array(
			'average'          => (float) $average,
			'count'            => absint( $count ),
			'criteria_average' => $criteria_average,
		);
	}
}
