<?php
 final class WP_Term { public $term_id; public $name = ''; public $slug = ''; public $term_group = ''; public $term_taxonomy_id = 0; public $taxonomy = ''; public $description = ''; public $parent = 0; public $count = 0; public $filter = 'raw'; public static function get_instance( $term_id, $taxonomy = null ) { global $wpdb; $term_id = (int) $term_id; if ( ! $term_id ) { return false; } $_term = wp_cache_get( $term_id, 'terms' ); if ( ! $_term || ( $taxonomy && $taxonomy !== $_term->taxonomy ) ) { $_term = false; $terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, tt.* FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE t.term_id = %d", $term_id ) ); if ( ! $terms ) { return false; } if ( $taxonomy ) { foreach ( $terms as $match ) { if ( $taxonomy === $match->taxonomy ) { $_term = $match; break; } } } elseif ( 1 === count( $terms ) ) { $_term = reset( $terms ); } else { foreach ( $terms as $t ) { if ( ! taxonomy_exists( $t->taxonomy ) ) { continue; } if ( $_term ) { return new WP_Error( 'ambiguous_term_id', __( 'Term ID is shared between multiple taxonomies' ), $term_id ); } $_term = $t; } } if ( ! $_term ) { return false; } if ( ! taxonomy_exists( $_term->taxonomy ) ) { return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) ); } $_term = sanitize_term( $_term, $_term->taxonomy, 'raw' ); if ( 1 === count( $terms ) ) { wp_cache_add( $term_id, $_term, 'terms' ); } } $term_obj = new WP_Term( $_term ); $term_obj->filter( $term_obj->filter ); return $term_obj; } public function __construct( $term ) { foreach ( get_object_vars( $term ) as $key => $value ) { $this->$key = $value; } } public function filter( $filter ) { sanitize_term( $this, $this->taxonomy, $filter ); } public function to_array() { return get_object_vars( $this ); } public function __get( $key ) { switch ( $key ) { case 'data': $data = new stdClass(); $columns = array( 'term_id', 'name', 'slug', 'term_group', 'term_taxonomy_id', 'taxonomy', 'description', 'parent', 'count' ); foreach ( $columns as $column ) { $data->{$column} = isset( $this->{$column} ) ? $this->{$column} : null; } return sanitize_term( $data, $data->taxonomy, 'raw' ); } } } 