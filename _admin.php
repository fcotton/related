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
require_once dirname(__FILE__).'/_widgets.php';

$_menu['Blog']->addItem(
	__('Related pages'),
	'plugin.php?p=related',
	'index.php?pf=related/icon.png',
	preg_match('/plugin.php\?p=related(&.*)?$/',$_SERVER['REQUEST_URI']),
	$core->auth->check('contentadmin,pages',$core->blog->id)
);

$core->auth->setPermissionType('pages',__('manage related pages'));
$core->addBehavior('sitemapsDefineParts',array('behaviorRelated','sitemapsDefineParts'));
?>
