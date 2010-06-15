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

//$core->addBehavior('initWidgets',array('widgetsRelated','init'));


class relatedHelpers
{
	public static function getPublicList($rs)
	{
		if (!$rs || $rs->isEmpty()) return;
		
		$res = array();
		while ($rs->fetch()) {
			if ($rs->post_status != 1) continue;
			if (($pos = $rs->getPosition()) === null) continue;
			if ($pos <= 0) $pos = 10000;
			$res[] = array(
				'id' => $rs->post_id,
				'title' => $rs->post_title,
				'url'   => $rs->getURL(),
				'active' => $rs->post_selected,
				'order'  => $pos
				);
		}
		usort($res,array('relatedHelpers','orderCallBack'));
		return $res;
	}
	
	protected static function orderCallBack($a,$b)
	{
		if ($a['order'] == $b['order']) return 0;		
		return $a['order'] > $b['order'] ? 1 : -1;
	}
}

class widgetsRelated
{
	public static function pagesList($w)
	{
		global $core;
		
		if ($w->homeonly && $core->url->type != 'default') {
			return;
		}
		
		$params['post_type'] = 'related';
		$params['no_content'] = true;
		$params['post_selected'] = true;
		$rs = $core->blog->getPosts($params);
		$rs->extend('rsRelatedBase');
		
		if ($rs->isEmpty()) {
			return;
		}
		
		$title = $w->title ? html::escapeHTML($w->title) : __('Related pages');

		$res =
		'<div id="related">'.
		'<h2>'.$title.'</h2>'.
		'<ul>';
		
		$pages_list = relatedHelpers::getPublicList($rs);
		foreach ($pages_list as $page) {
			$res .= '<li><a href="'.$page['url'].'">'.
			html::escapeHTML($page['title']).'</a></li>';
		}
		
		$res .= '</ul></div>';
		return $res;
	}

	public static function init($w)
	{
	    $w->create('related',__('Related pages'),array('widgetsRelated','pagesList'));
	    $w->related->setting('title',__('Title:'),'');
	    $w->related->setting('homeonly',__('Home page only'),1,'check');
	}
}
?>