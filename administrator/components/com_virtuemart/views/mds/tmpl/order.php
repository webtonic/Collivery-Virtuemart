<?php
// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );
AdminUIHelper::startAdminArea( $this );

// Get the plugins
JPluginHelper::importPlugin( 'vmpayment' );
JPluginHelper::importPlugin( 'vmshopper' );
JPluginHelper::importPlugin( 'vmshipment' );

$selected_town = array_search( $this->state_name, $this->towns );
$addresses = $this->addresses;
unset( $addresses[$this->default_address_id] );
list( $first_default_contact ) = array_keys( $this->default_contacts );
$services = $this->mds_services;
unset( $services[$this->mds_service] );
?>

<p><b>Please Note:</b> if you make changes and accept, those changes will be sent to MDS Collivery as a collection and delivery request, make sure your information is correct. If you have managed to pass incorrect information then you can log onto <a href="https://quote.collivery.co.za/login.php" target="blank">MDS Collivery</a> to cancel or make changes.</p>
<form accept-charset="UTF-8" action="" method="post" id="api_quote">
	<input type="hidden" value="<?php echo $this->orderst->virtuemart_order_id; ?>" name="virtuemart_order_id"/>
	<div class="parallel">
		<table width="100%">
			<tr>
				<td style="width:33.33333333333333%; overflow: scroll;">
					<fieldset class="parallel_target">
						<legend>Collection Details:</legend>
						<label for="which_collection_address">Which Collection Address:</label>
						(&nbsp;&nbsp;New&nbsp;&nbsp;<input class="which_collection_address" id="which_collection_address" name="which_collection_address" type="radio" value="default">
						|&nbsp;&nbsp;Saved&nbsp;&nbsp;<input checked="checked" class="which_collection_address" id="which_collection_address" name="which_collection_address" type="radio" value="saved"> )
						<div id="which_collection_hide_default" style="display:none;">
							<label for="collection_town">Town</label>
							<select id="collection_town" name="collection_town">
								<?php foreach ( $this->towns as $default_towns_key => $default_towns_value ): ?>
									<option value="<?php echo $default_towns_key; ?>"><?php echo $default_towns_value; ?></option>
								<?php endforeach; ?>
							</select>
							<br />
							<div id="populate_collection_suburb">
								<label for="collection_suburb">Suburb</label>
								<select id="collection_suburb" name="collection_suburb">
									<option value=""></option>
								</select>
							</div>
							<br />
							<label for="collection_which_company">Private/Corprate</label>
							( Private <input checked="checked" class="collection_which_company" id="collection_which_company" name="collection_which_company" type="radio" value="private">
							Company <input class="collection_which_company" id="collection_which_company" name="collection_which_company" type="radio" value="company"> )
							<div id="collection_hide_company" style="display:none">
								<label for="collection_company_name">Company</label> <input id="collection_company_name" name="collection_company_name" size="30" type="text" value="<?php echo $default_address['company_name']; ?>">
							</div>
							<br />
							<label for="collection_location_type">Location Type</label>
							<select id="collection_location_type" name="collection_location_type">
								<?php foreach ( $this->location_types as $default_location_key => $default_location_value ): ?>
									<option value="<?php echo $default_location_key; ?>"><?php echo $default_location_value; ?></option>
								<?php endforeach; ?>
							</select>
							<br />
							<label for="collection_building_details">Building Details</label>
							<input id="collection_building_details" name="collection_building_details" size="30" type="text"/>
							<br />
							<label for="collection_street">Street</label>
							<input id="collection_street" name="collection_street" size="30" type="text"/>
							<br />
							<label for="collection_full_name">Contact Person</label>
							<input id="collection_full_name" name="collection_full_name" size="30" type="text"/>
							<br />
							<label for="collection_phone">Landline</label>
							<input id="collection_phone" name="collection_phone" size="30" type="text"/>
							<br />
							<label for="collection_cellphone">Cell Phone</label>
							<input id="collection_cellphone" name="collection_cellphone" size="30" type="text"/>
							<br />
							<label for="collection_email">Email</label>
							<input id="collection_email" name="collection_email" size="30" type="text"/>
						</div>
						<div id="which_collection_hide_saved">
							<label for="collivery_from">Address:</label><br />
							<select name="collivery_from" id="collivery_from">
								<option value="<?php echo $this->default_address_id; ?>" selected="selected"><?php echo $this->addresses[$this->default_address_id]; ?></option>
								<?php foreach ( $addresses as $address_key=>$address_value ):?>
								<option value="<?php echo $address_key;?>"><?php echo $address_value;?></option>
								<?php endforeach;?>
							</select>
							<br />
							<label for="contact_from">Contact:</label><br />
							<select name="contact_from" id="contact_from">
								<option value="<?php echo $first_default_contact; ?>" selected="selected"><?php echo $this->default_contacts[$first_default_contact]['nice_contact']; ?></option>
							</select>
						</div>
					</fieldset>
				</td>
				<td style="width:33.33333333333333%; overflow: scroll;">
					<fieldset class="parallel_target">
						<legend>Parcel's / Instructions / Service:</legend>
						<label for="service">Service</label>
						<select id="service" name="service">
							<option value="<?php echo $this->mds_service; ?>" selected="selected"><?php if(isset($this->mds_services[$this->mds_service])) { echo $this->mds_services[$this->mds_service]; }?></option>
							<?php foreach ( $services as $services_key => $services_value ): ?>
								<option value="<?php echo $services_key; ?>"><?php echo $services_value; ?></option>
							<?php endforeach; ?>
						</select>
						<br />
						<label for="cover">Insurance Cover</label>
						(&nbsp;&nbsp;Up to R5000&nbsp;&nbsp;<input id="cover" name="cover" type="radio" value="1" <?php if($this->risk_cover == 1) {echo 'checked="checked" ';}?> >
						|&nbsp;&nbsp;No Cover&nbsp;&nbsp;<input id="cover" name="cover" type="radio" value="0" <?php if($this->risk_cover == 0) {echo 'checked="checked" ';}?> > )
						<br />
						<label for="service">Collection Time:</label>
						<input type="text" name="collection_time" id="datetimepicker4" value="<?php echo date( "Y-m-d H:m", strtotime( 'now + 1 hour' ) );?>"/><input id="open" type="button" value="open"/><input id="close" type="button" value="close"/><input id="reset" type="button" value="reset"/>
