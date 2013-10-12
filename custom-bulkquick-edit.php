<?php
/**
 * Plugin Name: Custom Bulk/Quick Edit
 * Plugin URI: http://wordpress.org/extend/plugins/custom-bulkquick-edit/
 * Description: Custom Bulk/Quick Edit plugin allows you to easily add previously defined custom fields to the edit screen bulk and quick edit panels.
 * Version: 1.0.1
 * Author: Michael Cannon
 * Author URI: http://aihr.us/about-aihrus/michael-cannon-resume/
 * License: GPLv2 or later
 */


/**
 * Copyright 2013 Michael Cannon (email: mc@aihr.us)
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */
class Custom_Bulkquick_Edit {
	const ID          = 'custom-bulkquick-edit';
	const PLUGIN_FILE = 'custom-bulkquick-edit/custom-bulkquick-edit.php';
	const VERSION     = '1.0.1';

	private static $base              = null;
	private static $field_key         = 'cbqe_';
	private static $fields_enabled    = array();
	private static $no_instance       = true;
	private static $post_types_ignore = array(
		'attachment',
		'page',
	);

	public static $donate_button   = '';
	public static $post_types      = array();
	public static $post_types_keys = array();
	public static $scripts_bulk    = array();
	public static $scripts_extra   = array();
	public static $scripts_quick   = array();
	public static $scripts_called  = false;
	public static $settings_link   = '';


	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'init', array( $this, 'init' ) );
		load_plugin_textdomain( self::ID, false, 'custom-bulkquick-edit/languages' );
	}


	public function admin_init() {
		self::$settings_link = '<a href="' . get_admin_url() . 'options-general.php?page=' . Custom_Bulkquick_Edit_Settings::ID . '">' . esc_html__( 'Settings', 'custom-bulkquick-edit' ) . '</a>';

		$this->update();
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		add_action( 'bulk_edit_custom_box', array( $this, 'bulk_edit_custom_box' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_custom_box' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post' ), 25 );
		add_action( 'wp_ajax_save_post_bulk_edit', array( 'Custom_Bulkquick_Edit', 'save_post_bulk_edit' ) );
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}


	public function init() {
		self::$base          = plugin_basename( __FILE__ );
		self::$donate_button = <<<EOD
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="WM4F995W9LHXE">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
EOD;
	}


	public function plugin_action_links( $links, $file ) {
		if ( $file == self::$base )
			array_unshift( $links, self::$settings_link );

		return $links;
	}


	public function activation() {
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
	}


	public function deactivation() {
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
	}


	public function uninstall() {
		if ( ! current_user_can( 'activate_plugins' ) )
			return;

		global $wpdb;

		require_once 'lib/class-custom-bulkquick-edit-settings.php';
		$delete_data = cbqe_get_option( 'delete_data', false );
		if ( $delete_data ) {
			delete_option( Custom_Bulkquick_Edit_Settings::ID );
			$wpdb->query( 'OPTIMIZE TABLE `' . $wpdb->options . '`' );
		}
	}


	public static function plugin_row_meta( $input, $file ) {
		if ( $file != self::$base )
			return $input;

		$disable_donate = cbqe_get_option( 'disable_donate' );
		if ( $disable_donate )
			return $input;

		$links = array(
			'<a href="http://aihr.us/about-aihrus/donate/"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" alt="PayPal - The safer, easier way to pay online!" /></a>',
			'<a href="http://aihr.us/downloads/custom-bulkquick-edit-premium-wordpress-plugin/">Purchase Custom Bulk/Quick Edit Premium</a>',
		);

		$input = array_merge( $input, $links );

		return $input;
	}


	public function admin_notices_0_0_1() {
		$content  = '<div class="updated fade"><p>';
		$content .= sprintf( __( 'If your Custom Bulk/Quick Edit display has gone to funky town, please <a href="%s">read the FAQ</a> about possible CSS fixes.', 'custom-bulkquick-edit' ), 'https://aihrus.zendesk.com/entries/23722573-Major-Changes-Since-2-10-0' );
		$content .= '</p></div>';

		echo $content;
	}


	public function admin_notices_donate() {
		$content  = '<div class="updated fade"><p>';
		$content .= sprintf( esc_html__( 'Please donate $2 towards development and support of this Custom Bulk/Quick Edit plugin. %s', 'custom-bulkquick-edit' ), self::$donate_button );
		$content .= '</p></div>';

		echo $content;
	}


	public function update() {
		$prior_version = cbqe_get_option( 'admin_notices' );
		if ( $prior_version ) {
			if ( $prior_version < '0.0.1' )
				add_action( 'admin_notices', array( $this, 'admin_notices_0_0_1' ) );

			cbqe_set_option( 'admin_notices' );
		}

		// display donate on major/minor version release
		$donate_version = cbqe_get_option( 'donate_version', false );
		if ( ! $donate_version || ( $donate_version != self::VERSION && preg_match( '#\.0$#', self::VERSION ) ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notices_donate' ) );
			cbqe_set_option( 'donate_version', self::VERSION );
		}
	}


	/**
	 *
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public static function manage_posts_custom_column_precapture( $column, $post_id ) {
		ob_start();
	}


	public static function manage_posts_custom_column_capture( $column, $post_id ) {
		$buffer = ob_get_contents();
		ob_end_clean();
		self::manage_posts_custom_column( $column, $post_id, $buffer );
	}


	public static function manage_posts_custom_column( $column, $post_id, $buffer = null ) {
		global $post;

		$field_type = self::is_field_enabled( $post->post_type, $column );
		if ( ! $field_type ) {
			echo $buffer;
			return;
		}

		$details = self::get_field_details( $post->post_type, $column );
		$options = explode( "\n", $details );
		$options = array_map( 'trim', $options );

		$result = '';
		switch ( $column ) {
		case 'post_excerpt':
			$result = $post->post_excerpt;
			break;

		default:
			$current = get_post_meta( $post_id, $column, true );

			switch ( $field_type ) {
			case 'categories':
			case 'taxonomy':
				$taxonomy   = $column;
				$post_type  = get_post_type( $post_id );
				$post_terms = array();

				$terms = get_the_terms( $post_id, $taxonomy );
				if ( ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$post_terms[] = '<a href="edit.php?post_type=' . $post_type . '&' . $taxonomy . '=' . $term->slug . '">' . esc_html( sanitize_term_field( 'name', $term->name, $term->term_id, $taxonomy, 'edit' ) ) . '</a>';
					}
				}

				$result = implode( ', ', $post_terms );
				break;

			case 'input':
			case 'textarea':
				$result = $current;
				break;

			case 'checkbox':
			case 'radio':
				if ( ! is_array( $current ) )
					$current = array( $current );

				foreach ( $options as $option ) {
					$parts = explode( '|', $option );
					$value = array_shift( $parts );
					if ( empty( $parts ) )
						$name = $value;
					else
						$name = array_shift( $parts );

					if ( in_array( $value, $current ) )
						$result .= '<input type="' . $field_type . '" name="' . $column . '" value="' . $value . '" checked="checked" disabled="disabled" /> ' . $name . '<br />';
				}
				break;

			case 'select':
				if ( ! is_array( $current ) )
					$current = array( $current );

				$select_options = '';
				foreach ( $options as $option ) {
					$parts = explode( '|', $option );
					$value = array_shift( $parts );
					if ( empty( $parts ) )
						$name = $value;
					else
						$name = array_shift( $parts );

					if ( in_array( $value, $current  ) )
						$select_options .= '<option value="' . $value . '" selected="selected">' . $name . '</option>';
				}

				if ( $select_options ) {
					$result  = '<select name="' . $column . '" disabled="disabled">';
					$result .= $select_options;
					$result .= '</select>';
				}
				break;

			default:
				$result = apply_filters( 'cbqe_manage_posts_custom_column_field_type', $current, $field_type, $column, $post_id );
				break;
			}
		}

		$result = apply_filters( 'cbqe_posts_custom_column', $result, $column, $post_id );

		if ( $result )
			echo $result;
	}


	public static function manage_posts_columns( $columns ) {
		global $post;

		if ( is_null( $post ) )
			return $columns;

		$fields = self::get_enabled_fields( $post->post_type );
		foreach ( $fields as $key => $field_name ) {
			if ( false !== strstr( $field_name, Custom_Bulkquick_Edit_Settings::AUTO ) || false !== strstr( $field_name, Custom_Bulkquick_Edit_Settings::RESET ) )
				continue;

			$title                  = Custom_Bulkquick_Edit_Settings::$settings[ $key ]['label'];
			$columns[ $field_name ] = $title;
		}

		$columns = apply_filters( 'cbqe_columns', $columns );

		return $columns;
	}


	public static function get_enabled_fields( $post_type ) {
		$fields   = array();
		$settings = Custom_Bulkquick_Edit_Settings::$settings;
		foreach ( $settings as $key => $value ) {
			if ( $post_type != $value['section'] )
				continue;

			if ( strstr( $key, Custom_Bulkquick_Edit_Settings::CONFIG ) )
				continue;

			$field_name = str_replace( $post_type . Custom_Bulkquick_Edit_Settings::ENABLE, '', $key );
			$field_type = self::is_field_enabled( $post_type, $field_name );
			if ( $field_type )
				$fields[ $key ] = $field_name;
		}

		return $fields;
	}


	public static function get_scripts() {
		if ( empty( self::$scripts_called ) ) {
			echo '
<script type="text/javascript">
jQuery(document).ready(function($) {
	var wp_inline_edit = inlineEditPost.edit;
	inlineEditPost.edit = function( id ) {
		wp_inline_edit.apply( this, arguments );
		var post_id = 0;
		if ( typeof( id ) == "object" )
			post_id = parseInt( this.getId( id ) );

		if ( post_id > 0 ) {
			// define the edit row
			var edit_row = $( "#edit-" + post_id );
			var post_row = $( "#post-" + post_id );
			';

			$scripts = implode( "\n", self::$scripts_quick );
			echo $scripts;

			echo '
		}
	};

	';

			$scripts = implode( "\n", self::$scripts_extra );
			echo $scripts;

			echo '

	$( "#bulk_edit" ).on( "click", function() {
		var bulk_row = $( "#bulk-edit" );
		var post_ids = new Array();
		bulk_row.find( "#bulk-titles" ).children().each( function() {
			post_ids.push( $( this ).attr( "id" ).replace( /^(ttle)/i, "" ) );
		});

		$.ajax({
			url: ajaxurl,
				type: "POST",
				async: false,
				cache: false,
				data: {
					action: "save_post_bulk_edit",
					post_ids: post_ids,
					';

			$scripts = implode( ",\n", self::$scripts_bulk );
			echo $scripts;

			echo '
				}
		});
	});
});
</script>
			';

			self::$scripts_called = true;
		}
	}


	/**
	 *
	 *
	 * @SuppressWarnings(PHPMD.Superglobals)
	 * @SuppressWarnings(PHPMD.ExitExpression)
	 */
	public function save_post_bulk_edit() {
		$post_ids = ! empty( $_POST[ 'post_ids' ] ) ? $_POST[ 'post_ids' ] : array();
		if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
			foreach ( $post_ids as $post_id ) {
				self::save_post_items( $post_id, 'bulk_edit' );
			}
		}

		die();
	}


	/**
	 *
	 *
	 * @SuppressWarnings(PHPMD.Superglobals)
	 */
	public function save_post_items( $post_id, $mode = '' ) {
		if ( ! preg_match( '#^\d+$#', $post_id ) )
			return;

		unset( $_POST['action'] );
		unset( $_POST['post_ids'] );

		if ( empty( $_POST ) )
			return;

		$post      = get_post( $post_id );
		$post_type = $post->post_type;

		foreach ( $_POST as $field => $value ) {
			if ( false === strpos( $field, self::$field_key ) && 'tax_input' != $field )
				continue;

			if ( '' == $value && 'bulk_edit' == $mode )
				continue;

			if ( 'tax_input' != $field )
				self::save_post_item( $post_id, $post_type, $field, $value );
			else
				foreach ( $value as $key => $val )
					self::save_post_item( $post_id, $post_type, $key, $val );
		}
	}


	public static function save_post_item( $post_id, $post_type, $field, $value ) {
		$field_name = str_replace( self::$field_key, '', $field );
		$field_type = self::is_field_enabled( $post_type, $field_name );
		if ( ! $field_type )
			return;

		if ( false !== strstr( $field_name, Custom_Bulkquick_Edit_Settings::RESET ) ) {
			$taxonomy = str_replace( Custom_Bulkquick_Edit_Settings::RESET, '', $field_name );
			wp_delete_object_term_relationships( $post_id, $taxonomy );
			return;
		}

		$value = stripslashes_deep( $value );
		if ( 'taxonomy' == $field_type ) {
			// WordPress doesn't keep " enclosed CSV terms together, so
			// don't worry about it here then by using `str_getcsv`
			$values = explode( ',', $value );
			wp_set_object_terms( $post_id, $values, $field_name );
			return;
		} elseif ( 'categories' == $field_type ) {
			wp_set_object_terms( $post_id, $value, $field_name );
			return;
		}

		if ( ! in_array( $field_name, array( 'post_excerpt' ) ) ) {
			update_post_meta( $post_id, $field_name, $value );
		} else {
			$data = array(
				'ID' => $post_id,
				$field_name => $value,
			);
			wp_update_post( $data );
		}
	}


	/**
	 *
	 *
	 * @SuppressWarnings(PHPMD.Superglobals)
	 */
	public static function get_post_types() {
		if ( ! empty( self::$post_types ) )
			return self::$post_types;

		if ( isset( $_GET['post_type'] ) ) {
			$post_type                      = esc_attr( $_GET['post_type'] );
			self::$post_types[ $post_type ] = $post_type;
			self::$post_types_keys[]        = $post_type;

			return self::$post_types;
		} else {
			$args = array(
				'public' => true,
				'_builtin' => true,
			);

			$args = apply_filters( 'cbqe_get_post_types_args', $args );

			$post_types = get_post_types( $args, 'objects' );
			foreach ( $post_types as $post_type ) {
				if ( in_array( $post_type->name, self::$post_types_ignore ) )
					continue;

				self::$post_types[ $post_type->name ] = $post_type->label;
				self::$post_types_keys[]              = $post_type->name;
			}

			return self::$post_types;
		}
	}


	public static function get_field_details( $post_type = null, $field_name = null ) {
		$key = self::get_field_key( $post_type, $field_name );

		if ( empty( $key ) )
			return false;

		$key    .= Custom_Bulkquick_Edit_Settings::CONFIG;
		$details = cbqe_get_option( $key );

		return $details;
	}


	public static function get_field_key( $post_type = null, $field_name = null ) {
		if ( is_null( $post_type ) ) {
			global $post;

			if ( is_null( $post ) )
				return false;

			$post_type = $post->post_type;
		}

		if ( false !== strstr( $field_name, self::$field_key ) )
			$field_name = preg_replace( '#^' . self::$field_key . '#', '', $field_name );

		$key = $post_type . Custom_Bulkquick_Edit_Settings::ENABLE . $field_name;

		return $key;
	}


	public static function is_field_enabled( $post_type = null, $field_name = null ) {
		if ( is_null( $field_name ) )
			return false;

		if ( is_null( $post_type ) ) {
			global $post;

			if ( is_null( $post ) )
				return false;

			$post_type = $post->post_type;
		}

		$key = self::get_field_key( $post_type, $field_name );
		if ( isset( self::$fields_enabled[ $key ] ) )
			return self::$fields_enabled[ $key ];

		$enable = cbqe_get_option( $key );
		if ( ! empty( $enable )  ) {
			self::$fields_enabled[ $key ] = $enable;
			return $enable;
		} else {
			self::$fields_enabled[ $key ] = false;
			return false;
		}
	}


	public function bulk_edit_custom_box( $column_name, $post_type ) {
		self::quick_edit_custom_box( $column_name, $post_type, true );
	}


	public static function quick_edit_custom_box( $column_name, $post_type, $bulk_mode = false ) {
		if ( ! in_array( $post_type, self::$post_types_keys ) )
			return;

		$field_type = self::is_field_enabled( $post_type, $column_name );
		if ( empty( $field_type ) )
			return;

		$key        = self::get_field_key( $post_type, $column_name );
		$field_name = self::$field_key . $column_name;

		$open_fieldset  = '
			<fieldset class="inline-edit-col-right inline-edit-' . $field_type . '">
			<div class="inline-edit-col inline-edit-' . $column_name . '">
			';
		$close_fieldset = '
			</div>
			</fieldset>
			';

		if ( $bulk_mode && in_array( $field_type, array( 'categories', 'taxonomy' ) ) ) {
			$key_reset = $key . Custom_Bulkquick_Edit_Settings::RESET;
			$enable    = cbqe_get_option( $key_reset );

			if ( $enable ) {
				$field_reset  = $field_name . Custom_Bulkquick_Edit_Settings::RESET;
				$title        = Custom_Bulkquick_Edit_Settings::$settings[ $key_reset ]['label'];
				$column_reset = $column_name . Custom_Bulkquick_Edit_Settings::RESET;

				$result  = '';
				$result .= '<label class="alignleft">';
				$result .= '<input type="checkbox" name="' . $field_reset . '" />';
				$result .= ' ';
				$result .= '<span class="checkbox-title">' . $title . '</span>';
				$result .= '</label>';

				echo $open_fieldset;
				echo $result;
				echo $close_fieldset;

				self::$scripts_bulk[ $column_reset ] = "'{$field_reset}': bulk_row.find( 'input[name^={$field_reset}]:checkbox:checked' ).map(function(){ return $(this).val(); }).get()";
			}

			// return now otherwise taxonomy entries are duplicated
			return;
		}

		if ( 'post_excerpt' == $column_name ) {
			$field_type = 'textarea';
		} elseif ( false !== strstr( $column_name, Custom_Bulkquick_Edit_Settings::RESET ) ) {
			if ( ! $bulk_mode )
				return;
			else
				$field_type = 'checkbox';
		}

		if ( self::$no_instance ) {
			self::$no_instance = false;
			wp_nonce_field( self::$base, self::ID );
		}

		$key            = self::get_field_key( $post_type, $column_name );
		$field_name     = self::$field_key . $column_name;
		$field_name_var = str_replace( '-', '_', $field_name );
		$title          = Custom_Bulkquick_Edit_Settings::$settings[ $key ]['label'];

		echo $open_fieldset;

		if ( ! in_array( $field_type, array( 'checkbox', 'radio' ) ) ) {
			$class = '';
			if ( 'categories' == $field_type )
				$class = 'inline-edit-categories-label';
			elseif ( 'taxonomy' != $field_type )
				echo '<label class="inline-edit-group">';
			else
				echo '<label class="inline-edit-tags">';

			echo '<span class="title ' . $class . '">' . $title . '</span>';
		}

		$details = self::get_field_details( $post_type, $column_name );
		$options = explode( "\n", $details );
		$options = array_map( 'trim', $options );
		$result  = '';

		switch ( $field_type ) {
		case 'checkbox':
		case 'radio':
			$result      .= '<label class="alignleft">';
			$check_title  = '<span class="checkbox-title">' . $title . '</span>';
			$multiple     = '';
			$do_pre_title = true;
			if ( 'checkbox' == $field_type ) {
				if ( 1 < count( $options ) )
					$multiple = '[]';
				else
					$do_pre_title = false;
			}

			if ( $do_pre_title ) {
				$result .= $check_title;
				$result .= '</label>';
			}

			foreach ( $options as $option ) {
				if ( $do_pre_title )
					$result .= '<label class="inline-edit-group">';

				$parts = explode( '|', $option );
				$value = array_shift( $parts );
				if ( empty( $parts ) )
					$name = $value;
				else
					$name = array_shift( $parts );

				$result .= '<input type="' . $field_type . '" name="' . $field_name . $multiple . '" value="' . $value . '" />';
				$result .= ' ' . $name;

				if ( $do_pre_title )
					$result .= '</label>';
			}

			if ( empty( $do_pre_title ) ) {
				$result .= $check_title;
				$result .= '</label>';
			}
			break;

		case 'input':
			$result = '<input type="text" name="' . $field_name . '" autocomplete="off" />';
			break;

		case 'select':
			$result .= '<select name="' . $field_name . '">';
			$result .= '<option></option>';
			foreach ( $options as $option ) {
				$parts = explode( '|', $option );
				$value = array_shift( $parts );
				if ( empty( $parts ) )
					$name = $value;
				else
					$name = array_shift( $parts );

				$result .= '<option value="' . $value . '">' . $name . '</option>';
			}
			$result .= '</select>';
			break;

		case 'textarea':
			$result = '<textarea cols="22" rows="1" name="' . $field_name . '" autocomplete="off"></textarea>';
			break;

		case 'categories':
			$taxonomy = str_replace( self::$field_key, '', $field_name );

			ob_start();
			wp_terms_checklist( null, array( 'taxonomy' => $taxonomy ) );
			$terms = ob_get_contents();
			ob_end_clean();

			$result  = '<input type="hidden" name="tax_input[' . $taxonomy . '][]" />';
			$result .= '<ul class="cat-checklist ' . esc_attr( $taxonomy ) . '-checklist">';
			$result .= $terms;
			$result .= '</ul>';
			break;

		case 'taxonomy':
			$taxonomy  = str_replace( self::$field_key, '', $field_name );
			$tax_class = 'tax_input_' . $taxonomy;
			$result    = '<textarea cols="22" rows="1" name="' . $field_name . '" class="' . $tax_class . ' ' . $field_name_var . '" autocomplete="off"></textarea>';
			break;

		default:
			$result = apply_filters( 'cbqe_quick_edit_custom_box_field', '', $field_type, $field_name, $options );
			break;
		}

		echo $result;

		if ( ! in_array( $field_type, array( 'checkbox', 'radio' ) ) )
			echo "\n</label>";

		echo $close_fieldset;

		switch ( $field_type ) {
		case 'checkbox':
			self::$scripts_bulk[ $column_name ]        = "'{$field_name}': bulk_row.find( 'input[name^={$field_name}]:checkbox:checked' ).map(function(){ return $(this).val(); }).get()";
			self::$scripts_quick[ $column_name . '1' ] = "var {$field_name_var} = $( '.column-{$column_name} input:checkbox:checked', post_row ).map(function(){ return $(this).val(); }).get();";
			self::$scripts_quick[ $column_name . '2' ] = "$.each( {$field_name_var}, function( key, value ){ $( ':input[name^={$field_name}]', edit_row ).filter('[value=' + value + ']').prop('checked', true); } );";
			break;

		case 'radio':
			self::$scripts_bulk[ $column_name ]        = "'{$field_name}': bulk_row.find( 'input[name={$field_name}]:radio:checked' ).val()";
			self::$scripts_quick[ $column_name . '1' ] = "var {$field_name_var} = $( '.column-{$column_name} input:radio:checked', post_row ).val();";
			self::$scripts_quick[ $column_name . '2' ] = "$( ':input[name={$field_name}]', edit_row ).filter('[value=' + {$field_name_var} + ']').prop('checked', true);";
			break;

		case 'select':
			self::$scripts_bulk[ $column_name ]        = "'{$field_name}': bulk_row.find( '{$field_type}[name={$field_name}]' ).val()";
			self::$scripts_quick[ $column_name . '1' ] = "var {$field_name_var} = $( '.column-{$column_name} option', post_row ).filter(':selected').val();";
			self::$scripts_quick[ $column_name . '2' ] = "$( ':input[name={$field_name}] option[value=' + {$field_name_var} + ']', edit_row ).prop('selected', true);";
			break;

		case 'input':
		case 'textarea':
			self::$scripts_bulk[ $column_name ]        = "'{$field_name}': bulk_row.find( '{$field_type}[name={$field_name}]' ).val()";
			self::$scripts_quick[ $column_name . '1' ] = "var {$field_name_var} = $( '.column-{$column_name}', post_row ).text();";
			self::$scripts_quick[ $column_name . '2' ] = "$( ':input[name={$field_name}]', edit_row ).val( {$field_name_var} );";
			break;

		case 'categories':
			/*
				// need to send the values to the inline editor
				// self::$scripts_quick[ $column_name . '1' ] = "var {$field_name_var} = $( '.column-{$column_name}', post_row ).text();";
				// hierarchical taxonomies
				$('.post_category', post_row).each(function(){
					var term_ids = $(this).text();

					if ( term_ids ) {
						taxname = $(this).attr('id').replace('_'+id, '');
						$('ul.'+taxname+'-checklist :checkbox', edit_row).val(term_ids.split(','));
					}
				});
			 */
			break;

		case 'taxonomy':
			self::$scripts_bulk[ $column_name ]        = "'{$field_name}': bulk_row.find( 'textarea[name={$field_name}]' ).val()";
			self::$scripts_quick[ $column_name . '1' ] = "var {$field_name_var} = $( '.column-{$column_name}', post_row ).text();";
			self::$scripts_quick[ $column_name . '2' ] = "$( ':input[name={$field_name}]', edit_row ).val( {$field_name_var} );";

			$key = self::get_field_key( $post_type, $field_name );
			if ( empty( $key ) )
				break;

			$key       .= Custom_Bulkquick_Edit_Settings::AUTO;
			$do_suggest = cbqe_get_option( $key );
			if ( $do_suggest ) {
				$ajax_url   = site_url() . '/wp-admin/admin-ajax.php';
				$suggest_js = "suggest( '{$ajax_url}?action=ajax-tag-search&tax={$taxonomy}', { delay: 500, minchars: 2, multiple: true, multipleSep: inlineEditL10n.comma + ' ' } )";

				self::$scripts_quick[ $column_name . '3' ] = "$( 'textarea[name={$field_name}]', edit_row ).{$suggest_js};";

				/*
					// self::$scripts_extra[ $column_name . '1' ] = "$( 'tr.inline-editor textarea[name={$field_name}]' ).{$suggest_js};";
					// from wp-admin/js/inline.js
					// enable autocomplete for tags
					if ( 'post' == type ) {
						// support multi taxonomies?
						tax = 'post_tag';
						$('tr.inline-editor textarea[name="tax_input['+tax+']"]').suggest( ajaxurl + '?action=ajax-tag-search&tax=' + tax, { delay: 500, minchars: 2, multiple: true, multipleSep: inlineEditL10n.comma + ' ' } );
					}
				 */
			}
			break;

		default:
			self::$scripts_bulk  = apply_filters( 'cbqe_quick_scripts_bulk', self::$scripts_bulk, $post_type, $column_name, $field_name, $field_type, $field_name_var );
			self::$scripts_quick = apply_filters( 'cbqe_quick_scripts_quick', self::$scripts_quick, $post_type, $column_name, $field_name, $field_type, $field_name_var );
			self::$scripts_extra = apply_filters( 'cbqe_quick_scripts_extra', self::$scripts_extra, $post_type, $column_name, $field_name, $field_type, $field_name_var );
			break;
		}
	}


	/**
	 *
	 *
	 * @SuppressWarnings(PHPMD.Superglobals)
	 */
	public function save_post( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( ! in_array( $post_type, self::$post_types_keys ) )
			return;

		if ( ! current_user_can( 'edit_post', $post_id ) )
			return;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		if ( 'revision' == $post_type )
			return;

		if ( isset( $_POST[ self::ID ] ) && ! wp_verify_nonce( $_POST[ self::ID ], self::$base ) )
			return;

		remove_action( 'save_post', array( $this, 'save_post' ), 25 );
		self::save_post_items( $post_id );
	}


	/**
	 *
	 *
	 * @SuppressWarnings(PHPMD.Superglobals)
	 */
	public function admin_footer() {
		if ( self::$no_instance )
			return;

		if ( empty( $_GET['post_type'] ) ) {
			global $post;
			$_GET['post_type'] = ! empty( $post->post_type ) ? $post->post_type : false;
		}

		if ( in_array( $_GET['post_type'], self::$post_types_keys ) )
			self::get_scripts();
	}


}


register_activation_hook( __FILE__, array( 'Custom_Bulkquick_Edit', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'Custom_Bulkquick_Edit', 'deactivation' ) );
register_uninstall_hook( __FILE__, array( 'Custom_Bulkquick_Edit', 'uninstall' ) );


add_action( 'after_setup_theme', 'custom_bulkquick_edit_init', 999 );


/**
 *
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
 */
function custom_bulkquick_edit_init() {
	if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX )
		if ( ! is_admin() )
			return;

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( is_plugin_active( Custom_Bulkquick_Edit::PLUGIN_FILE ) ) {
		require_once 'lib/class-custom-bulkquick-edit-settings.php';

		global $Custom_Bulkquick_Edit;
		if ( is_null( $Custom_Bulkquick_Edit ) )
			$Custom_Bulkquick_Edit = new Custom_Bulkquick_Edit();

		global $Custom_Bulkquick_Edit_Settings;
		if ( is_null( $Custom_Bulkquick_Edit_Settings ) )
			$Custom_Bulkquick_Edit_Settings = new Custom_Bulkquick_Edit_Settings();
	}
}


?>
