<?php
/**
 * Plugin Name: Delivery Suburbs for WooCommerce
 * Plugin URI: https://www.enigmaplugins.com
 * Description: Display a delivery suburbs dropdown on checkout or product page. This is useful for stores that only deliver to specific suburbs, and wish to streamline the checkout process accordingly.
 * Author: Enigma Plugins
 * Author URI: https://www.enigmaplugins.com
 * Text Domain: woo-delivery-suburbs
 * Version: 1.0
 * License: GPL
 */


/* Registering activation hook */
function ds_activation() {
	global $wpdb;
	$table_name        = $wpdb->prefix . 'woocommerce_delivery_suburbs';
	$charset_collation = $wpdb->get_charset_collate();

	$table_check = $wpdb->query( "SHOW TABLES LIKE '" . $table_name . "'" );

	if ( $table_check->num_rows == 0 ) {

		$tbl_sql = "CREATE TABLE $table_name(
                    id int (9) NOT NULL AUTO_INCREMENT, 
                    location VARCHAR (55) NOT NULL,
                    delivery_suburb VARCHAR (55) NOT NULL,
                    PRIMARY KEY (id)
                    )";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $tbl_sql );
	}
	set_transient( 'ds_plugin_active', true, 5 );
}

register_activation_hook( __FILE__, 'ds_activation' );

add_action( 'admin_notices', 'ds_plugin_activation_notice' );
function ds_plugin_activation_notice() {
	if ( get_transient( 'ds_plugin_active' ) ) {
		?>
        <div class="notice notice-info is-dismissible">
            <p>Plugin activated. Please configure delivery suburbs settings <a
                        href="<?php get_admin_url() ?>admin.php?page=woo-delivery-suburbs">here</a>.</p>
        </div>
	<?php }

}

/* Loading text domain */
add_action( 'init', 'ds_load_textdomain' );

function ds_load_textdomain() {
	load_plugin_textdomain( 'woo-delivery-suburbs', false, basename( dirname( __FILE__ ) ) . '/languages' );
}

/* Loading scripts on the frontend */
add_action( 'wp_head', 'ds_frontend_script' );

function ds_frontend_script() {
	wp_enqueue_script( 'ds_frontend', plugins_url( 'js/ds_frontend.js', __FILE__ ) );
	wp_enqueue_style( 'ds_frontend_style', plugins_url( 'css/ds-frontend-style.css', __FILE__ ) );
}

/* Loading scripts on for the admin */
add_action( 'admin_enqueue_scripts', 'ds_admin_scripts' );
function ds_admin_scripts() {
	wp_enqueue_script( 'jquery' );
	wp_enqueue_style( 'ds-style', plugins_url( 'css/ds-style.css', __FILE__ ) );
	//wp_enqueue_script( 'ds_shipping_zone_update_alert', plugins_url( 'js/ds_shipping_zones_update_alert.js', __FILE__ ) );
}

function ds_detect_shipping_zones_update() { ?>
    <script type="text/javascript">
        jQuery(document).ready(function () {
            jQuery('.wc-shipping-zone-method-save').click(function () {
                alert("You have updated your shipping zones. You need to regenerate the suburb list in Delivery Suburbs plugin.");
            });
        });
    </script>
	<?php

}

add_action( 'admin_head', 'ds_detect_shipping_zones_update' );

/* Creating plugin menu under WooCommerce */
add_action( 'admin_menu', 'ds_admin_menu' );
function ds_admin_menu() {
	add_submenu_page( 'woocommerce', __( 'Delivery Suburbs', 'woo-delivery-suburbs' ), __( 'Delivery Suburbs', 'woo-delivery-suburbs' ), 'manage_options', 'woo-delivery-suburbs', 'ds_menu_page' );
}

