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
if (!defined('DC_CONTEXT_ADMIN')) return;

dcPage::check('pages,contentadmin');

$post_id = '';
$cat_id = '';
$post_dt = '';
$post_type = 'related';
$post_format = $core->auth->getOption('post_format');
$post_password = '';
$post_url = '';
$post_lang = $core->auth->getInfo('user_lang');
$post_title = '';
$post_excerpt = '';
$post_excerpt_xhtml = '';
$post_content = '';
$post_content_xhtml = '';
$post_notes = '';
$post_status = $core->auth->getInfo('user_post_status');
$post_selected = true;
$post_open_comment = false;
$post_open_tb = false;

$page_title = __('New page');

$can_view_page = true;
$can_edit_post = $core->auth->check('contentadmin,pages',$core->blog->id);
$can_publish = $core->auth->check('contentadmin',$core->blog->id);
$can_delete = false;
$preview = false;

# If user can't publish
if (!$can_publish) {
	$post_status = -2;
}

# Status combo
foreach ($core->blog->getAllPostStatus() as $k => $v) {
	$status_combo[$v] = (string) $k;
}

# Formaters combo
foreach ($core->getFormaters() as $v) {
	$formaters_combo[$v] = $v;
}

$page_isfile = (!empty($_REQUEST['st']) && $_REQUEST['st'] == 'file')?true:false;
$page_relatedfile = '';

# Get entry informations
if (!empty($_REQUEST['id']))
{
	$params = array();
	$params['post_id'] = $_REQUEST['id'];
	$params['post_type'] = 'related';
	
	$post = $core->blog->getPosts($params, false);
	$post->extend("rsRelated");
	
	if ($post->isEmpty())
	{
		$core->error->add(__('This page does not exist.'));
		$can_view_page = false;
	}
	else
	{
		$post_id = $post->post_id;
		$cat_id = $post->cat_id;
		$post_dt = date('Y-m-d H:i',strtotime($post->post_dt));
		$post_format = $post->post_format;
		$post_password = $post->post_password;
		$post_url = $post->post_url;
		$post_lang = $post->post_lang;
		$post_title = $post->post_title;
		$post_excerpt = $post->post_excerpt;
		$post_excerpt_xhtml = $post->post_excerpt_xhtml;
		$post_content = $post->post_content;
		$post_content_xhtml = $post->post_content_xhtml;
		$post_notes = $post->post_notes;
		$post_status = $post->post_status;
		$post_selected = (boolean) $post->post_selected;
		$post_open_comment = false;
		$post_open_tb = false;
		
		$page_title = __('Edit page');
		
		$can_edit_post = $post->isEditable();
		$can_delete = $post->isDeletable();

		try {
			$core->meta = new dcMeta($core);
			$post_metas = $core->meta->getMetaRecordset($post->post_meta,'related_file');
			if (!$post_metas->isEmpty()) {
				$page_relatedfile = $post_metas->meta_id;
				$page_isfile = true;
			}
		} catch (Exception $e) {}
		
	}
}

if ($page_isfile) {
	$post_content = '/** external content **/';
	$post_content_xhtml = '/** external content **/';

	$related_pages_files = array('-' => '');
	$dir = @dir($core->blog->settings->related->related_files_path);
	$allowed_exts = array('php','html','xml','txt');
	
	if ($dir)
	{
		while (($entry = $dir->read()) !== false) {
			$entry_path = $dir->path.'/'.$entry;
			if (in_array(files::getExtension($entry),$allowed_exts)) {
				if (is_file($entry_path) && is_readable($entry_path)) {
					$related_pages_files[$entry] = $entry;
				}
			}
		}
	}
}	

