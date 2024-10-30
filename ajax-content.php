<?php
/**
 * Inline Editor AJAX Get Content
 *
 * @copyright 2009 Business Xpand
 * @license GPL v2.0
 * @author Steven Raynham
 * @version 0.7.6
 * @link http://www.businessxpand.com/
 * @since File available since Release 0.7
 */
require( dirname(__FILE__) . '/../../../wp-config.php' );
wp_cache_init();

switch ( $post->post_type ) {
	case 'page' :
		$authorised = current_user_can( 'edit_page', $post->ID );
		break;
	case 'post' :
		$authorised = current_user_can( 'edit_post', $post->ID );
		break;
	default :
		$authorised = current_user_can( 'edit_posts' ) || current_user_can('edit_pages');
}
if ( $authorised ) {
	if ( isset( $_POST['id'] ) ) {
		$sql = "SELECT post_content FROM " . $wpdb->prefix . "posts WHERE ID = '" . $_POST['id'] . "';";
		$content = stripslashes( $wpdb->get_var( $sql ) );
		if ( isset( $_POST['filtered'] ) ) {
			if ( preg_match('/<!--more(.*?)?-->/', $content, $matches) ) {
				$content = explode( $matches[0], $content, 2 );
				$content = $content[0] . '<p><a class="more-link" href="' . get_permalink( $_POST['id'] ) . '">More &raquo;</a></p>';
			}
			$_SESSION['ile']['filter'] = false;
			$content = apply_filters( 'the_content', $content );
			$_SESSION['ile']['filter'] = true;
			$content = str_replace(']]>', ']]&gt;', $content);
		} else {
			$content = jsCompatibleString( $content );
		}
		die( $content );
	} else {
		die( 'false');
	}
} else
	die( 'false' );

/**
 * Create javascript string of content
 *
 * @author Steven Raynham
 * @since 0.7
 *
 * @param void
 * @return null
 */
function jsCompatibleString( $string )
{
	$string = trim( $string );
	require_once( ABSPATH . 'wp-includes/formatting.php' );
	$string = wpautop( $string );
	$string = format_to_edit( $string, true );

	$pattern = '/(<script\b[^>]*>.*<\/script>)/Uis';
	$string = preg_replace_callback( $pattern, 'ileEncodeJavascript', $string );

	$search = array( "\r\n",
					 "\r",
					 "\n",
					 '&lt;',
					 '&gt;',
					 '<!--',
					 '-->',
					 '<![CDATA[',
					 ']]>',
					 '[ilejs]',
					 '[/ilejs]' );
	$replace = array( '',
					  '',
					  '',
					  '[ile]&lt;',
					  '&gt;[ile]',
					  '&lt;!--',
					  '--&gt;',
					  '&lt;![CDATA[',
					  ']]&gt;',
					  '<!--ilejs',
					  'ilejs-->' );
	$string = str_replace( $search, $replace, $string );
	$string = str_replace( '[ile]', '<!--ile-->', $string );
	return $string;
}

function ileEncodeJavascript( $matches )
{
	return '[ilejs]' . base64_encode( $matches[1] ) . '[/ilejs]';
}
