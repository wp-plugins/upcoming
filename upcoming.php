<?php
/*
Plugin Name: Upcoming
Version: 0.4
Plugin URI: http://yoast.com/wordpress/upcoming/
Description: Easily create a list of your upcoming events on your blog. Use the <a href="widgets.php">Widget</a> or include it in a post or page.
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

require_once("xmlfunctions.php");
require_once(ABSPATH . 'wp-includes/class-snoopy.php');

// Load some defaults
$options['apikey'] 	= "";
$options['topblock'] 	= "\n".'<table class="upcoming">'."\n";
$options['eventblock'] 	= '<tr class="%STATE%"><td colspan="3"><h4><a href="%EVENTURL%">%EVENTNAME%</a></h4></td></tr>'."\n"
						. '<tr><th>Date:</th><td>%EVENTSTARTDATE% %EVENTENDDATE%</td>'
						. '<td><small><a rel="nofollow" href="%UPCOMINGURL%">Check it out on Upcoming</a></small></td></tr>'."\n"
						. '<tr><th valign="top">Location:</th><td colspan="2">%VENUELINK%<br/>'
						. '%VENUEADDRESS%<br/>'
						. '%VENUECITY%, %VENUECOUNTRY%</td></tr>'."\n";
												
$options['footerblock'] = '<tr><td colspan="2"><a href="http://upcoming.yahoo.com/user/%USERID%/">'
						. '<img style="float:right" src="%IMGLINK%" alt="powered by Upcoming" title="Powered by Upcoming"/></a>'
						. '</td></tr>'."\n"
						. '</table>'."\n\n";

add_option("UpcomingOptions",$options,'','no');

$options  	= get_option("UpcomingOptions");
$endpoint 	= "http://upcoming.yahooapis.com/services/rest/?api_key=".$options['apikey'];

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

function get_upcoming_events_for_user($userid) { 
	global $endpoint;
	$url		= $endpoint."&method=user.getWatchlist&user_id=".$userid;
	$xml 		= upcoming_get_url($url, "user".$userid);
	$xml2a 		= new XMLToArray(); 
	$eventsary 	= $xml2a->parse($xml);
	$events 	= $eventsary["_ELEMENTS"][0]["_ELEMENTS"];
	return $events;
}

function get_upcoming_events_for_group($groupid) {
	global $endpoint;
	$url		= $endpoint."&method=group.getEvents&group_id=".$groupid;
	$xml 		= upcoming_get_url($url, "group".$groupid);
	$xml2a 		= new XMLToArray(); 
	$eventsary 	= $xml2a->parse($xml);
	$events 	= $eventsary["_ELEMENTS"][0]["_ELEMENTS"];
	return $events;
}

function get_event_info($id) {
	global $endpoint;
	$xml 		= upcoming_get_url($endpoint."&method=event.getInfo&event_id=".$id, "event".$id, 259200);
	$xml2a 		= new XMLToArray();
	$eventinfo 	= $xml2a->parse($xml);
	$eventinfo 	= $eventinfo['_ELEMENTS'][0]['_ELEMENTS'][0];
	return $eventinfo;
}

function get_userid_from_username($username) {
	$url			= $endpoint."&method=user.getInfoByUsername&username=".$username;
	$xml 			= upcoming_get_url($url, "getid".$username, 2592000);
	$xml2a 			= new XMLToArray();
	$userinfo 		= $xml2a->parse($xml);
	return $userinfo['_ELEMENTS'][0]['_ELEMENTS'][0]['id'];
}

function create_event_block($event, $eb) {
	// Pick an event $event and a template $eb and replace all vars with real values.
	$eventinfo 	= get_event_info($event['id']);
	$tags 		= explode(",",$eventinfo['tags']);
	$state 		= $event['status'];

	// Are you speaking / performing?	
	if (in_array("speaker".$event['username'],$tags)) 
		$state = "speaking";

	// Construct Venue URL
	if (isset($eventinfo['venue_url']) && $eventinfo['venue_url'] != "")
		$venuelink = '<a rel="nofollow" href="'.$eventinfo['venue_url'].'">'.$event['venue_name'].'</a>';
	else
		$venuelink = $event['venue_name'];

	if ($event['end_date'] != $event['start_date'] && $event['end_date'] != "")
		$eventenddate = "- ".$event['end_date'];

	$replacables = array(
		'%STATE%' 				=> $state,
		'%UPCOMINGURL%'			=> 'http://upcoming.yahoo.com/event/'.$event['id'],
		'%EVENTURL%'			=> $eventinfo['url'],
		'%EVENTNAME%'			=> $eventinfo['name'],
		'%EVENTDESCRIPTION%'	=> $eventinfo['description'],
		'%EVENTSTARTDATE%'		=> $eventinfo['start_date'],
		'%EVENTENDDATE%'		=> $eventenddate,
		'%VENUELINK%'			=> $venuelink,
		'%VENUEADDRESS%'		=> $event['venue_address'],
		'%VENUECITY%'			=> $event['venue_city'],
		'%VENUECOUNTRY%'		=> $event['venue_country_name']
	);
	
	foreach ($replacables as $var => $rep) {
		if (!isset($rep)) 
			$rep = "";
		$eb = str_replace($var,$rep,$eb);
	}
	return $eb;
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

	if (isset($atts['states']))
		$states 	= explode(",",$atts['states']);		
	else
		$states 	= array('attend');
	
	if (isset($atts['userid'])) {
		$events = get_upcoming_events_for_user($atts['userid']);
		
	} else if (isset($atts['username'])) {
		$userid = get_userid_from_username($atts['username']);
		$events = get_upcoming_events_for_user($userid);
		
	} else if (isset($atts['groupid'])) {
		$events = get_upcoming_events_for_group($atts['groupid']);
		
	} else if ( isset($atts[0]) && is_numeric($atts[0]) ) {
		$events = get_upcoming_events_for_user($atts[0]);
		
	} else if ( isset($atts[0]) && !is_numeric($atts[0]) ) {
		$userid = get_userid_from_username($atts[0]);
		$events = get_upcoming_events_for_user($userid);		
	}
	
	$content = $options['topblock'];
	
	if (isset($atts['groupid'])) {
		foreach($events as $event) {
			$eb = create_event_block($event, $options['eventblock']);
			$content .= $eb;
		}
	} else {
		foreach($events as $event) {
			if (in_array($event['status'], $states)) {
				$eb = create_event_block($event, $options['eventblock']);
				$content .= $eb;
			}
		}		
	}
			
	$footer = str_replace('%USERID%',$atts['userid'],$options['footerblock']);
	$footer = str_replace('%IMGLINK%',$upcomingpluginpath."upcoming_logo2.gif",$footer);
	$content .= $footer;
		
	return $content;
}

function upcomingwidget_control() {
	$options = get_option('UpcomingWidget');

	if ( !is_array($options) ) {
		$options = array(
			'title'			=> 'Upcoming Events', 
			'numevents'		=> 5,
			'widgeteb'		=> '<li><a href="%EVENTURL%">%EVENTNAME%</a><br/>'."\n"
								.'%EVENTSTARTDATE% %EVENTENDDATE%<br/>'."\n"
								.'%VENUECITY%, %VENUECOUNTRY%</li>'
		);
	}

	if ( $_POST['upcomingwidget-submit'] ) {
		$options['title'] 		= strip_tags(stripslashes($_POST['upcomingwidget-title']));
		$options['numevents'] 	= $_POST['upcomingwidget-numevents'];
		$options['userid'] 		= $_POST['upcomingwidget-userid'];
		$options['widgeteb'] 	= stripslashes($_POST['upcomingwidget-widgeteb']);
		update_option('upcomingwidget', $options);
	}

	$title = htmlspecialchars($options['title'], ENT_QUOTES);

	echo '<p style="text-align:right;"><label for="upcomingwidget-title">Title:</label><br /> <input style="width: 200px;" id="upcomingwidget-title" name="upcomingwidget-title" type="text" value="'.$title.'" /></p>';

	echo '<p style="text-align:right;"><label for="upcomingwidget-numevents">Number of events to display:</label><br /> <input style="width: 200px;" id="upcomingwidget-numevents" name="upcomingwidget-numevents" type="text" value="'.$options['numevents'].'" /></p>';

	echo '<p style="text-align:right;"><label for="upcomingwidget-userid">Upcoming User ID:</label><br /> <input style="width: 200px;" id="upcomingwidget-userid" name="upcomingwidget-userid" type="text" value="'.$options['userid'].'" /></p>';

	echo '<p style="text-align:right;"><label for="upcomingwidget-eb">Event block template:</label><br /> <textarea rows="6" cols="50" name="upcomingwidget-widgeteb">'.$options['widgeteb'].'</textarea></p>';

	echo '<input type="hidden" id="upcomingwidget-submit" name="upcomingwidget-submit" value="1" />';
}

function upcomingwidget_init() {
	if (!function_exists('register_sidebar_widget'))
		return;
	
	function upcomingwidget($args) {
		extract($args);
				
		$options 	= get_option('UpcomingWidget');
		$title 		= $options['title'];
		$events 	= get_upcoming_events($options['userid']);
		$states 	= array('attend');

		echo $before_widget;
		echo $before_title . $title . $after_title;
		echo "<ul>\n";

		$i = 0;
		while ($i < $options['numevents'] && $i < count($events)) {
			if (in_array($events[$i]['status'], $states)) {
				echo create_event_block($events[$i], $options['widgeteb']);
				$i++;
			}
		}

		echo "</ul>\n";
		echo $after_widget;
	}
	
	register_sidebar_widget('Upcoming Widget', 'upcomingwidget');
	register_widget_control('Upcoming Widget', 'upcomingwidget_control', 450, 200);
}

if ( ! class_exists( 'Upcoming_Admin' ) ) {

	class Upcoming_Admin {
		
		function add_config_page() {
			global $wpdb;
			if ( function_exists('add_submenu_page') ) {
				add_options_page('Upcoming Configuration', 'Upcoming', 10, basename(__FILE__), array('Upcoming_Admin','config_page'));
				add_filter( 'plugin_action_links', array( 'Upcoming_Admin', 'filter_plugin_actions'), 10, 2 );
				add_filter( 'ozh_adminmenu_icon', array( 'Upcoming_Admin', 'add_ozh_adminmenu_icon' ) );
			}
		}

		function add_ozh_adminmenu_icon( $hook ) {
			static $upcomingicon;
			if (!$upcomingicon) {
				$upcomingicon = WP_CONTENT_URL . '/plugins/' . plugin_basename(dirname(__FILE__)). '/calendar.png';
			}
			if ($hook == 'upcoming.php') return $upcomingicon;
			return $hook;
		}

		function filter_plugin_actions( $links, $file ){
			//Static so we don't call plugin_basename on every plugin row.
			static $this_plugin;
			if ( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);
			
			if ( $file == $this_plugin ){
				$settings_link = '<a href="options-general.php?page=upcoming.php">' . __('Settings') . '</a>';
				array_unshift( $links, $settings_link ); // before other links
			}
			return $links;
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
				echo "<div id=\"message\" class=\"error\"><p>Error, please enter your API key, you can <a href=\"http://upcoming.yahoo.com/services/api/keygen.php\">create one here</a>.</p></div>\n";
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
add_action('plugins_loaded', 'upcomingwidget_init');

?>