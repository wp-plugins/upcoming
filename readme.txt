=== Upcoming ===
Contributors: joostdevalk
Donate link: http://yoast.com/donate/
Tags: social, events, event calendar
Requires at least: 2.2
Tested up to: 2.7
stable tag: 0.4

Easily create a list of your upcoming events on your blog. Use the widget or include it in a post or page.

== Description ==
Easily create a list of your upcoming events on your blog, completely templatable, either in a post or page, or in a widget!

Example: [Joost de Valk's Speaking Page](http://yoast.com/speaking/)

More info:

* More info on [Upcoming](http://yoast.com/wordpress/upcoming/).
* Check out the other [Wordpress plugins](http://yoast.com/wordpress/) by the same author.

To get the same effect with the conferences you're speaking add, tag these events with speaker:username, and add the following CSS to your stylesheet:

`tr.speaking td h4 {
	background: url(/wp-content/plugins/upcoming/microphone.png) no-repeat left top;
	padding-left: 30px;
}`

The microphone icon that comes with the plugin is &copy; [Everaldo Coelho](http://www.everaldo.com/), licensed under the LGPL and can be found [here](http://www.iconfinder.net/index.php?q=microphone&page=icondetails&iconid=1846&size=22&q=microphone&s12=on&s16=on&s22=on&s32=on&s48=on&s64=on&s128=on)

== Changelog ==

* 0.4 Added functionality for groups, thanks to the guys at [Ribot](http://ribot.co.uk/)
* 0.3.2 Made sure Snoopy was available.
* 0.3.1 Small bugfix
* 0.3 Code clean up and addition of a widget
* 0.2 Minor bugfixes and addition of %STATE% replace code
* 0.1 Initial version

== Installation ==

1. Unzip the `upcoming.zip` file. 
1. Upload the the `upcoming` folder (not just the files in it!) to your `wp-contents/plugins` folder. 

**Activate**

1. In your WordPress administration, go to the Plugins page
1. Activate the Upcoming plugin and a subpage for Upcoming will appear in your Settings menu.
1. Either enable the widget, or add code for a user: `[upcoming userid="12345"]` or for a group `[upcoming groupid="12345"]` to any page or post of your choosing.

If you find any bugs or have any ideas, please mail me.
