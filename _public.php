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

// Public behaviors definition and binding
/**
 * 
 */
class relatedPublicBehaviors
{
	/**
	 * 
	 */
	public static function addTplPath($core)
	{
		$core->tpl->setPath($core->tpl->getPath(), dirname(__FILE__).'/default-templates');
	}

	/**
	 * 
	 */
	public static function templateBeforeBlock()
	{
		$args = func_get_args();
		array_shift($args);
		
		if ($args[0] == 'Entries') {
			if (!empty($args[1])) {
				$attrs = $args[1];
				if (!empty($attrs['type']) && $attrs['type'] == 'related') {
					$p = "<?php \$params['post_type'] = 'related'; ?>\n";
					if (!empty($attrs['basename'])) {
						$p .= "<?php \$params['post_url'] = '".$attrs['basename']."'; ?>\n";
					}
					return $p;
				}
			}
		}
	}

	/**
	 * 
	 */
	public static function coreBlogGetPosts($rs)
	{
		$rs->extend("rsRelatedBase");
	}

	/**
	 * 
	 */
	public static function sitemapsURLsCollect($sitemaps)
	{
		global $core;
		
		if ($core->blog->settings->sitemaps->sitemaps_related_url) {
			$freq = $sitemaps->getFrequency($core->blog->settings->sitemaps->sitemaps_related_fq);
			$prio = $sitemaps->getPriority($core->blog->settings->sitemaps->sitemaps_related_pr);

			$rs = $core->blog->getPosts(array('post_type' => 'related','post_status' => 1,'no_content' => true));
			$rs->extend('rsRelated');
				
			while ($rs->fetch()) {
				if ($rs->post_password != '') continue;
				$sitemaps->addEntry($rs->getURL(),$prio,$freq,$rs->getISO8601Date());
			}
		}
	}
}

$core->addBehavior('coreBlogGetPosts',    array('relatedPublicBehaviors','coreBlogGetPosts'));
$core->addBehavior('publicBeforeDocument',array('relatedPublicBehaviors','addTplPath'));
$core->addBehavior('templateBeforeBlock', array('relatedPublicBehaviors','templateBeforeBlock'));
$core->addBehavior('sitemapsURLsCollect', array('relatedPublicBehaviors','sitemapsURLsCollect'));
$core->addBehavior('initWidgets',array('widgetsRelated','init'));


// Templates tags definition and binding
/**
 * 
 */
class relatedTemplates
{
	/**
	 * 
	 */
	public static function PageContent($attr)
	{
		global $core, $_ctx;

		$urls = '0';
		if (!empty($attr['absolute_urls'])) {
			$urls = '1';
		}

		$f = $core->tpl->getFilters($attr);
		$p =
		"<?php if ((\$related_file = \$_ctx->posts->getRelatedFilename()) !== false) { \n".
			"if (files::getExtension(\$related_file) == 'php') { \n".
				'include $related_file;'."\n".
			"} else { \n".
				'$previous_tpl_path = $core->tpl->getPath();'."\n".
				'$core->tpl->setPath($core->blog->settings->related->related_files_path);'."\n".
				'echo $core->tpl->getData(basename($related_file));'."\n".
				'$core->tpl->setPath($previous_tpl_path);'."\n".
				'unset($previous_tpl_path);'."\n".
			"}\n".
			'unset($related_file);'."\n".
		"} else { \n".
			'echo '.sprintf($f,'$_ctx->posts->getContent('.$urls.')').';'."\n".
		"} ?>\n";
		
		return $p;
	}
}

$core->tpl->addValue('EntryContent', array('relatedTemplates', 'PageContent'));
?>