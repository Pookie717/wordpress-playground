<?php
 abstract class WP_Privacy_Requests_Table extends WP_List_Table { protected $request_type = 'INVALID'; protected $post_type = 'INVALID'; public function get_columns() { $columns = array( 'cb' => '<input type="checkbox" />', 'email' => __( 'Requester' ), 'status' => __( 'Status' ), 'created_timestamp' => __( 'Requested' ), 'next_steps' => __( 'Next steps' ), ); return $columns; } protected function get_admin_url() { $pagenow = str_replace( '_', '-', $this->request_type ); if ( 'remove-personal-data' === $pagenow ) { $pagenow = 'erase-personal-data'; } return admin_url( $pagenow . '.php' ); } protected function get_sortable_columns() { $desc_first = isset( $_GET['orderby'] ); return array( 'email' => 'requester', 'created_timestamp' => array( 'requested', $desc_first ), ); } protected function get_default_primary_column_name() { return 'email'; } protected function get_request_counts() { global $wpdb; $cache_key = $this->post_type . '-' . $this->request_type; $counts = wp_cache_get( $cache_key, 'counts' ); if ( false !== $counts ) { return $counts; } $query = "
			SELECT post_status, COUNT( * ) AS num_posts
			FROM {$wpdb->posts}
			WHERE post_type = %s
			AND post_name = %s
			GROUP BY post_status"; $results = (array) $wpdb->get_results( $wpdb->prepare( $query, $this->post_type, $this->request_type ), ARRAY_A ); $counts = array_fill_keys( get_post_stati(), 0 ); foreach ( $results as $row ) { $counts[ $row['post_status'] ] = $row['num_posts']; } $counts = (object) $counts; wp_cache_set( $cache_key, $counts, 'counts' ); return $counts; } protected function get_views() { $current_status = isset( $_REQUEST['filter-status'] ) ? sanitize_text_field( $_REQUEST['filter-status'] ) : ''; $statuses = _wp_privacy_statuses(); $views = array(); $counts = $this->get_request_counts(); $total_requests = absint( array_sum( (array) $counts ) ); $admin_url = $this->get_admin_url(); $current_link_attributes = empty( $current_status ) ? ' class="current" aria-current="page"' : ''; $status_label = sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_requests, 'requests' ), number_format_i18n( $total_requests ) ); $views['all'] = sprintf( '<a href="%s"%s>%s</a>', esc_url( $admin_url ), $current_link_attributes, $status_label ); foreach ( $statuses as $status => $label ) { $post_status = get_post_status_object( $status ); if ( ! $post_status ) { continue; } $current_link_attributes = $status === $current_status ? ' class="current" aria-current="page"' : ''; $total_status_requests = absint( $counts->{$status} ); if ( ! $total_status_requests ) { continue; } $status_label = sprintf( translate_nooped_plural( $post_status->label_count, $total_status_requests ), number_format_i18n( $total_status_requests ) ); $status_link = add_query_arg( 'filter-status', $status, $admin_url ); $views[ $status ] = sprintf( '<a href="%s"%s>%s</a>', esc_url( $status_link ), $current_link_attributes, $status_label ); } return $views; } protected function get_bulk_actions() { return array( 'resend' => __( 'Resend confirmation requests' ), 'complete' => __( 'Mark requests as completed' ), 'delete' => __( 'Delete requests' ), ); } public function process_bulk_action() { $action = $this->current_action(); $request_ids = isset( $_REQUEST['request_id'] ) ? wp_parse_id_list( wp_unslash( $_REQUEST['request_id'] ) ) : array(); if ( empty( $request_ids ) ) { return; } $count = 0; $failures = 0; check_admin_referer( 'bulk-privacy_requests' ); switch ( $action ) { case 'resend': foreach ( $request_ids as $request_id ) { $resend = _wp_privacy_resend_request( $request_id ); if ( $resend && ! is_wp_error( $resend ) ) { $count++; } else { $failures++; } } if ( $failures ) { add_settings_error( 'bulk_action', 'bulk_action', sprintf( _n( '%d confirmation request failed to resend.', '%d confirmation requests failed to resend.', $failures ), $failures ), 'error' ); } if ( $count ) { add_settings_error( 'bulk_action', 'bulk_action', sprintf( _n( '%d confirmation request re-sent successfully.', '%d confirmation requests re-sent successfully.', $count ), $count ), 'success' ); } break; case 'complete': foreach ( $request_ids as $request_id ) { $result = _wp_privacy_completed_request( $request_id ); if ( $result && ! is_wp_error( $result ) ) { $count++; } } add_settings_error( 'bulk_action', 'bulk_action', sprintf( _n( '%d request marked as complete.', '%d requests marked as complete.', $count ), $count ), 'success' ); break; case 'delete': foreach ( $request_ids as $request_id ) { if ( wp_delete_post( $request_id, true ) ) { $count++; } else { $failures++; } } if ( $failures ) { add_settings_error( 'bulk_action', 'bulk_action', sprintf( _n( '%d request failed to delete.', '%d requests failed to delete.', $failures ), $failures ), 'error' ); } if ( $count ) { add_settings_error( 'bulk_action', 'bulk_action', sprintf( _n( '%d request deleted successfully.', '%d requests deleted successfully.', $count ), $count ), 'success' ); } break; } } public function prepare_items() { $this->items = array(); $posts_per_page = $this->get_items_per_page( $this->request_type . '_requests_per_page' ); $args = array( 'post_type' => $this->post_type, 'post_name__in' => array( $this->request_type ), 'posts_per_page' => $posts_per_page, 'offset' => isset( $_REQUEST['paged'] ) ? max( 0, absint( $_REQUEST['paged'] ) - 1 ) * $posts_per_page : 0, 'post_status' => 'any', 's' => isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '', ); $orderby_mapping = array( 'requester' => 'post_title', 'requested' => 'post_date', ); if ( isset( $_REQUEST['orderby'] ) && isset( $orderby_mapping[ $_REQUEST['orderby'] ] ) ) { $args['orderby'] = $orderby_mapping[ $_REQUEST['orderby'] ]; } if ( isset( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), array( 'ASC', 'DESC' ), true ) ) { $args['order'] = strtoupper( $_REQUEST['order'] ); } if ( ! empty( $_REQUEST['filter-status'] ) ) { $filter_status = isset( $_REQUEST['filter-status'] ) ? sanitize_text_field( $_REQUEST['filter-status'] ) : ''; $args['post_status'] = $filter_status; } $requests_query = new WP_Query( $args ); $requests = $requests_query->posts; foreach ( $requests as $request ) { $this->items[] = wp_get_user_request( $request->ID ); } $this->items = array_filter( $this->items ); $this->set_pagination_args( array( 'total_items' => $requests_query->found_posts, 'per_page' => $posts_per_page, ) ); } public function column_cb( $item ) { return sprintf( '<input type="checkbox" name="request_id[]" value="%1$s" /><span class="spinner"></span>', esc_attr( $item->ID ) ); } public function column_status( $item ) { $status = get_post_status( $item->ID ); $status_object = get_post_status_object( $status ); if ( ! $status_object || empty( $status_object->label ) ) { return '-'; } $timestamp = false; switch ( $status ) { case 'request-confirmed': $timestamp = $item->confirmed_timestamp; break; case 'request-completed': $timestamp = $item->completed_timestamp; break; } echo '<span class="status-label status-' . esc_attr( $status ) . '">'; echo esc_html( $status_object->label ); if ( $timestamp ) { echo ' (' . $this->get_timestamp_as_date( $timestamp ) . ')'; } echo '</span>'; } protected function get_timestamp_as_date( $timestamp ) { if ( empty( $timestamp ) ) { return ''; } $time_diff = time() - $timestamp; if ( $time_diff >= 0 && $time_diff < DAY_IN_SECONDS ) { return sprintf( __( '%s ago' ), human_time_diff( $timestamp ) ); } return date_i18n( get_option( 'date_format' ), $timestamp ); } public function column_default( $item, $column_name ) { do_action( "manage_{$this->screen->id}_custom_column", $column_name, $item ); } public function column_created_timestamp( $item ) { return $this->get_timestamp_as_date( $item->created_timestamp ); } public function column_email( $item ) { return sprintf( '<a href="%1$s">%2$s</a> %3$s', esc_url( 'mailto:' . $item->email ), $item->email, $this->row_actions( array() ) ); } public function column_next_steps( $item ) {} public function single_row( $item ) { $status = $item->status; echo '<tr id="request-' . esc_attr( $item->ID ) . '" class="status-' . esc_attr( $status ) . '">'; $this->single_row_columns( $item ); echo '</tr>'; } public function embed_scripts() {} } 