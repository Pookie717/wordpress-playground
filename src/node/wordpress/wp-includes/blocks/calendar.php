<?php
 function render_block_core_calendar( $attributes ) { global $monthnum, $year; if ( ! block_core_calendar_has_published_posts() ) { if ( is_user_logged_in() ) { return '<div>' . __( 'The calendar block is hidden because there are no published posts.' ) . '</div>'; } return ''; } $previous_monthnum = $monthnum; $previous_year = $year; if ( isset( $attributes['month'] ) && isset( $attributes['year'] ) ) { $permalink_structure = get_option( 'permalink_structure' ); if ( strpos( $permalink_structure, '%monthnum%' ) !== false && strpos( $permalink_structure, '%year%' ) !== false ) { $monthnum = $attributes['month']; $year = $attributes['year']; } } $wrapper_attributes = get_block_wrapper_attributes(); $output = sprintf( '<div %1$s>%2$s</div>', $wrapper_attributes, get_calendar( true, false ) ); $monthnum = $previous_monthnum; $year = $previous_year; return $output; } function register_block_core_calendar() { register_block_type_from_metadata( __DIR__ . '/calendar', array( 'render_callback' => 'render_block_core_calendar', ) ); } add_action( 'init', 'register_block_core_calendar' ); function block_core_calendar_has_published_posts() { if ( is_multisite() ) { return 0 < (int) get_option( 'post_count' ); } $has_published_posts = get_option( 'wp_calendar_block_has_published_posts', null ); if ( null !== $has_published_posts ) { return (bool) $has_published_posts; } return block_core_calendar_update_has_published_posts(); } function block_core_calendar_update_has_published_posts() { global $wpdb; $has_published_posts = (bool) $wpdb->get_var( "SELECT 1 as test FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' LIMIT 1" ); update_option( 'wp_calendar_block_has_published_posts', $has_published_posts ); return $has_published_posts; } if ( ! is_multisite() ) { function block_core_calendar_update_has_published_post_on_delete( $post_id ) { $post = get_post( $post_id ); if ( ! $post || 'publish' !== $post->post_status || 'post' !== $post->post_type ) { return; } block_core_calendar_update_has_published_posts(); } function block_core_calendar_update_has_published_post_on_transition_post_status( $new_status, $old_status, $post ) { if ( $new_status === $old_status ) { return; } if ( 'post' !== get_post_type( $post ) ) { return; } if ( 'publish' !== $new_status && 'publish' !== $old_status ) { return; } block_core_calendar_update_has_published_posts(); } add_action( 'delete_post', 'block_core_calendar_update_has_published_post_on_delete' ); add_action( 'transition_post_status', 'block_core_calendar_update_has_published_post_on_transition_post_status', 10, 3 ); } 