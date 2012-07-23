<?php
/**
 * @package WPBadgeDisplay
 */
/*
Plugin Name: WPBadgeDisplay
Plugin URI: https://github.com/davelester/WPBadgeDisplay
Description: Adds a widget to your WordPress blog to display Open Badges.
Version: 0.4
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
	
	<p><label for="openbadges_user_id">Open Badges ID: <input class="widefat" id="openbadges_user_id" name="openbadges_user_id" type="text" value="<?php echo get_option('openbadges_user_id'); ?>" /></label></p>
	<?php
	}

	function update($new_instance, $old_instance)
	{
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		update_option('openbadges_user_id', $_POST['openbadges_user_id']);
		
		return $instance;
	}

	function widget($args, $instance)
	{		
		$openbadgesuserid = get_option('openbadges_user_id');
		$url = "http://beta.openbadges.org/displayer/". $openbadgesuserid ."/groups.json";
		$groupsjson = file_get_contents($url, 0, null, null);
		$groupsdata = json_decode($groupsjson);

		extract($args);
		$title = apply_filters( 'widget_title', $instance['title'] );

		echo "<style>#wpbadgedisplay_widget img {
			max-height:80px;
			max-width:80px;
		}</style>";

		echo "<div id='wpbadgedisplay_widget'>";

		if (!empty($title))
			echo $before_title . $title . $after_title;;

		foreach ($groupsdata->groups as $group) {
			echo "<h1>" . $group->name . "</h1>";
			
			$badgesurl = "http://beta.openbadges.org/displayer/".$openbadgesuserid."/group/".$group->groupId.".json";
			$badgesjson = file_get_contents($badgesurl, 0, null, null);
			$badgesdata = json_decode($badgesjson);
			
			foreach ($badgesdata->badges as $badge) {
				echo "<h2><a href='" . $badge->assertion->badge->issuer->origin . "'>". $badge->assertion->badge->name . "</a></h2>";
				echo "<img src='" . $badge->assertion->badge->image . "'>";
			}
		}
		echo "</div>";
	}
 
}
add_action( 'widgets_init', create_function('', 'return register_widget("WPBadgeDisplayWidget");') );?>