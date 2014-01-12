<?php
/*
Plugin Name: Wiki Page Links
Plugin URI: http://www.flyingsquirrel.ca/index.php/wordpress-plugins/wiki-links/
Description: Automatically links to pages, wiki-style.
Author: Darcy Casselman
Version: 0.4
Author URI: http://www.flyingsquirrel.ca/

    Copyright 2008-2011  Darcy Casselman  (email : dscassel@gmail.com)

	This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.


*/

require_once ABSPATH . WPINC . "/post.php";

if ( !class_exists("WikiLinksPlugin") ) {
class WikiLinksPlugin {

    const REGEX_WIKILINK = '/\[\[([^\]]+)\]\]/';
    const DELIMITER_LINK = '|';
    const DELIMITER_POST_TYPE = '*';    
    const ADD_POST_CLASS = 'wikilink_create';
    const DEFAULT_POST_TYPE = 'page';

    public $name = "WikiPageLinksPlugin";
    public $shortName = "WikiPageLinks";
    public $longName = "Wiki Page Links Plugin";
    public $adminOptionsName = "WikiPageLinksPluginAdminOptions";

    var $debug = false;
    
    function log($message) {
        if ($this->debug)
            error_log($message . "\n", 3, "/tmp/wikilinks.log");
    }
    
    function __construct() {
	    // WordPress Hooks
        //add_action('admin_menu', array(&$this, 'addAdminPanel'));  
		add_filter('the_content', array(&$this, 'wiki_filter'));
	}
	
	function _install() {
	    $this->log("Wiki Links installed!");
	}
	
	function _uninstall() {
	    $this->log("Wiki Links uninstalled!");
    }
    
    /**
     * Parse the wikilink. These links follow this pattern:
     *
     * link text|Post Title*post_type
     *
     * The 'link text' and post_type are optional. The order
     * is significant. Extra whitespace before/after an item
     * is trimmed. All of these are valid formats:
     *
     * link text | Some Post Title * post
 *
     * Some Post Title
     *
     * Some Post Title*post
     * 
     * link text | Some Post Title
     */
    function parse_wikilink($wikilink) {
   		list($link_text, $post_title) = explode(self::DELIMITER_LINK, $wikilink, 2);
        if (!$post_title) {
            $post_title = $link_text;
        }
        list($post_title, $post_type) = explode(self::DELIMITER_POST_TYPE, $post_title, 2);
        if (!$post_type) {
            $post_type = '';
        }
    	return array(trim($link_text), trim($post_title), trim($post_type));
    }

    /**
     * Locate a post asociated with the requested title or return
     * false if no post can be located.
     */
    function get_post_by_title($title, $post_type = 'page') {
        $post = get_page_by_title($title, OBJECT, $post_type);
        return $post;
    }

	/* The filter.
	 * Replaces double brackets with links to pages.
	 */
	function wiki_filter($content) {
		//Match only phrases in double brackets.  A backslash can be
		//used to escape the sequence, if you want literal double brackets.
		preg_match_all(self::REGEX_WIKILINK, $content, $matches);

		//$matches[1] is an array of all the phrases in double brackets.
		//Dumping all the matches into a hash ensures we only look up
		//each matching page name once.
		$links = array();
		foreach( $matches[1] as $keyword ) {
			$links[$keyword] = current($matches[0]);
			next($matches[0]);
		}

		foreach( $links as $full_link => $match ) {
			list($link_text, $post_title, $post_type) = $this->parse_wikilink($full_link);

            if (!$post_type) {
                $post_type = self::DEFAULT_POST_TYPE;
            }

            $clean_title = html_entity_decode($post_title, ENT_QUOTES);

			if ( $post = $this->get_post_by_title( $clean_title, $post_type ) ) {
				$content = str_replace($match, 
					"<a href='". get_permalink($post->ID) ."'>$post_title</a>",
					$content);
			} elseif ( is_user_logged_in() && current_user_can('edit_posts') ) {
				// Add a link to create the page if it doesn't exist.
                // Todo: If the post is a custom post type, our check for 'edit_posts'
                // capability might be incorrect. 
	
				$encodedlink = urlencode($post_title);
                $link_class = self::ADD_POST_CLASS;
                $create_post_url = get_admin_url() . "/post-new.php?post_type={$post_type}&post_title={$encodedlink}";
                $anchor_tag = "<a href='{$create_post_url}' class='{$link_class}' title='Create this post'>?</a>";
				$content = str_replace($match, "{$post_title}[{$anchor_tag}]", $content);

			} else {
				$content = str_replace($match, $page_title, $content);
			}
		}
		
		return $content;
	}
}
}


if (class_exists(WikiLinksPlugin) && !isset($wikiLinks_plugin)) {
    $wikiLinks_plugin = new WikiLinksPlugin();    
}

?>
