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

		$openbadgesuserid = wpbadgedisplay_convert_email_to_openbadges_id($_POST['openbadges_email']);
		update_option('openbadges_user_id', $openbadgesuserid);

		return $instance;
	}

	function widget($args, $instance)
	{
		extract($args);
		$title = apply_filters( 'widget_title', $instance['title'] );

		if (!empty($title))
			echo $before_title . $title . $after_title;;

		$badgedata = wpbadgedisplay_get_public_backpack_contents(get_option('openbadges_user_id'), null);
		echo wpbadgedisplay_return_embed($badgedata);
	}

}
add_action( 'widgets_init', create_function('', 'return register_widget("WPBadgeDisplayWidget");') );

// Using OpenBadges User ID, retrieve array of public groups and badges from backpack displayer api
function wpbadgedisplay_get_public_backpack_contents($openbadgesuserid)
{
	$backpackdata = array();

	$groupsurl = "http://beta.openbadges.org/displayer/". $openbadgesuserid ."/groups.json";
	$groupsjson = file_get_contents($groupsurl, 0, null, null);
	$groupsdata = json_decode($groupsjson);

	foreach ($groupsdata->groups as $group) {
		$badgesurl = "http://beta.openbadges.org/displayer/".$openbadgesuserid."/group/".$group->groupId.".json";
		$badgesjson = file_get_contents($badgesurl, 0, null, null);
		$badgesdata = json_decode($badgesjson);

		$badgesingroup = array();

		foreach ($badgesdata->badges as $badge) {
			$badgedata = array(
				'title' => $badge->assertion->badge->name,
				'image' => $badge->imageUrl,
				'criteriaurl' => $badge->assertion->badge->criteria,
				'issuername' => $badge->assertion->badge->issuer->name,
				'issuerurl' => $badge->assertion->badge->issuer->origin,
			);
			array_push($badgesingroup, $badgedata);
		}

		$groupdata = array(
			'groupname' => $group->name,
			'groupID' => $group->groupId,
			'numberofbadges' => $group->badges,
			'badges' => $badgesingroup
			);
		array_push($backpackdata, $groupdata);
	}

	return $backpackdata;
}

/* Generate HTML returned to display badges. Used by both widgets and shortcodes */
function wpbadgedisplay_return_embed($badgedata, $options=null) {

	// @todo: max-height and max-widget should be plugin configurations
	echo "<style>#wpbadgedisplay_widget img {
		max-height:80px;
		max-width:80px;
	}</style>";

	echo "<div id='wpbadgedisplay_widget'>";

	foreach ($badgedata as $group) {
		echo "<h1>" . $group['groupname'] . "</h1>";

		foreach($group['badges'] as $badge) {
			echo "<h2><a href='" . $badge['criteriaurl'] . "'>". $badge['title'] . "</h2>";
			echo "<img src='" . $badge['image'] . "' border='0'></a>";
		}

		if (!$group['badges']) {
			echo "No badges have been added to this group.";
		}
	}

	if (!$badgedata) {
		echo "No public groups exist for this user.";
	}
	echo "</div>";
}

function wpbadgedisplay_convert_email_to_openbadges_id($email) {
	$emailjson = wp_remote_post( 'http://beta.openbadges.org/displayer/convert/email', array(
		'body' => array(
			'email' => $email
		),
	) );

	// @todo The user id should probably be cached locally since it's persistent anyway
	if ( is_wp_error( $emailjson ) || 200 != $emailjson['response']['code'] ) {
		return '';
	}

	$body = json_decode( $emailjson['body'] );
	return $body->userId;
}

function wpbadgedisplay_read_shortcodes( $atts ) {
	extract( shortcode_atts( array(
		'email' => '',
		'username' => '',
		'badgename' => ''
	), $atts ) );

	// Create params array
	$params = array();

	// If both email and username specified, return an error message
	if ($email && $username) {
		return "An email address and username cannot both be included as attributes of a single shortcode.";
	}

	// If a username for a WordPress install is given, retrieve its email address
	if ($username) {
		$email = get_the_author_meta('user_email', get_user_by('login', $username)->ID);
	}

	// If we still have no email value, fall back on the author of the current post
	if ( ! $email ) {
		$email = get_the_author_meta( 'user_email' );
	}

	/* 	With a user's email address, retrieve their Mozilla Persona ID
		Ideally, email->ID conversion will run only once since a persona ID will not change */
	if ($email) {
		$openbadgesuserid = wpbadgedisplay_convert_email_to_openbadges_id($email);
	}

	/*  Adds a hook for other plugins (like WPBadger) to add more shortcodes
		that can optionally be added to the params array */
	do_action('openbadges_shortcode');

	$badgedata = wpbadgedisplay_get_public_backpack_contents($openbadgesuserid);
	return wpbadgedisplay_return_embed($badgedata);

	// @todo: github ticket #3, if email or username not specified and shortcode is called
	// on an author page, automatically retrieve the author email from the plugin
}
add_shortcode('openbadges', 'wpbadgedisplay_read_shortcodes');
?>