<!--						<input id="collection_time" name="collection_time" type="text" >-->
						<hr />
						<label for="parcels">Parcel(s)</label>
						<!-- This is here only so that we can clone it when trying to create a new itemized -->
						<div style="display:none">
							<table class="itemized_package_node">
								<thead>
									<tr>
										<th align="left">Length</th>
										<th align="left">Width</th>
										<th align="left">Height</th>
										<th align="left">Weight</th>
										<th align="left">&nbsp;</th>
									</tr>
								</thead>
								<tbody id="package_area">
									<tr class="package_row">
										<td><input id="length" name="length" size="11" type="text" value=""></td>
										<td><input id="width" name="width" size="11" type="text" value=""></td>
										<td><input id="height" name="height" size="11" type="text" value=""></td>
										<td><input id="weight" name="weight" size="11" type="text" value=""></td>
										<td><a href="#">Remove</a></td>
									</tr>
								</tbody>
							</table>
						</div>
						<table class="package_items">
							<thead>
								<tr>
									<th align="left">Length</th>
									<th align="left">Width</th>
									<th align="left">Height</th>
									<th align="left">Weight</th>
									<th align="left">&nbsp;</th>
								</tr>
							</thead>
							<tbody id="package_area">
								<?php $count = 1;?>
								<?php foreach ( $this->package_info['parcels'] as $parcel ): ?>
									<tr class="package_row" id="item<?php echo $count;?>">
										<td><input id="parcels[<?php echo $count;?>][length]" name="parcels[<?php echo $count;?>][length]" size="11" type="text" value="<?php echo $parcel['length']; ?>"></td>
										<td><input id="parcels[<?php echo $count;?>][width]" name="parcels[<?php echo $count;?>][width]" size="11" type="text" value="<?php echo $parcel['width']; ?>"></td>
										<td><input id="parcels[<?php echo $count;?>][height]" name="parcels[<?php echo $count;?>][height]" size="11" type="text" value="<?php echo $parcel['height']; ?>"></td>
										<td><input id="parcels[<?php echo $count;?>][weight]" name="parcels[<?php echo $count;?>][weight]" size="11" type="text" value="<?php echo $parcel['weight']; ?>"></td>
										<?php if ( $count > 1 ):?>
										<td><a href="#" onclick="remove_parcel(<?php echo $count;?>); return false;">Remove</a></td>
										<?php endif;?>
									</tr>
								<?php $count++;?>
								<?php endforeach; ?>
							</tbody>
						</table><a href="#" id="create_fields" onclick="return false;">Add Package</a>
						<hr />
						<label for="instructions">Instructions:</label>
						<textarea cols="50" name="instructions" rows="7"></textarea>
					</fieldset>
				</td>
				<td style="width:33.33333333333333%; overflow: scroll;">
					<fieldset class="parallel_target">
						<legend>Destination Details:</legend>
						<label for="which_destination_address">Which Address:</label>
						(&nbsp;&nbsp;New&nbsp;&nbsp;<input checked="checked" class="which_destination_address" id="which_destination_address" name="which_destination_address" type="radio" value="default">
						|&nbsp;&nbsp;Saved&nbsp;&nbsp;<input class="which_destination_address" id="which_destination_address" name="which_destination_address" type="radio" value="saved"> )
						<div id="which_destination_hide_default">
							<label for="destination_town">Town</label>
							<select id="destination_town" name="destination_town">
								<option value="<?php echo $selected_town; ?>" selected="selected"><?php echo $this->state_name; ?></option>
								<?php foreach ( $this->destination_towns as $towns_key => $towns_value ): ?>
									<option value="<?php echo $towns_key; ?>"><?php echo $towns_value; ?></option>
								<?php endforeach; ?>
							</select>
							<br />
							<div id="populate_destination_suburb">
								<label for="destination_suburb">Suburb</label>
								<select id="destination_suburb" name="destination_suburb">
									<option value="<?php echo $this->destination_suburb_key; ?>" selected="selected"><?php echo $this->first_destination_suburb; ?></option>
									<?php foreach ( $this->destination_suburbs as $suburb_key => $suburb_value ): ?>
										<option value="<?php echo $suburb_key; ?>"><?php echo $suburb_value; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<br />
							<label for="destination_which_company">Private/Corprate</label>
							( Private <input checked="checked" class="destination_which_company" id="destination_which_company" name="destination_which_company" type="radio" value="private">
							Company <input class="destination_which_company" id="destination_which_company" name="destination_which_company" type="radio" value="company"> )
							<div id="destination_hide_company" <?php if ( $this->orderst->company == "" ) {echo 'style="display:none"';}?>>
								<label for="destination_company_name">Company</label>
								<input id="destination_company_name" name="destination_company_name" size="30" type="text" value="<?php echo $this->orderst->company;?>">
							</div>
							<br />
							<label for="destination_location_type">Location Type</label>
							<select id="destination_location_type" name="destination_location_type">
								<option value="<?php echo $this->orderst->mds_location_type; ?>" selected="selected"><?php echo $this->first_destination_location_type; ?></option>
								<?php foreach ( $this->destination_location_types as $key => $value ): ?>
									<option value="<?php echo $key; ?>"><?php echo $value; ?></option>
								<?php endforeach; ?>
							</select>
							<br />
							<label for="destination_building_details">Building Details</label>
							<input id="destination_building_details" name="destination_building_details" size="30" type="text" value="<?php echo $this->orderst->mds_building; ?>">
							<br />
							<label for="destination_street">Street</label>
							<input id="destination_street" name="destination_street" size="30" type="text" value="<?php echo $this->orderst->address_1 . ', ' . $this->orderst->address_2; ?>" data-validetta="required">
							<br />
							<label for="destination_full_name">Contact Person</label>
							<input id="destination_full_name" name="destination_full_name" size="30" type="text" value="<?php echo $this->orderst->first_name . ' ' . $this->orderst->last_name; ?>" data-validetta="required">
							<br />
							<label for="destination_phone">Landline</label>
							<input id="destination_phone" name="destination_phone" size="30" type="text" value="<?php echo $this->orderst->phone_1; ?>">
							<br />
							<label for="destination_cellphone">Cell Phone</label>
							<input id="destination_cellphone" name="destination_cellphone" size="30" type="text" value="<?php echo $this->orderst->phone_2; ?>" data-validetta="required">
							<br />
							<label for="destination_email">Email</label>
							<input id="destination_email" name="destination_email" size="30" type="text" value="<?php echo $this->orderst->email; ?>" data-validetta="email">
						</div>
						<div id="which_destination_hide_saved" style="display:none;">
							<label for="collivery_to">Address:</label><br />
							<select name="collivery_to" id="collivery_to">
								<option value="" selected="selected">---Select Below---</option>
								<?php foreach ( $addresses as $address_key=>$address_value ):?>
								<option value="<?php echo $address_key;?>"><?php echo $address_value;?></option>
								<?php endforeach;?>
							</select>
							<br />
							<label for="contact_to">Contact:</label><br />
							<select name="contact_to" id="contact_to">
								<option></option>
							</select>
						</div>
					</fieldset>
				</td>
			</tr>
		</table>
	</div>
	<ul id="top_menu">
		<li><button type="button" id="get_quote">Get Quote</button></li>
		<li><button type="button" id="accept_quote">Accept/Dispatch</button></li>
	</ul>
</form>

<div id="api_results"></div>

<?php
AdminUIHelper::imitateTabs( 'end' );
AdminUIHelper::endAdminArea();
?>
