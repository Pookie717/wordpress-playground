<?php
 function wp_register_layout_support( $block_type ) { $support_layout = block_has_support( $block_type, array( '__experimentalLayout' ), false ); if ( $support_layout ) { if ( ! $block_type->attributes ) { $block_type->attributes = array(); } if ( ! array_key_exists( 'layout', $block_type->attributes ) ) { $block_type->attributes['layout'] = array( 'type' => 'object', ); } } } function wp_get_layout_style( $selector, $layout, $has_block_gap_support = false, $gap_value = null, $should_skip_gap_serialization = false, $fallback_gap_value = '0.5em' ) { $layout_type = isset( $layout['type'] ) ? $layout['type'] : 'default'; $style = ''; if ( 'default' === $layout_type ) { $content_size = isset( $layout['contentSize'] ) ? $layout['contentSize'] : ''; $wide_size = isset( $layout['wideSize'] ) ? $layout['wideSize'] : ''; $all_max_width_value = $content_size ? $content_size : $wide_size; $wide_max_width_value = $wide_size ? $wide_size : $content_size; $all_max_width_value = safecss_filter_attr( explode( ';', $all_max_width_value )[0] ); $wide_max_width_value = safecss_filter_attr( explode( ';', $wide_max_width_value )[0] ); if ( $content_size || $wide_size ) { $style = "$selector > :where(:not(.alignleft):not(.alignright)) {"; $style .= 'max-width: ' . esc_html( $all_max_width_value ) . ';'; $style .= 'margin-left: auto !important;'; $style .= 'margin-right: auto !important;'; $style .= '}'; $style .= "$selector > .alignwide { max-width: " . esc_html( $wide_max_width_value ) . ';}'; $style .= "$selector .alignfull { max-width: none; }"; } $style .= "$selector > .alignleft { float: left; margin-inline-start: 0; margin-inline-end: 2em; }"; $style .= "$selector > .alignright { float: right; margin-inline-start: 2em; margin-inline-end: 0; }"; $style .= "$selector > .aligncenter { margin-left: auto !important; margin-right: auto !important; }"; if ( $has_block_gap_support ) { if ( is_array( $gap_value ) ) { $gap_value = isset( $gap_value['top'] ) ? $gap_value['top'] : null; } $gap_style = $gap_value && ! $should_skip_gap_serialization ? $gap_value : 'var( --wp--style--block-gap )'; $style .= "$selector > * { margin-block-start: 0; margin-block-end: 0; }"; $style .= "$selector > * + * { margin-block-start: $gap_style; margin-block-end: 0; }"; } } elseif ( 'flex' === $layout_type ) { $layout_orientation = isset( $layout['orientation'] ) ? $layout['orientation'] : 'horizontal'; $justify_content_options = array( 'left' => 'flex-start', 'right' => 'flex-end', 'center' => 'center', ); if ( 'horizontal' === $layout_orientation ) { $justify_content_options += array( 'space-between' => 'space-between' ); } $flex_wrap_options = array( 'wrap', 'nowrap' ); $flex_wrap = ! empty( $layout['flexWrap'] ) && in_array( $layout['flexWrap'], $flex_wrap_options, true ) ? $layout['flexWrap'] : 'wrap'; $style = "$selector {"; $style .= 'display: flex;'; if ( $has_block_gap_support ) { if ( is_array( $gap_value ) ) { $gap_row = isset( $gap_value['top'] ) ? $gap_value['top'] : $fallback_gap_value; $gap_column = isset( $gap_value['left'] ) ? $gap_value['left'] : $fallback_gap_value; $gap_value = $gap_row === $gap_column ? $gap_row : $gap_row . ' ' . $gap_column; } $gap_style = $gap_value && ! $should_skip_gap_serialization ? $gap_value : "var( --wp--style--block-gap, $fallback_gap_value )"; $style .= "gap: $gap_style;"; } else { $style .= "gap: $fallback_gap_value;"; } $style .= "flex-wrap: $flex_wrap;"; if ( 'horizontal' === $layout_orientation ) { $style .= 'align-items: center;'; if ( ! empty( $layout['justifyContent'] ) && array_key_exists( $layout['justifyContent'], $justify_content_options ) ) { $style .= "justify-content: {$justify_content_options[ $layout['justifyContent'] ]};"; } } else { $style .= 'flex-direction: column;'; if ( ! empty( $layout['justifyContent'] ) && array_key_exists( $layout['justifyContent'], $justify_content_options ) ) { $style .= "align-items: {$justify_content_options[ $layout['justifyContent'] ]};"; } else { $style .= 'align-items: flex-start;'; } } $style .= '}'; $style .= "$selector > * { margin: 0; }"; } return $style; } function wp_render_layout_support_flag( $block_content, $block ) { $block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] ); $support_layout = block_has_support( $block_type, array( '__experimentalLayout' ), false ); if ( ! $support_layout ) { return $block_content; } $block_gap = wp_get_global_settings( array( 'spacing', 'blockGap' ) ); $default_layout = wp_get_global_settings( array( 'layout' ) ); $has_block_gap_support = isset( $block_gap ) ? null !== $block_gap : false; $default_block_layout = _wp_array_get( $block_type->supports, array( '__experimentalLayout', 'default' ), array() ); $used_layout = isset( $block['attrs']['layout'] ) ? $block['attrs']['layout'] : $default_block_layout; if ( isset( $used_layout['inherit'] ) && $used_layout['inherit'] ) { if ( ! $default_layout ) { return $block_content; } $used_layout = $default_layout; } $class_names = array(); $container_class = wp_unique_id( 'wp-container-' ); $class_names[] = $container_class; if ( ! empty( $block['attrs']['layout']['orientation'] ) ) { $class_names[] = 'is-' . sanitize_title( $block['attrs']['layout']['orientation'] ); } if ( ! empty( $block['attrs']['layout']['justifyContent'] ) ) { $class_names[] = 'is-content-justification-' . sanitize_title( $block['attrs']['layout']['justifyContent'] ); } if ( ! empty( $block['attrs']['layout']['flexWrap'] ) && 'nowrap' === $block['attrs']['layout']['flexWrap'] ) { $class_names[] = 'is-nowrap'; } $gap_value = _wp_array_get( $block, array( 'attrs', 'style', 'spacing', 'blockGap' ) ); if ( is_array( $gap_value ) ) { foreach ( $gap_value as $key => $value ) { $gap_value[ $key ] = $value && preg_match( '%[\\\(&=}]|/\*%', $value ) ? null : $value; } } else { $gap_value = $gap_value && preg_match( '%[\\\(&=}]|/\*%', $gap_value ) ? null : $gap_value; } $fallback_gap_value = _wp_array_get( $block_type->supports, array( 'spacing', 'blockGap', '__experimentalDefault' ), '0.5em' ); $should_skip_gap_serialization = wp_should_skip_block_supports_serialization( $block_type, 'spacing', 'blockGap' ); $style = wp_get_layout_style( ".$container_class", $used_layout, $has_block_gap_support, $gap_value, $should_skip_gap_serialization, $fallback_gap_value ); $content = preg_replace( '/' . preg_quote( 'class="', '/' ) . '/', 'class="' . esc_attr( implode( ' ', $class_names ) ) . ' ', $block_content, 1 ); wp_enqueue_block_support_styles( $style ); return $content; } WP_Block_Supports::get_instance()->register( 'layout', array( 'register_attribute' => 'wp_register_layout_support', ) ); add_filter( 'render_block', 'wp_render_layout_support_flag', 10, 2 ); function wp_restore_group_inner_container( $block_content, $block ) { $tag_name = isset( $block['attrs']['tagName'] ) ? $block['attrs']['tagName'] : 'div'; $group_with_inner_container_regex = sprintf( '/(^\s*<%1$s\b[^>]*wp-block-group(\s|")[^>]*>)(\s*<div\b[^>]*wp-block-group__inner-container(\s|")[^>]*>)((.|\S|\s)*)/U', preg_quote( $tag_name, '/' ) ); if ( WP_Theme_JSON_Resolver::theme_has_support() || 1 === preg_match( $group_with_inner_container_regex, $block_content ) || ( isset( $block['attrs']['layout']['type'] ) && 'default' !== $block['attrs']['layout']['type'] ) ) { return $block_content; } $replace_regex = sprintf( '/(^\s*<%1$s\b[^>]*wp-block-group[^>]*>)(.*)(<\/%1$s>\s*$)/ms', preg_quote( $tag_name, '/' ) ); $updated_content = preg_replace_callback( $replace_regex, static function( $matches ) { return $matches[1] . '<div class="wp-block-group__inner-container">' . $matches[2] . '</div>' . $matches[3]; }, $block_content ); return $updated_content; } add_filter( 'render_block_core/group', 'wp_restore_group_inner_container', 10, 2 ); function wp_restore_image_outer_container( $block_content, $block ) { $image_with_align = "
/# 1) everything up to the class attribute contents
(
	^\s*
	<figure\b
	[^>]*
	\bclass=
	[\"']
)
# 2) the class attribute contents
(
	[^\"']*
	\bwp-block-image\b
	[^\"']*
	\b(?:alignleft|alignright|aligncenter)\b
	[^\"']*
)
# 3) everything after the class attribute contents
(
	[\"']
	[^>]*
	>
	.*
	<\/figure>
)/iUx"; if ( WP_Theme_JSON_Resolver::theme_has_support() || 0 === preg_match( $image_with_align, $block_content, $matches ) ) { return $block_content; } $wrapper_classnames = array( 'wp-block-image' ); if ( ! empty( $block['attrs']['className'] ) ) { $wrapper_classnames = array_merge( $wrapper_classnames, explode( ' ', $block['attrs']['className'] ) ); } $content_classnames = explode( ' ', $matches[2] ); $filtered_content_classnames = array_diff( $content_classnames, $wrapper_classnames ); return '<div class="' . implode( ' ', $wrapper_classnames ) . '">' . $matches[1] . implode( ' ', $filtered_content_classnames ) . $matches[3] . '</div>'; } add_filter( 'render_block_core/image', 'wp_restore_image_outer_container', 10, 2 ); 