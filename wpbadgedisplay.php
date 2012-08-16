<?php
/**
 * @package WPBadgeDisplay
 */
/*
Plugin Name: WPBadgeDisplay
Plugin URI: https://github.com/davelester/WPBadgeDisplay
Description: Adds a widget for displaying Open Badges on your blog. 
Version: 0.8
Author: Dave Lester
Author URI: http://www.davelester.org
*/

class WPBadgeDisplayWidget extends WP_Widget
{
	public function __construct() {
		parent::__construct(
	 		'WPBadgeDisplayWidget',
			'WPBadgeDisplay Widget',
			array( 'description' => __( 'Display Open Badges', 'text_domain' ), )
		);
	}
 
	function form($instance)
	{
		$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
		$title = $instance['title'];
	?>
	<p><label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>" /></label></p>
	
	<p><label for="openbadges_user_id">Email Account: <input class="widefat" id="openbadges_email" name="openbadges_email" type="text" value="<?php echo get_option('openbadges_email'); ?>" /></label></p>
	<?php
	}

	function update($new_instance, $old_instance)
	{
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		update_option('openbadges_email', $_POST['openbadges_email']);

		// build the http query, and stream POST when calling the file
		$postdata = http_build_query(
		    array(
		        'email' => $_POST['openbadges_email']
		    )
		);

		$opts = array('http' =>
		    array(
		        'method'  => 'POST',
		        'header'  => 'Content-type: application/x-www-form-urlencoded',
		        'content' => $postdata
		    )
		);

		$context  = stream_context_create($opts);
		$emailjson = file_get_contents('http://beta.openbadges.org/displayer/convert/email', false, $context);
		$emaildata = json_decode($emailjson);
		
		update_option('openbadges_user_id', $emaildata->userId);
		
		return $instance;
	}

	function widget($args, $instance)
	{
		extract($args);
		$title = apply_filters( 'widget_title', $instance['title'] );

		if (!empty($title))
			echo $before_title . $title . $after_title;;

		echo wpbadgedisplay_return_embed(get_option('openbadges_user_id'), null);
	}
 
}
add_action( 'widgets_init', create_function('', 'return register_widget("WPBadgeDisplayWidget");') );

/* Embed function that returns the badges, and is used by both widgets and shortocdes */
function wpbadgedisplay_return_embed($openbadgesuserid, $badge) {	
	// Retrieve a list of groups, based upon a user id
	$url = "http://beta.openbadges.org/displayer/". $openbadgesuserid ."/groups.json";
	$groupsjson = file_get_contents($url, 0, null, null);
	$groupsdata = json_decode($groupsjson);

	// @todo: max-height and max-widget should be plugin configurations
	echo "<style>#wpbadgedisplay_widget img {
		max-height:80px;
		max-width:80px;
	}</style>";

	echo "<div id='wpbadgedisplay_widget'>";

	foreach ($groupsdata->groups as $group) {
		echo "<h1>" . $group->name . "</h1>";
		
		$badgesurl = "http://beta.openbadges.org/displayer/".$openbadgesuserid."/group/".$group->groupId.".json";
		$badgesjson = file_get_contents($badgesurl, 0, null, null);
		$badgesdata = json_decode($badgesjson);
		
		foreach ($badgesdata->badges as $badge) {
			echo "<h2><a href='" . $badge->assertion->badge->issuer->origin . "'>". $badge->assertion->badge->name . "</a></h2>";
			echo "<img src='" . $badge->assertion->badge->image . "'>";
		}
		
		// If no badges have been added to a public group, print a message
		if (!$badgesdata->badges) {
			echo "No badges have been added to this group.";
		}
	}

	// If no public groups exist, print a message
	if (!$groupsdata->groups) {
		echo "No public groups exist for this user.";
	}
	echo "</div>";
}

function wpbadgedisplay_openbadgestag( $atts ) {
	extract( shortcode_atts( array(
		'email' => '',
		'username' => '',
		'badge_name' => ''
	), $atts ) );

	// If both email and username specified, return an error message
	if ($email && $username) {
		return "An email address and username cannot both be included as attributes of a single shortcode.";	
	}
	
	if ($email) {
		// First step is retrieving the Open Badges ID for an email address
		$postdata = http_build_query(
		    array(
		        'email' => $email
		    )
		);

		$opts = array('http' =>
		    array(
		        'method'  => 'POST',
		        'header'  => 'Content-type: application/x-www-form-urlencoded',
		        'content' => $postdata
		    )
		);

		$context  = stream_context_create($opts);
		$emailjson = file_get_contents('http://beta.openbadges.org/displayer/convert/email', false, $context);
		$emaildata = json_decode($emailjson);

		// Display badges, based on Open badges id and optional params
		return wpbadgedisplay_return_embed($emaildata->userId, $badge);
	}
	
	if ($username) {
		
	}
	
	// @todo: github ticket #3, if email or username not specified and shortcode is called
	// on an author page, automatically retrieve the author email from the plugin
}
add_shortcode('openbadges', 'wpbadgedisplay_openbadgestag');
?>