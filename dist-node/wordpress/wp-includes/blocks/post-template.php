<?php
 function block_core_post_template_uses_featured_image( $inner_blocks ) { foreach ( $inner_blocks as $block ) { if ( 'core/post-featured-image' === $block->name ) { return true; } if ( 'core/cover' === $block->name && ! empty( $block->attributes['useFeaturedImage'] ) ) { return true; } if ( $block->inner_blocks && block_core_post_template_uses_featured_image( $block->inner_blocks ) ) { return true; } } return false; } function render_block_core_post_template( $attributes, $content, $block ) { $page_key = isset( $block->context['queryId'] ) ? 'query-' . $block->context['queryId'] . '-page' : 'query-page'; $page = empty( $_GET[ $page_key ] ) ? 1 : (int) $_GET[ $page_key ]; $query_args = build_query_vars_from_query_block( $block, $page ); $use_global_query = ( isset( $block->context['query']['inherit'] ) && $block->context['query']['inherit'] ); if ( $use_global_query ) { global $wp_query; if ( $wp_query && isset( $wp_query->query_vars ) && is_array( $wp_query->query_vars ) ) { unset( $query_args['offset'] ); $query_args = wp_parse_args( $wp_query->query_vars, $query_args ); if ( empty( $query_args['post_type'] ) && is_singular() ) { $query_args['post_type'] = get_post_type( get_the_ID() ); } } } $query = new WP_Query( $query_args ); if ( ! $query->have_posts() ) { return ''; } if ( block_core_post_template_uses_featured_image( $block->inner_blocks ) ) { update_post_thumbnail_cache( $query ); } $classnames = ''; if ( isset( $block->context['displayLayout'] ) && isset( $block->context['query'] ) ) { if ( isset( $block->context['displayLayout']['type'] ) && 'flex' === $block->context['displayLayout']['type'] ) { $classnames = "is-flex-container columns-{$block->context['displayLayout']['columns']}"; } } $wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $classnames ) ); $content = ''; while ( $query->have_posts() ) { $query->the_post(); $block_instance = $block->parsed_block; $block_instance['blockName'] = 'core/null'; $block_content = ( new WP_Block( $block_instance, array( 'postType' => get_post_type(), 'postId' => get_the_ID(), ) ) )->render( array( 'dynamic' => false ) ); $post_classes = implode( ' ', get_post_class( 'wp-block-post' ) ); $content .= '<li class="' . esc_attr( $post_classes ) . '">' . $block_content . '</li>'; } wp_reset_postdata(); return sprintf( '<ul %1$s>%2$s</ul>', $wrapper_attributes, $content ); } function register_block_core_post_template() { register_block_type_from_metadata( __DIR__ . '/post-template', array( 'render_callback' => 'render_block_core_post_template', 'skip_inner_blocks' => true, ) ); } add_action( 'init', 'register_block_core_post_template' ); 