# Format excerpt and content
if (!empty($_POST) && $can_edit_post)
{
	$post_format = $_POST['post_format'];
	$post_excerpt = $_POST['post_excerpt'];
	if (!$page_isfile) {
		$post_content = $_POST['post_content'];
	}
	
	$post_title = $_POST['post_title'];
		
	if (isset($_POST['post_status'])) {
		$post_status = (integer) $_POST['post_status'];
	}
	
	if (empty($_POST['post_dt'])) {
		$post_dt = '';
	} else {
		$post_dt = strtotime($_POST['post_dt']);
		$post_dt = date('Y-m-d H:i',$post_dt);
	}
	
	$post_lang = $_POST['post_lang'];
	$post_password = !empty($_POST['post_password']) ? $_POST['post_password'] : null;
	
	$post_notes = $_POST['post_notes'];
	
	if (isset($_POST['post_url'])) {
		$post_url = $_POST['post_url'];
	}
	
	$core->blog->setPostContent(
		$post_id,$post_format,$post_lang,
		$post_excerpt,$post_excerpt_xhtml,$post_content,$post_content_xhtml
	);
	
	$preview = !empty($_POST['preview']);

	if ($page_isfile)
	{
		$related_upl = null;
		if (!empty($_FILES['up_file']['name'])) {
			$related_upl = true;
		} elseif (!empty($_POST['repository_file']) && in_array($_POST['repository_file'],$related_pages_files)) {
			$related_upl = false;
		}
		
		if ($related_upl !== null) {
			try
			{
				if ($related_upl) {
					files::uploadStatus($_FILES['up_file']);
					$src_file = $_FILES['up_file']['tmp_name'];
					$trg_file = $core->blog->settings->related->related_files_path.'/'.$_FILES['up_file']['name'];
					if (move_uploaded_file($src_file,$trg_file)) {
						$page_relatedfile = $_FILES['up_file']['name'];
					}
				} else {
					$page_relatedfile = $_POST['repository_file'];
				}
			}
			catch (Exception $e)
			{
				$core->error->add($e->getMessage());
			}
		}
	}
}

# Create or update post
if (!empty($_POST) && !empty($_POST['save']) && $can_edit_post)
{
	$cur = $core->con->openCursor($core->prefix.'post');
	
	$cur->post_title = $post_title;
	$cur->cat_id = null;
	$cur->post_dt = $post_dt ? date('Y-m-d H:i:00',strtotime($post_dt)) : '';
	$cur->post_type = $post_type;
	$cur->post_format = $post_format;
	$cur->post_password = $post_password;
	$cur->post_lang = $post_lang;
	$cur->post_type = $post_type;
	$cur->post_excerpt = $post_excerpt;
	$cur->post_excerpt_xhtml = $post_excerpt_xhtml;
	$cur->post_content = $post_content;
	$cur->post_content_xhtml = $post_content_xhtml;
	$cur->post_notes = $post_notes;
	$cur->post_status = $post_status;
	$cur->post_selected = (integer)$post_selected;
	$cur->post_open_comment = 0;
	$cur->post_open_tb = 0;
	
	if (isset($_POST['post_url'])) {
		$cur->post_url = $post_url;
	}
		
	# Update post
	if ($post_id)
	{
		try
		{
			if ($page_isfile && empty($page_relatedfile)) {
				throw new Exception(__('Missing file.'));
			}
	
			# --BEHAVIOR-- adminBeforePostUpdate
			$core->callBehavior('adminBeforePostUpdate',$cur,$post_id);
			# --BEHAVIOR-- adminBeforePageUpdate
			$core->callBehavior('adminBeforePageUpdate',$cur,$post_id);
			
			$core->con->begin();
			$core->blog->updPost($post_id,$cur);
			if ($page_isfile) {
				try {
					if ($core->meta === null) {
						$core->meta = new dcMeta($core);
					}
					$core->meta->delPostMeta($post_id,'related_file');
					$core->meta->setPostMeta($post_id,'related_file',$page_relatedfile);
				}
				catch (Exception $e) {
					$core->con->rollback();
					throw $e;
				}
			}
			$core->con->commit();
			
			# --BEHAVIOR-- adminAfterPostUpdate
			$core->callBehavior('adminAfterPostUpdate',$cur,$post_id);
			# --BEHAVIOR-- adminAfterPageUpdate
			$core->callBehavior('adminAfterPageUpdate',$cur,$post_id);
			
			http::redirect('plugin.php?p=related&do=edit&id='.$post_id.'&upd=1');
		}
		catch (Exception $e)
		{
			$core->error->add($e->getMessage());
		}
	}
	else
	{
		$cur->user_id = $core->auth->userID();
		if (!isset($_POST['post_url'])) {
			$cur->post_url = text::str2URL($post_title);
		}
		
		try
		{
			if ($page_isfile && empty($page_relatedfile)) {
				throw new Exception(__('Missing file.'));
			}
	
			# --BEHAVIOR-- adminBeforePostCreate
			$core->callBehavior('adminBeforePostCreate',$cur);
			# --BEHAVIOR-- adminBeforePageCreate
			$core->callBehavior('adminBeforePageCreate',$cur);
			
			$core->con->begin();
			$return_id = $core->blog->addPost($cur);
			if ($page_isfile) {
				try {
					if ($core->meta === null) {
						$core->meta = new dcMeta($core);
					}
					$core->meta->setPostMeta($return_id,'related_file',$page_relatedfile);
				}
				catch (Exception $e) {
					$core->con->rollback();
					throw $e;
				}
			}
			$core->con->commit();
			
			# --BEHAVIOR-- adminAfterPostCreate
			$core->callBehavior('adminAfterPostCreate',$cur,$return_id);
			# --BEHAVIOR-- adminAfterPageCreate
			$core->callBehavior('adminAfterPageCreate',$cur,$return_id);
			
			http::redirect('plugin.php?p=related&do=edit&id='.$return_id.'&crea=1');
		}
		catch (Exception $e)
		{
			$core->error->add($e->getMessage());
		}
	}
}