/* Rendering settings page for the plugin */
function ds_menu_page() {
	global $wpdb;

	if ( $_POST['get_names'] ) {
		$table_name   = $wpdb->prefix . 'woocommerce_delivery_suburbs';
		$qry_truncate = "TRUNCATE TABLE $table_name";
		$wpdb->query( $qry_truncate );
		$shipping_zones = new WC_Shipping_Zones();
		$zones          = $shipping_zones->get_zones();
		if ( $zones ) {
			foreach ( $zones as $zone ) {
				$locations = $zone['zone_locations'];

				foreach ( $locations as $location ) {

					if ( $location->type == "country" ) {
						$country = $location->code;
					}

					if ( $location->type == "postcode" ) {
						$country_code = $country;
						$postalcode   = $location->code;
						$url          = "http://api.geonames.org/postalCodeLookupJSON?postalcode=" . $postalcode . "&country=" . $country_code . "&username=enigmaweb";

						$ch = curl_init();
						curl_setopt( $ch, CURLOPT_URL, $url );
						curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
						$data = curl_exec( $ch );
						$obj  = json_decode( $data );


						foreach ( $obj->postalcodes as $postalcode ) {
							global $data;
							$data = array(
								'delivery_suburb' => ( $postalcode->postalcode . '-' . $postalcode->placeName ),
								'location'        => ( $postalcode->placeName ),
							);
							$wpdb->insert( $wpdb->prefix . 'woocommerce_delivery_suburbs', $data );
						}
					}

				}
			}
		}
	}

	if ( isset( $_POST['display_options'] ) ) {
		if ( get_option( 'ds_show_suburb_field' ) ) {
			update_option( 'ds_show_suburb_field', $_POST['show_suburb'] );

		} else {
			add_option( 'ds_show_suburb_field', $_POST['show_suburb'], '', 'yes' );
		}
	}

	?>
    <div class="wrap">
        <h1>Delivery Suburbs</h1>
		<?php if ( isset( $_POST['display_options'] ) ): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( 'Display settings saved', 'woo-delivery-suburbs' ); ?></p>
            </div>
		<?php endif; ?>
		<?php if ( isset( $_POST['get_names'] ) ): ?>
			<?php if ( $zones ): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e( 'Suburb list generated and saved successfuly', 'woo-delivery-suburbs' ); ?></p>
                </div>
			<?php else: ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e( 'You\'ll need to add some postcodes first. Go to <a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=shipping">Shipping Zones</a>.', 'woo-delivery-suburbs' ); ?></p>
                </div>
			<?php endif; ?>
		<?php endif; ?>
        <!-- Rendering area for generating suburb list -->
        <div class="box-style">
            <h4><?php _e( 'Generate Delivery Suburbs List', 'woo-delivery-suburbs' ); ?></h4>
            <p><?php _e( 'Suburb data comes from <a href="http://geonames.org">geonames.org</a>. Click this button to get the latest suburb data based on the postcodes you have set in <a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=shipping">Shipping Zones</a>.', 'woo-delivery-suburbs' ); ?></p>
            <form action="" method="post">
                <table>
                    <tr>
                        <td><input type="submit" name="get_names" class="button-primary"
                                   value="<?php _e( 'Generate Suburb List', 'woo-delivery-suburbs' ) ?>"/></td>
                    </tr>
                </table>
            </form>
        </div>

        <!-- Rendering area for display settings. -->
        <div class="box-style">
            <h4><?php _e( 'Display Options', 'woo-delivery-suburbs' ); ?></h4>
            <form method="post" action="">
                <table>
                    <tr>
                        <td><label><?php _e( 'Show in checkout page', 'woo-delivery-suburbs' ); ?></label></td>
                        <td><input type="radio" name="show_suburb"
                                   value="checkout" <?php checked( 'checkout', get_option( 'ds_show_suburb_field' ), true ); ?> />
                        </td>
                    </tr>
                    <tr>
                        <td><label><?php _e( 'Show in product and checkout page', 'woo-delivery-suburbs' ) ?></label>
                        </td>
                        <td><input type="radio" name="show_suburb"
                                   value="product" <?php checked( 'product', get_option( 'ds_show_suburb_field' ), true ); ?> />
                        </td>
                    </tr>
                    <tr>
                        <td><input type="submit" name="display_options"
                                   value="<?php _e( 'Save Option', 'woo-delivery-suburbs' ) ?>" class="button-primary">
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <!-- Rendering area for import/export feature -->
        <div class="box-style">

            <table>

                <tr>
                    <h4><?php _e( 'Export Suburb List', 'woo-delivery-suburbs' ); ?></h4>
                    <form method="post" action="">
                        <td><input type="submit" name="export_csv" value="Export Suburb List" class="button-primary"></td>
                    </form>
                </tr>
            </table>
            <div class="box-style-import">
            <table>
                <tr>
                    <h4><?php _e( 'Import Suburb List', 'woo-delivery-suburbs' ); ?></h4>
                    <form method="post" action="" enctype="multipart/form-data">
                        <td><input type="file" name="file" value="Choose CSV"></td>
                        <td><input type="submit" name="import_csv" value="Import Suburb List" class="button-primary"></td>
                    </form>
                </tr>
            </table>
            </div>
        </div>
    </div>
	<?php

}


