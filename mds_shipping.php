<?php
/**
 * Shipment plugin for general, rules-based shipments, like regular postal services with complex shipping cost structures
 *
 * @version 2.0.0
 * @package MDS Collivery Shipping Module
 * @subpackage Plugins - shipment
 * @copyright Copyright (C) 2014 MDS Collivery - All rights reserved.
 * @license GNU/GPL version 3 or later: http://www.gnu.org/copyleft/gpl.html
 * @author MDS Collivery
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

if ( ! class_exists( 'vmPSPlugin' ) ) {
	require JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
}

if ( ! class_exists( 'plgVmShipmentRules_Shipping_Base' ) ) {
	require dirname( __FILE__ ).DS.'mds_shipping_base.php';
}

/**
 * MDS Shipping
 */
class plgVmShipmentMds_Shipping extends plgVmShipmentMds_Shipping_Base {
	function __construct( & $subject, $config )
	{
		parent::__construct ( $subject, $config );
	}

}
