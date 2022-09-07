<?php
 class WP_Sitemaps_Index { protected $registry; private $max_sitemaps = 50000; public function __construct( WP_Sitemaps_Registry $registry ) { $this->registry = $registry; } public function get_sitemap_list() { $sitemaps = array(); $providers = $this->registry->get_providers(); foreach ( $providers as $name => $provider ) { $sitemap_entries = $provider->get_sitemap_entries(); if ( ! $sitemap_entries ) { continue; } array_push( $sitemaps, ...$sitemap_entries ); if ( count( $sitemaps ) >= $this->max_sitemaps ) { break; } } return array_slice( $sitemaps, 0, $this->max_sitemaps, true ); } public function get_index_url() { global $wp_rewrite; if ( ! $wp_rewrite->using_permalinks() ) { return home_url( '/?sitemap=index' ); } return home_url( '/wp-sitemap.xml' ); } } 