/* Checking for checkout display option */
if ( get_option( 'ds_show_suburb_field' ) == 'checkout' ) {
	add_filter( 'woocommerce_checkout_fields', 'ds_checkout_field' );

}

/* Replacing postcode field with delivery suburb field on the checkout page */
function ds_checkout_field( $fields ) {
	$data = ds_get_delivery_suburbs();

	unset( $fields['billing']['billing_postcode'] );
	unset( $fields['shipping']['shipping_postcode'] );
	unset( $fields['shipping']['shipping_city'] );
	$fields['shipping']['shipping_suburb'] = array(
		'label'    => __( 'Delivery Suburb', 'woocommerce' ),
		'type'     => 'select',
		'name'     => 'postcode',
		'default'  => 'choice 1',
		'options'  => $data,
		'required' => true,
		'class'    => array( 'form-row-last', 'ds_shipping_suburb_select' ),
		'clear'    => true
	);

	return $fields;
}

/* Rendering suburbs for the delivery suburb field on the checkout page */
function ds_get_delivery_suburbs() {
	global $wpdb;
	$suburbs    = [];
	$table      = $wpdb->prefix . 'woocommerce_delivery_suburbs';
	$suburb_qry = "select * from $table order by `location` ASC";
	$results    = $wpdb->get_results( $suburb_qry, OBJECT );
	foreach ( $results as $result ) {
		$suburbs[ $result->delivery_suburb ] = $result->location;
	}

	return $suburbs;
}


/* Display the extra data in the order admin panel */
function ds_display_order_data_in_admin( $order ) { ?>
	<?php if ( get_post_meta( $order->id, '_shipping_suburb', true ) ): ?>
		<?php $suburb = explode( "-", get_post_meta( $order->id, '_shipping_suburb', true ) ); ?>
		<?php echo '<p><strong>' . __( 'Delivery Suburb', 'woo-delivery-suburbs' ) . ':</strong> ' . $suburb[1] . '</p>'; ?>
	<?php endif; ?>
<?php }

//add_action( 'woocommerce_admin_order_data_after_shipping_address', 'ds_display_order_data_in_admin' );

function ds_add_suburb_order_meta( $order ) {
	$o = wc_get_order( $order );
	$i = $o->get_items();
	foreach ( $i as $key => $value ) {
		if ( ! wc_get_order_item_meta( $key, 'Delivery Suburb', true ) ) {
			$suburb = explode( "-", get_post_meta( $order, '_shipping_suburb', true ) );
			wc_add_order_item_meta( $key, 'Delivery Suburb', $suburb[1] );
		}
	}
}

add_action( 'woocommerce_thankyou', 'ds_add_suburb_order_meta' );


/* Delivery Suburbs on Product Page == Start == */


if ( get_option( 'ds_show_suburb_field' ) == 'product' ) {
	add_filter( 'woocommerce_checkout_fields', 'ds_unset_postcode_fields', 30, 1 );
	add_action( 'woocommerce_before_add_to_cart_form', 'ds_add_suburb_field', 30 );
	add_action( 'woocommerce_before_add_to_cart_button', 'ds_hidden_suburb_field' );
	add_action( 'woocommerce_add_to_cart_validation', 'ds_delivery_suburb_validation', 10, 3 );
	add_action( 'woocommerce_before_cart_table', 'checking_values' );
}

