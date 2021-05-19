<?php

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

// Load the view framework
jimport( 'joomla.application.component.view' );

/**
 * Json View class
 */
class VirtuemartViewMds extends JViewLegacy {
	var $db;
	var $towns;
	var $suburbs;
	var $services;
	var $location_types;
	var $extension_id;
	var $app_name;
	var $app_info;
	var $collivery;
	var $risk_cover;
	var $username;
	var $password;
	
	function __construct()
	{
		parent::__construct();

		// Load the database and execute our sql file
		$this->db = JFactory::getDBO();

		// Get information of our plugin so we can pass it on to MDS Collivery for Logs
		$sel_query = "SELECT * FROM `#__extensions` where type = 'plugin' and element = 'mds_shipping' and folder = 'vmshipment';";
		$this->db->setQuery( $sel_query );
		$this->db->query();
		$this->extension_id = $this->db->loadObjectList()[0]->extension_id;
		$this->app_name = $this->db->loadObjectList()[0]->extension_id;
		$this->app_info = json_decode( $this->db->loadObjectList()[0]->manifest_cache );

		// Get our login information
		$config_query = "SELECT * FROM `#__mds_collivery_config` where id = 1;";
		$this->db->setQuery( $config_query );
		$this->db->query();
		$this->password = $this->db->loadObjectList()[0]->password;
		$this->username = $this->db->loadObjectList()[0]->username;
		$this->risk_cover = $this->db->loadObjectList()[0]->risk_cover;

		$version = new JVersion();
		require_once preg_replace( '|com_installer|i', "", JPATH_COMPONENT_ADMINISTRATOR ).'/helpers/config.php';

		$config = [
			'app_name'      => $this->app_info->name, // Application Name
			'app_version'   => $this->app_info->version, // Application Version
			'app_host'      => "Joomla: ".$version->getShortVersion().' - Virtuemart: '.VmConfig::getInstalledVersion(), // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / ''
			'app_url'       => JURI::base(), // URL your site is hosted on
			'user_email'    => $this->username,
			'user_password' => $this->password
		];

		// Use the MDS API Files
		require_once JPATH_PLUGINS . '/vmshipment/mds_shipping/Mds/Cache.php';
		require_once JPATH_PLUGINS . '/vmshipment/mds_shipping/Mds/Collivery.php';
		$this->collivery = new Mds\Collivery( $config );

		// Get some information from the API
		$this->towns = $this->collivery->make_key_value_array($this->collivery->getTowns());
		$this->services = $this->collivery->make_key_value_array($this->collivery->getServices(), 'id', 'text');
		$this->location_types = $this->collivery->make_key_value_array($this->collivery->getLocationTypes());
		$this->suburbs = $this->collivery->make_key_value_array($this->collivery->getSuburbs());
	}

