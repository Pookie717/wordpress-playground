<?php
 define( 'EP_NONE', 0 ); define( 'EP_PERMALINK', 1 ); define( 'EP_ATTACHMENT', 2 ); define( 'EP_DATE', 4 ); define( 'EP_YEAR', 8 ); define( 'EP_MONTH', 16 ); define( 'EP_DAY', 32 ); define( 'EP_ROOT', 64 ); define( 'EP_COMMENTS', 128 ); define( 'EP_SEARCH', 256 ); define( 'EP_CATEGORIES', 512 ); define( 'EP_TAGS', 1024 ); define( 'EP_AUTHORS', 2048 ); define( 'EP_PAGES', 4096 ); define( 'EP_ALL_ARCHIVES', EP_DATE | EP_YEAR | EP_MONTH | EP_DAY | EP_CATEGORIES | EP_TAGS | EP_AUTHORS ); define( 'EP_ALL', EP_PERMALINK | EP_ATTACHMENT | EP_ROOT | EP_COMMENTS | EP_SEARCH | EP_PAGES | EP_ALL_ARCHIVES ); function add_rewrite_rule( $regex, $query, $after = 'bottom' ) { global $wp_rewrite; $wp_rewrite->add_rule( $regex, $query, $after ); } function add_rewrite_tag( $tag, $regex, $query = '' ) { if ( strlen( $tag ) < 3 || '%' !== $tag[0] || '%' !== $tag[ strlen( $tag ) - 1 ] ) { return; } global $wp_rewrite, $wp; if ( empty( $query ) ) { $qv = trim( $tag, '%' ); $wp->add_query_var( $qv ); $query = $qv . '='; } $wp_rewrite->add_rewrite_tag( $tag, $regex, $query ); } function remove_rewrite_tag( $tag ) { global $wp_rewrite; $wp_rewrite->remove_rewrite_tag( $tag ); } function add_permastruct( $name, $struct, $args = array() ) { global $wp_rewrite; if ( ! is_array( $args ) ) { $args = array( 'with_front' => $args ); } if ( func_num_args() == 4 ) { $args['ep_mask'] = func_get_arg( 3 ); } $wp_rewrite->add_permastruct( $name, $struct, $args ); } function remove_permastruct( $name ) { global $wp_rewrite; $wp_rewrite->remove_permastruct( $name ); } function add_feed( $feedname, $function ) { global $wp_rewrite; if ( ! in_array( $feedname, $wp_rewrite->feeds, true ) ) { $wp_rewrite->feeds[] = $feedname; } $hook = 'do_feed_' . $feedname; remove_action( $hook, $hook ); add_action( $hook, $function, 10, 2 ); return $hook; } function flush_rewrite_rules( $hard = true ) { global $wp_rewrite; if ( is_callable( array( $wp_rewrite, 'flush_rules' ) ) ) { $wp_rewrite->flush_rules( $hard ); } } function add_rewrite_endpoint( $name, $places, $query_var = true ) { global $wp_rewrite; $wp_rewrite->add_endpoint( $name, $places, $query_var ); } function _wp_filter_taxonomy_base( $base ) { if ( ! empty( $base ) ) { $base = preg_replace( '|^/index\.php/|', '', $base ); $base = trim( $base, '/' ); } return $base; } function wp_resolve_numeric_slug_conflicts( $query_vars = array() ) { if ( ! isset( $query_vars['year'] ) && ! isset( $query_vars['monthnum'] ) && ! isset( $query_vars['day'] ) ) { return $query_vars; } $permastructs = array_values( array_filter( explode( '/', get_option( 'permalink_structure' ) ) ) ); $postname_index = array_search( '%postname%', $permastructs, true ); if ( false === $postname_index ) { return $query_vars; } $compare = ''; if ( 0 === $postname_index && ( isset( $query_vars['year'] ) || isset( $query_vars['monthnum'] ) ) ) { $compare = 'year'; } elseif ( $postname_index && '%year%' === $permastructs[ $postname_index - 1 ] && ( isset( $query_vars['monthnum'] ) || isset( $query_vars['day'] ) ) ) { $compare = 'monthnum'; } elseif ( $postname_index && '%monthnum%' === $permastructs[ $postname_index - 1 ] && isset( $query_vars['day'] ) ) { $compare = 'day'; } if ( ! $compare ) { return $query_vars; } $value = $query_vars[ $compare ]; $post = get_page_by_path( $value, OBJECT, 'post' ); if ( ! ( $post instanceof WP_Post ) ) { return $query_vars; } if ( preg_match( '/^([0-9]{4})\-([0-9]{2})/', $post->post_date, $matches ) && isset( $query_vars['year'] ) && ( 'monthnum' === $compare || 'day' === $compare ) ) { if ( (int) $query_vars['year'] !== (int) $matches[1] ) { return $query_vars; } if ( 'day' === $compare && isset( $query_vars['monthnum'] ) && (int) $query_vars['monthnum'] !== (int) $matches[2] ) { return $query_vars; } } $maybe_page = ''; if ( 'year' === $compare && isset( $query_vars['monthnum'] ) ) { $maybe_page = $query_vars['monthnum']; } elseif ( 'monthnum' === $compare && isset( $query_vars['day'] ) ) { $maybe_page = $query_vars['day']; } $maybe_page = (int) trim( $maybe_page, '/' ); $post_page_count = substr_count( $post->post_content, '<!--nextpage-->' ) + 1; if ( 1 === $post_page_count && $maybe_page ) { return $query_vars; } if ( $post_page_count > 1 && $maybe_page > $post_page_count ) { return $query_vars; } if ( '' !== $maybe_page ) { $query_vars['page'] = (int) $maybe_page; } unset( $query_vars['year'] ); unset( $query_vars['monthnum'] ); unset( $query_vars['day'] ); $query_vars['name'] = $post->post_name; return $query_vars; } function url_to_postid( $url ) { global $wp_rewrite; $url = apply_filters( 'url_to_postid', $url ); $url_host = str_replace( 'www.', '', parse_url( $url, PHP_URL_HOST ) ); $home_url_host = str_replace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) ); if ( $url_host && $url_host !== $home_url_host ) { return 0; } if ( preg_match( '#[?&](p|page_id|attachment_id)=(\d+)#', $url, $values ) ) { $id = absint( $values[2] ); if ( $id ) { return $id; } } $url_split = explode( '#', $url ); $url = $url_split[0]; $url_split = explode( '?', $url ); $url = $url_split[0]; $scheme = parse_url( home_url(), PHP_URL_SCHEME ); $url = set_url_scheme( $url, $scheme ); if ( false !== strpos( home_url(), '://www.' ) && false === strpos( $url, '://www.' ) ) { $url = str_replace( '://', '://www.', $url ); } if ( false === strpos( home_url(), '://www.' ) ) { $url = str_replace( '://www.', '://', $url ); } if ( trim( $url, '/' ) === home_url() && 'page' === get_option( 'show_on_front' ) ) { $page_on_front = get_option( 'page_on_front' ); if ( $page_on_front && get_post( $page_on_front ) instanceof WP_Post ) { return (int) $page_on_front; } } $rewrite = $wp_rewrite->wp_rewrite_rules(); if ( empty( $rewrite ) ) { return 0; } if ( ! $wp_rewrite->using_index_permalinks() ) { $url = str_replace( $wp_rewrite->index . '/', '', $url ); } if ( false !== strpos( trailingslashit( $url ), home_url( '/' ) ) ) { $url = str_replace( home_url(), '', $url ); } else { $home_path = parse_url( home_url( '/' ) ); $home_path = isset( $home_path['path'] ) ? $home_path['path'] : ''; $url = preg_replace( sprintf( '#^%s#', preg_quote( $home_path ) ), '', trailingslashit( $url ) ); } $url = trim( $url, '/' ); $request = $url; $post_type_query_vars = array(); foreach ( get_post_types( array(), 'objects' ) as $post_type => $t ) { if ( ! empty( $t->query_var ) ) { $post_type_query_vars[ $t->query_var ] = $post_type; } } $request_match = $request; foreach ( (array) $rewrite as $match => $query ) { if ( ! empty( $url ) && ( $url != $request ) && ( strpos( $match, $url ) === 0 ) ) { $request_match = $url . '/' . $request; } if ( preg_match( "#^$match#", $request_match, $matches ) ) { if ( $wp_rewrite->use_verbose_page_rules && preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch ) ) { $page = get_page_by_path( $matches[ $varmatch[1] ] ); if ( ! $page ) { continue; } $post_status_obj = get_post_status_object( $page->post_status ); if ( ! $post_status_obj->public && ! $post_status_obj->protected && ! $post_status_obj->private && $post_status_obj->exclude_from_search ) { continue; } } $query = preg_replace( '!^.+\?!', '', $query ); $query = addslashes( WP_MatchesMapRegex::apply( $query, $matches ) ); global $wp; parse_str( $query, $query_vars ); $query = array(); foreach ( (array) $query_vars as $key => $value ) { if ( in_array( (string) $key, $wp->public_query_vars, true ) ) { $query[ $key ] = $value; if ( isset( $post_type_query_vars[ $key ] ) ) { $query['post_type'] = $post_type_query_vars[ $key ]; $query['name'] = $value; } } } $query = wp_resolve_numeric_slug_conflicts( $query ); $query = new WP_Query( $query ); if ( ! empty( $query->posts ) && $query->is_singular ) { return $query->post->ID; } else { return 0; } } } return 0; } 