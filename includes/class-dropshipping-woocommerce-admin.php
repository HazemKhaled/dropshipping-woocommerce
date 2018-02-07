<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package     Knawat_Dropshipping_Woocommerce
 * @subpackage  Knawat_Dropshipping_Woocommerce/admin
 * @copyright   Copyright (c) 2018, Knawat
 * @since       1.0.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The admin-specific functionality of the plugin.
 *
 * @package     Knawat_Dropshipping_Woocommerce
 * @subpackage  Knawat_Dropshipping_Woocommerce/admin
 * @author     Dharmesh Patel <dspatel44@gmail.com>
 */
class Knawat_Dropshipping_Woocommerce_Admin {


	public $adminpage_url;
	
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->adminpage_url = admin_url('admin.php?page=knawat_dropship' );

		add_action( 'admin_menu', array( $this, 'add_menu_pages') );
		add_action( 'after_setup_theme', array( $this, 'knawat_setup_wizard' ) );
		add_filter( 'views_edit-product', array( $this, 'knawat_dropshipwc_add_new_product_filter' ) );
		add_filter( 'views_edit-shop_order', array( $this, 'knawat_dropshipwc_add_new_order_filter' ) );
		add_action( 'load-edit.php', array( $this, 'knawat_dropshipwc_load_custom_knawat_filter' ) );
		add_action( 'load-edit.php', array( $this, 'knawat_dropshipwc_load_custom_knawat_order_filter' ) );
		add_filter( 'admin_footer_text', array( $this, 'add_dropshipping_woocommerce_credit' ) );
	}

	/**
	 * Create the Admin menu and submenu and assign their links to global varibles.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function add_menu_pages() {

		add_menu_page( __( 'Knawat Dropshipping', 'dropshipping-woocommerce' ), __( 'Dropshipping', 'dropshipping-woocommerce' ), 'manage_options', 'knawat_dropship', array( $this, 'admin_page' ), KNAWAT_DROPWC_PLUGIN_URL . 'assets/images/knawat.png', '30' );
	}

	/**
	 * Include require libraries & config for knawat setup wizard.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function knawat_setup_wizard() {
		require_once KNAWAT_DROPWC_PLUGIN_DIR . 'includes/knawat-merlin-config.php';
	}

	/**
	 * Load Admin page.
	 *
	 * @since 1.0
	 * @return void
	 */
	function admin_page() {
		
		?>
		<div class="wrap">
		    <h1><?php esc_html_e( 'Knawat Dropshipping', 'dropshipping-woocommerce' ); ?></h1>
		    <h2><?php esc_html_e( 'Settings', 'dropshipping-woocommerce' ); ?></h2>
		    <?php
		    // Set Default Tab to Import.
		    $tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'settings';
		    ?>
		    <div id="poststuff">
		        <div id="post-body" class="metabox-holder columns-2">

		            <div id="postbox-container-1" class="postbox-container">
		            	<?php //require_once KNAWAT_DROPWC_PLUGIN_DIR . '/templates/admin-sidebar.php'; ?>
		            </div>
		            <div id="postbox-container-2" class="postbox-container">

		                <div class="dropshipping-woocommerce-page">
		                	<?php
		                		require_once KNAWAT_DROPWC_PLUGIN_DIR . '/templates/dropshipping-woocommerce-admin-page.php';
			                ?>
		                	<div style="clear: both"></div>
		                </div>
		        	</div>
		        
		    </div>
		</div>
		<?php
	}

	/**
	 * Add Knawat Orders Filter view at filters
	 *
	 * @since 1.0
	 * @param  array $views Array of filter views
	 * @return array $views Array of filter views
	 */
	function knawat_dropshipwc_add_new_order_filter( $views ){

		global $wpdb;

		$t_order_items = $wpdb->prefix . "woocommerce_order_items";
		$t_order_itemmeta = $wpdb->prefix . "woocommerce_order_itemmeta";

		$count_query = "SELECT COUNT( DISTINCT {$wpdb->posts}.ID ) as count FROM {$wpdb->posts} WHERE 1=1 AND {$wpdb->posts}.post_type = 'shop_order' AND {$wpdb->posts}.ID IN (
				SELECT DISTINCT order_id from {$t_order_items} AS oi
				INNER JOIN {$t_order_itemmeta} as oim ON oi.order_item_id = oim.order_item_id
				WHERE oi.order_item_type = 'line_item'
				AND oim.meta_key = '_product_id'
				AND oim.meta_value IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='dropshipping' AND meta_value='knawat' )
			)";

		$count = $wpdb->get_var( $count_query );

		if( $count > 0 ){
			$class = '';
			if ( isset( $_GET[ 'knawat_orders' ] ) && !empty( $_GET[ 'knawat_orders' ] ) ){
				$class = 'current';
			}

			$views_html = sprintf( "<a class='%s' href='edit.php?post_type=shop_order&knawat_orders=1'>%s</a><span class='count'>(%d)</span>", $class, __('Knawat Orders', 'dropshipping-woocommerce' ), $count );
			$views['knawat'] = $views_html;
		}
		return $views;
	}

	/**
	 * Add `posts_where` filter if knawat orders need to filter
	 *
	 * @since 1.0
	 * @return void
	 */
	function knawat_dropshipwc_load_custom_knawat_order_filter(){
	    global $typenow;
	    if( 'shop_order' != $typenow ){
	        return;
	    }

	    if ( isset( $_GET[ 'knawat_orders' ] ) && !empty( $_GET[ 'knawat_orders' ] ) && trim( $_GET[ 'knawat_orders' ] ) == 1 ){
			add_filter( 'posts_where' , array( $this, 'knawat_dropshipwc_posts_where_knawat_orders') );
	    }
	}

	/**
	 * Add condtion in WHERE statement for filter only knawat orders in orders list table
	 *
	 * @since  1.0
	 * @param  string $where Where condition of SQL statement for orders query
	 * @return string $where Modified Where condition of SQL statement for orders query
	 */
	function knawat_dropshipwc_posts_where_knawat_orders( $where ){
	    global $wpdb;

	    $t_order_items = $wpdb->prefix . "woocommerce_order_items";
		$t_order_itemmeta = $wpdb->prefix . "woocommerce_order_itemmeta";

	    if ( isset( $_GET[ 'knawat_orders' ] ) && !empty( $_GET[ 'knawat_orders' ] ) && trim( $_GET[ 'knawat_orders' ] ) == 1 ){
	        $where .= " AND ID IN (
	        	SELECT DISTINCT order_id from {$t_order_items} AS oi
				INNER JOIN {$t_order_itemmeta} as oim ON oi.order_item_id = oim.order_item_id
				WHERE oi.order_item_type = 'line_item' 
				AND oim.meta_key = '_product_id'
				AND oim.meta_value IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='dropshipping' AND meta_value='knawat' )
	        )";
	    }
	    return $where;	
	}


	/**
	 * Add Knawat Product Filter view at filters
	 *
	 * @since 1.0
	 * @param  array $views Array of filter views
	 * @return array $views Array of filter views
	 */
	function knawat_dropshipwc_add_new_product_filter( $views ){

		global $wpdb;

		$count = $wpdb->query( "SELECT COUNT( DISTINCT p.ID) as count FROM {$wpdb->posts} as p INNER JOIN {$wpdb->postmeta} as pm ON ( p.ID = pm.post_id ) WHERE 1=1 AND ( ( pm.meta_key = 'dropshipping' AND pm.meta_value = 'knawat' ) ) AND p.post_type = 'product' AND ((p.post_status != 'trash') )" );

		if( $count > 0 ){
			$class = '';
			if ( isset( $_GET[ 'knawat_products' ] ) && !empty( $_GET[ 'knawat_products' ] ) ){
				$class = 'current';
			}

			$views_html = sprintf( "<a class='%s' href='edit.php?post_type=product&knawat_products=1'>%s</a><span class='count'>(%d)</span>", $class, __('Knawat Products', 'dropshipping-woocommerce' ), $count );
			$views['knawat'] = $views_html;
		}
		return $views;
	}

	/** 
	 * Add `posts_where` filter if knawat products need to filter
	 *
	 * @since 1.0
	 * @return void
	 */
	function knawat_dropshipwc_load_custom_knawat_filter(){
	    global $typenow;
	    if( 'product' != $typenow ){
	        return;
	    }
	    
	    if ( isset( $_GET[ 'knawat_products' ] ) && !empty( $_GET[ 'knawat_products' ] ) && trim( $_GET[ 'knawat_products' ] ) == 1 ){
	    	add_filter( 'posts_where' , array( $this, 'knawat_dropshipwc_posts_where_knawat_products') );
	    }
	}

	/**
	 * Add condtion in WHERE statement for filter only knawat products in products list table
	 *
	 * @since  1.0
	 * @param  string $where Where condition of SQL statement for products query
	 * @return string $where Modified Where condition of SQL statement for products query
	 */
	function knawat_dropshipwc_posts_where_knawat_products( $where ){
	    global $wpdb;       
	    if ( isset( $_GET[ 'knawat_products' ] ) && !empty( $_GET[ 'knawat_products' ] ) && trim( $_GET[ 'knawat_products' ] ) == 1 ){
	        $where .= " AND ID IN ( SELECT post_id FROM $wpdb->postmeta WHERE meta_key='dropshipping' AND meta_value='knawat' )";
	    }
	    return $where;
	}


	/**
	 * Add Knawat Dropshipping Woocommerce ratting text in wp-admin footer
	 *
	 * @since 1.0
	 * @return void
	 */
	public function add_dropshipping_woocommerce_credit( $footer_text ){
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( $page != '' && $page == 'knawat_dropship' ) {
			$rate_url = 'https://wordpress.org/support/plugin/dropshipping-woocommerce/reviews/?rate=5#new-post';

			$footer_text .= sprintf(
				esc_html__( ' Rate %1$s Dropshipping Woocommerce%2$s %3$s', 'dropshipping-woocommerce' ),
				'<strong>',
				'</strong>',
				'<a href="' . $rate_url . '" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			);
		}
		return $footer_text;
	}

}
