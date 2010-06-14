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
if (!defined('DC_CONTEXT_ADMIN')) exit;
dcPage::check('pages,contentadmin');

$repository = $core->blog->settings->related->related_files_path;
$url_prefix = $core->blog->settings->related->related_url_prefix;
$default_tab = 'pages_compose';

/**
 * Build "Manage Pages" tab
 */
$params = array(
	'post_type' => 'related'
);

$page = !empty($_GET['page']) ? $_GET['page'] : 1;
$nb_per_page =  30;
if (!empty($_GET['nb']) && (integer) $_GET['nb'] > 0) {
	$nb_per_page = (integer) $_GET['nb'];
}

$params['limit'] = array((($page-1)*$nb_per_page),$nb_per_page);
$params['no_content'] = true;

# Get pages
try {
	$pages = $core->blog->getPosts($params);
	$pages->extend("rsRelated");
	$counter = $core->blog->getPosts($params,true);
	$page_list = new adminPageList($core,$pages,$counter->f(0));
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}

# Actions combo box
$combo_action = array();
if ($core->auth->check('publish,contentadmin',$core->blog->id)) {
	$combo_action[__('publish')] = 'publish';
	$combo_action[__('unpublish')] = 'unpublish';
	$combo_action[__('mark as pending')] = 'pending';
}
if ($core->auth->check('admin',$core->blog->id)) {
	$combo_action[__('change author')] = 'author';
}
if ($core->auth->check('delete,contentadmin',$core->blog->id)) {
	$combo_action[__('delete')] = 'delete';
}

# --BEHAVIOR-- adminPagesActionsCombo
$core->callBehavior('adminPagesActionsCombo',array(&$combo_action));

/**
 * Manage public list if requested
 */
