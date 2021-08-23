<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of periodical, a plugin for Dotclear 2.
# 
# Copyright (c) 2009-2021 Jean-Christian Denis and contributors
# 
# Licensed under the GPL version 2.0 license.
# A copy of this license is available in LICENSE file or at
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------

if (!defined('DC_CONTEXT_ADMIN')) {

	return null;
}

$core->blog->settings->addNamespace('periodical'); 

if ($core->blog->settings->periodical->periodical_active) {

	$_menu['Plugins']->addItem(
		__('Periodical'),
		'plugin.php?p=periodical',
		'index.php?pf=periodical/icon.png',
		preg_match(
			'/plugin.php\?p=periodical(&.*)?$/',
			$_SERVER['REQUEST_URI']
		),
		$core->auth->check('usage,contentadmin', $core->blog->id)
	);

	$core->addBehavior(
		'adminDashboardFavorites',
		array('adminPeriodical', 'adminDashboardFavorites')
	);
	$core->addBehavior(
		'adminPostHeaders',
		array('adminPeriodical', 'adminPostHeaders')
	);
	$core->addBehavior(
		'adminPostsActionsPage',
		array('adminPeriodical', 'adminPostsActionsPage')
	);
	$core->addBehavior(
		'adminPostFormItems',
		array('adminPeriodical', 'adminPostFormItems')
	);
	$core->addBehavior(
		'adminAfterPostUpdate',
		array('adminPeriodical', 'adminAfterPostSave')
	);
	$core->addBehavior(
		'adminAfterPostCreate',
		array('adminPeriodical', 'adminAfterPostSave')
	);
}

$core->addBehavior(
	'adminBeforePostDelete',
	array('adminPeriodical', 'adminBeforePostDelete')
);

/**
 * @ingroup DC_PLUGIN_PERIODICAL
 * @brief Periodical - admin methods.
 * @since 2.6
 */
class adminPeriodical
{
	public static $combo_period = null;

	/**
	 * Favorites.
	 *
	 * @param	dcCore      $core dcCore instance
	 * @param	arrayObject $favs Array of favorites
	 */
	public static function adminDashboardFavorites(dcCore $core, $favs)
	{
		$favs->register('periodical', array(
			'title'		=> __('Periodical'),
			'url'		=> 'plugin.php?p=periodical',
			'small-icon'	=> 'index.php?pf=periodical/icon.png',
			'large-icon'	=> 'index.php?pf=periodical/icon-big.png',
			'permissions'	=> $core->auth->check(
				'usage,contentadmin',
				$core->blog->id
			),
			'active_cb'	=> array(
				'adminPeriodical', 
				'adminDashboardFavoritesActive'
			)
		));
	}

	/**
	 * Favorites selection.
	 *
	 * @param	string $request Requested page
	 * @param	array  $params  Requested parameters
	 */
	public static function adminDashboardFavoritesActive($request, $params)
	{
		return $request == 'plugin.php' 
			&& isset($params['p']) 
			&& $params['p'] == 'periodical';
	}

	/**
	 * Add javascript for toggle
	 * 
	 * @return string HTML head
	 */
	public static function adminPostHeaders()
	{
		return dcPage::jsLoad('index.php?pf=periodical/js/toggle.js');
	}

	/**
	 * Delete relation between post and period
	 * 
	 * @param  integer $post_id Post id
	 */
	public static function adminBeforePostDelete($post_id)
	{
		self::delPeriod($GLOBALS['core'], $post_id);
	}

	/**
	 * Add actions to posts page combo
	 * 
	 * @param  dcCore             $core dcCore instance
	 * @param  dcPostsActionsPage $ap   dcPostsActionsPage instance
	 */
	public static function adminPostsActionsPage(dcCore $core, dcPostsActionsPage $pa)
	{
		$pa->addAction(
			array(
				__('Periodical') => array(
					__('Add to periodical') => 'periodical_add'
				)
			),
			array('adminPeriodical', 'callbackAdd')
		);

		if (!$core->auth->check('delete,contentadmin', $core->blog->id)) {

			return null;
		}
		$pa->addAction(
			array(
				__('Periodical') => array(
					__('Remove from periodical') => 'periodical_remove'
				)
			),
			array('adminPeriodical', 'callbackRemove')
		);
	}

	/**
	 * Posts actions callback to remove period
	 * 
	 * @param  dcCore             $core dcCore instance
	 * @param  dcPostsActionsPage $pa   dcPostsActionsPage instance
	 * @param  ArrayObject        $post _POST actions
	 */
	public static function callbackRemove(dcCore $core, dcPostsActionsPage $pa, ArrayObject $post)
	{
		# No entry
		$posts_ids = $pa->getIDs();
		if (empty($posts_ids)) {
			throw new Exception(__('No entry selected'));
		}

		# No right
		if (!$core->auth->check('delete,contentadmin', $core->blog->id)) {
			throw new Exception(__('No enough right'));
		}

		# Remove linked period
		foreach($posts_ids as $post_id) {
			self::delPeriod($core, $post_id);
		}

		dcPage::addSuccessNotice(__('Posts have been removed from periodical.'));
		$pa->redirect(true);
	}

