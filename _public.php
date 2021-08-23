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

if (!defined('DC_RC_PATH')) {

	return null;
}

if (!in_array($core->url->type, array('default', 'feed'))) {

	return null;
}

$core->blog->settings->addNamespace('periodical'); 

$core->addBehavior(
	'publicBeforeDocument',
	array('publicPeriodical', 'publicBeforeDocument')
);

/**
 * @ingroup DC_PLUGIN_PERIODICAL
 * @brief Periodical - public methods.
 * @since 2.6
 */
class publicPeriodical
{
	/**
	 * Publish periodical
	 * 
	 * @param  dcCore $core dcCore instance
	 */
	public static function publicBeforeDocument(dcCore $core)
	{
		try {
			$per = new periodical($core);
			$s = $core->blog->settings->periodical;

			$per->lockUpdate();

			# Get periods
			$periods =  $core->auth->sudo(array($per, 'getPeriods'));

			# No period
			if ($periods->isEmpty()) {
				$per->unlockUpdate();

				return null;
			}

			$now = dt::toUTC(time());
			$posts_order = $s->periodical_pub_order;
			if (!preg_match('/^(post_dt|post_creadt|post_id) (asc|desc)$/', $posts_order)) {
				$posts_order = 'post_dt asc';
			}
			$cur_period = $core->con->openCursor($core->prefix.'periodical');

			while($periods->fetch()) {

				# Check if period is ongoing
				$cur_tz = strtotime($periods->periodical_curdt);
				$end_tz = strtotime($periods->periodical_enddt);
				$now_tz = $now + dt::getTimeOffset($periods->periodical_tz, $now);

				if ($now_tz > $cur_tz && $now_tz < $end_tz) {

					$last_nb = 0;
					$last_tz = $cur_tz;
					
					$max_nb = $periods->periodical_pub_nb;
					$max_tz = $end_tz < $now_tz ? $end_tz : $now_tz;
					
					# Calculate nb of posts to get
					$loop_tz = $cur_tz;
					$limit = 0;
					try {
						while(1) {
							if ($loop_tz > $max_tz) {
								break;
							}
							$loop_tz = $per->getNextTime($loop_tz, $periods->periodical_pub_int);
							$limit += 1;
						}
					}
					catch (Exception $e) { }

					# If period need update
					if ($limit > 0) {

						# Get posts to publish related to this period
						$posts_params = array();
						$posts_params['periodical_id'] = $periods->periodical_id;
						$posts_params['post_status'] = '-2';
						$posts_params['order'] = $posts_order;
						$posts_params['limit'] = $limit * $max_nb;
						$posts_params['no_content'] = true;
						$posts =  $core->auth->sudo(array($per, 'getPosts'), $posts_params);

						if (!$posts->isEmpty()) {
							$cur_post = $core->con->openCursor($core->prefix.'post');
							
							while($posts->fetch()) {

								# Publish post with right date
								$cur_post->clean();
								$cur_post->post_status = 1;

								# Update post date with right date
								if ($s->periodical_upddate) {
									$cur_post->post_dt = date('Y-m-d H:i:s', $last_tz);
									$cur_post->post_tz = $periods->periodical_tz;
								}
								else {
									$cur_post->post_dt = $posts->post_dt;
								}

								# Also update post url with right date
								if ($s->periodical_updurl) {
									$cur_post->post_url = $core->blog->getPostURL('', $cur_post->post_dt, $posts->post_title, $posts->post_id);
								}

								$cur_post->update(
									'WHERE post_id = '.$posts->post_id.' '.
									"AND blog_id = '".$core->con->escape($core->blog->id)."' "
								);

								# Delete post relation to this period
								$per->delPost($posts->post_id);

								$last_nb++;

								# Increment upddt if nb of publishing is to the max
								if ($last_nb == $max_nb) {
									$last_tz = $per->getNextTime($last_tz, $periods->periodical_pub_int);
									$last_nb = 0;
								}

								# --BEHAVIOR-- periodicalAfterPublishedPeriodicalEntry
								$core->callBehavior('periodicalAfterPublishedPeriodicalEntry', $core, $posts, $periods);

							}
							$core->blog->triggerBlog();
						}
					}

					# Update last published date of this period even if there's no post to publish
					$cur_period->clean();
					$cur_period->periodical_curdt = date('Y-m-d H:i:s', $loop_tz);
					$cur_period->update(
						'WHERE periodical_id = '.$periods->periodical_id.' '.
						"AND blog_id = '".$core->con->escape($core->blog->id)."' "
					);
				}
			}
			$per->unlockUpdate();
		}
		catch (Exception $e) {
			$per->unlockUpdate();

			return null;
		}
	}
}