if (!empty($_POST['delete']) && $can_delete)
{
	try {
		$core->blog->delPost($post_id);
		http::redirect($p_url);
	} catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

/* DISPLAY
-------------------------------------------------------- */
$default_tab = 'edit-entry';
if (!$can_edit_post || !empty($_POST['preview'])) {
	$default_tab = 'preview-entry';
}
?>
<html>
<head>
	<title><?php echo __('Related pages'); ?></title>
<?php
echo dcPage::jsDatePicker().
	dcPage::jsToolBar().
  	dcPage::jsModal().
	dcPage::jsLoad(DC_ADMIN_URL.'?pf=related/js/_page.js').
	dcPage::jsConfirmClose('entry-form').
	# --BEHAVIOR-- adminRelatedHeaders
	$core->callBehavior('adminRelatedHeaders').	
	dcPage::jsPageTabs($default_tab);
?>
</head>

<body>
<?php
if (!empty($_GET['upd'])) {
		echo '<p class="message">'.__('Page has been successfully updated.').'</p>';
}
elseif (!empty($_GET['crea'])) {
		echo '<p class="message">'.__('Page has been successfully created.').'</p>';
}
?>

	<h2><?php echo html::escapeHTML($core->blog->name); ?> &gt; <a href="<?php echo $p_url; ?>"><?php echo __('Related pages'); ?></a>

<?php
if ($post_id && $post->post_status == 1) {
	echo ' - <a id="post-preview" href="'.$post->getURL().'" class="button">'.__('View page').'</a>';
} elseif ($post_id) {
	$preview_url =
	$core->blog->url.$core->url->getBase('pagespreview').'/'.
	$core->auth->userID().'/'.
	http::browserUID(DC_MASTER_KEY.$core->auth->userID().$core->auth->getInfo('user_pwd')).
	'/'.$post->post_url;
	echo ' - <a id="post-preview" href="'.$preview_url.'" class="button">'.__('Preview page').'</a>';
}
echo '</h2>';

# Exit if we cannot view page
if (!$can_view_page) {
	exit;
}

/* Page form if we can edit page
-------------------------------------------------------- */
if ($can_edit_post)
{
	echo '<div class="multi-part" title="'.__('Edit page').'" id="edit-entry">';
	echo '<form action="plugin.php?p=related&amp;do=edit" method="post" id="entry-form" enctype="multipart/form-data">';
	echo '<div id="entry-sidebar">';
	
	echo
	'<p><label>'.__('Page status:').dcPage::help('post','p_status').
	form::combo('post_status',$status_combo,$post_status,'',3,!$can_publish).
	'</label></p>'.
	
	'<p><label>'.__('Text formating:').dcPage::help('post','p_format').
	form::combo('post_format',$formaters_combo,$post_format,'',3).
	'</label></p>'.
	
	'<div class="lockable">'.
	'<p><label>'.__('Basename:').dcPage::help('post','p_basename').
	form::field('post_url',10,255,html::escapeHTML($post_url),'maximal',3).
	'</label></p>'.
	'<p class="form-note warn">'.
	__('Warning: If you set the URL manually, it may conflict with another entry.').
	'</p>'.
	'</div>';

	echo	
	'<div id="page_options">'.
	'<h3 style="display:inline;">'.__('More options').'</h3>'.
	'<p><label>'.__('Page lang:').dcPage::help('post','p_lang').
	form::field('post_lang',5,255,html::escapeHTML($post_lang),'',3).
	'</label></p>'.

	'<p><label>'.__('Published on:').dcPage::help('post','p_date').
	form::field('post_dt',16,16,$post_dt,'',3).
	'</label></p>'.
	
	'<p><label>'.__('Page password:').dcPage::help('post','p_password').
	form::field('post_password',10,32,html::escapeHTML($post_password),'maximal',3).
	'</label></p>'.	
	'</div>';
		
	# --BEHAVIOR-- adminPageFormSidebar
	$core->callBehavior('adminPageFormSidebar',isset($post) ? $post : null);
	
	echo '</div>';		// End #entry-sidebar
	
	echo '<div id="entry-content"><fieldset class="constrained">';
	
	echo
	'<p class="col"><label class="required" title="'.__('Required field').'">'.__('Title:').
	dcPage::help('post','p_title').
	form::field('post_title',20,255,html::escapeHTML($post_title),'maximal',2).
	'</label></p>'.
	
	'<p class="area" id="excerpt-area"><label for="post_excerpt">'.__('Summary:').
	dcPage::help('post','p_excerpt').'</label> '.
	form::textarea('post_excerpt',50,5,html::escapeHTML($post_excerpt),'',2).
	'</p>';
	
	if (!$page_isfile)
	{
		echo
		'<p class="area"><label class="required" title="'.__('Required field').'" '.
		'for="post_content">'.__('Content:').
		dcPage::help('post','p_content').'</label> '.
		form::textarea('post_content',50,$core->auth->getOption('edit_size'),html::escapeHTML($post_content),'',2).
		'</p>';
	}
	else
	{
		echo
		'<p class="col"><label class="required" title="'.__('Required field').'" '.
		'for="page_relatedfile">'.__('Included file:').
		dcPage::help('post','page_relatedfile').'</label></p> ';
		
		echo
		'<fieldset>'.
		'<p><label>'.__('Pick up a local file in your related pages repository').' '.
		form::combo('repository_file',$related_pages_files,$page_relatedfile).
		'</label></p>'.
		form::hidden(array('MAX_FILE_SIZE'),DC_MAX_UPLOAD_SIZE).
		'<p><label>'.__('or upload a new file').' '.
		'<input type="file" id="up_file" name="up_file" size="20" />'.
		'</label></p>'.
		'</fieldset>';
		
		echo
		form::hidden('st','file');
	}
	
	echo
	'<p class="area" id="notes-area"><label>'.__('Notes:').
	dcPage::help('post','p_notes').'</label>'.
	form::textarea('post_notes',50,5,html::escapeHTML($post_notes),'',2).
	'</p>';
	
	# --BEHAVIOR-- adminPageForm
	$core->callBehavior('adminPageForm',isset($post) ? $post : null);
	
	echo
	'<p>'.
	$core->formNonce().
	($post_id ? form::hidden('id',$post_id) : '').
	'<input type="submit" value="'.__('save').' (s)" tabindex="4" '.
	'accesskey="s" name="save" /> '.
	($can_delete ? '<input type="submit" value="'.__('delete').'" name="delete" />' : '').
	'</p>';
	
	echo '</fieldset></div>';		// End #entry-content
	echo '</form>';
	echo '</div>';
}
?>
	</body>
</html>