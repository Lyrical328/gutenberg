<?php
/**
 * REST API: WP_REST_Terms_Search_Handler class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.5.0
 */

/**
 * Core class representing a search handler for term in the REST API.
 *
 * @since 5.5.0
 *
 * @see WP_REST_Search_Handler
 */
class WP_REST_Term_Search_Handler extends WP_REST_Search_Handler {

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 */
	public function __construct() {
		$this->type = 'term';

		// Support all public post types except attachments.
		$this->subtypes = array_values(
			get_taxonomies(
				array(
					'public' => true,
				),
				'names'
			)
		);
	}

	/**
	 * Searches the object type content for a given search request.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_REST_Request $request Full REST request.
	 * @return array Associative array containing an `WP_REST_Search_Handler::RESULT_IDS` containing
	 *               an array of found IDs and `WP_REST_Search_Handler::RESULT_TOTAL` containing the
	 *               total count for the matching search results.
	 */
	public function search_items( WP_REST_Request $request ) {
		$term_search = '';
		if ( ! empty( $request['search'] ) ) {
			$term_search = $request['search'];
		}

		$term_search = apply_filters( 'rest_term_search_query', $term_search, $request );

		$taxonomies = $request[ WP_REST_Search_Controller::PROP_SUBTYPE ];
		if ( in_array( WP_REST_Search_Controller::TYPE_ANY, $taxonomies, true ) ) {
			$taxonomies = $this->subtypes;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'name__like' => $term_search,
			)
		);

		$found_ids = array();
		foreach ( $terms as $term ) {
			$found_ids[] = $term->term_id;
		}

		return array(
			self::RESULT_IDS   => $found_ids,
			self::RESULT_TOTAL => count( $found_ids ),
		);
	}

	/**
	 * Prepares the search result for a given ID.
	 *
	 * @since 5.0.0
	 *
	 * @param int   $id     Item ID.
	 * @param array $fields Fields to include for the item.
	 * @return array Associative array containing all fields for the item.
	 */
	public function prepare_item( $id, array $fields ) {
		$term = get_term( $id );

		$data = array();

		if ( in_array( WP_REST_Search_Controller::PROP_ID, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_ID ] = (int) $id;
		}
		if ( in_array( WP_REST_Search_Controller::PROP_TITLE, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_TITLE ] = $term->name;
		}
		if ( in_array( WP_REST_Search_Controller::PROP_URL, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_URL ] = get_term_link( $id );
		}
		if ( in_array( WP_REST_Search_Controller::PROP_TYPE, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_TYPE ] = $term->taxonomy;
		}

		return $data;
	}

	/**
	 * Prepares links for the search result of a given ID.
	 *
	 * @since 5.0.0
	 *
	 * @param int $id Item ID.
	 * @return array Links for the given item.
	 */
	public function prepare_item_links( $id ) {
		$term = get_term( $id );

		$links = array();

		$item_route = $this->detect_rest_item_route( $term );
		if ( ! empty( $item_route ) ) {
			$links['self'] = array(
				'href'       => rest_url( $item_route ),
				'embeddable' => true,
			);
		}

		$links['about'] = array(
			'href' => rest_url( sprintf( 'wp/v2/taxonomies/%s', $term->taxonomy ) ),
		);

		return $links;
	}

	/**
	 * Attempts to detect the route to access a single item.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_Post $post Term object.
	 * @return string REST route relative to the REST base URI, or empty string if unknown.
	 */
	protected function detect_rest_item_route( $term ) {
		$taxonomy = get_taxonomy( $term->taxonomy );
		if ( ! $taxonomy ) {
			return '';
		}

		// It's currently impossible to detect the REST URL from a custom controller.
		if ( ! empty( $taxonomy->rest_controller_class ) && 'WP_REST_Terms_Controller' !== $taxonomy->rest_controller_class ) {
			return '';
		}

		$namespace = 'wp/v2';
		$rest_base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

		return sprintf( '%s/%s/%d', $namespace, $rest_base, $term->term_id );
	}

}
