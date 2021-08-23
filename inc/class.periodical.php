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

if (!defined('DC_RC_PATH')){return;}

class periodical
{
	public $core;
	public $con;
	
	protected $table;
	protected $blog;
	private $lock = null;
	
	public function __construct($core)
	{
		$this->core = $core;
		$this->con = $core->con;
		
		$this->table = $core->con->escape($core->prefix.'periodical');
		$this->blog = $core->con->escape($core->blog->id);
	}
	
	public function openCursor()
	{
		return $this->con->openCursor($this->table);
	}
	
	# Get periods
	public function getPeriods($params=array(),$count_only=false)
	{
		if ($count_only) {
			$q = 'SELECT count(T.periodical_id) ';
		}
		else
		{
			$q = 'SELECT T.periodical_id, T.periodical_type, ';

			if (!empty($params['columns']) && is_array($params['columns'])) {
				$q .= implode(', ',$params['columns']).', ';
			}
			$q .= 
			'T.periodical_title, T.periodical_tz, '.
			'T.periodical_curdt, T.periodical_enddt, '.
			'T.periodical_pub_int, T.periodical_pub_nb ';
		}
		
		$q .= 'FROM '.$this->table.' T ';
		
		if (!empty($params['from'])) {
			$q .= $params['from'].' ';
		}
		$q .= "WHERE T.blog_id = '".$this->blog."' ";
		
		if (isset($params['periodical_type'])) {
			if (is_array($params['periodical_type']) && !empty($params['periodical_type'])) {
				$q .= 'AND T.periodical_type '.$this->con->in($params['periodical_type']);
			}
			elseif ($params['periodical_type'] != '') {
				$q .= "AND T.periodical_type = '".$this->con->escape($params['periodical_type'])."' ";
			}
		}
		else {
			$q .= "AND T.periodical_type = 'post' ";
		}
		if (!empty($params['periodical_id'])) {
			if (is_array($params['periodical_id'])) {
				array_walk($params['periodical_id'],create_function('&$v,$k','if($v!==null){$v=(integer)$v;}'));
			}
			else {
				$params['periodical_id'] = array((integer) $params['periodical_id']);
			}
			$q .= 'AND T.periodical_id '.$this->con->in($params['periodical_id']);
		}
		if (!empty($params['periodical_title'])) {
			$q .= "AND T.periodical_title = '".$this->con->escape($params['periodical_title'])."' ";
		}
		if (!empty($params['sql'])) {
			$q .= $params['sql'].' ';
		}
		if (!$count_only) {
			if (!empty($params['order'])) {
				$q .= 'ORDER BY '.$this->con->escape($params['order']).' ';
			}
			else {
				$q .= 'ORDER BY T.periodical_id ASC ';
			}
		}
		if (!$count_only && !empty($params['limit'])) {
			$q .= $this->con->limit($params['limit']);
		}
		$rs = $this->con->select($q);
		$rs->core = $this->core;
		$rs->periodical = $this;
		
		return $rs;
	}
	
	public function addPeriod($cur)
	{
		$this->con->writeLock($this->table);
		
		try
		{
			$id = $this->con->select(
				'SELECT MAX(periodical_id) FROM '.$this->table
			)->f(0) + 1;

			$cur->periodical_id = $id;
			$cur->blog_id = $this->blog;
			$cur->periodical_type = 'post';
			$cur->periodical_tz = $this->core->auth->getInfo('user_tz');
			$cur->insert();
			$this->con->unlock();
		}
		catch (Exception $e)
		{
			$this->con->unlock();
			throw $e;
		}
		return $cur->periodical_id;
	}
	
	public function updPeriod($period_id,$cur)
	{
		$period_id = (integer) $period_id;
		
		if ($cur->periodical_tz == '' 
		&& ($cur->periodical_curdt != '' || $cur->periodical_enddt != '')) {
			$cur->periodical_tz = $this->core->auth->getInfo('user_tz');
		}
		$cur->update(
			"WHERE blog_id = '".$this->blog."' ".
			"AND periodical_id = ".$period_id." "
		);
	}

