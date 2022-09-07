<?php
 function wp_get_global_settings( $path = array(), $context = array() ) { if ( ! empty( $context['block_name'] ) ) { $path = array_merge( array( 'blocks', $context['block_name'] ), $path ); } $origin = 'custom'; if ( isset( $context['origin'] ) && 'base' === $context['origin'] ) { $origin = 'theme'; } $settings = WP_Theme_JSON_Resolver::get_merged_data( $origin )->get_settings(); return _wp_array_get( $settings, $path, $settings ); } function wp_get_global_styles( $path = array(), $context = array() ) { if ( ! empty( $context['block_name'] ) ) { $path = array_merge( array( 'blocks', $context['block_name'] ), $path ); } $origin = 'custom'; if ( isset( $context['origin'] ) && 'base' === $context['origin'] ) { $origin = 'theme'; } $styles = WP_Theme_JSON_Resolver::get_merged_data( $origin )->get_raw_data()['styles']; return _wp_array_get( $styles, $path, $styles ); } function wp_get_global_stylesheet( $types = array() ) { $can_use_cached = ( ( empty( $types ) ) && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) && ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) && ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) && ! is_admin() ); $transient_name = 'global_styles_' . get_stylesheet(); if ( $can_use_cached ) { $cached = get_transient( $transient_name ); if ( $cached ) { return $cached; } } $tree = WP_Theme_JSON_Resolver::get_merged_data(); $supports_theme_json = WP_Theme_JSON_Resolver::theme_has_support(); if ( empty( $types ) && ! $supports_theme_json ) { $types = array( 'variables', 'presets' ); } elseif ( empty( $types ) ) { $types = array( 'variables', 'styles', 'presets' ); } $styles_variables = ''; if ( in_array( 'variables', $types, true ) ) { $styles_variables = $tree->get_stylesheet( array( 'variables' ) ); $types = array_diff( $types, array( 'variables' ) ); } $styles_rest = ''; if ( ! empty( $types ) ) { $origins = array( 'default', 'theme', 'custom' ); if ( ! $supports_theme_json ) { $origins = array( 'default' ); } $styles_rest = $tree->get_stylesheet( $types, $origins ); } $stylesheet = $styles_variables . $styles_rest; if ( $can_use_cached ) { set_transient( $transient_name, $stylesheet, MINUTE_IN_SECONDS ); } return $stylesheet; } function wp_get_global_styles_svg_filters() { $can_use_cached = ( ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) && ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) && ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) && ! is_admin() ); $transient_name = 'global_styles_svg_filters_' . get_stylesheet(); if ( $can_use_cached ) { $cached = get_transient( $transient_name ); if ( $cached ) { return $cached; } } $supports_theme_json = WP_Theme_JSON_Resolver::theme_has_support(); $origins = array( 'default', 'theme', 'custom' ); if ( ! $supports_theme_json ) { $origins = array( 'default' ); } $tree = WP_Theme_JSON_Resolver::get_merged_data(); $svgs = $tree->get_svg_filters( $origins ); if ( $can_use_cached ) { set_transient( $transient_name, $svgs, MINUTE_IN_SECONDS ); } return $svgs; } 