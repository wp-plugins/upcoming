<?php
/*
Plugin Name: Upcoming
Version: 0.1
Plugin URI: http://yoast.com/wordpress/upcoming/
Description: Easily create a list of your upcoming events on your blog
Author: Joost de Valk
Author URI: http://yoast.com/
*/

// Pre-2.6 compatibility
if ( !defined('WP_CONTENT_URL') )
    define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if ( !defined('WP_CONTENT_DIR') )
    define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
 
// Guess the location
$upcomingpluginpath = WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)).'/';

require("xmlfunctions.php");

// Load some defaults
$options['apikey'] 	= "";
$options['topblock'] 	= "\n".'<table class="upcoming">'."\n";
$options['eventblock'] 	= '<tr class="%SPEAKING%"><td colspan="3"><h4><a href="%EVENTURL%">%EVENTNAME%</a></h4></td></tr>'."\n"
						. '<tr><th>Date:</th><td>%EVENTSTARTDATE% %EVENTENDDATE%</td>'
						. '<td><small><a rel="nofollow" href="%UPCOMINGURL%">Check it out on Upcoming</a></small></td></tr>'."\n"
						. '<tr class="%STATE%"><th valign="top">Location:</th><td colspan="2">%VENUELINK%<br/>'
						. '%VENUEADDRESS%<br/>'
						. '%VENUECITY%, %VENUECOUNTRY%</td></tr>'."\n";
												
$options['footerblock'] = '<tr><td colspan="2"><a href="http://upcoming.yahoo.com/user/%USERID%/">'
						. '<img style="float:right" src="%IMGLINK%" alt="powered by Upcoming" title="Powered by Upcoming"/></a>'
						. '</td></tr>'."\n"
						. '</table>'."\n\n";

add_option("UpcomingOptions",$options,'','no');

function upcoming_get_url($url, $cacheid, $cachetime = 86400) {
	// Cache the requests to the DB to not overload the Upcoming API
	$cache = get_option("UpcomingCache");
	if (!isset($cache[$cacheid]) || $cache[$cacheid]['expires'] < time()) {
		$snoopy = new Snoopy;
		$snoopy->fetch($url);
		$newcache 				= array();
		$newcache['xml'] 		= $snoopy->results;
		$newcache['expires'] 	= time() + $cachetime;
		$cache[$cacheid] 		= $newcache;
		update_option("UpcomingCache",$cache);
	}
	return $cache[$cacheid]['xml'];
}

function get_event_info($id) {
	$options  	= get_option("UpcomingOptions");	
	$endpoint 	= "http://upcoming.yahooapis.com/services/rest/?api_key=".$options['apikey'];
	$xml 		= upcoming_get_url($endpoint."&method=event.getInfo&event_id=".$id, "event".$id, 259200);
	$xml2a 		= new XMLToArray();
	$eventinfo 	= $xml2a->parse($xml);
	$eventinfo 	= $eventinfo['_ELEMENTS'][0]['_ELEMENTS'][0];
	return $eventinfo;
}

function show_upcoming($atts, $content = "") {
	global $upcomingpluginpath;

	$options  = get_option("UpcomingOptions");
	if ($options['apikey'] != "") {		
		$endpoint = "http://upcoming.yahooapis.com/services/rest/?api_key=".$options['apikey'];
	} else {
		$content = "Please enter your API key for the Upcoming plugin in the options panel.";
		return $content;
	}

	if (isset($atts['states'])) {
		$states 	= explode(",",$atts['states']);		
	} else {
		$states 	= array('attend');
	}
	
	if (isset($atts['userid'])) {
		$url	= $endpoint."&method=user.getWatchlist&user_id=".$atts['userid'];
		$xml 	= upcoming_get_url($url, "user".$atts['userid']);
	} else {
		exit;
	}
				
	$xml2a 		= new XMLToArray(); 
	$eventsary 	= $xml2a->parse($xml);
	$events 	= $eventsary["_ELEMENTS"]["0"]["_ELEMENTS"];
	
	$content = $options['topblock'];
	
	foreach($events as $event) {
		if (in_array($event['status'], $states)) {
			$eventinfo = get_event_info($event['id']);
			$tags = explode(",",$eventinfo['tags']);
			
			// Are you speaking / performing?
			$state = $event['status'];
			if (in_array("speaker".$event['username'],$tags)) {
				$state = "speaking";
			}

			// Construct Venue URL
			if (isset($eventinfo['venue_url']) && $eventinfo['venue_url'] != "") {
				$venuelink = '<a rel="nofollow" href="'.$eventinfo['venue_url'].'">'.$event['venue_name'].'</a>';
			} else {
				$venuelink = $event['venue_name'];
			}

			$eb = $options['eventblock'];
			
			$eb = str_replace('%STATE%',$speaking,$eb);
			$eb = str_replace('%UPCOMINGURL%','http://upcoming.yahoo.com/event/'.$event['id'],$eb);
			$eb = str_replace('%EVENTURL%',$eventinfo['url'],$eb);
			$eb = str_replace('%EVENTNAME%',$event['name'],$eb);
			$eb = str_replace('%EVENTDESCRIPTION%',$event['description'],$eb);
			$eb = str_replace('%EVENTSTARTDATE%',$event['start_date'],$eb);			
			$eb = str_replace('%VENUELINK%',$venuelink,$eb);
			$eb = str_replace('%VENUEADDRESS%',$event['venue_address'],$eb);
			$eb = str_replace('%VENUECITY%',$event['venue_city'],$eb);
			$eb = str_replace('%VENUECOUNTRY%',$event['venue_country_name'],$eb);

			if ($event['end_date'] != $event['start_date'] && $event['end_date'] != "") {
				$eb = str_replace('%EVENTENDDATE%',"- ".$event['end_date'],$eb);		
			} else {
				$eb = str_replace('%EVENTENDDATE%','',$eb);
			}

			$content .= $eb;
		}
	}

	$footer = str_replace('%USERID%',$atts['userid'],$options['footerblock']);
	$footer = str_replace('%IMGLINK%',$upcomingpluginpath."upcoming_logo2.gif",$footer);
	$content .= $footer;
		
	return $content;
}

