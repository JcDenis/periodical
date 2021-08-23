<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of periodical, a plugin for Dotclear 2.
# 
# Copyright (c) 2009-2013 Jean-Christian Denis and contributors
# contact@jcdenis.fr http://jcd.lv
# 
# Licensed under the GPL version 2.0 license.
# A copy of this license is available in LICENSE file or at
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------

if (!defined('DC_RC_PATH')){return;}

# Add ability to send social messages when a feed is update
class periodicalSoCialMeWriter
{
	public static function soCialMeWriterMarker($rs)
	{
		$rs['periodicalcreate'] = array(
			'name' => __('New periodical publication'),
			'description' => __('When an entry is published on a period'),
			'action' => array('Message','Link'),
			'format' => array('Message'),
			'wildcards' => array('Message' => array('%posttitle%','%posturl%','%shortposturl%','%postauthor%','%sitetitle%','%siteurl%','%shortsiteurl%'))
		);
	}
	
	public static function periodicalAfterPublishedPeriodicalEntry($core,$post,$period)
	{
		$key = 'periodicalcreate';
		
		# Is install
		if (!$core->plugins->moduleExists('soCialMe')) return;
		
		# Is active
		if (!$core->blog->settings->soCialMeWriter->active) return;
		
		# Load services
		$soCialMeWriter = new soCialMeWriter($core);
		
		# List of service per action
		$actions = $soCialMeWriter->getMarker('action');
		
		# List of format per type
		$formats = $soCialMeWriter->getMarker('format');
		
		# prepare data
		$shortposturl = soCialMeWriter::reduceURL($post->getURL());
		$shortposturl = $shortposturl ? $shortposturl : $post->getURL();
		
		$shortsiteurl = soCialMeWriter::reduceURL($core->blog->url);
		$shortsiteurl = $shortsiteurl ? $shortsiteurl : $core->blog->url;
		
		# sendMessage
		if (!empty($formats[$key]['Message']) && !empty($actions[$key]['Message']))
		{
			// parse message
			$message_txt = str_replace(
				array('%posttitle%','%posturl%','%shortposturl%','%postauthor%','%sitetitle%','%siteurl%','%shortsiteurl%'),
				array($post->post_title,$post->getURL(),$shortposturl,$post->getUserCN(),$core->blog->name,$core->blog->url,$shortsiteurl),
				$formats[$key]['Message']
			);
			
			// send message
			if (!empty($message_txt))
			{
				foreach($actions[$key]['Message'] as $service_id)
				{
					$soCialMeWriter->play($service_id,'Message','Content',$message_txt);
				}
			}
		}
		
		# sendLink
		if (!empty($actions[$key]['Link']))
		{
			foreach($actions[$key]['Link'] as $service_id)
			{
				$soCialMeWriter->play($service_id,'Link','Content',$post->post_title,$shortposturl);
			}
		}
		
		# sendData
		// not yet implemented
		
		#sendArticle
		// not yet implemented
	}
}
?>