# WPBadgeDisplay

## Overview
WPBadgeDisplay is a WordPress plugin for displaying [Open Badges](http://www.openbadges.org) on your blog. The plugin's theme widget allows users to easily configure the display of badges that are associated with a particular email address.

## Installation

Download the WPBadgeDisplay plugin, moving the WPBadger folder into the /wp-content/plugins/ directory on your server, and install it like any other WordPress plugin.

## Usage

1. Add the badge widget to your theme by navigating to Appearance -> Widgets in the WordPress administrative panel. There, you can specify where you'd like to display badges (for example, your theme's main sidebar).
2. Configure the widget by adding the email address badges are associated with, and adding an optional title that will display above your badges.

Alternatively badges can be added to any page using the [openbadges] tag:

    [openbadges
       email          = 'user_email@example.com'
       display        = ''|'inline-block'
       show_badgename = 0|1
       show_badgedesc = 0|1
    ]
                 
    

## Details
See the [WPBadgeDisplay wiki](https://github.com/davelester/wpbadgedisplay/wiki) for details on the plugin's roadmap, a list of early adopters and examples, and contact information. If you run into a problem, share your problem on the [issue tracker](https://github.com/davelester/wpbadgedisplay/issues?state=open).