	# Delete a period
	public function delPeriod($period_id)
	{
		$period_id = (integer) $period_id;
		
		$params = array();
		$params['periodical_id'] = $period_id;
		$params['post_status'] = '';
		$rs = $this->getPosts($params);

		if (!$rs->isEmpty()) {
			throw new Exception('Periodical is not empty');
		}
		
		$this->con->execute(
			'DELETE FROM '.$this->table.' '.
			"WHERE blog_id = '".$this->blog."' ".
			"AND periodical_id = ".$period_id." "
		);
	}
	
	# Remove all posts related to a period
	public function delPeriodPosts($period_id)
	{
		$params = array();
		$params['post_status'] = '';
		$params['periodical_id'] = (integer) $period_id;
		
		$rs = $this->getPosts($params);
		
		if ($rs->isEmpty()) return;
		
		$ids = array();
		while($rs->fetch())
		{
			$ids[] = $rs->post_id;
		}
		
		if (empty($ids)) return;
		
		$this->con->execute(
			'DELETE FROM '.$this->core->prefix.'meta '.
			"WHERE meta_type = 'periodical' ".
			"AND post_id ".$this->con->in($ids)
		);
	}

	# Get posts related to periods
	public function getPosts($params=array(),$count_only=false)
	{
		if (!isset($params['columns'])) $params['columns'] = array();
		if (!isset($params['from'])) $params['from'] = '';
		if (!isset($params['sql'])) $params['sql'] = '';
		
		$params['columns'][] = 'T.periodical_id';
		$params['columns'][] = 'T.periodical_title';
		$params['columns'][] = 'T.periodical_type';
		$params['columns'][] = 'T.periodical_tz';
		$params['columns'][] = 'T.periodical_curdt';
		$params['columns'][] = 'T.periodical_enddt';
		$params['columns'][] = 'T.periodical_pub_int';
		$params['columns'][] = 'T.periodical_pub_nb';
		
		$params['from'] .= 'LEFT JOIN '.$this->core->prefix.'meta R ON P.post_id = R.post_id ';
		$params['from'] .= 'LEFT JOIN '.$this->table.' T ON  CAST(T.periodical_id as char)=R.meta_id ';
		
		$params['sql'] .= "AND R.meta_type = 'periodical' ";
		$params['sql'] .= "AND T.periodical_type = 'post' ";
		
		if (!empty($params['periodical_id'])) {
			if (is_array($params['periodical_id'])) {
				array_walk($params['periodical_id'],create_function('&$v,$k','if($v!==null){$v=(integer)$v;}'));
			}
			else {
				$params['periodical_id'] = array((integer) $params['periodical_id']);
			}
			$params['sql'] .= 'AND T.periodical_id '.$this->con->in($params['periodical_id']);
			unset($params['periodical_id']);
		}
		if ($this->core->auth->check('admin',$this->core->blog->id)) {
			if (isset($params['post_status'])) {
				if ($params['post_status'] != '') {
					$params['sql'] .= 'AND P.post_status = '.(integer) $params['post_status'].' ';
				}
				unset($params['post_status']);
			}
		}
		else {
			$params['sql'] .= 'AND P.post_status = -2 ';
		}
		
		$rs = $this->core->blog->getPosts($params,$count_only);
		$rs->periodical = $this;
		
		return $rs;
	}
	
	# Add post to periodical
	public function addPost($period_id,$post_id)
	{
		$period_id = (integer) $period_id;
		$post_id = (integer) $post_id;
		
		# Check if exists
		$rs = $this->getPosts(array('post_id' => $post_id,'periodical_id' => $period_id));
		if (!$rs->isEmpty()) return;
		
		$cur = $this->con->openCursor($this->core->prefix.'meta');
		$this->con->writeLock($this->core->prefix.'meta');
		
		try
		{
			$cur->post_id = $post_id;
			$cur->meta_id = $period_id;
			$cur->meta_type = 'periodical';
			$cur->insert();
			$this->con->unlock();
		}
		catch (Exception $e)
		{
			$this->con->unlock();
			throw $e;
		}
	}
	
