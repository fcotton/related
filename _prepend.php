<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of Related, a plugin for DotClear2.
# Copyright (c) 2006-2010 Pep and contributors.
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------
if (!defined('DC_RC_PATH')) return;

global $__autoload, $core;

$__autoload['dcRelated']		= dirname(__FILE__).'/inc/lib.related.php';
$__autoload['rsRelated']		= dirname(__FILE__).'/inc/lib.related.php';
$__autoload['adminPageList']	= dirname(__FILE__).'/inc/lib.related.php';
$__autoload['widgetsRelated'] = dirname(__FILE__).'/inc/widgets.php';

// Setting custom URL handlers
$url_prefix = $core->blog->settings->related->related_url_prefix;
$url_prefix = (empty($url_prefix))?'static':$url_prefix;
$url_pattern = $url_prefix.'/(.+)$';
$core->url->register('related',$url_prefix,$url_pattern,array('relatedUrlHandlers','related'));
$core->url->register('relatedpreview','relatedpreview','^pagespreview/(.+)$',array('relatedUrlHandlers','relatedpreview'));
unset($url_prefix,$url_pattern);

// Registering new post_type
$core->setPostType('related','plugin.php?p=related&do=edit&id=%d',$core->url->getBase('related').'/%s');

/**
 * 
 */
class relatedUrlHandlers extends dcUrlHandlers
{
	/**
	 * 
	 */
	public static function related($args)
	{
		global $core, $_ctx;
		
		if ($args == '') {
			self::p404();
		}

		$core->blog->withoutPassword(false);

		$params['post_url'] = $args;
		$params['post_type'] = 'related';
		$_ctx->posts = $core->blog->getPosts($params);
		$_ctx->posts->extend('rsRelated');

		$core->blog->withoutPassword(true);

		if ($_ctx->posts->isEmpty()) {
			# No entry
			self::p404();
		}

		$post_id = $_ctx->posts->post_id;
		$post_password = $_ctx->posts->post_password;

		# Password protected entry
		if ($post_password != '' && !$_cxt->preview) {
			# Get passwords cookie
			if (isset($_COOKIE['dc_passwd'])) {
				$pwd_cookie = unserialize($_COOKIE['dc_passwd']);
			}
			else {
				$pwd_cookie = array();
			}
			
			# Check for match
			if ((!empty($_POST['password']) && $_POST['password'] == $post_password)
			|| (isset($pwd_cookie[$post_id]) && $pwd_cookie[$post_id] == $post_password)) {
				$pwd_cookie[$post_id] = $post_password;
				setcookie('dc_passwd',serialize($pwd_cookie),0,'/');
			}
			else {
				self::serveDocument('password-form.html','text/html',false);
				exit;
			}
		}

		if ($filename = $_ctx->posts->getRelatedFilename()) {
			$GLOBALS['mod_files'][] = $filename;
		}

		self::serveDocument('external.html');
	}

	/**
	 * 
	 */
	public static function relatedpreview($args)
	{
		$core = $GLOBALS['core'];
		$_ctx = $GLOBALS['_ctx'];
		
		if (!preg_match('#^(.+?)/([0-9a-z]{40})/(.+?)$#',$args,$m)) {
			# The specified Preview URL is malformed.
			self::p404();
		}
		else {
			$user_id = $m[1];
			$user_key = $m[2];
			$post_url = $m[3];
			if (!$core->auth->checkUser($user_id,null,$user_key)) {
				# The user has no access to the entry.
				self::p404();
			}
			else {
				$_ctx->preview = true;
				self::related($post_url);
			}
		}
	}
}
?>