if (isset($_POST['pages_upd']))
{
	$default_tab = 'pages_order';
	
	$public_pages = dcRelated::getPublicList($pages);
	$visible = (!empty($_POST['p_visibles']) && is_array($_POST['p_visibles']))?$_POST['p_visibles']:array();
	$order = (!empty($_POST['public_order']))?explode(',',$_POST['public_order']):array();
	
	try {
		$i = 1;
		$meta = new dcMeta($core);
		foreach ($public_pages as $c_page) {
			$cur = $core->con->openCursor($core->prefix.'post');
			$cur->post_upddt = date('Y-m-d H:i:s');
			$cur->post_selected = (integer)in_array($c_page['id'],$visible);
			$cur->update('WHERE post_id = '.$c_page['id']);

			if (!empty($order)) {
				$pos = array_search($c_page['id'],$order);
				$pos = (integer)$pos + 1;
				$meta->delPostMeta($c_page['id'],'related_position');
				$meta->setPostMeta($c_page['id'],'related_position',$pos);
			}
		}
		$core->blog->triggerBlog();
		http::redirect($p_url.'&reord=1');
	}
	catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

if (!empty($_GET['reord'])) {
	$msg = __('Pages list has been successfully sorted.');
	$default_tab = 'pages_order';
}

/**
 * Update configuration if requested
 */
if (isset($_POST['saveconfig']))
{
	$default_tab = 'pages_config';
	
	if (trim($_POST['repository']) == '') {
		$tmp_repository = $core->blog->public_path.'/related';
	} else {
		$tmp_repository = trim($_POST['repository']);
	}
	
	if (trim($_POST['url_prefix']) == '') {
		$url_prefix = 'static';
	} else {
		$url_prefix = text::str2URL(trim($_POST['url_prefix']));
	}
	
	$core->blog->settings->addNamespace('related');
	$core->blog->settings->related->put('related_url_prefix',$url_prefix);
	
	if (is_dir($tmp_repository) && is_writable($tmp_repository) && is_writable($tmp_repository)) {
		$core->blog->settings->related->put('related_files_path', $tmp_repository);
		$repository = $tmp_repository;
		$msg = __('Configuration successfully updated.');
	} else {
		$core->error->add(__('Directory for related files repository needs to allow read and write access.'));
	}
}

/**
 * Display full panel
 */
?>
<html>
	<head>
		<title><?php echo __('Related pages'); ?></title>
		<?php
		echo
		dcPage::jsToolMan().
		dcPage::jsPageTabs($default_tab).
		dcPage::jsLoad('index.php?pf=related/_pages.js');
		?>
	</head>
	<body>
		<h2><?php echo html::escapeHTML($core->blog->name); ?> &gt; <?php echo __('Related pages'); ?></h2>
		<?php if (!empty($msg)) echo '<p class="message">'.$msg.'</p>'; ?>

<?php
// "Manage Pages" tab
echo
'<div class="multi-part" id="pages_compose" title="'.__('Manage pages').'">'.
'<p><a class="button" href="plugin.php?p=related&amp;do=edit&amp;st=post">'.__('New post as page').'</a>&nbsp;-&nbsp;'.
'<a class="button" href="plugin.php?p=related&amp;do=edit&amp;st=file">'.__('New included page').'</a></p>';

if (!$core->error->flag())
{
	$page_list->display($page,$nb_per_page,
	'<form action="posts_actions.php" method="post" id="form-pages">'.
	'%s'.
	'<div class="two-cols">'.
	'<p class="col checkboxes-helpers"></p>'.
	'<p class="col right">'.__('Selected entries action:').
	form::combo('action',$combo_action).
	'<input type="submit" value="'.__('ok').'" /></p>'.
	form::hidden(array('post_type'),'related').
	form::hidden(array('redir'),html::escapeHTML($_SERVER['REQUEST_URI'])).
	$core->formNonce().
	'</div>'.
	'</form>'
	);
}
echo '</div>';

// "Arrange public list" tab
echo 
'<div class="multi-part" id="pages_order" title="'.__('Arrange public list').'">';
if (!$core->error->flag())
{
	$public_pages = dcRelated::getPublicList($pages);
	if (!empty($public_pages))
	{
		echo
		'<form action="plugin.php?p=related" method="post" id="form-public-pages">'.
		'<table class="dragable ">'.
		'<thead><tr>'.
		'<th>'.__('Order').'</th>'.
		'<th>'.__('Visible').'</th>'.
		'<th class="nowrap maximal">'.__('Page title').'</th>'.
		'</tr></thead>'.
		'<tbody id="pages-list" >';

		$i = 1;
		foreach ($public_pages as $page)
		{
			echo
			'<tr class="line'.($page['active']? '' : ' offline').'" id="p_'.$page['id'].'">'.
			'<td class="handle">'.form::field(array('p_order['.$page['id'].']'),2,5,(string) $i).'</td>'.
			'<td class="nowrap">'.form::checkbox(array('p_visibles[]'),$page['id'],$page['active']).'</td>'.
			'<td class="nowrap">'.$page['title'].'</td>'.
			'</tr>';
			$i++;
		}
		echo
		'</tbody></table>'.
		'<p>'.form::hidden('public_order','').
		$core->formNonce().
		'<input type="submit" name="pages_upd" value="'.__('Save').'" /></p>'.
		'</form>';
	}
	else
	{
		echo '<p><strong>'.__('No page').'</strong></p>';
	}
}
echo '</div>';

// "Options" tab
if ($core->auth->check('admin',$core->blog->id))
{
	echo 
	'<div class="multi-part" id="pages_config" title="'.__('Options').'">'.
	'<form method="post" action="plugin.php">'.
	'<fieldset>'.
	'<legend>'. __('General options').'</legend>'.
	'<p><label class=" classic">'. __('Repository path :').' '.
	form::field('repository', 60, 255, $repository).
	'</label></p>'.
	'</fieldset>'.
	'<fieldset>'.
	'<legend>'. __('Advanced options').'</legend>'.
	'<p><label class=" classic">'. __('URL prefix :').' '.
	form::field('url_prefix', 60, 255, $url_prefix).
	'</label></p>'.
	'</fieldset>'.
	'<p><input type="hidden" name="p" value="related" />'.
	$core->formNonce().
	'<input type="submit" name="saveconfig" value="'.__('Save configuration').'" /></p>'.
	'</form>'.
	'</div>';
}
echo dcPage::helpBlock('relatedpages');
?>
	</body>
</html>