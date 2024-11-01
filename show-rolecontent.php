<?php
/*
Plugin Name: Show User Level Content
Plugin URI: http://www.fourhourworkweekdiary.com
Description: Hides content only visible to those with specified User Level 
Author: Rex Reed
Version: 0.2
Author URI: http://www.fourhourworkweekdiary.com
*/

// ##### This plugin should be completely self contained and work totally on its own without source editing.   
// ##### ---------- NOTHING USER-CONFIGURABLE AFTER HERE ------------

// Just in case someone's loaded up the page standalone for whatever reason,
// make sure it doesn't crash in an ugly way
if (!function_exists('add_action'))
{
  die("This page must be loaded as part of WordPress");
}

// Defer setup function until pluggable functions have been loaded
// (required since we need to call get_currentuserinfo)
add_action('init', 'showusercontent_setup');

// Initialize plugin variables and functions, called during the 
// 'init' action by WordPress
function showusercontent_setup()
{
  // Setup options stored in database
  showusercontent_setup_options();
  
  // Defer admin menu setup to only run if we're in admin section
  add_action('admin_menu', 'showusercontent_admin_hook');

  // Don't bother with anything more unless someone is logged in
  if (is_user_logged_in())
  {
    // Make sure private pages aren't cached publicly
    header('Cache-Control: private');
    header('Pragma: no-cache');

    // Initialize global variables      
    $showusercontent_exception_text = get_option('showusercontent_exception_text');
  }
			
   // Setup filters
	 add_filter('the_content', 'Wp_UserLevelContent');
	//add_filter('query', 'showusercontent_query');
	//add_filter('the_title', 'showusercontent_the_title');
	//add_filter('get_comment_text', 'showusercontent_get_comment_text');
	//if ($showusercontent_post_preview == 'excerpt') // FIXED THIS LINE
		//add_filter('get_the_excerpt', 'showusercontent_the_excerpt');
}

// Gets the user level of the current user
// If the user isn't logged in, returns null
function get_user_level()
{
  if (!is_user_logged_in())
  {
  	return null;
  }
  
  global $user_ID, $user_key;
  get_currentuserinfo();
  
  $current_user_level = get_usermeta($user_ID, $table_prefix . "user_level", true);
  // Only want numbers here
  if (($current_user_level == '') || !is_numeric($current_user_level))
  {
    return intval(0);
  }
  return intval($current_user_level);
}

function Wp_UserLevelContent($text) {
    
	global $user_ID;
	
	$userlevel=get_user_level();
				
  // First, find the hide string
  $startpos = strpos($text, '[hide'); // Look for start of code
  $endpos = strpos($text, '[/hide]');

	$regexp = "\[hide\s[^>]*(\"??)([^\" \]]*?)\\1[^>]*>(.*)\[\/hide\]";
	if(preg_match_all("/$regexp/siU", $text, $matches)) {
		//echo "<hr>" . print_r($matches[2]); 
		//echo "<hr>" . print_r($matches[3]);
		//echo "<hr>"; 
	}
	
	$texttohide = substr($text,$startpos,$endpos);
	$endpos = strpos($texttohide, '[/hide]'); 
	$texttohide = substr($texttohide,0,$endpos+7);
	
  $hidecmd = substr($texttohide,0,strlen('[hide')+4); // look for next 4 characters until the ]
	$hidecmd = substr($hidecmd,0,strpos($hidecmd,']')+1);
	
	//return "hidecmd: $hidecmd";	
	
	$showusercontent_exception_text = get_option('showusercontent_exception_text');
	
  // If User Not Registered, replace everything between [hide] tags
  if ($user_ID == '') {
  
		// Replace text to hide with exception text
		
		// Old Method
		//$text = str_replace($texttohide, $showusercontent_exception_text, $text);
		
		// New Method
		foreach ($matches[3] as $k => $v) {
		  $hidecmd="[hide " . $matches[2][$k] . "]";
			$text = str_replace($v,$showusercontent_exception_text,$text);
			$text = str_replace($hidecmd,"",$text);
			$text = str_replace("[/hide]","",$text);		
		}
    
    return $text;
  
  } else {
  
    // Old Method
		// If user registered, hide for user levels below the indicated level
		$hidelevel=split(' ',$hidecmd);
		$hidelevel=substr($hidelevel[1],0,strlen($hidelevel[1])-1);
		
		// If the current user level is less than indicated level, replace all content
		// Old Method
		//if ($userlevel<$hidelevel) {
		//	 $text = str_replace($texttohide, $showusercontent_exception_text, $text);
		//}
		// Replace the [hide] tags
    //$text = str_replace($hidecmd, "", $text);
    //$text = str_replace('[/hide]', "", $text);
		
		// New Method
		foreach ($matches[3] as $k => $v) {
			if ($userlevel<$matches[2][$k]) {
  			$text = str_replace($v,$showusercontent_exception_text,$text);
			}		
			$hidecmd="[hide " . $matches[2][$k] . "]";    	
			$text = str_replace($hidecmd,"",$text);
		}		
  	$text = str_replace("[/hide]","",$text);		

    return $text; 
  }
}

