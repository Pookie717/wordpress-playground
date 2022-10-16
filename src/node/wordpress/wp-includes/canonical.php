<?php
 function redirect_canonical( $requested_url = null, $do_redirect = true ) { global $wp_rewrite, $is_IIS, $wp_query, $wpdb, $wp; if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( strtoupper( $_SERVER['REQUEST_METHOD'] ), array( 'GET', 'HEAD' ), true ) ) { return; } if ( is_preview() && get_query_var( 'p' ) && 'publish' === get_post_status( get_query_var( 'p' ) ) ) { if ( ! isset( $_GET['preview_id'] ) || ! isset( $_GET['preview_nonce'] ) || ! wp_verify_nonce( $_GET['preview_nonce'], 'post_preview_' . (int) $_GET['preview_id'] ) ) { $wp_query->is_preview = false; } } if ( is_admin() || is_search() || is_preview() || is_trackback() || is_favicon() || ( $is_IIS && ! iis7_supports_permalinks() ) ) { return; } if ( ! $requested_url && isset( $_SERVER['HTTP_HOST'] ) ) { $requested_url = is_ssl() ? 'https://' : 'http://'; $requested_url .= $_SERVER['HTTP_HOST']; $requested_url .= $_SERVER['REQUEST_URI']; } $original = parse_url( $requested_url ); if ( false === $original ) { return; } $redirect = $original; $redirect_url = false; $redirect_obj = false; if ( ! isset( $redirect['path'] ) ) { $redirect['path'] = ''; } if ( ! isset( $redirect['query'] ) ) { $redirect['query'] = ''; } $redirect['path'] = preg_replace( '|(%C2%A0)+$|i', '', $redirect['path'] ); if ( get_query_var( 'preview' ) ) { $redirect['query'] = remove_query_arg( 'preview', $redirect['query'] ); } $post_id = get_query_var( 'p' ); if ( is_feed() && $post_id ) { $redirect_url = get_post_comments_feed_link( $post_id, get_query_var( 'feed' ) ); $redirect_obj = get_post( $post_id ); if ( $redirect_url ) { $redirect['query'] = _remove_qs_args_if_not_in_url( $redirect['query'], array( 'p', 'page_id', 'attachment_id', 'pagename', 'name', 'post_type', 'feed' ), $redirect_url ); $redirect['path'] = parse_url( $redirect_url, PHP_URL_PATH ); } } if ( is_singular() && $wp_query->post_count < 1 && $post_id ) { $vars = $wpdb->get_results( $wpdb->prepare( "SELECT post_type, post_parent FROM $wpdb->posts WHERE ID = %d", $post_id ) ); if ( ! empty( $vars[0] ) ) { $vars = $vars[0]; if ( 'revision' === $vars->post_type && $vars->post_parent > 0 ) { $post_id = $vars->post_parent; } $redirect_url = get_permalink( $post_id ); $redirect_obj = get_post( $post_id ); if ( $redirect_url ) { $redirect['query'] = _remove_qs_args_if_not_in_url( $redirect['query'], array( 'p', 'page_id', 'attachment_id', 'pagename', 'name', 'post_type' ), $redirect_url ); } } } if ( is_404() ) { $post_id = max( get_query_var( 'p' ), get_query_var( 'page_id' ), get_query_var( 'attachment_id' ) ); $redirect_post = $post_id ? get_post( $post_id ) : false; if ( $redirect_post ) { $post_type_obj = get_post_type_object( $redirect_post->post_type ); if ( $post_type_obj && $post_type_obj->public && 'auto-draft' !== $redirect_post->post_status ) { $redirect_url = get_permalink( $redirect_post ); $redirect_obj = get_post( $redirect_post ); $redirect['query'] = _remove_qs_args_if_not_in_url( $redirect['query'], array( 'p', 'page_id', 'attachment_id', 'pagename', 'name', 'post_type' ), $redirect_url ); } } $year = get_query_var( 'year' ); $month = get_query_var( 'monthnum' ); $day = get_query_var( 'day' ); if ( $year && $month && $day ) { $date = sprintf( '%04d-%02d-%02d', $year, $month, $day ); if ( ! wp_checkdate( $month, $day, $year, $date ) ) { $redirect_url = get_month_link( $year, $month ); $redirect['query'] = _remove_qs_args_if_not_in_url( $redirect['query'], array( 'year', 'monthnum', 'day' ), $redirect_url ); } } elseif ( $year && $month && $month > 12 ) { $redirect_url = get_year_link( $year ); $redirect['query'] = _remove_qs_args_if_not_in_url( $redirect['query'], array( 'year', 'monthnum' ), $redirect_url ); } if ( get_query_var( 'page' ) ) { $post_id = 0; if ( $wp_query->queried_object instanceof WP_Post ) { $post_id = $wp_query->queried_object->ID; } elseif ( $wp_query->post ) { $post_id = $wp_query->post->ID; } if ( $post_id ) { $redirect_url = get_permalink( $post_id ); $redirect_obj = get_post( $post_id ); $redirect['path'] = rtrim( $redirect['path'], (int) get_query_var( 'page' ) . '/' ); $redirect['query'] = remove_query_arg( 'page', $redirect['query'] ); } } if ( ! $redirect_url ) { $redirect_url = redirect_guess_404_permalink(); if ( $redirect_url ) { $redirect['query'] = _remove_qs_args_if_not_in_url( $redirect['query'], array( 'page', 'feed', 'p', 'page_id', 'attachment_id', 'pagename', 'name', 'post_type' ), $redirect_url ); } } } elseif ( is_object( $wp_rewrite ) && $wp_rewrite->using_permalinks() ) { if ( is_attachment() && ! array_diff( array_keys( $wp->query_vars ), array( 'attachment', 'attachment_id' ) ) && ! $redirect_url ) { if ( ! empty( $_GET['attachment_id'] ) ) { $redirect_url = get_attachment_link( get_query_var( 'attachment_id' ) ); $redirect_obj = get_post( get_query_var( 'attachment_id' ) ); if ( $redirect_url ) { $redirect['query'] = remove_query_arg( 'attachment_id', $redirect['query'] ); } } else { $redirect_url = get_attachment_link(); $redirect_obj = get_post(); } } elseif ( is_single() && ! empty( $_GET['p'] ) && ! $redirect_url ) { $redirect_url = get_permalink( get_query_var( 'p' ) ); $redirect_obj = get_post( get_query_var( 'p' ) ); if ( $redirect_url ) { $redirect['query'] = remove_query_arg( array( 'p', 'post_type' ), $redirect['query'] ); } } elseif ( is_single() && ! empty( $_GET['name'] ) && ! $redirect_url ) { $redirect_url = get_permalink( $wp_query->get_queried_object_id() ); $redirect_obj = get_post( $wp_query->get_queried_object_id() ); if ( $redirect_url ) { $redirect['query'] = remove_query_arg( 'name', $redirect['query'] ); } } elseif ( is_page() && ! empty( $_GET['page_id'] ) && ! $redirect_url ) { $redirect_url = get_permalink( get_query_var( 'page_id' ) ); $redirect_obj = get_post( get_query_var( 'page_id' ) ); if ( $redirect_url ) { $redirect['query'] = remove_query_arg( 'page_id', $redirect['query'] ); } } elseif ( is_page() && ! is_feed() && ! $redirect_url && 'page' === get_option( 'show_on_front' ) && get_queried_object_id() === (int) get_option( 'page_on_front' ) ) { $redirect_url = home_url( '/' ); } elseif ( is_home() && ! empty( $_GET['page_id'] ) && ! $redirect_url && 'page' === get_option( 'show_on_front' ) && get_query_var( 'page_id' ) === (int) get_option( 'page_for_posts' ) ) { $redirect_url = get_permalink( get_option( 'page_for_posts' ) ); $redirect_obj = get_post( get_option( 'page_for_posts' ) ); if ( $redirect_url ) { $redirect['query'] = remove_query_arg( 'page_id', $redirect['query'] ); } } elseif ( ! empty( $_GET['m'] ) && ( is_year() || is_month() || is_day() ) ) { $m = get_query_var( 'm' ); switch ( strlen( $m ) ) { case 4: $redirect_url = get_year_link( $m ); break; case 6: $redirect_url = get_month_link( substr( $m, 0, 4 ), substr( $m, 4, 2 ) ); break; case 8: $redirect_url = get_day_link( substr( $m, 0, 4 ), substr( $m, 4, 2 ), substr( $m, 6, 2 ) ); break; } if ( $redirect_url ) { $redirect['query'] = remove_query_arg( 'm', $redirect['query'] ); } } elseif ( is_date() ) { $year = get_query_var( 'year' ); $month = get_query_var( 'monthnum' ); $day = get_query_var( 'day' ); if ( is_day() && $year && $month && ! empty( $_GET['day'] ) ) { $redirect_url = get_day_link( $year, $month, $day ); if ( $redirect_url ) { $redirect['query'] = remove_query_arg( array( 'year', 'monthnum', 'day' ), $redirect['query'] ); } } elseif ( is_month() && $year && ! empty( $_GET['monthnum'] ) ) { $redirect_url = get_month_link( $year, $month ); if ( $redirect_url ) { $redirect['query'] = remove_query_arg( array( 'year', 'monthnum' ), $redirect['query'] ); } } elseif ( is_year() && ! empty( $_GET['year'] ) ) { $redirect_url = get_year_link( $year ); if ( $redirect_url ) { $redirect['query'] = remove_query_arg( 'year', $redirect['query'] ); } } } elseif ( is_author() && ! empty( $_GET['author'] ) && preg_match( '|^[0-9]+$|', $_GET['author'] ) ) { $author = get_userdata( get_query_var( 'author' ) ); if ( false !== $author && $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE $wpdb->posts.post_author = %d AND $wpdb->posts.post_status = 'publish' LIMIT 1", $author->ID ) ) ) { $redirect_url = get_author_posts_url( $author->ID, $author->user_nicename ); $redirect_obj = $author; if ( $redirect_url ) { $redirect['query'] = remove_query_arg( 'author', $redirect['query'] ); } } } elseif ( is_category() || is_tag() || is_tax() ) { $term_count = 0; foreach ( $wp_query->tax_query->queried_terms as $tax_query ) { $term_count += count( $tax_query['terms'] ); } $obj = $wp_query->get_queried_object(); if ( $term_count <= 1 && ! empty( $obj->term_id ) ) { $tax_url = get_term_link( (int) $obj->term_id, $obj->taxonomy ); if ( $tax_url && ! is_wp_error( $tax_url ) ) { if ( ! empty( $redirect['query'] ) ) { $qv_remove = array( 'term', 'taxonomy' ); if ( is_category() ) { $qv_remove[] = 'category_name'; $qv_remove[] = 'cat'; } elseif ( is_tag() ) { $qv_remove[] = 'tag'; $qv_remove[] = 'tag_id'; } else { $tax_obj = get_taxonomy( $obj->taxonomy ); if ( false !== $tax_obj->query_var ) { $qv_remove[] = $tax_obj->query_var; } } $rewrite_vars = array_diff( array_keys( $wp_query->query ), array_keys( $_GET ) ); if ( ! array_diff( $rewrite_vars, array_keys( $_GET ) ) ) { $redirect['query'] = remove_query_arg( $qv_remove, $redirect['query'] ); $tax_url = parse_url( $tax_url ); if ( ! empty( $tax_url['query'] ) ) { parse_str( $tax_url['query'], $query_vars ); $redirect['query'] = add_query_arg( $query_vars, $redirect['query'] ); } else { $redirect['path'] = $tax_url['path']; } } else { foreach ( $qv_remove as $_qv ) { if ( isset( $rewrite_vars[ $_qv ] ) ) { $redirect['query'] = remove_query_arg( $_qv, $redirect['query'] ); } } } } } } } elseif ( is_single() && strpos( $wp_rewrite->permalink_structure, '%category%' ) !== false ) { $category_name = get_query_var( 'category_name' ); if ( $category_name ) { $category = get_category_by_path( $category_name ); if ( ! $category || is_wp_error( $category ) || ! has_term( $category->term_id, 'category', $wp_query->get_queried_object_id() ) ) { $redirect_url = get_permalink( $wp_query->get_queried_object_id() ); $redirect_obj = get_post( $wp_query->get_queried_object_id() ); } } } if ( is_singular() && get_query_var( 'page' ) ) { $page = get_query_var( 'page' ); if ( ! $redirect_url ) { $redirect_url = get_permalink( get_queried_object_id() ); $redirect_obj = get_post( get_queried_object_id() ); } if ( $page > 1 ) { $redirect_url = trailingslashit( $redirect_url ); if ( is_front_page() ) { $redirect_url .= user_trailingslashit( "$wp_rewrite->pagination_base/$page", 'paged' ); } else { $redirect_url .= user_trailingslashit( $page, 'single_paged' ); } } $redirect['query'] = remove_query_arg( 'page', $redirect['query'] ); } if ( get_query_var( 'sitemap' ) ) { $redirect_url = get_sitemap_url( get_query_var( 'sitemap' ), get_query_var( 'sitemap-subtype' ), get_query_var( 'paged' ) ); $redirect['query'] = remove_query_arg( array( 'sitemap', 'sitemap-subtype', 'paged' ), $redirect['query'] ); } elseif ( get_query_var( 'paged' ) || is_feed() || get_query_var( 'cpage' ) ) { $paged = get_query_var( 'paged' ); $feed = get_query_var( 'feed' ); $cpage = get_query_var( 'cpage' ); while ( preg_match( "#/$wp_rewrite->pagination_base/?[0-9]+?(/+)?$#", $redirect['path'] ) || preg_match( '#/(comments/?)?(feed|rss2?|rdf|atom)(/+)?$#', $redirect['path'] ) || preg_match( "#/{$wp_rewrite->comments_pagination_base}-[0-9]+(/+)?$#", $redirect['path'] ) ) { $redirect['path'] = preg_replace( "#/$wp_rewrite->pagination_base/?[0-9]+?(/+)?$#", '/', $redirect['path'] ); $redirect['path'] = preg_replace( '#/(comments/?)?(feed|rss2?|rdf|atom)(/+|$)#', '/', $redirect['path'] ); $redirect['path'] = preg_replace( "#/{$wp_rewrite->comments_pagination_base}-[0-9]+?(/+)?$#", '/', $redirect['path'] ); } $addl_path = ''; $default_feed = get_default_feed(); if ( is_feed() && in_array( $feed, $wp_rewrite->feeds, true ) ) { $addl_path = ! empty( $addl_path ) ? trailingslashit( $addl_path ) : ''; if ( ! is_singular() && get_query_var( 'withcomments' ) ) { $addl_path .= 'comments/'; } if ( ( 'rss' === $default_feed && 'feed' === $feed ) || 'rss' === $feed ) { $format = ( 'rss2' === $default_feed ) ? '' : 'rss2'; } else { $format = ( $default_feed === $feed || 'feed' === $feed ) ? '' : $feed; } $addl_path .= user_trailingslashit( 'feed/' . $format, 'feed' ); $redirect['query'] = remove_query_arg( 'feed', $redirect['query'] ); } elseif ( is_feed() && 'old' === $feed ) { $old_feed_files = array( 'wp-atom.php' => 'atom', 'wp-commentsrss2.php' => 'comments_rss2', 'wp-feed.php' => $default_feed, 'wp-rdf.php' => 'rdf', 'wp-rss.php' => 'rss2', 'wp-rss2.php' => 'rss2', ); if ( isset( $old_feed_files[ basename( $redirect['path'] ) ] ) ) { $redirect_url = get_feed_link( $old_feed_files[ basename( $redirect['path'] ) ] ); wp_redirect( $redirect_url, 301 ); die(); } } if ( $paged > 0 ) { $redirect['query'] = remove_query_arg( 'paged', $redirect['query'] ); if ( ! is_feed() ) { if ( ! is_single() ) { $addl_path = ! empty( $addl_path ) ? trailingslashit( $addl_path ) : ''; if ( $paged > 1 ) { $addl_path .= user_trailingslashit( "$wp_rewrite->pagination_base/$paged", 'paged' ); } } } elseif ( $paged > 1 ) { $redirect['query'] = add_query_arg( 'paged', $paged, $redirect['query'] ); } } $default_comments_page = get_option( 'default_comments_page' ); if ( get_option( 'page_comments' ) && ( 'newest' === $default_comments_page && $cpage > 0 || 'newest' !== $default_comments_page && $cpage > 1 ) ) { $addl_path = ( ! empty( $addl_path ) ? trailingslashit( $addl_path ) : '' ); $addl_path .= user_trailingslashit( $wp_rewrite->comments_pagination_base . '-' . $cpage, 'commentpaged' ); $redirect['query'] = remove_query_arg( 'cpage', $redirect['query'] ); } $redirect['path'] = preg_replace( '|/' . preg_quote( $wp_rewrite->index, '|' ) . '/?$|', '/', $redirect['path'] ); $redirect['path'] = user_trailingslashit( $redirect['path'] ); if ( ! empty( $addl_path ) && $wp_rewrite->using_index_permalinks() && strpos( $redirect['path'], '/' . $wp_rewrite->index . '/' ) === false ) { $redirect['path'] = trailingslashit( $redirect['path'] ) . $wp_rewrite->index . '/'; } if ( ! empty( $addl_path ) ) { $redirect['path'] = trailingslashit( $redirect['path'] ) . $addl_path; } $redirect_url = $redirect['scheme'] . '://' . $redirect['host'] . $redirect['path']; } if ( 'wp-register.php' === basename( $redirect['path'] ) ) { if ( is_multisite() ) { $redirect_url = apply_filters( 'wp_signup_location', network_site_url( 'wp-signup.php' ) ); } else { $redirect_url = wp_registration_url(); } wp_redirect( $redirect_url, 301 ); die(); } } $redirect['query'] = preg_replace( '#^\??&*?#', '', $redirect['query'] ); if ( $redirect_url && ! empty( $redirect['query'] ) ) { parse_str( $redirect['query'], $_parsed_query ); $redirect = parse_url( $redirect_url ); if ( ! empty( $_parsed_query['name'] ) && ! empty( $redirect['query'] ) ) { parse_str( $redirect['query'], $_parsed_redirect_query ); if ( empty( $_parsed_redirect_query['name'] ) ) { unset( $_parsed_query['name'] ); } } $_parsed_query = array_combine( rawurlencode_deep( array_keys( $_parsed_query ) ), rawurlencode_deep( array_values( $_parsed_query ) ) ); $redirect_url = add_query_arg( $_parsed_query, $redirect_url ); } if ( $redirect_url ) { $redirect = parse_url( $redirect_url ); } $user_home = parse_url( home_url() ); if ( ! empty( $user_home['host'] ) ) { $redirect['host'] = $user_home['host']; } if ( empty( $user_home['path'] ) ) { $user_home['path'] = '/'; } if ( ! empty( $user_home['port'] ) ) { $redirect['port'] = $user_home['port']; } else { unset( $redirect['port'] ); } $redirect['path'] = preg_replace( '|/' . preg_quote( $wp_rewrite->index, '|' ) . '/*?$|', '/', $redirect['path'] ); $punctuation_pattern = implode( '|', array_map( 'preg_quote', array( ' ', '%20', '!', '%21', '"', '%22', "'", '%27', '(', '%28', ')', '%29', ',', '%2C', '.', '%2E', ';', '%3B', '{', '%7B', '}', '%7D', '%E2%80%9C', '%E2%80%9D', ) ) ); $redirect['path'] = preg_replace( "#($punctuation_pattern)+$#", '', $redirect['path'] ); if ( ! empty( $redirect['query'] ) ) { $redirect['query'] = preg_replace( "#((^|&)(p|page_id|cat|tag)=[^&]*?)($punctuation_pattern)+$#", '$1', $redirect['query'] ); $redirect['query'] = trim( preg_replace( '#(^|&)(p|page_id|cat|tag)=?(&|$)#', '&', $redirect['query'] ), '&' ); $redirect['query'] = preg_replace( '#(^|&)feed=rss(&|$)#', '$1feed=rss2$2', $redirect['query'] ); $redirect['query'] = preg_replace( '#^\??&*?#', '', $redirect['query'] ); } if ( ! $wp_rewrite->using_index_permalinks() ) { $redirect['path'] = str_replace( '/' . $wp_rewrite->index . '/', '/', $redirect['path'] ); } if ( is_object( $wp_rewrite ) && $wp_rewrite->using_permalinks() && ! is_404() && ( ! is_front_page() || is_front_page() && get_query_var( 'paged' ) > 1 ) ) { $user_ts_type = ''; if ( get_query_var( 'paged' ) > 0 ) { $user_ts_type = 'paged'; } else { foreach ( array( 'single', 'category', 'page', 'day', 'month', 'year', 'home' ) as $type ) { $func = 'is_' . $type; if ( call_user_func( $func ) ) { $user_ts_type = $type; break; } } } $redirect['path'] = user_trailingslashit( $redirect['path'], $user_ts_type ); } elseif ( is_front_page() ) { $redirect['path'] = trailingslashit( $redirect['path'] ); } if ( is_robots() || ! empty( get_query_var( 'sitemap' ) ) || ! empty( get_query_var( 'sitemap-stylesheet' ) ) ) { $redirect['path'] = untrailingslashit( $redirect['path'] ); } if ( strpos( $redirect['path'], '//' ) > -1 ) { $redirect['path'] = preg_replace( '|/+|', '/', $redirect['path'] ); } if ( trailingslashit( $redirect['path'] ) === trailingslashit( $user_home['path'] ) ) { $redirect['path'] = trailingslashit( $redirect['path'] ); } $original_host_low = strtolower( $original['host'] ); $redirect_host_low = strtolower( $redirect['host'] ); if ( $original_host_low === $redirect_host_low || ( 'www.' . $original_host_low !== $redirect_host_low && 'www.' . $redirect_host_low !== $original_host_low ) ) { $redirect['host'] = $original['host']; } $compare_original = array( $original['host'], $original['path'] ); if ( ! empty( $original['port'] ) ) { $compare_original[] = $original['port']; } if ( ! empty( $original['query'] ) ) { $compare_original[] = $original['query']; } $compare_redirect = array( $redirect['host'], $redirect['path'] ); if ( ! empty( $redirect['port'] ) ) { $compare_redirect[] = $redirect['port']; } if ( ! empty( $redirect['query'] ) ) { $compare_redirect[] = $redirect['query']; } if ( $compare_original !== $compare_redirect ) { $redirect_url = $redirect['scheme'] . '://' . $redirect['host']; if ( ! empty( $redirect['port'] ) ) { $redirect_url .= ':' . $redirect['port']; } $redirect_url .= $redirect['path']; if ( ! empty( $redirect['query'] ) ) { $redirect_url .= '?' . $redirect['query']; } } if ( ! $redirect_url || $redirect_url === $requested_url ) { return; } if ( false !== strpos( $requested_url, '%' ) ) { if ( ! function_exists( 'lowercase_octets' ) ) { function lowercase_octets( $matches ) { return strtolower( $matches[0] ); } } $requested_url = preg_replace_callback( '|%[a-fA-F0-9][a-fA-F0-9]|', 'lowercase_octets', $requested_url ); } if ( $redirect_obj instanceof WP_Post ) { $post_status_obj = get_post_status_object( get_post_status( $redirect_obj ) ); if ( ! ( $post_status_obj->private && current_user_can( 'read_post', $redirect_obj->ID ) ) && ! is_post_publicly_viewable( $redirect_obj ) ) { $redirect_obj = false; $redirect_url = false; } } $redirect_url = apply_filters( 'redirect_canonical', $redirect_url, $requested_url ); if ( ! $redirect_url || strip_fragment_from_url( $redirect_url ) === strip_fragment_from_url( $requested_url ) ) { return; } if ( $do_redirect ) { if ( ! redirect_canonical( $redirect_url, false ) ) { wp_redirect( $redirect_url, 301 ); exit; } else { return; } } else { return $redirect_url; } } function _remove_qs_args_if_not_in_url( $query_string, array $args_to_check, $url ) { $parsed_url = parse_url( $url ); if ( ! empty( $parsed_url['query'] ) ) { parse_str( $parsed_url['query'], $parsed_query ); foreach ( $args_to_check as $qv ) { if ( ! isset( $parsed_query[ $qv ] ) ) { $query_string = remove_query_arg( $qv, $query_string ); } } } else { $query_string = remove_query_arg( $args_to_check, $query_string ); } return $query_string; } function strip_fragment_from_url( $url ) { $parsed_url = wp_parse_url( $url ); if ( ! empty( $parsed_url['host'] ) ) { $url = ''; if ( ! empty( $parsed_url['scheme'] ) ) { $url = $parsed_url['scheme'] . ':'; } $url .= '//' . $parsed_url['host']; if ( ! empty( $parsed_url['port'] ) ) { $url .= ':' . $parsed_url['port']; } if ( ! empty( $parsed_url['path'] ) ) { $url .= $parsed_url['path']; } if ( ! empty( $parsed_url['query'] ) ) { $url .= '?' . $parsed_url['query']; } } return $url; } function redirect_guess_404_permalink() { global $wpdb; if ( false === apply_filters( 'do_redirect_guess_404_permalink', true ) ) { return false; } $pre = apply_filters( 'pre_redirect_guess_404_permalink', null ); if ( null !== $pre ) { return $pre; } if ( get_query_var( 'name' ) ) { $strict_guess = apply_filters( 'strict_redirect_guess_404_permalink', false ); if ( $strict_guess ) { $where = $wpdb->prepare( 'post_name = %s', get_query_var( 'name' ) ); } else { $where = $wpdb->prepare( 'post_name LIKE %s', $wpdb->esc_like( get_query_var( 'name' ) ) . '%' ); } if ( get_query_var( 'post_type' ) ) { if ( is_array( get_query_var( 'post_type' ) ) ) { $where .= " AND post_type IN ('" . join( "', '", esc_sql( get_query_var( 'post_type' ) ) ) . "')"; } else { $where .= $wpdb->prepare( ' AND post_type = %s', get_query_var( 'post_type' ) ); } } else { $where .= " AND post_type IN ('" . implode( "', '", get_post_types( array( 'public' => true ) ) ) . "')"; } if ( get_query_var( 'year' ) ) { $where .= $wpdb->prepare( ' AND YEAR(post_date) = %d', get_query_var( 'year' ) ); } if ( get_query_var( 'monthnum' ) ) { $where .= $wpdb->prepare( ' AND MONTH(post_date) = %d', get_query_var( 'monthnum' ) ); } if ( get_query_var( 'day' ) ) { $where .= $wpdb->prepare( ' AND DAYOFMONTH(post_date) = %d', get_query_var( 'day' ) ); } $publicly_viewable_statuses = array_filter( get_post_stati(), 'is_post_status_viewable' ); $post_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE $where AND post_status IN ('" . implode( "', '", esc_sql( $publicly_viewable_statuses ) ) . "')" ); if ( ! $post_id ) { return false; } if ( get_query_var( 'feed' ) ) { return get_post_comments_feed_link( $post_id, get_query_var( 'feed' ) ); } elseif ( get_query_var( 'page' ) > 1 ) { return trailingslashit( get_permalink( $post_id ) ) . user_trailingslashit( get_query_var( 'page' ), 'single_paged' ); } else { return get_permalink( $post_id ); } } return false; } function wp_redirect_admin_locations() { global $wp_rewrite; if ( ! ( is_404() && $wp_rewrite->using_permalinks() ) ) { return; } $admins = array( home_url( 'wp-admin', 'relative' ), home_url( 'dashboard', 'relative' ), home_url( 'admin', 'relative' ), site_url( 'dashboard', 'relative' ), site_url( 'admin', 'relative' ), ); if ( in_array( untrailingslashit( $_SERVER['REQUEST_URI'] ), $admins, true ) ) { wp_redirect( admin_url() ); exit; } $logins = array( home_url( 'wp-login.php', 'relative' ), home_url( 'login', 'relative' ), site_url( 'login', 'relative' ), ); if ( in_array( untrailingslashit( $_SERVER['REQUEST_URI'] ), $logins, true ) ) { wp_redirect( wp_login_url() ); exit; } } 