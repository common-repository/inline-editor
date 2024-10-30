<?php
/**
 * Inline Editor AJAX Save File
 *
 * @copyright 2009 Business Xpand
 * @license GPL v2.0
 * @author Steven Raynham
 * @version 0.7.6
 * @link http://www.businessxpand.com/
 * @since File available since Release 0.5
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
	if ( isset( $_POST['id'] ) && isset( $_POST['content'] ) && !empty( $_POST['id'] ) && !empty( $_POST['content'] ) ) {
		$opePost['ID'] = $_POST['id'];
		$opePost['post_content'] = rawurldecode( $_POST['content'] );
		$search = array( '<!--ile-->&lt;',
						 '&gt;<!--ile-->',
						 '&lt;!--',
						 '--&gt;',
						 '&lt;![CDATA[',
						 ']]&gt;',
						 '<!--ilejs',
						 'ilejs-->' );
		$replace = array( '[ilelt]',
						  '[ilegt]',
						  '<!--',
						  '-->',
						  '<![CDATA[',
						  ']]>',
						  '[ilejs]',
						  '[/ilejs]' );
		$opePost['post_content'] = str_replace( $search, $replace, $opePost['post_content'] );
		$search = array( '[ilelt]',
						 '[ilegt]' );
		$replace = array( '&lt;',
						  '&gt;' );
		$opePost['post_content'] = str_replace( $search, $replace, $opePost['post_content'] );

		$pattern = '/<p>\[ilejs\](.*)\[\/ilejs\]<\/p>/Uis';
		$opePost['post_content'] = preg_replace_callback( $pattern, 'ileDecodeJavascript', $opePost['post_content'] );

		$opePost['post_content'] = stripslashes( format_to_post( $opePost['post_content'] ) );

		if ( opeUpdatePost( $opePost ) === false )
			die( '{"response":"0","message":"' . __( 'Unable to save, database error generated.' ) . '"}' );
		else
			die( '{"response":"1","message":"' . __( 'Content updated.' ) . '"}' );
	} else {
		die( '{"response":"1","message":"' . __( 'No id or content.' ) . '"}');
	}
} else
	die( '{"response":"1","message":"' . __( 'You are not authorised to edit.' ) . '"}');

function opeUpdatePost( $post )
{
	global $wpdb;
	require_once( ABSPATH . WPINC . '/post.php' );
	wp_save_post_revision( $post['ID'] );
	return $wpdb->update(
		$wpdb->posts, array(
			'post_content' => $post['post_content'],
			'post_modified' => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 )
		),
		array( 'ID' => $post['ID'] )
	);
}

function ileDecodeJavascript( $matches )
{
	return "\r\n" . base64_decode( $matches[1] ) . "\r\n";
}
