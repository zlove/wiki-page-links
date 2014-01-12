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

    var $name = "WikiPageLinksPlugin";
    var $shortName = "WikiPageLinks";
    var $longName = "Wiki Page Links Plugin";
    var $adminOptionsName = "WikiPageLinksPluginAdminOptions";
    
    var $debug = false;

	var $defaultShortcuts = array(
		'wiki' => 'http://en.wikipedia.org/wiki/%s',
	);
    
    function log($message) {
        if ($this->debug)
            error_log($message . "\n", 3, "/tmp/wikilinks.log");
    
    }
    
    //PHP4 constructor
	function WikiLinksPlugin() {$this->__construct();} 
    
    //PHP5 constructor
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
     * Added by Daniel Llewellyn (Fremen):
     * separate out the viewed title from the link name from wikilinks of the form [[link|some user title]]
     */
    function wiki_get_piped_title($link) {
   		list($link, $title) = split('\|', $link, 2);
    	if (!$title) $title = $link;
    	return array($link, $title);
    }

	/* The filter.
	 * Replaces double brackets with links to pages.
	 */
	function wiki_filter($content) {
		$options = $this->getAdminOptions();

		//Match only phrases in double brackets.  A backslash can be
		//used to escape the sequence, if you want literal double brackets.
		preg_match_all('/\[\[([^\]]+)\]\]/', $content, $matches);

		//$matches[1] is an array of all the phrases in double brackets.
		//Dumping all the matches into a hash ensures we only look up
		//each matching page name once.
		$links = array();
		foreach( $matches[1] as $keyword ) {
			$links[$keyword] = current($matches[0]);
			next($matches[0]);
		}

		foreach( $links as $full_link => $match ) {
			// If the "page title" contains a ':', it *may* be a shortcut
			// link rather than a page.  Deal with those first.
			list($prefix, $sublink) = split(':', $full_link, 2);

			if ( $sublink ) {
				if ( array_key_exists($prefix, $options['shortcuts']) ) {
					list($link, $subtitle) = $this->wiki_get_piped_title($sublink);
					$shortcutLink = sprintf( $options['shortcuts'][$prefix],
						rawurlencode($link));
					$content = str_replace($match, 
						"<a href='$shortcutLink'>$subtitle</a>",
						$content);
					continue;
				}
			}
			
			list($link, $page_title) = $this->wiki_get_piped_title($full_link);

			//We have a page link. 
			//TODO: cut down on db hits and get the list of pages instead.
			if ( $page = get_page_by_title(html_entity_decode($link, ENT_QUOTES)) ) {
				$content = str_replace($match, 
					"<a href='". get_permalink($page->ID) ."'>$page_title</a>",
					$content);
			} else if ( is_user_logged_in() ) {
				//Add a link to create the page if it doesn't exist.
				//TODO: limit showing the link to users who can create posts.

				$home = get_option('siteurl');
				$encodedlink = urlencode($link);
				$content = str_replace($match, "{$page_title}[<a href='$home/wp-admin/post-new.php?post_type=page&post_title=$encodedlink' class='nonexistant_page' title='Create this page (requires a valid \"contributer\" account)'>?</a>]", $content);

			} else {
				
				$content = str_replace($match, $page_title, $content);
			}
		}
		
		return $content;
	}

    function getAdminOptions() {
        //defaults
        $options = array(
            'shortcuts' => $this->defaultShortcuts,
        );
    
    	$savedOptions = get_option($this->adminOptionsName);
    	
		if (!empty($savedOptions)) {
			foreach ($savedOptions as $key => $value) {
			    $options[$key] = $value;
			}
		} 
		
		return $options;
    
    }
    
    function saveAdminOptions($options) {
        if (get_option($this->adminOptionsName)) {
            $this->log("Updating option: " . $this->adminOptionsName);
            update_option($this->adminOptionsName, $options);
        } else {
            $this->log("Adding new option: " . $this->adminOptionsName);
            add_option($this->adminOptionsName, $options);
        }
    }
        
    function adminPanel() {  
        $adminOptions = $this->getAdminOptions();
        $submitButton = "submit_Save${shortname}Options";

        if (isset($_POST[$submitButton])) {
            for ( $i=0; $i < $this->shortcutCount; $i++ ) {
                if (isset($_POST["shortcut$i"])) {
                    $adminOptions['feeds'][$i] = $_POST["feeds$i"];
                }
            }
            
            print_r($adminOptions);
            $this->saveAdminOptions($adminOptions);
            
            ?>
            
        <div class="updated"><p><strong>
        <?php  _e("Settings Updated", $this->name); ?>
        </strong></p></div>
        
            <?php
        }
        
        ?>  
        <div class="wrap">  
            <h2><?php echo $longName; ?></h2>  
            <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <?php wp_nonce_field('update-options'); ?>
            
            <table class="form-table">
            <tr valign="top">
            
            <th scope="row"></th>
            <td>

            <input type="text" 
                   name="feeds<?php echo $i; ?>" 
                   value="<?php echo $feed; ?>"
                   size="50" />
            <br />
            
            </td>
            </tr>

            </table>
            
            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="page_options"
             value="new_option_name,some_other_option,option_etc" />

            <p class="submit">
            <input type="submit" 
			    name="<?php echo $submitButton; ?>" 
                value="<?php _e('Save Changes'); ?>" />
            </p>

            
            </form>
        </div>  
        <?php  
    }    
    
    function addAdminPanel()   
    {  
        add_submenu_page('options-general.php', 
        $this->longName, 
        $this->shortName, 
        10, __FILE__, 
        array(&$this, 'adminPanel'));
    }    

}
}


if (class_exists(WikiLinksPlugin) && !isset($wikiLinks_plugin)) {
    $wikiLinks_plugin = new WikiLinksPlugin();    
}

?>