	# Delete post from periodical
	public function delPost($post_id)
	{
		$post_id = (integer) $post_id;
		
		$this->con->execute(
			'DELETE FROM '.$this->core->prefix.'meta '.
			"WHERE meta_type = 'periodical' ".
			"AND post_id = '".$post_id."' "
		);
		return true;
	}

	# Remove all posts without pending status from periodical
	public function cleanPosts($period_id=null)
	{
		$params = array();
		$params['post_status'] = '';
		$params['sql'] = 'AND post_status != -2 ';
		if ($period_id !== null) {
			$params['periodical_id'] = (integer) $period_id;
		}
		$rs = $this->getPosts($params);
		
		if ($rs->isEmpty()) return;
		
		$ids = array();
		while($rs->fetch())
		{
			$ids[] = $rs->post_id;
		}
		
		if (empty($ids)) return;
		
		$this->con->execute(
			'DELETE FROM '.$this->core->prefix.'meta '.
			"WHERE meta_type = 'periodical' ".
			"AND post_id ".$this->con->in($ids)
		);
	}
	
	public static function getTimesCombo()
	{
		return array(
			__('Hourly') => 'hour',
			__('twice a day') => 'halfday',
			__('Daily') => 'day',
			__('Weekly') => 'week',
			__('Monthly') => 'month'
		);
	}
	
	public static function getNextTime($ts,$period)
	{
		$ts = (integer) $ts;
		$e = explode(',',date('H,i,s,n,j,Y',$ts));
		switch($period)
		{
			case 'hour':
			$new_ts = mktime($e[0] + 1,$e[1],$e[2],$e[3],$e[4],$e[5]);
			break;

			case 'halfday':
			$new_ts = mktime($e[0],$e[1] + 12,$e[2],$e[3],$e[4],$e[5]);
			break;

			case 'day':
			$new_ts = mktime($e[0],$e[1],$e[2],$e[3],$e[4] + 1,$e[5]);
			break;

			case 'week':
			$new_ts = mktime($e[0],$e[1],$e[2],$e[3],$e[4] + 7,$e[5]);
			break;

			case 'month':
			$new_ts = mktime($e[0],$e[1],$e[2],$e[3] + 1,$e[4],$e[5]);
			break;

			default:
			$new_ts = 0;
			throw new Exception(__('Unknow frequence'));
			break;
		}
		return $new_ts;
	}

	# Lock a file to see if an update is ongoing
	public function lockUpdate()
	{
		try
		{
			# Need flock function
			if (!function_exists('flock')) {
				throw New Exception("Can't call php function named flock");
			}
			# Cache writable ?
			if (!is_writable(DC_TPL_CACHE)) {
				throw new Exception("Can't write in cache fodler");
			}
			# Set file path
			$f_md5 = md5($this->blog);
			$cached_file = sprintf('%s/%s/%s/%s/%s.txt',
				DC_TPL_CACHE,
				'periodical',
				substr($f_md5,0,2),
				substr($f_md5,2,2),
				$f_md5
			);
			# Real path
			$cached_file = path::real($cached_file,false);
			# Make dir
			if (!is_dir(dirname($cached_file))) {

					files::makeDir(dirname($cached_file),true);
			}
			# Make file
			if (!file_exists($cached_file)) {
				!$fp = @fopen($cached_file, 'w');
				if ($fp === false) {
					throw New Exception("Can't create file");
				}
				fwrite($fp,'1',strlen('1'));
				fclose($fp);
			}
			# Open file
			if (!($fp = @fopen($cached_file, 'r+'))) {
				throw New Exception("Can't open file");
			}
			# Lock file
			if (!flock($fp,LOCK_EX)) {
				throw New Exception("Can't lock file");
			}
			$this->lock = $fp;
			return true;
		}
		catch (Exception $e)
		{
			throw $e;
		}
		return false;
	}

	public function unlockUpdate()
	{
		@fclose($this->lock);
		$this->lock = null;
	}
}
?>