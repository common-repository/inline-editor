<?php
/*
Plugin Name: Inline Editor
Plugin URI: http://www.wpxpand.com
Description: Allows a logged in user to edit content on the displaying page instead of having to log in to the admin area. Utilises <a href="http://bkirchoff.com/" target="_blank">Brian Kirchoff's</a> brilliant <a href="http://www.nicedit.com/" target="_blank">NicEdit component</a>.
Author: WPXpand
Version: 0.7.6
Author URI: http://www.wpxpand.com
*/
//error_reporting(E_ALL);
//ini_set('display_errors', '1');

define( 'ILE_PATH', dirname( __FILE__ ) . '/' );
define( 'ILE_URL', WP_PLUGIN_URL . '/' . str_replace( basename( __FILE__ ), '', plugin_basename( __FILE__ ) ) );

/**
 * Inline Editor Class
 *
 * @copyright 2009 Business Xpand
 * @license GPL v2.0
 * @author Steven Raynham
 * @version 0.7.6
 * @link http://www.businessxpand.com/
 * @since File available since Release 0.5
 */
class InlineEditor
{
	var $authorised = false;
	var $bxNews;

	/**
	 * Construct the plugin
	 *
	 * @author Steven Raynham
	 * @since 0.7.1
	 *
	 * @param void
	 * @return null
	 */
	function InlineEditor()
	{
		if ( is_admin() ) {
			register_activation_hook( __FILE__ , array( &$this, 'activate' ) );
			register_deactivation_hook( __FILE__ , array( &$this, 'deactivate' ) );
			add_action( 'init', array( &$this,'adminInit' ) );
			add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'pluginActionLinks' ), 10, 4 );
			add_action( 'admin_menu', array( &$this, 'adminMenu' ) );
			if ( !class_exists( 'BxNews' ) ) include_once( 'class-bx-news.php' );
			$this->bxNews = new BxNews( 'http://www.wpxpand.com/feeds/wordpress-plugins/', false );
		} else {
			wp_deregister_script( 'nicedit' );
			wp_register_script( 'nicedit', ILE_URL . 'nicEdit.js', '', '0.9r23.ile1' );
			add_action( 'init', array( &$this, 'init' ) );
			add_action( 'wp_head', array( &$this, 'wpHead' ) );
			add_filter( 'the_content', array( &$this, 'theContent' ) );
			add_filter( 'the_content_more_link', array( &$this, 'theContentMoreLink' ) );
			add_filter( 'edit_post_link', array( &$this, 'editPostLink' ) );
		}
	}

   /**
	 * Set initial settings
	 *
	 * @author Steven Raynham
	 * @since 0.6.6
	 *
	 * @param void
	 * @return null
	 */
	function activate()
	{
		$options = array( 'editbutton' => '1',
						  'wpeditlink' => '1' );
		if ( get_option( 'ile_configuration' ) === false )
			add_option( 'ile_configuration', $options );
		$options = array( 'full' => '1' );
		if ( get_option( 'ile_buttons' ) === false )
			add_option( 'ile_buttons', $options );
	}

   /**
	 * Remove settings
	 *
	 * @author Steven Raynham
	 * @since 0.6.7
	 *
	 * @param void
	 * @return null
	 */
	function deactivate()
	{
		if ( get_option( 'ile_configuration' ) === false )
			delete_option( 'ile_configuration', $options );
		if ( get_option( 'ile_buttons' ) === false )
			delete_option( 'ile_buttons', $options );
	}

   /**
	 * Register the settings link on the plugin screen
	 *
	 * @author Steven Raynham
	 * @since 0.5
	 *
	 * @param $links array
	 * @return array
	 */
	function pluginActionLinks( $links )
	{
		$settingsLink = '<a href="options-general.php?page=' . basename( __FILE__ ) . '">' . __('Settings') . '</a>';
		array_unshift( $links, $settingsLink );
		return $links;
	}


   /**
	 * Initialise the wp admin area
	 *
	 * @author Steven Raynham
	 * @since 0.6.5
	 *
	 * @param void
	 * @return null
	 */
	function adminInit()
	{
		if ( isset( $_POST['action'] ) && isset( $_POST['ile-form'] ) ) {
				check_admin_referer( 'ile-nonce', 'ile-nonce' );
				switch ( $_POST['action'] ) {
					case 'save':
						if ( isset( $_POST['doaction_save'] ) ) {
							delete_option( 'ile_buttons' );
							if ( isset( $_POST['ile_button'] ) && ( count( $_POST['ile_button'] ) > 0 ) ) {
								foreach ( $_POST['ile_button'] as $name => $value ) {
									if ( !empty( $value ) ) $ileOptions[$name] = $value;
								}
								if ( get_option( 'ile_buttons' ) )
									update_option( 'ile_buttons', $ileOptions );
								else
									add_option( 'ile_buttons', $ileOptions );
							}
							delete_option( 'ile_configuration' );
							if ( isset( $_POST['ile_configuration'] ) && ( count( $_POST['ile_configuration'] ) > 0 ) ) {
								foreach ( $_POST['ile_configuration'] as $name => $value ) {
									if ( !empty( $value ) ) $ileOptions[$name] = $value;
								}
							}
							if ( !isset( $ileOptions['wpeditlink'] ) ) $ileOptions['wpeditlink'] = 0;
							if ( !isset( $ileOptions['editbutton'] ) ) $ileOptions['editbutton'] = 0;
							if ( ( $ileOptions['wpeditlink'] == 0 )  && ( $ileOptions['editbutton'] == 0 ) ) {
								$this->message .= '<p>At least on edit button/link must be set, so the edit button has been activated.</p>';
								$ileOptions['editbutton'] = 1;
							}
							if ( get_option( 'ile_configuration' ) )
								update_option( 'ile_configuration', $ileOptions );
							else
								add_option( 'ile_configuration', $ileOptions );
						}
						$this->message .= '<p>Options saved.</p>';
						break;
				}
			}

	}

   /**
	 * Admin menu in settings tab
	 *
	 * @author Steven Raynham
	 * @since 0.5
	 *
	 * @param void
	 * @return null
	 */
	function adminMenu()
	{
		add_options_page( __( 'Inline Editor' ), __( 'Inline Editor' ), 'level_7', basename(__FILE__), array( &$this, 'optionsPage' ) );
	}

   /**
	 * Admin options page
	 *
	 * @author Steven Raynham
	 * @since 0.6.5
	 *
	 * @param void
	 * @return null
	 */
	function optionsPage()
	{
		$buttonOptions = get_option( 'ile_buttons' );
		$buttonArray = array( 'bold',
							  'italic',
							  'underline',
							  'left',
							  'center',
							  'right',
							  'justify',
							  'ol',
							  'ul',
							  'subscript',
							  'superscript',
							  'strikethrough',
							  'indent',
							  'outdent',
							  'hr',
							  'image',
							  'forecolor',
							  'bgcolor',
							  'link',
							  'unlink',
							  'fontSize',
							  'fontFamily',
							  'fontFormat',
							  'xhtml' );
		$configurationOptions = get_option( 'ile_configuration' );
		if ( !isset( $configurationOptions['editbutton'] ) ) $configurationOptions['editbutton'] = 1;
		if ( !isset( $configurationOptions['wpeditlink'] ) ) $configurationOptions['wpeditlink'] = 1;
?><div class='wrap'>
	<h2><?php _e( 'Inline Editor' ); ?></h2>
	<?php if ( !empty( $this->message ) ) { ?><div id="message" class="updated fade"><p><strong><?php _e( $this->message ); ?></strong></p></div><?php } ?>
	<h3><?php _e( 'Instructions' ); ?></h3>
	<ul>
		<li><?php _e( 'Full panel includes all available NicEdit buttons.' ); ?></li>
		<li><?php _e( 'Simple panel consists of just bold, italic, underline, alignments, lists and linking.' ); ?></li>
		<li><?php _e( 'If these are not selected you can customise the buttons you need.' ); ?></li>
	</ul>
	<hr/>
	<div>
		<form method="post">
			<?php wp_nonce_field( 'ile-nonce', 'ile-nonce', true, true ); ?>
			<input type="hidden" name="action" value="save"/>
			<input type="hidden" name="ile-form" value="true"/>
			<table>
				<tbody id="id_list">
					<tr valign="top"><td align="right"><input type="checkbox" name="ile_configuration[editbutton]" value="1"<?php echo ( ( $configurationOptions['editbutton'] == 1 ) ?  ' checked="checked"' : '' ); ?>/></td><td><?php _e( 'Show edit button' ); ?></td></tr>
					<tr valign="top"><td align="right"><input type="checkbox" name="ile_configuration[wpeditlink]" value="1"<?php echo ( ( $configurationOptions['wpeditlink'] == 1 ) ?  ' checked="checked"' : '' ); ?>/></td><td><?php _e( 'Convert Wordpress edit link' ); ?></td></tr>
					<tr valign="top"><td align="right"><input type="checkbox" name="ile_configuration[dblclick]" value="1"<?php echo ( isset( $configurationOptions['dblclick'] ) ?  ' checked="checked"' : '' ); ?>/></td><td><?php _e( 'Edit content on double click' ); ?></td></tr>
					<tr><td colspan="2">&nbsp;</td></tr>
					<tr valign="top"><td align="right"><input type="checkbox" name="ile_button[full]" id="ile_button_full" value="1"<?php echo ( isset( $buttonOptions['full'] ) ?  ' checked="checked"' : '' ); ?>/></td><td><?php _e( 'Full panel' ); ?></td></tr>
					<tr valign="top"><td align="right"><input type="checkbox" name="ile_button[simple]" id="ile_button_simple" value="1"<?php echo ( isset( $buttonOptions['simple'] ) ?  ' checked="checked"' : '' ); ?>/></td><td><?php _e( 'Simple panel' ); ?></td></tr>
					<tr><td colspan="2">&nbsp;</td></tr>
					<tr valign="top"><td>&nbsp;<td><strong id="ile_custom_title"><?php _e( 'Custom buttons' ); ?>:</strong></td></tr>
					<?php foreach ( $buttonArray as $button ) { ?>
					<tr valign="top"><td align="right"><input type="checkbox" class="ile_button" name="ile_button[<?php echo $button; ?>]" value="1"<?php echo ( isset( $buttonOptions[$button] ) ?  ' checked="checked"' : '' ); ?>/></td><td class="ile_button_title"><?php echo $button; ?></td></tr>
					<?php } ?>				</tbody>
			</table>
			<p class="submit"><input class="button-primary" type="submit" id="submit_changes" name="doaction_save" value="<?php _e( 'Save changes' ); ?>"/></p>
		</form>
		<script type="text/javascript">
		/* <![CDATA[ */
			function buttonCheck(){
				if (jQuery('#ile_button_full').is(':checked') || jQuery('#ile_button_simple').is(':checked')) {
					jQuery('#ile_custom_title').css('color','#aaaaaa');
					jQuery('input.ile_button').attr('disabled', 'disabled');
					jQuery('td.ile_button_title').css('color','#aaaaaa');
				} else {
					jQuery('#ile_custom_title').css('color','#000000');
					jQuery('input.ile_button').removeAttr('disabled');
					jQuery('td.ile_button_title').css('color','#000000');
				}
			}
			buttonCheck();
			jQuery('#ile_button_full').click(function(){
				buttonCheck();
				if (jQuery('#ile_button_full').is(':checked')) {
					if (jQuery('#ile_button_simple').is(':checked')) {
						jQuery('#ile_button_simple').removeAttr('checked');
					}
				}
			});
			jQuery('#ile_button_simple').click(function(){
				buttonCheck();
				if (jQuery('#ile_button_simple').is(':checked')) {
					if (jQuery('#ile_button_full').is(':checked')) {
						jQuery('#ile_button_full').removeAttr('checked');
					}
				}
			});
		/* ]]> */
		</script>
	</div>
</div>
<div class="wrap">
<?php $this->bxNews->getFeed( '', array( 'http://wordpress.org/extend/plugins/inline-editor/' ) ); ?>
</div><?php
	}

   /**
	 * Initialise blog
	 *
	 * @author Steven Raynham
	 * @since 0.7.5
	 *
	 * @param void
	 * @return null
	 */
	function init()
	{
		if ( !isset( $_SESSION ) ) {
			@session_cache_limiter('private, must-revalidate');
			@session_start();
		}
		$_SESSION['ile']['filter'] = true;
	}

   /**
	 * Add HTML head elements
	 *
	 * @author Steven Raynham
	 * @since 0.7.5
	 *
	 * @param void
	 * @return null
	 */
	function wpHead()
	{
		global $wp_query, $wpdb, $post;

		switch ( $post->post_type ) {
			case 'page' :
				$this->authorised = current_user_can( 'edit_page', $post->ID );
				break;
			case 'post' :
				$this->authorised = current_user_can( 'edit_post', $post->ID );
				break;
			default :
				$this->authorised = current_user_can( 'edit_posts' ) || current_user_can('edit_pages');
		}
		if ( $this->authorised ) {
			wp_print_scripts( 'jquery' );
			wp_print_scripts( 'sack' );
			wp_print_scripts( 'nicedit' );
		}
		if ( $this->authorised ) {
			if ( $buttonOptions = get_option( 'ile_buttons' ) ){
				if ( isset( $buttonOptions['full'] ) ) {
					$buttonList = 'full';
				} else if ( isset( $buttonOptions['simple'] ) ) {
					$buttonList = 'simple';
				} else {
					$buttonList = '';
					foreach ( $buttonOptions as $name => $value ) {
						if ( !empty( $buttonList ) ) $buttonList .= ',';
						$buttonList .= "'$name'";
					}
					$buttonList = "'save'," . $buttonList;
				}
			} else {
				$buttonList = "'save'";
			}
			switch ( $buttonList ) {
				case 'full':
					$panel = 'fullPanel:true,';
					break;
				case 'simple':
					$panel = "buttonList:['save','bold','italic','underline','left','center','right','justify','ol','ul','link','unlink'],";
					break;
				default:
					$panel = 'buttonList:[' . $buttonList . '],';
			}
			$configurationOptions = get_option( 'ile_configuration' );
			echo '
<script type="text/javascript">
/* <![CDATA[ */
	var ileNicEditor;
	function startEditing(postId) {
		jQuery("#ileEditLink"+postId).hide();' .
		( ( $configurationOptions['editbutton'] == 1 ) ? '
		jQuery("#ileEditButton-"+postId).hide();' : '' ) . '
		jQuery("#ileSaveButton-"+postId).show();
		jQuery("#ileCancelButton-"+postId).show();
		ileCreateNicEditor(postId);
	}
	function ileCreateNicEditor(postId) {
		jQuery.ajax({async:false,
					 type:"POST",
					 url:"' . ILE_URL . 'ajax-content.php",
					 data:"id="+postId,
					 success:function(data){
						jQuery("#ileContent-"+postId).html(data);
					 }
					 });
		ileNicEditor = new nicEditor({' . $panel . '
									  iconsPath:"' . ILE_URL . 'nicEditorIcons.gif",
									  onSave:function(content,id,instance){ileSave(postId,content)}
									 }).panelInstance("ileContent-"+postId,{hasPanel:true});
		jQuery("#ileCancelButton"+postId).show();
	}
	function ileSave(postId,content){
		jQuery.post("' . ILE_URL . 'ajax-save.php",
					{"id":postId,
					 "content":content},
					 function(data){
						 alert(data.message)
						 jQuery("#ileCancelButton-"+postId).click();
					 },
					 "json");
	}
/* ]]> */
</script>
<style type="text/css">' .
( ( $configurationOptions['editbutton'] == 1 ) ? '
	.ileClassEditButton{position:absolute;z-index:1000;margin:0;padding:0;border:0;cursor:pointer;filter:alpha(opacity=60);-moz-opacity:0.60;-khtml-opacity:0.60;opacity:0.60;}
	.ileClassEditButton:hover{filter:alpha(opacity=100);-moz-opacity:1;-khtml-opacity:1;opacity:1;}' : '' ) . '
	.ileClassLoader{left:0;width:98%;text-align:center;position:absolute;z-index:1000;}
</style>';
		}
	}

   /**
	 * Add div tags edit and inline button around content
	 *
	 * @author Steven Raynham
	 * @since 0.7.6
	 *
	 * @param $content string
	 * @return string
	 */
	function theContent( $content )
	{
		global $wp_query;
		if ( $this->authorised ) {
			if ( $_SESSION['ile']['filter'] ) {
			$postId = $wp_query->post->ID;
			$configurationOptions = get_option( 'ile_configuration' );
			$originalContent = $content;
			$content = '<div id="ileLoader-' . $postId . '" class="ileClassLoader"><img src="' . ILE_URL . 'ajax-loader.gif" title="' . __( 'Loading' ) . '..."/></div>';
			if ( $configurationOptions['editbutton'] == 1 )
				$content .= '<img id="ileEditButton-' . $postId . '" class="ileClassEditButton" src="' . ILE_URL . 'edit.png" title="' . __( 'Edit Inline' ) . '"/>';
			$content .= '<div id="ileContent-' . $postId . '">' . $originalContent . '</div>'.
						'<input id="ileSaveButton-' . $postId . '" type="button" value="' . __( 'Save editing' ). '"/>
						<input id="ileCancelButton-' . $postId . '" type="button" value="' . __( 'Cancel editing' ). '"/>
<script type="text/javascript">
/* <![CDATA[ */
	jQuery("#ileLoader-' . $postId . '").hide();
	jQuery("#ileSaveButton-' . $postId . '").hide();
	jQuery("#ileCancelButton-' . $postId . '").hide();
	jQuery("#ileLoader-' . $postId . '").ajaxStart(function(){
		jQuery("#ileLoader-' . $postId . '").show();
	})
	jQuery("#ileLoader-' . $postId . '").ajaxStop(function(){
		jQuery("#ileLoader-' . $postId . '").hide();
	})
	jQuery("#ileEditButton-' . $postId . '").click(function(){
		startEditing(' . $postId . ');
	})' .
	( isset( $configurationOptions['dblclick'] ) ? '
	jQuery("#ileContent-' . $postId . '").dblclick(function(){
		startEditing(' . $postId . ');
	});' : '' ) . '
	jQuery("#ileSaveButton-' . $postId . '").click(function(){ileSave(' . $postId . ',jQuery("div.nicEdit-main").html());});
	jQuery("#ileCancelButton-' . $postId . '").click(function(){
		ileNicEditor.removeInstance("ileContent-' . $postId . '");
		jQuery.ajax({async:false,
					 type:"POST",
					 url:"' . ILE_URL . 'ajax-content.php",
					 data:"id=' . $postId . '&filtered=1",
					 success:function(data){
						 if (data.search(/<script/i)==-1)
							jQuery("#ileContent-' . $postId . '").html(data);
						else
							jQuery(location).attr("href","' . $_SERVER['REQUEST_URI'] . '");
					 }
					 });
		jQuery("#ileSaveButton-' . $postId . '").hide();
		jQuery("#ileCancelButton-' . $postId . '").hide();' .
		( ( $configurationOptions['wpeditlink'] == 1 ) ? '
		jQuery("#ileEditLink-' . $postId . '").show();' : '' ) .
		( ( $configurationOptions['editbutton'] == 1 ) ? '
		jQuery("#ileEditButton-' . $postId . '").show();' : '' ) . '
	})
/* ]]> */
</script>
';
		}
		}
		return $content;
	}

   /**
	 * Filter the default more link
	 *
	 * @author Steven Raynham
	 * @since 0.7
	 *
	 * @param $link string
	 * @return string
	 */
	function theContentMoreLink( $link )
	{
		global $wp_query;
		if ( $this->authorised ) {
			$postId = $wp_query->post->ID;
			$link = '<a class="more-link" href="' . get_permalink( $postId ) . '">More &raquo;</a>';
		}
		return $link;
	}

   /**
	 * Filter the default edit post link
	 *
	 * @author Steven Raynham
	 * @since 0.7
	 *
	 * @param $link string
	 * @return string
	 */
	function editPostLink( $link )
	{
		global $wp_query;
		if ( $this->authorised ) {
			$postId = $wp_query->post->ID;
			$configurationOptions = get_option( 'ile_configuration' );
			if ( $configurationOptions['wpeditlink'] == 1 )
				$link = '<a href="#" id="ileEditLink-' . $postId . '">' . __( 'Edit inline' ) . '</a>
<script type="text/javascript">
/* <![CDATA[ */
	if (jQuery("#ileEditLink-' . $postId . '").length>0) {
		jQuery("#ileEditLink-' . $postId . '").click(function(){
			startEditing(' . $postId . ');
		});
	}
/* ]]> */
</script>
';
		}
		return $link;
	}
}
new InlineEditor;
