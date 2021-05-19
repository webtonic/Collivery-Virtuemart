<?php
// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );
AdminUIHelper::startAdminArea( $this );
$validation_results = json_decode( $this->order->validation_results );

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );
if ( VmConfig::get( 'usefancy', 0 ) ) {
	vmJsApi::js( 'fancybox/jquery.fancybox-1.3.4.pack' );
	vmJsApi::css( 'jquery.fancybox-1.3.4' );
	$box = "
	//<![CDATA[
		jQuery(document).ready(function($) {
			jQuery('.show_pod').click(function(event) {
				event.preventDefault();
				var id = jQuery(this).attr('rel');
				var con = jQuery('#'+id).html();
				jQuery.fancybox ({ div: '#'+id, content: con });
			});
			jQuery('.show_waybill').click(function(event) {
				event.preventDefault();
				var id = jQuery(this).attr('rel');
				var con = jQuery('#'+id).html();
				jQuery.fancybox ({ div: '#'+id, content: con });
			});
			jQuery('.show_image').click(function(event) {
				event.preventDefault();
				var id = jQuery(this).attr('rel');
				var con = jQuery('#'+id).html();
				jQuery.fancybox ({ div: '#'+id, content: con });
			});
		});
	//]]>
	";
} else {
	vmJsApi::js ( 'facebox' );
	vmJsApi::css ( 'facebox' );
	$box = "
	//<![CDATA[
		jQuery(document).ready(function($) {
			jQuery('.show_pod').click(function(event) {
				event.preventDefault();
				var id = jQuery(this).attr('rel');
				var con = jQuery('#'+id).html();
				jQuery.facebox( { div: '#'+id }, 'my-groovy-style');
			});
			jQuery('.show_waybill').click(function(event) {
				event.preventDefault();
				var id = jQuery(this).attr('rel');
				var con = jQuery('#'+id).html();
				jQuery.facebox( { div: '#'+id }, 'my-groovy-style');
			});
			jQuery('.show_image').click(function(event) {
				event.preventDefault();
				var id = jQuery(this).attr('rel');
				var con = jQuery('#'+id).html();
				jQuery.facebox( { div: '#'+id }, 'my-groovy-style');
			});
		});
	//]]>
	";
}

