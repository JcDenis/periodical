<?php
/**
 * @brief periodical, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugin
 *
 * @author Jean-Christian Denis and contributors
 *
 * @copyright Jean-Christian Denis
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
if (!defined('DC_RC_PATH')) {
    return;
}

class periodical
{
    public $con;

    protected $table;
    protected $blog;
    private $lock = null;

    public function __construct()
    {
        $this->con   = dcCore::app()->con;
        $this->table = dcCore::app()->con->escape(dcCore::app()->prefix . initPeriodical::PERIOD_TABLE_NAME);
        $this->blog  = dcCore::app()->con->escape(dcCore::app()->blog->id);
    }

    public function openCursor()
    {
        return $this->con->openCursor($this->table);
    }

    # Get periods
    public function getPeriods($params = [], $count_only = false)
    {
        if ($count_only) {
            $q = 'SELECT count(T.periodical_id) ';
        } else {
            $q = 'SELECT T.periodical_id, T.periodical_type, ';

            if (!empty($params['columns']) && is_array($params['columns'])) {
                $q .= implode(', ', $params['columns']) . ', ';
            }
            $q .= 'T.periodical_title, T.periodical_tz, ' .
            'T.periodical_curdt, T.periodical_enddt, ' .
            'T.periodical_pub_int, T.periodical_pub_nb ';
        }

        $q .= 'FROM ' . $this->table . ' T ';

        if (!empty($params['from'])) {
            $q .= $params['from'] . ' ';
        }
        $q .= "WHERE T.blog_id = '" . $this->blog . "' ";

        if (isset($params['periodical_type'])) {
            if (is_array($params['periodical_type']) && !empty($params['periodical_type'])) {
                $q .= 'AND T.periodical_type ' . $this->con->in($params['periodical_type']);
            } elseif ($params['periodical_type'] != '') {
                $q .= "AND T.periodical_type = '" . $this->con->escape($params['periodical_type']) . "' ";
            }
        } else {
            $q .= "AND T.periodical_type = 'post' ";
        }
        if (!empty($params['periodical_id'])) {
            if (is_array($params['periodical_id'])) {
                array_walk($params['periodical_id'], create_function('&$v,$k', 'if($v!==null){$v=(integer)$v;}'));
            } else {
                $params['periodical_id'] = [(int) $params['periodical_id']];
            }
            $q .= 'AND T.periodical_id ' . $this->con->in($params['periodical_id']);
        }
        if (!empty($params['periodical_title'])) {
            $q .= "AND T.periodical_title = '" . $this->con->escape($params['periodical_title']) . "' ";
        }
        if (!empty($params['sql'])) {
            $q .= $params['sql'] . ' ';
        }
        if (!$count_only) {
            if (!empty($params['order'])) {
                $q .= 'ORDER BY ' . $this->con->escape($params['order']) . ' ';
            } else {
                $q .= 'ORDER BY T.periodical_id ASC ';
            }
        }
        if (!$count_only && !empty($params['limit'])) {
            $q .= $this->con->limit($params['limit']);
        }
        $rs = $this->con->select($q);
        //$rs->core = dcCore::app();
        //$rs->periodical = $this;

        return $rs;
    }

    public function addPeriod($cur)
    {
        $this->con->writeLock($this->table);

        try {
            $id = $this->con->select(
                'SELECT MAX(periodical_id) FROM ' . $this->table
            )->f(0) + 1;

            $cur->periodical_id   = $id;
            $cur->blog_id         = $this->blog;
            $cur->periodical_type = 'post';
            $cur->periodical_tz   = dcCore::app()->auth->getInfo('user_tz');
            $cur->insert();
            $this->con->unlock();
        } catch (Exception $e) {
            $this->con->unlock();

            throw $e;
        }

        return $cur->periodical_id;
    }

    public function updPeriod($period_id, $cur)
    {
        $period_id = (int) $period_id;

        if ($cur->periodical_tz == ''
        && ($cur->periodical_curdt != '' || $cur->periodical_enddt != '')) {
            $cur->periodical_tz = dcCore::app()->auth->getInfo('user_tz');
        }
        $cur->update(
            "WHERE blog_id = '" . $this->blog . "' " .
            'AND periodical_id = ' . $period_id . ' '
        );
    }

    # Delete a period
    public function delPeriod($period_id)
    {
        $period_id = (int) $period_id;

        $params                  = [];
        $params['periodical_id'] = $period_id;
        $params['post_status']   = '';
        $rs                      = $this->getPosts($params);

        if (!$rs->isEmpty()) {
            throw new Exception('Periodical is not empty');
        }

        $this->con->execute(
            'DELETE FROM ' . $this->table . ' ' .
            "WHERE blog_id = '" . $this->blog . "' " .
            'AND periodical_id = ' . $period_id . ' '
        );
    }

    # Remove all posts related to a period
    public function delPeriodPosts($period_id)
    {
        $params                  = [];
        $params['post_status']   = '';
        $params['periodical_id'] = (int) $period_id;

        $rs = $this->getPosts($params);

        if ($rs->isEmpty()) {
            return;
        }

        $ids = [];
        while ($rs->fetch()) {
            $ids[] = $rs->post_id;
        }

        if (empty($ids)) {
            return;
        }

        $this->con->execute(
            'DELETE FROM ' . dcCore::app()->prefix . dcMeta::META_TABLE_NAME . ' ' .
            "WHERE meta_type = 'periodical' " .
            'AND post_id ' . $this->con->in($ids)
        );
    }

    # Get posts related to periods
    public function getPosts($params = [], $count_only = false)
    {
        if (!isset($params['columns'])) {
            $params['columns'] = [];
        }
        if (!isset($params['from'])) {
            $params['from'] = '';
        }
        if (!isset($params['join'])) {
            $params['join'] = '';
        }
        if (!isset($params['sql'])) {
            $params['sql'] = '';
        }

        $params['columns'][] = 'T.periodical_id';
        $params['columns'][] = 'T.periodical_title';
        $params['columns'][] = 'T.periodical_type';
        $params['columns'][] = 'T.periodical_tz';
        $params['columns'][] = 'T.periodical_curdt';
        $params['columns'][] = 'T.periodical_enddt';
        $params['columns'][] = 'T.periodical_pub_int';
        $params['columns'][] = 'T.periodical_pub_nb';

        $params['join'] .= 'LEFT JOIN ' . dcCore::app()->prefix . dcMeta::META_TABLE_NAME . ' R ON P.post_id = R.post_id ';
        $params['join'] .= 'LEFT JOIN ' . $this->table . ' T ON CAST(T.periodical_id as char) = CAST(R.meta_id as char) ';

        $params['sql'] .= "AND R.meta_type = 'periodical' ";
        $params['sql'] .= "AND T.periodical_type = 'post' ";

        if (!empty($params['periodical_id'])) {
            if (is_array($params['periodical_id'])) {
                array_walk($params['periodical_id'], function ($v) { if ($v !== null) { $v = (int) $v; } });
            } else {
                $params['periodical_id'] = [(int) $params['periodical_id']];
            }
            $params['sql'] .= 'AND T.periodical_id ' . $this->con->in($params['periodical_id']);
            unset($params['periodical_id']);
        }
        if (dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([dcAuth::PERMISSION_ADMIN]), dcCore::app()->blog->id)) {
            if (isset($params['post_status'])) {
                if ($params['post_status'] != '') {
                    $params['sql'] .= 'AND P.post_status = ' . (int) $params['post_status'] . ' ';
                }
                unset($params['post_status']);
            }
        } else {
            $params['sql'] .= 'AND P.post_status = -2 ';
        }

        $rs             = dcCore::app()->blog->getPosts($params, $count_only);
        $rs->periodical = $this;

        return $rs;
    }

    # Add post to periodical
    public function addPost($period_id, $post_id)
    {
        $period_id = (int) $period_id;
        $post_id   = (int) $post_id;

        # Check if exists
        $rs = $this->getPosts(['post_id' => $post_id, 'periodical_id' => $period_id]);
        if (!$rs->isEmpty()) {
            return;
        }

        $cur = $this->con->openCursor(dcCore::app()->prefix . dcMeta::META_TABLE_NAME);
        $this->con->writeLock(dcCore::app()->prefix . dcMeta::META_TABLE_NAME);

        try {
            $cur->post_id   = $post_id;
            $cur->meta_id   = $period_id;
            $cur->meta_type = 'periodical';
            $cur->insert();
            $this->con->unlock();
        } catch (Exception $e) {
            $this->con->unlock();

            throw $e;
        }
    }

    # Delete post from periodical
    public function delPost($post_id)
    {
        $post_id = (int) $post_id;

        $this->con->execute(
            'DELETE FROM ' . dcCore::app()->prefix . dcMeta::META_TABLE_NAME . ' ' .
            "WHERE meta_type = 'periodical' " .
            "AND post_id = '" . $post_id . "' "
        );

        return true;
    }

    # Remove all posts without pending status from periodical
    public function cleanPosts($period_id = null)
    {
        $params                = [];
        $params['post_status'] = '';
        $params['sql']         = 'AND post_status != -2 ';
        if ($period_id !== null) {
            $params['periodical_id'] = (int) $period_id;
        }
        $rs = $this->getPosts($params);

        if ($rs->isEmpty()) {
            return;
        }

        $ids = [];
        while ($rs->fetch()) {
            $ids[] = $rs->post_id;
        }

        if (empty($ids)) {
            return;
        }

        $this->con->execute(
            'DELETE FROM ' . dcCore::app()->prefix . dcMeta::META_TABLE_NAME . ' ' .
            "WHERE meta_type = 'periodical' " .
            'AND post_id ' . $this->con->in($ids)
        );
    }

    public static function getTimesCombo()
    {
        return [
            __('Hourly')      => 'hour',
            __('twice a day') => 'halfday',
            __('Daily')       => 'day',
            __('Weekly')      => 'week',
            __('Monthly')     => 'month',
        ];
    }

    public static function getNextTime($ts, $period)
    {
        $ts = (int) $ts;
        $e  = explode(',', date('H,i,s,n,j,Y', $ts));
        switch($period) {
            case 'hour':
                $new_ts = mktime($e[0] + 1, $e[1], $e[2], $e[3], $e[4], $e[5]);

                break;

            case 'halfday':
                $new_ts = mktime($e[0] + 12, $e[1], $e[2], $e[3], $e[4], $e[5]);

                break;

            case 'day':
                $new_ts = mktime($e[0], $e[1], $e[2], $e[3], $e[4] + 1, $e[5]);

                break;

            case 'week':
                $new_ts = mktime($e[0], $e[1], $e[2], $e[3], $e[4] + 7, $e[5]);

                break;

            case 'month':
                $new_ts = mktime($e[0], $e[1], $e[2], $e[3] + 1, $e[4], $e[5]);

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
        try {
            # Need flock function
            if (!function_exists('flock')) {
                throw new Exception("Can't call php function named flock");
            }
            # Cache writable ?
            if (!is_writable(DC_TPL_CACHE)) {
                throw new Exception("Can't write in cache fodler");
            }
            # Set file path
            $f_md5       = md5($this->blog);
            $cached_file = sprintf(
                '%s/%s/%s/%s/%s.txt',
                DC_TPL_CACHE,
                'periodical',
                substr($f_md5, 0, 2),
                substr($f_md5, 2, 2),
                $f_md5
            );
            # Real path
            $cached_file = path::real($cached_file, false);
            # Make dir
            if (!is_dir(dirname($cached_file))) {
                files::makeDir(dirname($cached_file), true);
            }
            # Make file
            if (!file_exists($cached_file)) {
                !$fp = @fopen($cached_file, 'w');
                if ($fp === false) {
                    throw new Exception("Can't create file");
                }
                fwrite($fp, '1', strlen('1'));
                fclose($fp);
            }
            # Open file
            if (!($fp = @fopen($cached_file, 'r+'))) {
                throw new Exception("Can't open file");
            }
            # Lock file
            if (!flock($fp, LOCK_EX)) {
                throw new Exception("Can't lock file");
            }
            $this->lock = $fp;

            return true;
        } catch (Exception $e) {
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