/*
 * The following hook will add a select field right above the "add to cart button"
 * will be used for getting suburbs
 */
function ds_add_suburb_field() { ?>
    <table class="variations ds_delivery_suburb" cellspacing="0">
        <tbody>
        <tr>
            <td class="label"><label for="color"><?php _e( 'Delivery Suburb', 'woo-delivery-suburbs' ) ?></label></td>
            <td class="value">
                <select name="ds_delivery_suburbs" class="ds_shipping_suburb_select">
                    <option value="" selected disabled><?php _e( 'Select Suburb', 'woo-delivery-suburbs' ) ?></option>
					<?php $suburbs = ds_get_delivery_suburbs();
					foreach ( $suburbs as $key => $value ) : ?>
                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
					<?php endforeach; ?>

                </select>
            </td>
        </tr>
        </tbody>

    </table>
	<?php
}


function ds_delivery_suburb_validation() {
	if ( empty( $_REQUEST['ds_delivery_suburb_hidden_val'] ) ) {
		wc_add_notice( __( 'Please select a delivery suburb.', 'woo-delivery-suburbs' ), 'error' );

		return false;
	}

	return true;
}

function ds_save_suburb_on_cart_field( $cart_item_data, $product_id ) {
	if ( isset( $_REQUEST['ds_delivery_suburb_hidden_val'] ) ) {
		$cart_item_data['ds_delivery_suburb_hidden_val'] = $_REQUEST['ds_delivery_suburb_hidden_val'];
		/* below statement make sure every add to cart action as unique line item */
		$cart_item_data['unique_key'] = md5( microtime() . rand() );
	}

	return $cart_item_data;
}

add_action( 'woocommerce_add_cart_item_data', 'ds_save_suburb_on_cart_field', 10, 2 );

function ds_render_suburb_on_cart_and_checkout( $cart_data, $cart_item = null ) {
	$custom_items = array();
	/* Woo 2.4.2 updates */
	if ( ! empty( $cart_data ) ) {
		$custom_items = $cart_data;
	}
	if ( isset( $cart_item['ds_delivery_suburb_hidden_val'] ) ) {
		$suburb         = explode( '-', $cart_item['ds_delivery_suburb_hidden_val'] );
		$custom_items[] = array( "name" => 'Delivery Suburb', "value" => $suburb[1] );
	}

	return $custom_items;
}

add_filter( 'woocommerce_get_item_data', 'ds_render_suburb_on_cart_and_checkout', 10, 2 );

function ds_order_suburb_handler( $item_id, $values, $cart_item_key ) {
	if ( isset( $values['ds_delivery_suburb_hidden_val'] ) ) {
		$suburb = explode( '-', $values['ds_delivery_suburb_hidden_val'] );
		wc_add_order_item_meta( $item_id, "Delivery Suburb", $suburb[1] );
		wc_add_order_item_meta( $item_id, "_complete_delivery_suburb", $values['ds_delivery_suburb_hidden_val'] );
	}
}

add_action( 'woocommerce_add_order_item_meta', 'ds_order_suburb_handler', 1, 3 );

function ds_unset_postcode_fields( $fields ) {
	unset( $fields['billing']['billing_postcode'] );
	unset( $fields['shipping']['shipping_city'] );
	$fields['shipping']['delivery_suburb_checkout'] = array(
		'label'   => __( 'Delivery Suburb', 'woocommerce' ),
		'type'    => 'text',
		'class'   => array( 'ds_delivery_suburb_checkout', 'form-row-first' ),
		'name'    => 'delivery_suburb_checkout',
		'default' => ds_suburb_name_from_cart()
	);

	return $fields;
}

function ds_suburb_name_from_cart() {
	global $woocommerce;
	$items = $woocommerce->cart->get_cart();
	foreach ( $items as $item ) {
		$suburb_name = explode( '-', $item['ds_delivery_suburb_hidden_val'] );
	}

	return $suburb_name[1];
}

add_action( 'woocommerce_thankyou', 'ds_add_shipping_suburb_post_meta' );