JHtml::_( 'behavior.formvalidation' );
$document = JFactory::getDocument ();
$document->addScriptDeclaration ( $box );
?>
<div class="parallel">
	<table width="100%">
		<tbody>
			<tr>
				<td width="50%">
					<fieldset class="parallel_target" style="background-color: rgb(246, 246, 246);">
						<legend style="font-size:large; font-weight:bold;">Status Information:</legend>
						<table>

                            <?php

                            $status_date = null;
                            $delivered  = false;
                            $last_update= null;
                            foreach ($this->tracking as $_tracking) {
                                if ($_tracking['status_id'] == $this->tracking_waybill['status_id']) {
                                    $status_date = $this->$_tracking['created_at'];
                                }
                                $last_update = $_tracking['created_at'];
                            }

                            echo '<tr><td>Waybill <a href="javascript:void(0);" rel="wrapped_waybill" class="show_waybill">' . $this->order->waybill . '</a></td></tr>' . '<tr><td>Status: ' . $this->tracking_waybill['status_name'] . '</td></tr>';

                            if (isset($status_date)) {
                                echo '<tr><td>Status last updated:' . date( "H:i:s", strtotime($status_date)) . ' on the ' .date( "d/M/Y", strtotime($status_date)) . '</td></tr>';
                            } else {
                                echo '<tr><td>Status last updated:' . date( "H:i:s", strtotime($last_update)) . ' on the ' .date( "d/M/Y", strtotime($last_update)) . '</td></tr>';
                            }


                            if (isset($this->tracking_waybill['collection_time'])) {

                                $collection_timestamp = $this->tracking_waybill['collection_time'];
                                $collection_datetime = new DateTime("@$collection_timestamp");

                                echo '<tr><td>Collection time ' . $collection_datetime->format('H:i:s') . ' on the ' . $collection_datetime->format('d/m/Y');
                            }
                            if (isset($this->tracking_waybill['delivery_time'])) {

                                $delivery_timestamp = $this->tracking_waybill['delivery_time'];
                                $delivery_datetime = new DateTime("@$delivery_timestamp");

                                echo '<tr><td>Delivered at ' . $delivery_datetime->format('H:i:s') . ' on the ' . $delivery_datetime->format('d/m/Y');
                            } else {
                                if (isset($this->tracking_waybill['eta'])) {
                                    echo '<tr><td>Estimated time of delivery: ' . date("H:i:s", $this->tracking_waybill['eta']) . ' on the ' . date("d/M/Y", $this->tracking_waybill['eta']) . '</td></tr>';
                                } else {
                                    echo '<tr><td>Delivery will be before ' . $this->tracking_waybill['delivery_time'] . ' on the ' . date("d/M/Y", strtotime($this->tracking_waybill['delivery_date'])) . '</td></tr>';
                                }
                            }
							?>
						</table>
						<div id="wrapped_waybill" style="display:none;width:620px;height:500px;">
							<iframe src="<?php echo $this->waybill_file_name;?>" style="width:500px; height:400px;" frameborder="0"></iframe>
						</div>						
					</fieldset>
				</td>
				<td width="50%">
					<fieldset class="parallel_target" style="background-color: rgb(246, 246, 246);">
						<legend style="font-size:large; font-weight:bold;">General Information:</legend>
						<table>
                            <?php echo '<tr><td>Quoted Weight: '.number_format( $this->deliver_info['weight'], 2, '.', '' ).' | Actual Weight: '.number_format( $this->tracking_waybill['weight'], 2, '.', '' ).'</td></tr>';?>
							<?php echo '<tr><td>Quoted Vol Weight: '.number_format( $this->deliver_info['vol_weight'], 2, '.', '' ).' | Actual Vol Weight: '.number_format( $this->tracking_waybill['volumetric_weight'], 2, '.', '' ).'</td></tr>';?>
							<?php echo '<tr><td>Quoted Price: R'.number_format( $this->deliver_info->price->inc_vat, 2, '.', '' ).' | Actual Price: R'.number_format( $this->tracking_waybill['total_price']*1.14, 2, '.', '' ).'</td></tr>';?>

                            <?php if (( $this->pod !== '' ) ):?>
								<tr>
									<td>
										Proof of delivery: <a href="javascript:void(0);" rel="wrapped_pod" class="show_pod">View POD</a>
										<div id="wrapped_pod" style="display:none;width:620px;height:500px;">
											<iframe src="<?php echo $this->pod;?>" style="width:500px; height:400px;" frameborder="0"></iframe>
										</div>
									</td>
								</tr>
							<?php endif;?>
							<?php if ( !empty( $this->image_list ) ):?>
								<tr>
									<td>
										Images (<?php echo count( $this->image_list );?>):
										<?php
											$count=1;
											foreach ( $this->image_list as $image ) {
												echo ' <a href="javascript:void(0);" rel="image_'.$count.'" class="show_image">Image '.$count.'</a><div id="image_'.$count.'" style="display:none;"><img src="'.$image.'"/></div>';
												$count++;
											}
										?>
									</td>
								</tr>
							<?php endif;?>
						</table>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="parallel">
	<table width="100%">
		<tbody>
			<tr>
				<td width="50%">
					<fieldset class="parallel_target" style="background-color: rgb(246, 246, 246);">
						<legend style="font-size:large; font-weight:bold;">Collection Address:</legend>
						<?php if ( isset( $this->collection_address['short_text'] ) && $this->collection_address['short_text'] != "" ) {echo '<p>'.$this->collection_address['short_text'].'</p>';}?>
						<?php
							$collection_count = 1;
							foreach ( $this->collection_contacts as $contact ) {

                                if ( isset( $contact['full_name'] ) && $contact['full_name'] != "" ) {
									if ( $collection_count == 1 ) {
                                        echo '<b>Contacts:</b><br />'.$contact['full_name'];
                                        if ( isset( $contact['cellphone'] ) && $contact['cellphone'] != "" )
                                            echo ', '.$contact['cellphone'];
                                        if ( isset( $contact['email'] ) && $contact['email'] != "" )
                                            echo ', '.$contact['email'];
                                        echo '<br />';

                                    }
									else if ( $collection_count != count( $this->collection_contacts ) ) {
											echo $contact['full_name'];
                                        if ( isset( $contact['cellphone'] ) && $contact['cellphone'] != "" )
                                            echo ', '.$contact['cellphone'];
                                        if ( isset( $contact['email'] ) && $contact['email'] != "" )
                                            echo ', '.$contact['email'];
                                        echo '<br />';
										}

									else {
										echo $contact['full_name'];
                                        if ( isset( $contact['cellphone'] ) && $contact['cellphone'] != "" )
                                            echo ', '.$contact['cellphone'];
                                        if ( isset( $contact['email'] ) && $contact['email'] != "" )
                                            echo ', '.$contact['email'];
									}
								}
								$collection_count++;
							}
						?>
					</fieldset>
				</td>
				<td width="50%">
					<fieldset class="parallel_target" style="background-color: rgb(246, 246, 246);">
						<legend style="font-size:large; font-weight:bold;">Destination Address:</legend>
                        <?php if (isset($this->destination_address['short_text']) && $this->destination_address['short_text'] != "") {
                            echo '<p>' . $this->destination_address['short_text'] . '</p>';
                        } ?>
                        <?php
                        $destination_count = 1;
                        foreach ($this->destination_contacts as $contact) {

                            if (isset($contact['full_name']) && $contact['full_name'] != "") {
                                if ($destination_count == 1) {
                                    echo '<b>Contacts:</b><br />' . $contact['full_name'];
                                    if (isset($contact['cellphone']) && $contact['cellphone'] != "")
                                        echo ', ' . $contact['cellphone'];
                                    if (isset($contact['email']) && $contact['email'] != "")
                                        echo ', ' . $contact['email'];
                                    echo '<br />';
                                } else if ($destination_count != count($this->destination_contacts)) {
                                    echo $contact['full_name'];
                                    if (isset($contact['cellphone']) && $contact['cellphone'] != "")
                                        echo ', ' . $contact['cellphone'];
                                    if (isset($contact['email']) && $contact['email'] != "")
                                        echo ', ' . $contact['email'];
                                    echo '<br />';
                                } else {
                                    echo $contact['full_name'];
                                    if (isset($contact['cellphone']) && $contact['cellphone'] != "")
                                        echo ', ' . $contact['cellphone'];
                                    if (isset($contact['email']) && $contact['email'] != "")
                                        echo ', ' . $contact['email'];
                                }
                            }
                            $destination_count++;
                        }
                        ?>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<?php AdminUIHelper::endAdminArea(); ?>