if ( ! class_exists( 'Upcoming_Admin' ) ) {

	class Upcoming_Admin {
		
		function add_config_page() {
			global $wpdb;
			if ( function_exists('add_submenu_page') ) {
				add_options_page('Upcoming Configuration', 'Upcoming', 10, basename(__FILE__), array('Upcoming_Admin','config_page'));
			}
		}
		
		function config_page() {			
			// Overwrite defaults with saved settings
			if ( isset($_POST['submit']) ) {
				if (!current_user_can('manage_options')) die(__('You cannot edit the Upcoming options.'));
				check_admin_referer('upcoming-config');

				foreach (array('topblock', 'eventblock', 'footerblock', 'apikey') as $option_name) {
					if (isset($_POST[$option_name])) {
						$options[$option_name] = stripslashes($_POST[$option_name]);
					}
				}

				if (isset($_POST['clearcache'])) {
					update_option("UpcomingCache",array());
				}
				
				update_option('UpcomingOptions', $options);
				if ($options['apikey'] != "") {
					echo "<div id=\"message\" class=\"updated fade\"><p>Settings Updated.</p></div>\n";					
				}
			}
			
			$options = get_option('UpcomingOptions');
			if ($options['apikey'] == "") {
				echo "<div id=\"message\" class=\"error\"><p>Error, please enter your API key, <a href=\"http://upcoming.yahoo.com/services/api/keygen.php\">create one here</a>.</p></div>\n";
			}
			?>
			<div class="wrap">
				<h2>Upcoming options</h2>
				<form action="" method="post" id="upcoming-conf">
					<?php
					if ( function_exists('wp_nonce_field') )
						wp_nonce_field('upcoming-config');
					?>
					<table class="form-table" style="width: 100%;">
						<tr valign="top">
							<th scrope="row" width="10%">
								<label for="apikey"><a href="http://upcoming.yahoo.com/services/api/keygen.php">API Key</a>:</label>
							</th>
							<td width="90%">
								<input type="text" name="apikey" id="apikey" value="<?php echo $options['apikey']; ?>"/>
							</td>
						</tr>
						<tr valign="top">
							<th scrope="row" width="10%">
								<label for="topblock">Top block:</label>
							</th>
							<td width="90%">
								<textarea name="topblock" id="topblock" rows="4" cols="70"><?php echo $options['topblock']; ?></textarea>
							</td>
						</tr>
						<tr valign="top">
							<th scrope="row">
								<label for="eventblock">Event block:</label>
							</th>
							<td>
								<textarea name="eventblock" id="eventblock" rows="10" cols="70"><?php echo $options['eventblock']; ?></textarea>
							</td>
						</tr>
						<tr valign="top">
							<th scrope="row">
								<label for="footerblock">Footer block:</label>
							</th>
							<td>
								<textarea name="footerblock" id="footerblock" rows="6" cols="70"><?php echo $options['footerblock']; ?></textarea>
							</td>
						</tr>
						<tr valign="top">
							<th scrope="row">
								<label for="clearcache">Clear Cache:</label>
							</th>
							<td>
								<input type="checkbox" name="clearcache" id="clearcache"/> Select and press "Update Settings" to clear the cache.
							</td>
						</tr>
					</table>
					<p class="submit"><input type="submit" name="submit" value="Update Settings &raquo;" /></p>
				</form>
			</div>
<?php		}	
	}
}

add_shortcode('upcoming', 'show_upcoming');
add_action('admin_menu', array('Upcoming_Admin','add_config_page'));
?>