	/**
	 * Posts actions callback to add period
	 * 
	 * @param  dcCore             $core dcCore instance
	 * @param  dcPostsActionsPage $pa   dcPostsActionsPage instance
	 * @param  ArrayObject        $post _POST actions
	 */
	public static function callbackAdd(dcCore $core, dcPostsActionsPage $pa, ArrayObject $post)
	{
		# No entry
		$posts_ids = $pa->getIDs();
		if (empty($posts_ids)) {
			throw new Exception(__('No entry selected'));
		}

		//todo: check if selected posts is unpublished

		# Save action
		if (!empty($post['periodical'])) {
			foreach($posts_ids as $post_id) {
				self::delPeriod($core, $post_id);
				self::addPeriod($core, $post_id, $post['periodical']);
			}

			dcPage::addSuccessNotice(__('Posts have been added to periodical.'));
			$pa->redirect(true);
		}

		# Display form
		else {
			$pa->beginPage(
				dcPage::breadcrumb(array(
					html::escapeHTML($core->blog->name) => '',
					$pa->getCallerTitle() => $pa->getRedirection(true),
					__('Add a period to this selection') => '' 
				))
			);

			echo
			'<form action="'.$pa->getURI().'" method="post">'.
			$pa->getCheckboxes().

			self::formPeriod($core).

			'<p>'.
			$core->formNonce().
			$pa->getHiddenFields().
			form::hidden(array('action'), 'periodical_add').
			'<input type="submit" value="'.__('Save').'" /></p>'.
			'</form>';

			$pa->endPage();
		}
	}

	/**
	 * Add form to post sidebar
	 * 
	 * @param  ArrayObject $main_items    Main items
	 * @param  ArrayObject $sidebar_items Sidebar items
	 * @param  record      $post          Post record or null
	 */
	public static function adminPostFormItems(ArrayObject $main_items, ArrayObject $sidebar_items, $post)
	{
		global $core;

		# Get existing linked period
		$period = '';
		if ($post) {
			$per = new periodical($core);
			$rs = $per->getPosts(array('post_id' => $post->post_id));
			$period = $rs->isEmpty() ? '' : $rs->periodical_id;
		}

		# Set linked period form items
		$sidebar_items['options-box']['items']['period'] =
			self::formPeriod($core, $period);
	}

	/**
	 * Save linked period
	 * 
	 * @param  cursor  $cur     Current post cursor
	 * @param  integer $post_id Post id
	 */
	public static function adminAfterPostSave(cursor $cur, $post_id)
	{
		global $core;

		if (!isset($_POST['periodical'])) {

			return null;
		}

		# Delete old linked period
		self::delPeriod($core, $post_id);

		# Add new linked period
		self::addPeriod($core, $post_id, $_POST['periodical']);
	}

	/**
	 * Posts period form field
	 * 
	 * @param  dcCore $core   dcCore instance
	 * @param  string $period Period
	 * @return string         Period form content
	 */
	protected static function formPeriod(dcCore $core, $period='')
	{
		$combo = self::comboPeriod($core);

		if (empty($combo)) {

			return null;
		}

		return 
		'<p><label for="periodical">'.
		__('Periodical').'</label>'.
		form::combo('periodical', $combo, $period).
		'</p>';
	}

	/**
	 * Combo of available periods
	 * 
	 * @param  dcCore $core dcCore instance
	 * @return array       List of period
	 */
	protected static function comboPeriod(dcCore $core)
	{
		if (adminPeriodical::$combo_period === null) {

			$per = new periodical($core);
			$periods = $per->getPeriods();

			if ($periods->isEmpty()) {

				adminPeriodical::$combo_period = array();
			}
			else {
				$combo = array('-' => '');
				while ($periods->fetch()) {
					$combo[html::escapeHTML($periods->periodical_title)] = $periods->periodical_id;
				}
			}
			adminPeriodical::$combo_period = $combo;
		}

		return adminPeriodical::$combo_period;
	}

	/**
	 * Remove period from posts.
	 * 
	 * @param  dcCore  $core    dcCore instance
	 * @param  integer $post_id Post id
	 */
	protected static function delPeriod(dcCore $core, $post_id)
	{
		if ($post_id === null) {

			return null;
		}

		$post_id = (integer) $post_id;
		$per = new periodical($core);
		$per->delPost($post_id);
	}

	/**
	 * Add period to posts
	 * 
	 * @param  dcCore  $core    dcCore instance
	 * @param  integer $post_id Post id
	 * @param  array   $period  Period
	 */
	protected static function addPeriod($core, $post_id, $period)
	{
		# Not saved
		if ($post_id === null || empty($period)) {

			return null;
		}

		# Period object
		$per = new periodical($core);

		# Get periods
		$period = $per->getPeriods(array('periodical_id' => $period));

		# No period
		if ($period->isEmpty()) {

			return null;
		}

		$post_id = (integer) $post_id;

		# Add relation
		$per->addPost($period->periodical_id, $post_id);
	}
}
