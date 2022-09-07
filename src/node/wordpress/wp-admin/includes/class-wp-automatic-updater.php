<?php
 class WP_Automatic_Updater { protected $update_results = array(); public function is_disabled() { if ( ! wp_is_file_mod_allowed( 'automatic_updater' ) ) { return true; } if ( wp_installing() ) { return true; } $disabled = defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED; return apply_filters( 'automatic_updater_disabled', $disabled ); } public function is_vcs_checkout( $context ) { $context_dirs = array( untrailingslashit( $context ) ); if ( ABSPATH !== $context ) { $context_dirs[] = untrailingslashit( ABSPATH ); } $vcs_dirs = array( '.svn', '.git', '.hg', '.bzr' ); $check_dirs = array(); foreach ( $context_dirs as $context_dir ) { do { $check_dirs[] = $context_dir; if ( dirname( $context_dir ) === $context_dir ) { break; } } while ( $context_dir = dirname( $context_dir ) ); } $check_dirs = array_unique( $check_dirs ); foreach ( $vcs_dirs as $vcs_dir ) { foreach ( $check_dirs as $check_dir ) { $checkout = @is_dir( rtrim( $check_dir, '\\/' ) . "/$vcs_dir" ); if ( $checkout ) { break 2; } } } return apply_filters( 'automatic_updates_is_vcs_checkout', $checkout, $context ); } public function should_update( $type, $item, $context ) { $skin = new Automatic_Upgrader_Skin; if ( $this->is_disabled() ) { return false; } $allow_relaxed_file_ownership = false; if ( 'core' === $type && isset( $item->new_files ) && ! $item->new_files ) { $allow_relaxed_file_ownership = true; } if ( ! $skin->request_filesystem_credentials( false, $context, $allow_relaxed_file_ownership ) || $this->is_vcs_checkout( $context ) ) { if ( 'core' === $type ) { $this->send_core_update_notification_email( $item ); } return false; } if ( 'core' === $type ) { $update = Core_Upgrader::should_update_to_version( $item->current ); } elseif ( 'plugin' === $type || 'theme' === $type ) { $update = ! empty( $item->autoupdate ); if ( ! $update && wp_is_auto_update_enabled_for_type( $type ) ) { $auto_updates = (array) get_site_option( "auto_update_{$type}s", array() ); $update = in_array( $item->{$type}, $auto_updates, true ); } } else { $update = ! empty( $item->autoupdate ); } if ( ! empty( $item->disable_autoupdate ) ) { $update = $item->disable_autoupdate; } $update = apply_filters( "auto_update_{$type}", $update, $item ); if ( ! $update ) { if ( 'core' === $type ) { $this->send_core_update_notification_email( $item ); } return false; } if ( 'core' === $type ) { global $wpdb; $php_compat = version_compare( phpversion(), $item->php_version, '>=' ); if ( file_exists( WP_CONTENT_DIR . '/db.php' ) && empty( $wpdb->is_mysql ) ) { $mysql_compat = true; } else { $mysql_compat = version_compare( $wpdb->db_version(), $item->mysql_version, '>=' ); } if ( ! $php_compat || ! $mysql_compat ) { return false; } } if ( in_array( $type, array( 'plugin', 'theme' ), true ) ) { if ( ! empty( $item->requires_php ) && version_compare( phpversion(), $item->requires_php, '<' ) ) { return false; } } return true; } protected function send_core_update_notification_email( $item ) { $notified = get_site_option( 'auto_core_update_notified' ); if ( $notified && get_site_option( 'admin_email' ) === $notified['email'] && $notified['version'] === $item->current ) { return false; } $notify = ! empty( $item->notify_email ); if ( ! apply_filters( 'send_core_update_notification_email', $notify, $item ) ) { return false; } $this->send_email( 'manual', $item ); return true; } public function update( $type, $item ) { $skin = new Automatic_Upgrader_Skin; switch ( $type ) { case 'core': add_filter( 'update_feedback', array( $skin, 'feedback' ) ); $upgrader = new Core_Upgrader( $skin ); $context = ABSPATH; break; case 'plugin': $upgrader = new Plugin_Upgrader( $skin ); $context = WP_PLUGIN_DIR; break; case 'theme': $upgrader = new Theme_Upgrader( $skin ); $context = get_theme_root( $item->theme ); break; case 'translation': $upgrader = new Language_Pack_Upgrader( $skin ); $context = WP_CONTENT_DIR; break; } if ( ! $this->should_update( $type, $item, $context ) ) { return false; } do_action( 'pre_auto_update', $type, $item, $context ); $upgrader_item = $item; switch ( $type ) { case 'core': $skin->feedback( __( 'Updating to WordPress %s' ), $item->version ); $item_name = sprintf( __( 'WordPress %s' ), $item->version ); break; case 'theme': $upgrader_item = $item->theme; $theme = wp_get_theme( $upgrader_item ); $item_name = $theme->Get( 'Name' ); $item->current_version = $theme->get( 'Version' ); if ( empty( $item->current_version ) ) { $item->current_version = false; } $skin->feedback( __( 'Updating theme: %s' ), $item_name ); break; case 'plugin': $upgrader_item = $item->plugin; $plugin_data = get_plugin_data( $context . '/' . $upgrader_item ); $item_name = $plugin_data['Name']; $item->current_version = $plugin_data['Version']; if ( empty( $item->current_version ) ) { $item->current_version = false; } $skin->feedback( __( 'Updating plugin: %s' ), $item_name ); break; case 'translation': $language_item_name = $upgrader->get_name_for_update( $item ); $item_name = sprintf( __( 'Translations for %s' ), $language_item_name ); $skin->feedback( sprintf( __( 'Updating translations for %1$s (%2$s)&#8230;' ), $language_item_name, $item->language ) ); break; } $allow_relaxed_file_ownership = false; if ( 'core' === $type && isset( $item->new_files ) && ! $item->new_files ) { $allow_relaxed_file_ownership = true; } $upgrade_result = $upgrader->upgrade( $upgrader_item, array( 'clear_update_cache' => false, 'pre_check_md5' => false, 'attempt_rollback' => true, 'allow_relaxed_file_ownership' => $allow_relaxed_file_ownership, ) ); if ( false === $upgrade_result ) { $upgrade_result = new WP_Error( 'fs_unavailable', __( 'Could not access filesystem.' ) ); } if ( 'core' === $type ) { if ( is_wp_error( $upgrade_result ) && ( 'up_to_date' === $upgrade_result->get_error_code() || 'locked' === $upgrade_result->get_error_code() ) ) { return false; } if ( is_wp_error( $upgrade_result ) ) { $upgrade_result->add( 'installation_failed', __( 'Installation failed.' ) ); $skin->error( $upgrade_result ); } else { $skin->feedback( __( 'WordPress updated successfully.' ) ); } } $this->update_results[ $type ][] = (object) array( 'item' => $item, 'result' => $upgrade_result, 'name' => $item_name, 'messages' => $skin->get_upgrade_messages(), ); return $upgrade_result; } public function run() { if ( $this->is_disabled() ) { return; } if ( ! is_main_network() || ! is_main_site() ) { return; } if ( ! WP_Upgrader::create_lock( 'auto_updater' ) ) { return; } remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 ); remove_action( 'upgrader_process_complete', 'wp_version_check' ); remove_action( 'upgrader_process_complete', 'wp_update_plugins' ); remove_action( 'upgrader_process_complete', 'wp_update_themes' ); wp_update_plugins(); $plugin_updates = get_site_transient( 'update_plugins' ); if ( $plugin_updates && ! empty( $plugin_updates->response ) ) { foreach ( $plugin_updates->response as $plugin ) { $this->update( 'plugin', $plugin ); } wp_clean_plugins_cache(); } wp_update_themes(); $theme_updates = get_site_transient( 'update_themes' ); if ( $theme_updates && ! empty( $theme_updates->response ) ) { foreach ( $theme_updates->response as $theme ) { $this->update( 'theme', (object) $theme ); } wp_clean_themes_cache(); } wp_version_check(); $core_update = find_core_auto_update(); if ( $core_update ) { $this->update( 'core', $core_update ); } $theme_stats = array(); if ( isset( $this->update_results['theme'] ) ) { foreach ( $this->update_results['theme'] as $upgrade ) { $theme_stats[ $upgrade->item->theme ] = ( true === $upgrade->result ); } } wp_update_themes( $theme_stats ); $plugin_stats = array(); if ( isset( $this->update_results['plugin'] ) ) { foreach ( $this->update_results['plugin'] as $upgrade ) { $plugin_stats[ $upgrade->item->plugin ] = ( true === $upgrade->result ); } } wp_update_plugins( $plugin_stats ); $language_updates = wp_get_translation_updates(); if ( $language_updates ) { foreach ( $language_updates as $update ) { $this->update( 'translation', $update ); } wp_clean_update_cache(); wp_version_check(); wp_update_themes(); wp_update_plugins(); } if ( ! empty( $this->update_results ) ) { $development_version = false !== strpos( get_bloginfo( 'version' ), '-' ); if ( apply_filters( 'automatic_updates_send_debug_email', $development_version ) ) { $this->send_debug_email(); } if ( ! empty( $this->update_results['core'] ) ) { $this->after_core_update( $this->update_results['core'][0] ); } elseif ( ! empty( $this->update_results['plugin'] ) || ! empty( $this->update_results['theme'] ) ) { $this->after_plugin_theme_update( $this->update_results ); } do_action( 'automatic_updates_complete', $this->update_results ); } WP_Upgrader::release_lock( 'auto_updater' ); } protected function after_core_update( $update_result ) { $wp_version = get_bloginfo( 'version' ); $core_update = $update_result->item; $result = $update_result->result; if ( ! is_wp_error( $result ) ) { $this->send_email( 'success', $core_update ); return; } $error_code = $result->get_error_code(); $critical = false; if ( 'disk_full' === $error_code || false !== strpos( $error_code, '__copy_dir' ) ) { $critical = true; } elseif ( 'rollback_was_required' === $error_code && is_wp_error( $result->get_error_data()->rollback ) ) { $critical = true; $rollback_result = $result->get_error_data()->rollback; } elseif ( false !== strpos( $error_code, 'do_rollback' ) ) { $critical = true; } if ( $critical ) { $critical_data = array( 'attempted' => $core_update->current, 'current' => $wp_version, 'error_code' => $error_code, 'error_data' => $result->get_error_data(), 'timestamp' => time(), 'critical' => true, ); if ( isset( $rollback_result ) ) { $critical_data['rollback_code'] = $rollback_result->get_error_code(); $critical_data['rollback_data'] = $rollback_result->get_error_data(); } update_site_option( 'auto_core_update_failed', $critical_data ); $this->send_email( 'critical', $core_update, $result ); return; } $send = true; $transient_failures = array( 'incompatible_archive', 'download_failed', 'insane_distro', 'locked' ); if ( in_array( $error_code, $transient_failures, true ) && ! get_site_option( 'auto_core_update_failed' ) ) { wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'wp_maybe_auto_update' ); $send = false; } $notified = get_site_option( 'auto_core_update_notified' ); if ( $notified && 'fail' === $notified['type'] && get_site_option( 'admin_email' ) === $notified['email'] && $notified['version'] === $core_update->current ) { $send = false; } update_site_option( 'auto_core_update_failed', array( 'attempted' => $core_update->current, 'current' => $wp_version, 'error_code' => $error_code, 'error_data' => $result->get_error_data(), 'timestamp' => time(), 'retry' => in_array( $error_code, $transient_failures, true ), ) ); if ( $send ) { $this->send_email( 'fail', $core_update, $result ); } } protected function send_email( $type, $core_update, $result = null ) { update_site_option( 'auto_core_update_notified', array( 'type' => $type, 'email' => get_site_option( 'admin_email' ), 'version' => $core_update->current, 'timestamp' => time(), ) ); $next_user_core_update = get_preferred_from_update_core(); if ( ! $next_user_core_update ) { $next_user_core_update = $core_update; } if ( 'upgrade' === $next_user_core_update->response && version_compare( $next_user_core_update->version, $core_update->version, '>' ) ) { $newer_version_available = true; } else { $newer_version_available = false; } if ( 'manual' !== $type && ! apply_filters( 'auto_core_update_send_email', true, $type, $core_update, $result ) ) { return; } switch ( $type ) { case 'success': $subject = __( '[%1$s] Your site has updated to WordPress %2$s' ); break; case 'fail': case 'manual': $subject = __( '[%1$s] WordPress %2$s is available. Please update!' ); break; case 'critical': $subject = __( '[%1$s] URGENT: Your site may be down due to a failed update' ); break; default: return; } $version = 'success' === $type ? $core_update->current : $next_user_core_update->current; $subject = sprintf( $subject, wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), $version ); $body = ''; switch ( $type ) { case 'success': $body .= sprintf( __( 'Howdy! Your site at %1$s has been updated automatically to WordPress %2$s.' ), home_url(), $core_update->current ); $body .= "\n\n"; if ( ! $newer_version_available ) { $body .= __( 'No further action is needed on your part.' ) . ' '; } list( $about_version ) = explode( '-', $core_update->current, 2 ); $body .= sprintf( __( 'For more on version %s, see the About WordPress screen:' ), $about_version ); $body .= "\n" . admin_url( 'about.php' ); if ( $newer_version_available ) { $body .= "\n\n" . sprintf( __( 'WordPress %s is also now available.' ), $next_user_core_update->current ) . ' '; $body .= __( 'Updating is easy and only takes a few moments:' ); $body .= "\n" . network_admin_url( 'update-core.php' ); } break; case 'fail': case 'manual': $body .= sprintf( __( 'Please update your site at %1$s to WordPress %2$s.' ), home_url(), $next_user_core_update->current ); $body .= "\n\n"; if ( 'fail' === $type && ! $newer_version_available ) { $body .= __( 'An attempt was made, but your site could not be updated automatically.' ) . ' '; } $body .= __( 'Updating is easy and only takes a few moments:' ); $body .= "\n" . network_admin_url( 'update-core.php' ); break; case 'critical': if ( $newer_version_available ) { $body .= sprintf( __( 'Your site at %1$s experienced a critical failure while trying to update WordPress to version %2$s.' ), home_url(), $core_update->current ); } else { $body .= sprintf( __( 'Your site at %1$s experienced a critical failure while trying to update to the latest version of WordPress, %2$s.' ), home_url(), $core_update->current ); } $body .= "\n\n" . __( "This means your site may be offline or broken. Don't panic; this can be fixed." ); $body .= "\n\n" . __( "Please check out your site now. It's possible that everything is working. If it says you need to update, you should do so:" ); $body .= "\n" . network_admin_url( 'update-core.php' ); break; } $critical_support = 'critical' === $type && ! empty( $core_update->support_email ); if ( $critical_support ) { $body .= "\n\n" . sprintf( __( 'The WordPress team is willing to help you. Forward this email to %s and the team will work with you to make sure your site is working.' ), $core_update->support_email ); } else { $body .= "\n\n" . __( 'If you experience any issues or need support, the volunteers in the WordPress.org support forums may be able to help.' ); $body .= "\n" . __( 'https://wordpress.org/support/forums/' ); } if ( 'success' !== $type || $newer_version_available ) { $body .= "\n\n" . __( 'Keeping your site updated is important for security. It also makes the internet a safer place for you and your readers.' ); } if ( $critical_support ) { $body .= ' ' . __( "If you reach out to us, we'll also ensure you'll never have this problem again." ); } if ( 'success' === $type && ! $newer_version_available && ( get_plugin_updates() || get_theme_updates() ) ) { $body .= "\n\n" . __( 'You also have some plugins or themes with updates available. Update them now:' ); $body .= "\n" . network_admin_url(); } $body .= "\n\n" . __( 'The WordPress Team' ) . "\n"; if ( 'critical' === $type && is_wp_error( $result ) ) { $body .= "\n***\n\n"; $body .= sprintf( __( 'Your site was running version %s.' ), get_bloginfo( 'version' ) ); $body .= ' ' . __( 'Some data that describes the error your site encountered has been put together.' ); $body .= ' ' . __( 'Your hosting company, support forum volunteers, or a friendly developer may be able to use this information to help you:' ); if ( 'rollback_was_required' === $result->get_error_code() ) { $errors = array( $result, $result->get_error_data()->update, $result->get_error_data()->rollback ); } else { $errors = array( $result ); } foreach ( $errors as $error ) { if ( ! is_wp_error( $error ) ) { continue; } $error_code = $error->get_error_code(); $body .= "\n\n" . sprintf( __( 'Error code: %s' ), $error_code ); if ( 'rollback_was_required' === $error_code ) { continue; } if ( $error->get_error_message() ) { $body .= "\n" . $error->get_error_message(); } $error_data = $error->get_error_data(); if ( $error_data ) { $body .= "\n" . implode( ', ', (array) $error_data ); } } $body .= "\n"; } $to = get_site_option( 'admin_email' ); $headers = ''; $email = compact( 'to', 'subject', 'body', 'headers' ); $email = apply_filters( 'auto_core_update_email', $email, $type, $core_update, $result ); wp_mail( $email['to'], wp_specialchars_decode( $email['subject'] ), $email['body'], $email['headers'] ); } protected function after_plugin_theme_update( $update_results ) { $successful_updates = array(); $failed_updates = array(); if ( ! empty( $update_results['plugin'] ) ) { $notifications_enabled = apply_filters( 'auto_plugin_update_send_email', true, $update_results['plugin'] ); if ( $notifications_enabled ) { foreach ( $update_results['plugin'] as $update_result ) { if ( true === $update_result->result ) { $successful_updates['plugin'][] = $update_result; } else { $failed_updates['plugin'][] = $update_result; } } } } if ( ! empty( $update_results['theme'] ) ) { $notifications_enabled = apply_filters( 'auto_theme_update_send_email', true, $update_results['theme'] ); if ( $notifications_enabled ) { foreach ( $update_results['theme'] as $update_result ) { if ( true === $update_result->result ) { $successful_updates['theme'][] = $update_result; } else { $failed_updates['theme'][] = $update_result; } } } } if ( empty( $successful_updates ) && empty( $failed_updates ) ) { return; } if ( empty( $failed_updates ) ) { $this->send_plugin_theme_email( 'success', $successful_updates, $failed_updates ); } elseif ( empty( $successful_updates ) ) { $this->send_plugin_theme_email( 'fail', $successful_updates, $failed_updates ); } else { $this->send_plugin_theme_email( 'mixed', $successful_updates, $failed_updates ); } } protected function send_plugin_theme_email( $type, $successful_updates, $failed_updates ) { if ( empty( $successful_updates ) && empty( $failed_updates ) ) { return; } $unique_failures = false; $past_failure_emails = get_option( 'auto_plugin_theme_update_emails', array() ); if ( 'fail' === $type ) { foreach ( $failed_updates as $update_type => $failures ) { foreach ( $failures as $failed_update ) { if ( ! isset( $past_failure_emails[ $failed_update->item->{$update_type} ] ) ) { $unique_failures = true; continue; } if ( version_compare( $past_failure_emails[ $failed_update->item->{$update_type} ], $failed_update->item->new_version, '<' ) ) { $unique_failures = true; } } } if ( ! $unique_failures ) { return; } } $body = array(); $successful_plugins = ( ! empty( $successful_updates['plugin'] ) ); $successful_themes = ( ! empty( $successful_updates['theme'] ) ); $failed_plugins = ( ! empty( $failed_updates['plugin'] ) ); $failed_themes = ( ! empty( $failed_updates['theme'] ) ); switch ( $type ) { case 'success': if ( $successful_plugins && $successful_themes ) { $subject = __( '[%s] Some plugins and themes have automatically updated' ); $body[] = sprintf( __( 'Howdy! Some plugins and themes have automatically updated to their latest versions on your site at %s. No further action is needed on your part.' ), home_url() ); } elseif ( $successful_plugins ) { $subject = __( '[%s] Some plugins were automatically updated' ); $body[] = sprintf( __( 'Howdy! Some plugins have automatically updated to their latest versions on your site at %s. No further action is needed on your part.' ), home_url() ); } else { $subject = __( '[%s] Some themes were automatically updated' ); $body[] = sprintf( __( 'Howdy! Some themes have automatically updated to their latest versions on your site at %s. No further action is needed on your part.' ), home_url() ); } break; case 'fail': case 'mixed': if ( $failed_plugins && $failed_themes ) { $subject = __( '[%s] Some plugins and themes have failed to update' ); $body[] = sprintf( __( 'Howdy! Plugins and themes failed to update on your site at %s.' ), home_url() ); } elseif ( $failed_plugins ) { $subject = __( '[%s] Some plugins have failed to update' ); $body[] = sprintf( __( 'Howdy! Plugins failed to update on your site at %s.' ), home_url() ); } else { $subject = __( '[%s] Some themes have failed to update' ); $body[] = sprintf( __( 'Howdy! Themes failed to update on your site at %s.' ), home_url() ); } break; } if ( in_array( $type, array( 'fail', 'mixed' ), true ) ) { $body[] = "\n"; $body[] = __( 'Please check your site now. It’s possible that everything is working. If there are updates available, you should update.' ); $body[] = "\n"; if ( ! empty( $failed_updates['plugin'] ) ) { $body[] = __( 'These plugins failed to update:' ); foreach ( $failed_updates['plugin'] as $item ) { if ( $item->item->current_version ) { $body[] = sprintf( __( '- %1$s (from version %2$s to %3$s)' ), $item->name, $item->item->current_version, $item->item->new_version ); } else { $body[] = sprintf( __( '- %1$s version %2$s' ), $item->name, $item->item->new_version ); } $past_failure_emails[ $item->item->plugin ] = $item->item->new_version; } $body[] = "\n"; } if ( ! empty( $failed_updates['theme'] ) ) { $body[] = __( 'These themes failed to update:' ); foreach ( $failed_updates['theme'] as $item ) { if ( $item->item->current_version ) { $body[] = sprintf( __( '- %1$s (from version %2$s to %3$s)' ), $item->name, $item->item->current_version, $item->item->new_version ); } else { $body[] = sprintf( __( '- %1$s version %2$s' ), $item->name, $item->item->new_version ); } $past_failure_emails[ $item->item->theme ] = $item->item->new_version; } $body[] = "\n"; } } if ( in_array( $type, array( 'success', 'mixed' ), true ) ) { $body[] = "\n"; if ( ! empty( $successful_updates['plugin'] ) ) { $body[] = __( 'These plugins are now up to date:' ); foreach ( $successful_updates['plugin'] as $item ) { if ( $item->item->current_version ) { $body[] = sprintf( __( '- %1$s (from version %2$s to %3$s)' ), $item->name, $item->item->current_version, $item->item->new_version ); } else { $body[] = sprintf( __( '- %1$s version %2$s' ), $item->name, $item->item->new_version ); } unset( $past_failure_emails[ $item->item->plugin ] ); } $body[] = "\n"; } if ( ! empty( $successful_updates['theme'] ) ) { $body[] = __( 'These themes are now up to date:' ); foreach ( $successful_updates['theme'] as $item ) { if ( $item->item->current_version ) { $body[] = sprintf( __( '- %1$s (from version %2$s to %3$s)' ), $item->name, $item->item->current_version, $item->item->new_version ); } else { $body[] = sprintf( __( '- %1$s version %2$s' ), $item->name, $item->item->new_version ); } unset( $past_failure_emails[ $item->item->theme ] ); } $body[] = "\n"; } } if ( $failed_plugins ) { $body[] = sprintf( __( 'To manage plugins on your site, visit the Plugins page: %s' ), admin_url( 'plugins.php' ) ); $body[] = "\n"; } if ( $failed_themes ) { $body[] = sprintf( __( 'To manage themes on your site, visit the Themes page: %s' ), admin_url( 'themes.php' ) ); $body[] = "\n"; } $body[] = __( 'If you experience any issues or need support, the volunteers in the WordPress.org support forums may be able to help.' ); $body[] = __( 'https://wordpress.org/support/forums/' ); $body[] = "\n" . __( 'The WordPress Team' ); if ( '' !== get_option( 'blogname' ) ) { $site_title = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ); } else { $site_title = parse_url( home_url(), PHP_URL_HOST ); } $body = implode( "\n", $body ); $to = get_site_option( 'admin_email' ); $subject = sprintf( $subject, $site_title ); $headers = ''; $email = compact( 'to', 'subject', 'body', 'headers' ); $email = apply_filters( 'auto_plugin_theme_update_email', $email, $type, $successful_updates, $failed_updates ); $result = wp_mail( $email['to'], wp_specialchars_decode( $email['subject'] ), $email['body'], $email['headers'] ); if ( $result ) { update_option( 'auto_plugin_theme_update_emails', $past_failure_emails ); } } protected function send_debug_email() { $update_count = 0; foreach ( $this->update_results as $type => $updates ) { $update_count += count( $updates ); } $body = array(); $failures = 0; $body[] = sprintf( __( 'WordPress site: %s' ), network_home_url( '/' ) ); if ( isset( $this->update_results['core'] ) ) { $result = $this->update_results['core'][0]; if ( $result->result && ! is_wp_error( $result->result ) ) { $body[] = sprintf( __( 'SUCCESS: WordPress was successfully updated to %s' ), $result->name ); } else { $body[] = sprintf( __( 'FAILED: WordPress failed to update to %s' ), $result->name ); $failures++; } $body[] = ''; } foreach ( array( 'plugin', 'theme', 'translation' ) as $type ) { if ( ! isset( $this->update_results[ $type ] ) ) { continue; } $success_items = wp_list_filter( $this->update_results[ $type ], array( 'result' => true ) ); if ( $success_items ) { $messages = array( 'plugin' => __( 'The following plugins were successfully updated:' ), 'theme' => __( 'The following themes were successfully updated:' ), 'translation' => __( 'The following translations were successfully updated:' ), ); $body[] = $messages[ $type ]; foreach ( wp_list_pluck( $success_items, 'name' ) as $name ) { $body[] = ' * ' . sprintf( __( 'SUCCESS: %s' ), $name ); } } if ( $success_items !== $this->update_results[ $type ] ) { $messages = array( 'plugin' => __( 'The following plugins failed to update:' ), 'theme' => __( 'The following themes failed to update:' ), 'translation' => __( 'The following translations failed to update:' ), ); $body[] = $messages[ $type ]; foreach ( $this->update_results[ $type ] as $item ) { if ( ! $item->result || is_wp_error( $item->result ) ) { $body[] = ' * ' . sprintf( __( 'FAILED: %s' ), $item->name ); $failures++; } } } $body[] = ''; } if ( '' !== get_bloginfo( 'name' ) ) { $site_title = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ); } else { $site_title = parse_url( home_url(), PHP_URL_HOST ); } if ( $failures ) { $body[] = trim( __( "BETA TESTING?
=============

This debugging email is sent when you are using a development version of WordPress.

If you think these failures might be due to a bug in WordPress, could you report it?
 * Open a thread in the support forums: https://wordpress.org/support/forum/alphabeta
 * Or, if you're comfortable writing a bug report: https://core.trac.wordpress.org/

Thanks! -- The WordPress Team" ) ); $body[] = ''; $subject = sprintf( __( '[%s] Background Update Failed' ), $site_title ); } else { $subject = sprintf( __( '[%s] Background Update Finished' ), $site_title ); } $body[] = trim( __( 'UPDATE LOG
==========' ) ); $body[] = ''; foreach ( array( 'core', 'plugin', 'theme', 'translation' ) as $type ) { if ( ! isset( $this->update_results[ $type ] ) ) { continue; } foreach ( $this->update_results[ $type ] as $update ) { $body[] = $update->name; $body[] = str_repeat( '-', strlen( $update->name ) ); foreach ( $update->messages as $message ) { $body[] = '  ' . html_entity_decode( str_replace( '&#8230;', '...', $message ) ); } if ( is_wp_error( $update->result ) ) { $results = array( 'update' => $update->result ); if ( 'rollback_was_required' === $update->result->get_error_code() ) { $results = (array) $update->result->get_error_data(); } foreach ( $results as $result_type => $result ) { if ( ! is_wp_error( $result ) ) { continue; } if ( 'rollback' === $result_type ) { $body[] = '  ' . sprintf( __( 'Rollback Error: [%1$s] %2$s' ), $result->get_error_code(), $result->get_error_message() ); } else { $body[] = '  ' . sprintf( __( 'Error: [%1$s] %2$s' ), $result->get_error_code(), $result->get_error_message() ); } if ( $result->get_error_data() ) { $body[] = '         ' . implode( ', ', (array) $result->get_error_data() ); } } } $body[] = ''; } } $email = array( 'to' => get_site_option( 'admin_email' ), 'subject' => $subject, 'body' => implode( "\n", $body ), 'headers' => '', ); $email = apply_filters( 'automatic_updates_debug_email', $email, $failures, $this->update_results ); wp_mail( $email['to'], wp_specialchars_decode( $email['subject'] ), $email['body'], $email['headers'] ); } } 