function ds_add_shipping_suburb_post_meta( $order ) {
	$o = wc_get_order( $order );
	$i = $o->get_items();
	foreach ( $i as $key => $value ) {
		$suburb = explode( '-', wc_get_order_item_meta( $key, '_complete_delivery_suburb', true ) );
		if ( get_post_meta( $order, '_shipping_suburb', true ) && get_post_meta( $order, '_shipping_suburb_postcode', true ) ) {
			update_post_meta( $order, '_shipping_suburb', $suburb[1] );
			update_post_meta( $order, '_shipping_suburb_postcode', $suburb[0] );
		} else {
			add_post_meta( $order, '_shipping_suburb', $suburb[1] );
			add_post_meta( $order, '_shipping_suburb_postcode', $suburb[0] );
		}
	}
}

function ds_hidden_suburb_field() { ?>
    <input type="hidden" name="ds_delivery_suburb_hidden_val" id="ds_delivery_suburb_hidden_val">
	<?php
}

function checking_values() {
	global $woocommerce;
	$items = $woocommerce->cart->get_cart();
	foreach ( $items as $item ) {
		$shipping_suburb_postcode = explode( "-", $item['ds_delivery_suburb_hidden_val'] );
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			update_user_meta( $user_id, 'shipping_postcode', $shipping_suburb_postcode[0] );
		}
		$woocommerce->customer->set_shipping_postcode( $shipping_suburb_postcode[0] );
	}
}

function ds_export_csv() {
	if ( $_POST['export_csv'] ) {
		global $wpdb;
		$table         = $wpdb->prefix . 'woocommerce_delivery_suburbs';
		$query_suburbs = "select * from $table";
		$results       = $wpdb->get_results( $query_suburbs, OBJECT );

		if ( $results ) {
			$output_filename = "delivery_suburbs.csv";
			$output_handle   = @fopen( 'php://output', 'w' );
			ob_end_clean();
			$suburbs_fields   = array();
			$suburbs_fields[] = 'location';
			$suburbs_fields[] = 'delivery_suburb';

			header( "Cache-Control: no-cache, must-revalidate" );
			header( 'Content-Description: File Transfer' );
			header( 'Content-type: text/csv' );
			header( 'Content-Disposition: attachment; filename=' . $output_filename );
			header( 'Expires: 0' );
			header( 'Pragma: no-cache' );

			fputcsv( $output_handle, $suburbs_fields );

			foreach ( $results as $result ) {
				$suburb_csv_input = array(
					'location'        => $result->location,
					'delivery_suburb' => $result->delivery_suburb
				);

				fputcsv( $output_handle, $suburb_csv_input );

			}

			fclose( $output_handle );
			exit();

			return true;

		}

		return false;
	}
}

add_action( 'init', 'ds_export_csv' );

function ds_import_csv() {
	global $wpdb;
	if ( isset( $_POST["import_csv"] ) ) {

		$filename = $_FILES["file"]["tmp_name"];


		if ( $_FILES["file"]["size"] > 0 ) {
			$file         = fopen( $filename, "r" );
			$table_name   = $wpdb->prefix . 'woocommerce_delivery_suburbs';
			$qry_truncate = "TRUNCATE TABLE $table_name";
			$wpdb->query( $qry_truncate );
			$i = 0;
			while ( ( $getData = fgetcsv( $file, 10000, "," ) ) !== false ) {

				if ( $i > 0 ) {

					$sql    = "INSERT into $table_name (location,delivery_suburb)
                   values ('" . $getData[0] . "','" . $getData[1] . "')";
					$result = $wpdb->query( $sql );
				}
				$i++;

			}

			if ($result){ ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e( 'Suburbs Imported', 'woo-delivery-suburbs' ); ?></p>
                </div>
			    <?php
            }

			fclose( $file );
		}
	}
}

add_action( 'init', 'ds_import_csv' );
/* Delivery Suburbs on Product Page == End == */

register_deactivation_hook( __FILE__, 'ds_deactivation' );

function ds_deactivation() {
	delete_option( 'ds_show_suburb_field' );
}