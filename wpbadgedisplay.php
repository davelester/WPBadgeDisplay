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
	
	<p><label for="openbadges_display">Display: 
	<select class="widefat" id="openbadges_display" name="openbadges_display">
		<option value=''       <?php if (get_option('openbadges_display') == '') { echo "selected='selected'"; } ?>>Default</option>
		<option value='block'  <?php if (get_option('openbadges_display') == 'block') { echo "selected='selected'"; } ?>>Block</option>
		<option value='inline' <?php if (get_option('openbadges_display') == 'inline') { echo "selected='selected'"; } ?>>Inline</option>
	</select>
	</label></p>
	
	<p><label for="openbadges_show_bname">Show badges name: <input class="widefat" id="openbadges_show_bname" name="openbadges_show_bname" type="checkbox" <?php if (get_option('openbadges_show_bname')) echo 'checked="checked"'; ?> /></label></p>
	<p><label for="openbadges_show_bdesc">Show badges description: <input class="widefat" id="openbadges_show_bdesc" name="openbadges_show_bdesc" type="checkbox" <?php if (get_option('openbadges_show_bdesc')) echo 'checked="checked"'; ?> /></label></p>
	<?php
	}

	function update($new_instance, $old_instance)
	{
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		update_option('openbadges_email', $_POST['openbadges_email']);

		$openbadgesuserid = wpbadgedisplay_convert_email_to_openbadges_id($_POST['openbadges_email']);
		update_option('openbadges_user_id', $openbadgesuserid);
		
		update_option('openbadges_display', $_POST['openbadges_display']);
		update_option('openbadges_show_bname', $_POST['openbadges_show_bname']);
		update_option('openbadges_show_bdesc', $_POST['openbadges_show_bdesc']);

		return $instance;
	}

	function widget($args, $instance)
	{
		extract($args);
		$title = apply_filters( 'widget_title', $instance['title'] );

		if (!empty($title))
			echo $before_title . $title . $after_title;;

		$badgedata = wpbadgedisplay_get_public_backpack_contents(get_option('openbadges_user_id'), null);
		$options   = array(
			'show_bname' => get_option('openbadges_show_bname'),
			'show_bdesc' => get_option('openbadges_show_bdesc'),
			'display'    => get_option('openbadges_display')
		);
		echo wpbadgedisplay_return_embed($badgedata, $options);
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
				'description' => $badge->assertion->badge->description,
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
	$display = '';
	if ($options['display']) {
		$display = 'display: ' . $options['display'] . ' !important;';
	}

	echo "<style>
	#wpbadgedisplay_widget img {
		max-height:80px;
		max-width:80px;
		$display
	}
	#wpbadgedisplay_widget li {
		list-style-type: none;
		$display
	}
	</style>";

	echo "<div id='wpbadgedisplay_widget'>";

	foreach ($badgedata as $group) {
		echo "<h1>" . $group['groupname'] . "</h1>";
		echo "<ol>";
		foreach($group['badges'] as $badge) {
			$url   = $badge['criteriaurl'];
			$title = $badge['title'];
			$desc  = $badge['description'];
			$image = $badge['image'];
			echo "<li>";
			if ($options['show_bname']) {
				echo "<h2><a href='$url'>$title</a></h2>";
			}
			if ($options['show_bdesc']) {
				echo "<p>$desc</p>";
			}
			echo "<a href='$url'><img src='$image' alt='$title' title='$desc' border='0'></a>";
			echo "</li>";
		}
		echo "</ol>";

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
		'email'      => '',
		'username'   => '',
		'badgename'  => '',
		'display'    => '',
		'show_badgedesc' => 0,
		'show_badgename' => 1
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
	$options = array(
		'show_bname' => $show_badgename,
		'show_bdesc' => $show_badgedesc,
		'display'    => $display
	);
	return wpbadgedisplay_return_embed($badgedata, $options);

	// @todo: github ticket #3, if email or username not specified and shortcode is called
	// on an author page, automatically retrieve the author email from the plugin
}
add_shortcode('openbadges', 'wpbadgedisplay_read_shortcodes');
?>