	function display( $tpl = null )
	{
		$curTask = JRequest::getWord( 'task' );

		if ( $curTask == 'get_quote' ) {
			$services = $this->collivery->make_key_value_array($this->collivery->getServices(), 'id', 'text');
			$post = JRequest::get( 'post' );

			// Now lets get the price for
			$data = [
				"num_package" => count( $post['parcels'] ),
				"services" => [$post['service']],
				"parcels" => $post['parcels'],
				"exclude_weekend" => 1,
				'risk_cover' => $post['cover']
			];

			// Check which collection address we using
			if ( $post['which_collection_address'] == 'default' ) {
				$data['collection_town'] = $post['collection_town'];
				$data['collection_location_type'] = $post['collection_location_type'];
			} else {
				$data['collection_address'] = $post['collivery_from'];
				$data['contact_from'] = $post['contact_from'];
			}

			// Check which destination address we using
			if ( $post['which_destination_address'] == 'default' ) {
				$data['delivery_town'] = $post['destination_town'];
				$data['delivery_location_type'] = $post['destination_location_type'];
			} else {
				$data['delivery_address'] = $post['collivery_to'];
				$data['contact_to'] = $post['contact_to'];
			}

			$response = $this->collivery->getPrice( $data );

			if ( !isset( $response['data'] ) ) {
				echo '<p class="mds_response">'.implode( ", ", $this->collivery->getErrors() ).'</p>';
				die();
			} else {
				$form = "";
				$form .= '<p class="mds_response"><b>Service: </b>'.$services[$response['data'][0]['service_type']].' - Price incl: R'.$response['data'][0]['total'].'</p>';
				echo $form;
				die();
			}
		}
		elseif ( $curTask == 'update' ) {
			// Get South Africa ID
			$sel_query = "SELECT * FROM `#__virtuemart_countries` WHERE `country_name`='South Africa';";
			$this->db->setQuery( $sel_query );
			$this->db->query();
			$virtuemart_country_id = $this->db->loadObjectList()[0]->virtuemart_country_id;
			// Go through all towns and check if the town is there
			$town_count = 0;
			$town_sql = "";

			$db_towns_query = "SELECT `virtuemart_state_id`, `state_name` FROM `#__virtuemart_states` WHERE `virtuemart_country_id`=".$virtuemart_country_id.";";
			$this->db->setQuery( $db_towns_query );
			$this->db->query();
			$result = json_decode(json_encode($this->db->loadObjectList()), true);
			$array_result = $this->collivery->make_key_value_array($result, 'virtuemart_state_id', 'state_name');
			$differences = array_diff($this->towns, $array_result);

			foreach ( $differences as $towns_key => $towns_value ) {
			// Insert our missing town
				if ($town_count > 0) {
					$town_sql .= ", (".$virtuemart_country_id.", '" . addslashes( $towns_value ) . "')";
				} else {
					$town_sql .= "(".$virtuemart_country_id.", '" . addslashes( $towns_value ) . "')";
				}
				$town_count++;
			}

			if ($town_count > 0) {
				$states_insert_query = "INSERT INTO `#__virtuemart_states` (`virtuemart_country_id`, `state_name`) VALUES " . $town_sql . ";";
				$this->db->setQuery( $states_insert_query );
				$this->db->query();
			}

			// Get Userfield ID
			$sel_query = "SELECT * FROM `#__virtuemart_userfields` WHERE `name`='mds_location_type';";
			$this->db->setQuery( $sel_query );
			$this->db->query();
			$virtuemart_userfield_id = $this->db->loadObjectList()[0]->virtuemart_userfield_id;

			// Go through all location types and check if its there or not.
			$location_sql = "";
			$location_count = 0;
			foreach ( $this->location_types as $key => $value ) {
				$del_query = "SELECT * FROM `#__virtuemart_userfield_values` WHERE `fieldtitle`='".$value."';";
				$this->db->setQuery( $del_query );
				$this->db->query();
				$result = $this->db->loadObjectList();

				if ( !isset( $result[0] ) ) {
					// Insert our location type
					if ($location_count > 0) {
						$location_sql .= ", ('" . $virtuemart_userfield_id . "', '" . addslashes( $value ) . "', '" . $key . "')";
					} else {
						$location_sql .= "('" . $virtuemart_userfield_id . "', '" . addslashes( $value ) . "', '" . $key . "')";
					}
					$location_count++;
				}
			}

			if ($location_count > 0) {
				$inser_value_query = "INSERT INTO `#__virtuemart_userfield_values` (`virtuemart_userfield_id`, `fieldtitle`, `fieldvalue`) VALUES " . $location_sql . ";";
				$this->db->setQuery( $inser_value_query );
				$this->db->query();
			}

			// Go through all services and check if its there or not
			$service_count = 0;
			foreach ( $this->services as $service_key => $service_value ) {
				$del_query = "SELECT * FROM `#__virtuemart_shipmentmethods_en_gb` WHERE `slug`=".$service_key.";";
				$this->db->setQuery( $del_query );
				$this->db->query();
				$result = $this->db->loadObjectList();
				if ( !isset( $result[0] ) ) {
					// Insert our service
					$service_insert = "(".$this->extension_id.", 'mds_shipping', 'markup=10');";

					// Insert all Services
					$states_insert_query = "INSERT INTO `#__virtuemart_shipmentmethods` (`shipment_jplugin_id`, `shipment_element`, `shipment_params`) VALUES " . $service_insert;
					$this->db->setQuery( $states_insert_query );
					$this->db->query();
					$new_virtuemart_shipmentmethod_id = $this->db->insertid();

					// Insert the descriptions
					$service_description_insert = "(".$new_virtuemart_shipmentmethod_id.", '".addslashes( $service_value )."', '".$service_value."', ".$service_key.")";
					$states_insert_query = "INSERT INTO `#__virtuemart_shipmentmethods_en_gb` (`virtuemart_shipmentmethod_id`, `shipment_name`, `shipment_desc`, `slug`) VALUES " . $service_description_insert;
					$this->db->setQuery( $states_insert_query );
					$this->db->query();
					$service_count++;
				}
			}

			// Get Userfield ID
			$sel_query = "SELECT * FROM `#__virtuemart_userfields` WHERE `name`='mds_suburb_id';";
			$this->db->setQuery( $sel_query );
			$this->db->query();
			$virtuemart_userfield_id = $this->db->loadObjectList()[0]->virtuemart_userfield_id;

			// Get all suburbs
			$all_query = "SELECT * FROM `#__virtuemart_userfield_values` WHERE `virtuemart_userfield_id`=".$virtuemart_userfield_id.";";
			$this->db->setQuery( $all_query );
			$this->db->query();
			$stored_suburbs = $this->db->loadAssocList( 'fieldvalue' );

			// Go through all suburbs and check if its there or not
			$suburb_sql = "";
			$suburb_count = 0;
			foreach ( $this->suburbs as $suburb_key => $suburb_value ) {
				if ( !isset( $stored_suburbs[$suburb_key] ) ) {
					// Insert our suburb
					if ($suburb_count > 0) {
						$suburb_sql .= ", (".$virtuemart_userfield_id.", '".addslashes( $suburb_value )."', ".$suburb_key.")";
					} else {
						$suburb_sql .= "(".$virtuemart_userfield_id.", '".addslashes( $suburb_value )."', ".$suburb_key.")";
					}
					$suburb_count++;
				}
			}

			if ($suburb_count > 0) {
				$suburb_insert_query = "INSERT INTO `#__virtuemart_userfield_values` (`virtuemart_userfield_id`, `fieldtitle`, `fieldvalue`) VALUES " . $suburb_sql . ";";
				$this->db->setQuery( $suburb_insert_query );
				$this->db->query();
			}

			if ( $town_count == 0 && $location_count == 0 && $service_count == 0 && $suburb_count == 0 ) {
				echo '<p class="mds_response">MDS Collivery - Virtuemart Plugin: Already up-to-date</p>';
				die();
			} else {
				$result = ['Tows Updated: '.$town_count, 'Locations Updated: '.$location_count, 'Services Updated: '.$service_count, 'Suburbs Updated: '.$suburb_count ];
				echo '<p class="mds_response">'.implode( ", ", $result ).'</p>';
				die();
			}
		}
		elseif ( $curTask == 'get_suburbs' ) {
			if ( !$suburbs = $this->collivery->make_key_value_array($this->collivery->getSuburbs( array_search( JRequest::getVar( 'town_name' ), $this->collivery->make_key_value_array($this->collivery->getTowns()))))) {
				echo '<option value="0">Error retrieving suburbs. Try again.</option>';
			} else {
				$options = "";
				foreach ( $suburbs as $key => $suburb ) {
					if ( $key != "" && $suburb != "" ) {
						$options .= '<option value="'.$key.'">'.$suburb.'</option>';
					}
				}
				echo $options;
			}
			die();
		}
		elseif ( $curTask == 'get_contacts' ) {
			if ( !$contacts = $this->collivery->make_key_value_array($this->collivery->getContacts( JRequest::getVar( 'address_id' ) ), 'id', 'full_name') ) {
				echo '<option value="0">Error retrieving contacts. Try again.</option>';
			} else {
				$options = "";
				foreach ( $contacts as $contacts_key => $contact_value ) {
					$options .= '<option value="'.$contacts_key.'">'.$contact_value.'</option>';
				}
				echo $options;
			}
			die();
		}
		else {
			$post = JRequest::get( 'post' );
			// Check which collection address we using and if we need to add the address to collivery api
			if ( $post['which_collection_address'] == 'default' ) {
				$collection_address = [
					'company_name' => ( $post['collection_company_name'] != "" ) ? $post['collection_company_name'] : 'Private',
					'building' => $post['collection_building_details'],
					'street' => $post['collection_street'],
					'location_type' => $post['collection_location_type'],
					'suburb_id' => $post['collection_suburb'],
					'building' => $post['collection_building_details'],
					'suburb_id' => $post['collection_suburb'],
					'town_id' => $post['collection_town'],
                    'contact' => ['full_name' => $post['collection_full_name'],
                        'work_phone' => $post['collection_phone'],
                        'cellphone' => $post['collection_cellphone'],
                        'email_address' => $post['collection_email']]
				];

				// Check for any problems
				if ( !$collection_address_response = $this->collivery->addAddress( $collection_address ) ) {
					echo '<p class="mds_response">'.implode( ", ", $this->collivery->getErrors() ).'</p>';
					die();
				} else {
					// set the collection address and contact from the returned array
					$collivery_from = $collection_address_response['id'];
					$contact_from = $collection_address_response['contacts'][0]['id'];
				}
			} else {
				$collivery_from = $post['collivery_from'];
				$contact_from = $post['contact_from'];
			}
			// Check which destination address we using and if we need to add the address to collivery api
			if ( $post['which_destination_address'] == 'default' ) {
				$destination_address = [
					'company_name' => ( $post['destination_company_name'] != "" ) ? $post['destination_company_name'] : 'Private',
					'building' => $post['destination_building_details'],
					'street' => $post['destination_street'],
					'location_type' => $post['destination_location_type'],
					'suburb_id' => $post['destination_suburb'],
					'building' => $post['destination_building_details'],
					'suburb_id' => $post['destination_suburb'],
					'town_id' => $post['destination_town'],
                    'contact' => ['full_name' => $post['destination_full_name'],
                        'work_phone' => $post['destination_phone'],
                        'cellphone' => $post['destination_cellphone'],
                        'email_address' => $post['destination_email']]
				];

				// Check for any problems
				if ( !$destination_address_response = $this->collivery->addAddress( $destination_address ) ) {
					echo '<p class="mds_response">'.implode( ", ", $this->collivery->getErrors() ).'</p>';
					die();
				} else {
					$collivery_to = $destination_address_response['id'];
					$contact_to = $destination_address_response['contacts'][0]["id"];
				}
			} else {
				$collivery_to = $post['collivery_to'];
				$contact_to = $post['contact_to'];
			}

			$data_collivery = [
				'collivery_from' => $collivery_from,
				'contact_from' => $contact_from,
				'collivery_to' => $collivery_to,
				'contact_to' => $contact_to,
				'collivery_type' => 2, // Package
				'service' => $post['service'],
				'cover' => $post['cover'],
				'collection_time' => $post['collection_time'],
				'parcel_count' => count( $post['parcels'] ),
				'parcels' => $post['parcels']
			];

		/*	// Check for any problems validating
			if ( !$validated = $this->collivery->validate( $data_collivery ) ) {
				echo '<p class="mds_response">'.implode( ", ", $this->collivery->getErrors() ).'</p>';
				die();
			} else {*/
				// Check for any problems adding
				if ( !$collivery_id = $this->collivery->addCollivery( $data_collivery ) ) {
					echo '<p class="mds_response">'.implode( ", ", $this->collivery->getErrors() ).'</p>';
					die();
				} else {
					// Check for any problems accepting
					if ( !$this->collivery->acceptCollivery( $collivery_id['data']['id']) ) {
						echo '<p class="mds_response">'.implode( ", ", $this->collivery->getErrors() ).'</p>';
						die();
					} else {
						// Save the results from validation into our table
						// Still beta version not complete yet..
						$validated = json_encode( $data_collivery );
						$insert_query = "INSERT INTO `#__mds_collivery_processed` (`status`, `validation_results`, `waybill`) VALUES (1, '".$validated."', ".$collivery_id['data']['id'].");";
						$this->db->setQuery( $insert_query );
						$this->db->query();
					}
				}
/*			}*/

			// Update the order history
			$orderModel = VmModel::getModel( 'orders' );
			$comment = 'Tracking Number: '.$collivery_id['data']['id'];
			$orderModel->_updateOrderHist( $post['virtuemart_order_id'], 'C', 0, $comment );

			// Update the order status
			$sel_query = "UPDATE `#__virtuemart_orders` SET `order_status`='C' where `virtuemart_order_id`=".$post['virtuemart_order_id'].";";
			$this->db->setQuery( $sel_query );
			$this->db->query();

			$sel_query = "UPDATE `#__virtuemart_order_items` SET `order_status`='C' where `virtuemart_order_id`=".$post['virtuemart_order_id'].";";
			$this->db->setQuery( $sel_query );
			$this->db->query();

			echo 'redirect|'.$post['virtuemart_order_id'];
			die();
		}
	}
	// Used to search through suburbs
	function recursive_array_search( $needle, $haystack )
	{
		foreach ( $haystack as $key => $value ) {
			$current_key = $key;
			if ( $needle === $value or ( is_array( $value ) && $this->recursive_array_search( $needle, $value ) !== false ) ) {
				return $current_key;
			}
		}
		return false;
	}
}