// UserLevelContent configuration page
function showusercontent_conf()
{
  global $table_prefix;
  $default_user_key = $table_prefix . 'user_level';

  ?>

  <div class="wrap">
    <h2><?php _e('Show User Level Content Configuration'); ?></h2>
    <p>Show User Level Content allows you to include tags that will hide parts of your content based upon the user's access level.</p>
  <?php 
  if (isset($_POST['submit']))
  {
    check_admin_referer();

    // Clean the incoming values
    $showusercontent_exception_text = stripslashes($_POST['showusercontent_exception_text']);
    if (current_user_can('unfiltered_html') == false)
    {
      $showusercontent_exception_text = wp_filter_post_kses($showusercontent_exception_text);
    }
    
    // Update values
    update_option('showusercontent_exception_text', $showusercontent_exception_text);
    
    echo '<div id="message" class="updated fade"><p><strong>' . __('Options saved.') . '</strong></p></div>';
  }

  global $showusercontent_exception_text;
  $showusercontent_exception_text = get_option('showusercontent_exception_text');
  ?>
    <form action="" method="post" id="showusercontent-conf">
      <table width="100%" cellspacing="2" cellpadding="5" class="editform"> 
        <tr valign="top">
          <th scope="row">Exception text:</th>
          <td><textarea name="showusercontent_exception_text" type="text" rows=6 cols=60 id="showusercontent_exception_text" size="45" class="code" /><?php echo form_option('showusercontent_exception_text'); ?></textarea> 
          <br />
          Input here the text replaces that the hidden text if user is not at a sufficient level to see it.
          </td>
        <tr>
      </table>
    
      <p class="submit"><input type="submit" name="submit" value="<?php _e('Update Show User Level Content &raquo;'); ?>" /></p>
    </form>
  </div>

<?php 
}

// Sets up the admin menu
function showusercontent_admin_hook()
{

  if (function_exists('add_submenu_page'))
  {
    add_submenu_page('plugins.php', 'Show User Level Content Configuration', 'Show User Level Content Configuration', 8, __FILE__, 'showusercontent_conf');
  }

  //add_filter('manage_posts_columns', 'showusercontent_add_column');
  //add_action('manage_posts_custom_column', 'showusercontent_do_column');

  //add_action('simple_edit_form', 'showusercontent_do_form');
  //add_action('edit_page_form', 'showusercontent_do_form');
  //add_action('edit_form_advanced', 'showusercontent_do_form');

  //add_filter('status_save_pre', 'showusercontent_status_save');
  //add_action('save_post', 'showusercontent_post_save');
}

// Creates the database-stored options for the plugins that customize
// the plugin's behavior
function showusercontent_setup_options()
{
  add_option('showusercontent_exception_text', 'showusercontent', 
             "Text to replace hide text if user is not at a sufficient level",
             'yes');
}

?>