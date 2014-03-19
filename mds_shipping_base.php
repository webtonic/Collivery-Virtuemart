<?php
error_reporting( E_ERROR );
defined( '_JEXEC' ) or die( 'Restricted access' );

if ( ! class_exists( 'vmPSPlugin' ) ) {
	require JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
}
// Only declare the class once...
if ( class_exists( 'plgVmShipmentRules_Shipping_Base' ) ) {
	return;
}

class plgVmShipmentMds_Shipping_Base extends vmPSPlugin
{
	// instance of class
	public static $_this = false;
	protected $cache;
	protected $db;
	protected $towns;
	protected $services;
	protected $location_types;
	protected $extension_id;
	protected $app_name;
	protected $app_info;
	protected $collivery;
	protected $password;
	protected $username;
	protected $converter;
	protected $risk_cover;

	/**
	 * Constructor
	 *
	 * @param JDispatcher $subject The object to observe
	 * @param Array   $config  An array that holds the plugin configuration
	 */
	function __construct( &$subject, $config )
	{
		parent::__construct( $subject, $config );
		$this->_loggable = true;
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$this->tableFields = array_keys( $this->getTableSQLFields () );
		$varsToPush = $this->getVarsToPush();
		$this->setConfigParameterable ( $this->_configTableFieldName, $varsToPush );

		// Load the database and execute our sql file
		$this->db = JFactory::getDBO();

		// Get information of our plugin so we can pass it on to MDS Collivery for Logs
		$sel_query = "SELECT * FROM `#__extensions` where type = 'plugin' and element = 'mds_shipping' and folder = 'vmshipment';";
		$this->db->setQuery( $sel_query );
		$this->db->query();
		$this->extension_id = $this->db->loadObjectList()[0]->extension_id;
		$this->app_name = $this->db->loadObjectList()[0]->extension_id;
		$this->app_info = json_decode( $this->db->loadObjectList()[0]->manifest_cache );

		// Get our config
		$sel_query = "SELECT * FROM `#__mds_collivery_config` where id=1;";
		$this->db->setQuery( $sel_query );
		$this->db->query();
		$this->password = $this->db->loadObjectList()[0]->password;
		$this->username = $this->db->loadObjectList()[0]->username;
		$this->risk_cover = $this->db->loadObjectList()[0]->risk_cover;

		$version = new JVersion();
		require_once preg_replace( '|com_installer|i', "", JPATH_COMPONENT_ADMINISTRATOR ).'/helpers/config.php';

		$config = array(
			'app_name'      => $this->app_info->name, // Application Name
			'app_version'   => $this->app_info->version, // Application Version
			'app_host'      => 'Joomla: '.$version->getShortVersion().' - Virtuemart: '.VmConfig::getInstalledVersion(), // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / ''
			'app_url'       => JURI::base(), // URL your site is hosted on
			'user_email'    => $this->username,
			'user_password' => $this->password
		);

		// Use the MDS API Files
		require_once 'Mds/Cache.php';
		require_once 'Mds/Collivery.php';
		$this->collivery = new Mds\Collivery( $config );

		// Get some information from the API
		$this->towns = $this->collivery->getTowns();
		$this->services = $this->collivery->getServices();
		$this->location_types = $this->collivery->getLocationTypes();

		// Class for converting lengths and weights
		require_once 'UnitConvertor.php';
		$this->converter = new UnitConvertor();
	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 */
	public function getVmPluginCreateTableSQL()
	{

		return $this->createTableSQL ( 'Shipment MDS Table' );
	}

	/**
	 * Fields to create the payment table
	 *
	 * @return string SQL Fileds
	 */
	function getTableSQLFields()
	{

		$SQLfields = array(
			'id'                           => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'          => 'int(11) UNSIGNED',
			'order_number'                 => 'char(32)',
			'virtuemart_shipmentmethod_id' => 'int(11) UNSIGNED',
			'shipment_name'                => 'varchar(5000)',
			'rule_name'                    => 'varchar(500)',
			'order_weight'                 => 'decimal(10,6)',
			'order_products'               => 'int(11)',
			'shipment_cost'                => 'decimal(10,2)',
		);

		return $SQLfields;
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the shipment-specific data.
	 *
	 * @param integer $order_number The order Number
	 * @return mixed Null for shipments that aren't active, text (HTML) otherwise
	 */
	public function plgVmOnShowOrderFEShipment( $virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name )
	{
		$this->onShowOrderFE( $virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name );
	}

	/**
	 * This event is fired after the order has been stored; it gets the shipment method-
	 * specific data.
	 *
	 * @param int     $order_id The order_id being processed
	 * @param object  $cart     the cart
	 * @param array   $order    The actual order saved in the DB
	 * @return mixed Null when this method was not selected, otherwise true
	 */
	function plgVmConfirmedOrder( VirtueMartCart $cart, $order )
	{

		if ( ! ( $method = $this->getVmPluginMethod ( $order['details']['BT']->virtuemart_shipmentmethod_id ) ) ) {
			return NULL; // Another method was selected, do nothing
		}
		if ( ! $this->selectedThisElement ( $method->shipment_element ) ) {
			return false;
		}
		$values['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
		$values['order_number'] = $order['details']['BT']->order_number;
		$values['virtuemart_shipmentmethod_id'] = $order['details']['BT']->virtuemart_shipmentmethod_id;
		$values['shipment_name'] = $this->renderPluginName ( $method );
		$values['rule_name'] = $method->rule_name;
		$values['order_weight'] = $this->getOrderWeight ( $cart, $method->weight_unit );
		$values['order_products'] = $this->getOrderProducts( $cart );
		$values['shipment_weight_unit'] = $method->weight_unit;
		$values['shipment_cost'] = $method->cost;
		$values['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData ( $values );

		return true;
	}

	/**
	 *
	 *
	 * @param VirtueMartCart $cart
	 * @param unknown $method
	 * @param unknown $cart_prices
	 * @return int
	 */
	function getCosts( VirtueMartCart $cart, $method, $cart_prices )
	{
		// If bill to address is not used then lets used ship to
		if ( empty( $cart->BT ) && empty( $cart->ST ) ) {
			return 0;
		}
		elseif ( empty( $cart->ST ) ) {
			$address = $cart->BT;
		}
		else {
			$address = $cart->ST;
		}

		// Get town name from the database
		$shippingModel = VmModel::getModel( 'mds' );
		$state_name = $shippingModel->getState( $address['virtuemart_state_id'] );
		$mds_service = $method->slug;

		$to_town_id = array_search( $state_name, $this->collivery->getTowns() );
		$to_town_type = $address['mds_location_type'];

		if ( $method->free_shipment && $cart_prices['salesPrice'] >= $method->free_shipment ) {
			return 0;
		}
		else {
			$email = $method->username;
			$password = $method->password;
			$service_type = $method->service_type;

			$default_address_id = $this->collivery->getDefaultAddressId();
			$default_address = $this->collivery->getAddress( $default_address_id );
			$default_contacts = $this->collivery->getContacts( $default_address_id );
			$first_contact_id = each( $default_contacts );
			$default_contact_id = $first_contact_id[0];

			if ( isset( $cart->products ) ) {
				$quantity = 1;
				$tot_parcel = 0;
				$total_weight = 0;
				$total_vol_weight = 0;
				foreach ( $cart->products as $product_arr ) {
					// Make sure we passing enough pacels to the API for the quantaties
					while ( $quantity <= $product_arr->quantity ) {
						// Length coversion, mds collivery only acceps CM
						if ( strtolower( $product_arr->product_lwh_uom ) != 'çm' ) {
							$length = $this->converter->convert( $product_arr->product_length,  strtolower( $product_arr->product_lwh_uom ), 'cm', 6 );
							$width = $this->converter->convert( $product_arr->product_width,  strtolower( $product_arr->product_lwh_uom ), 'cm', 6 );
							$height = $this->converter->convert( $product_arr->product_height,  strtolower( $product_arr->product_lwh_uom ), 'cm', 6 );
						}
						else {
							$length = $product_arr->product_length;
							$width = $product_arr->product_width;
							$height = $product_arr->product_height;
						}

						// Weight coversion, mds collivery only acceps KG'S
						if ( strtolower( $product_arr->product_weight_uom ) != 'kg' ) {
							$weight = $this->converter->convert( $product_arr->product_weight,  strtolower( $product_arr->product_weight_uom ), 'kg', 6 );
						}
						else {
							$weight = $product_arr->product_weight;
						}

						$parcels[] = array(
							"length" => str_replace( ",", "", $length ),
							"width" => str_replace( ",", "", $width ),
							"height" => str_replace( ",", "", $height ),
							"weight" => str_replace( ",", "", $weight )
						);

						$tot_parcel += 1;
						$quantity++;
					}

					$total_weight += $product_arr->product_weight * $product_arr->quantity;
					$total_vol_weight += ( ( $product_arr->product_length * $product_arr->quantity ) * ( $product_arr->product_width * $product_arr->quantity ) * ( $product_arr->product_height * $product_arr->quantity ) );
				}
				$total_vweight = $total_vol_weight / 4000;
			}

			// Now lets get the price for
			$data = array(
				"from_town_id" => $default_address['town_id'],
				"from_town_type" => $default_address['location_type'],
				"to_town_id" => $to_town_id,
				"to_town_type" => $to_town_type,
				"num_package" => $tot_parcel,
				"service" => $mds_service,
				"parcels" => $parcels,
				"exclude_weekend" => 1,
				"cover" => $this->risk_cover
			);

			$price = $this->collivery->getPrice( $data );
			if ( $price ) {
				return $price['price']['ex_vat'];
			}
			else {
				return 0;
			}
		}
	}

	/**
	 * Get Parcel Array
	 */
	private function get_cart_content()
	{
		// Reset array to defaults
		$cart = array(
			'parcel_count' => 0,
			'weight' => 0,
			'parcels' => array()
		);

		// Loop through every product in the cart
		$tot_parcel = 0;
		$total_weight = 0;
		$total_vol_weight = 0;
		foreach ( $products as $product_arr ) {
			$parcels[] = array(
				"length" => $product_arr->product_length * $product_arr->quantity,
				"width" => $product_arr->product_width * $product_arr->quantity,
				"height" => $product_arr->product_height * $product_arr->quantity,
				"weight" => $product_arr->product_weight * $product_arr->quantity
			);
			$tot_parcel += 1;
			$total_weight += $product_arr->product_weight * $product_arr->quantity;
			$total_vol_weight += ( ( $product_arr->product_length * $product_arr->quantity ) * ( $product_arr->product_width * $product_arr->quantity ) * ( $product_arr->product_height * $product_arr->quantity ) );
		}
		$total_vweight = $total_vol_weight / 4000;
	}

	/**
	 *
	 *
	 * @param VirtueMartCart $cart
	 * @param int     $method
	 * @param array   $cart_prices
	 * @return bool
	 */
	protected function checkConditions( $cart, $method, $cart_prices )
	{
		return true;
	}

	protected function getOrderProducts( VirtueMartCart $cart )
	{
		/* Cache the value in a static variable and calculate it only once! */
		static $products = 0;
		if ( empty( $products ) and count( $cart->products )>0 ) {
			$products = count( $cart->products );
		}
		return $products;
	}

	/**
	 *
	 *
	 * @param unknown $method
	 */
	function convert( &$method )
	{
		$method->weight_start = (float) $method->weight_start;
		$method->weight_stop = (float) $method->weight_stop;
		$method->orderamount_start = (float) $method->orderamount_start;
		$method->orderamount_stop = (float) $method->orderamount_stop;
		$method->zip_start = (int) $method->zip_start;
		$method->zip_stop = (int) $method->zip_stop;
		$method->nbproducts_start = (int) $method->nbproducts_start;
		$method->nbproducts_stop = (int) $method->nbproducts_stop;
	}

	/**
	 *
	 *
	 * @param unknown $cart
	 * @param unknown $method
	 * @return bool
	 */
	private function _nbproductsCond( $cart, $method )
	{
		$nbproducts = 0;
		foreach ( $cart->products as $product ) {
			$nbproducts += $product->quantity;
		}
		return $nbproducts;
	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 */
	function plgVmOnStoreInstallShipmentPluginTable( $jplugin_id )
	{
		return $this->onStoreInstallPluginTable ( $jplugin_id );
	}

	/**
	 * plg Vm On Select Check Shipment
	 *
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @param VirtueMartCart $cart The actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 */
	public function plgVmOnSelectCheckShipment( VirtueMartCart &$cart )
	{
		return $this->OnSelectCheck( $cart );
	}

	/**
	 * plgVmDisplayListFEShipment
	 * This event is fired to display the plugin methods in the cart (edit shipment/payment) for example
	 *
	 * @param object  $cart     Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean true on success, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 */
	public function plgVmDisplayListFEShipment( VirtueMartCart $cart, $selected = 0, &$htmlIn )
	{
		return $this->displayListFE( $cart, $selected, $htmlIn );
	}

	/**
	 * plgVmonSelectedCalculatePriceShipment
	 *
	 * Calculate the price (value, tax_id) of the selected method
	 * It is called by the calculator
	 * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	 *
	 * @param VirtueMartCart $cart             The Current Cart
	 * @param array   $cart_prices      The New Cart Prices
	 * @param unknown $cart_prices_name
	 * @return bool|null
	 */
	public function plgVmonSelectedCalculatePriceShipment( VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name )
	{
		return $this->onSelectedCalculatePrice( $cart, $cart_prices, $cart_prices_name );
	}

	/**
	 * plgVmOnCheckAutomaticSelectedShipment
	 *
	 * @param VirtueMartCart $cart
	 * @param Array   $cart_prices
	 *
	 * @return null if no plugin was found,
	 *         0 if more then one plugin was found,
	 *         virtuemart_xxx_id if only one plugin is found
	 */
	function plgVmOnCheckAutomaticSelectedShipment( VirtueMartCart $cart, array $cart_prices = array() )
	{
		return $this->onCheckAutomaticSelected( $cart, $cart_prices );
	}

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id            Method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 */
	function plgVmonShowOrderPrint( $order_number, $method_id )
	{
		$result = $this->onShowOrderPrint( $order_number, $method_id );
	}

	/**
	 * plgVmDeclarePluginParamsShipment
	 *
	 * @param unknown $name
	 * @param unknown $id
	 * @param unknown $data
	 * @return bool
	 */
	function plgVmDeclarePluginParamsShipment( $name, $id, &$data )
	{
		return $this->declarePluginParams( 'shipment', $name, $id, $data );
	}

	/**
	 * plgVmSetOnTablePluginParamsShipment
	 *
	 * @param unknown $name
	 * @param unknown $id
	 * @param unknown $table
	 * @return bool
	 */
	function plgVmSetOnTablePluginParamsShipment( $name, $id, &$table )
	{
		return $this->setOnTablePluginParams( $name, $id, $table );
	}
}
