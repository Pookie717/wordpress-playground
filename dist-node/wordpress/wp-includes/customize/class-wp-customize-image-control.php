<?php
 class WP_Customize_Image_Control extends WP_Customize_Upload_Control { public $type = 'image'; public $mime_type = 'image'; public function prepare_control() {} public function add_tab( $id, $label, $callback ) { _deprecated_function( __METHOD__, '4.1.0' ); } public function remove_tab( $id ) { _deprecated_function( __METHOD__, '4.1.0' ); } public function print_tab_image( $url, $thumbnail_url = null ) { _deprecated_function( __METHOD__, '4.1.0' ); } } 