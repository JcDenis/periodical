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
declare(strict_types=1);

namespace Dotclear\Plugin\periodical;

use cursor;
use dcAuth;
use dcBlog;
use dcCore;
use dcMeta;
use dcRecord;
use Exception;
use files;
use path;

/**
 * Manage records
 */
class Utils
{
    /** @var null|resource Lock update process */
    private static $lock = null;

    /**
     * Get escaped blog id
     */
    private static function blog(): string
    {
        return dcCore::app()->con->escapeStr(dcCore::app()->blog->id);
    }

    /**
     * Get escaped periodical full table name
     */
    private static function table(): string
    {
        return dcCore::app()->con->escapeStr(dcCore::app()->prefix . My::TABLE_NAME);
    }

    /**
     * Get periodical table cursor
     */
    public static function openCursor(): cursor
    {
        return dcCore::app()->con->openCursor(dcCore::app()->prefix . My::TABLE_NAME);
    }

    /**
     * Get periods
     */
    public static function getPeriods(array $params = [], bool $count_only = false): dcRecord
    {
        if ($count_only) {
            $q = 'SELECT count(T.periodical_id) ';
        } else {
            $q = 'SELECT T.periodical_id, T.periodical_type, ';

            if (!empty($params['columns']) && is_array($params['columns'])) {
                $q .= implode(', ', $params['columns']) . ', ';
            }
            $q .= 'T.periodical_title, ' .
            'T.periodical_curdt, T.periodical_enddt, ' .
            'T.periodical_pub_int, T.periodical_pub_nb ';
        }

        $q .= 'FROM ' . self::table() . ' T ';

        if (!empty($params['from'])) {
            $q .= $params['from'] . ' ';
        }
        $q .= "WHERE T.blog_id = '" . self::blog() . "' ";

        if (isset($params['periodical_type'])) {
            if (is_array($params['periodical_type']) && !empty($params['periodical_type'])) {
                $q .= 'AND T.periodical_type ' . dcCore::app()->con->in($params['periodical_type']);
            } elseif ($params['periodical_type'] != '') {
                $q .= "AND T.periodical_type = '" . dcCore::app()->con->escapeStr($params['periodical_type']) . "' ";
            }
        } else {
            $q .= "AND T.periodical_type = 'post' ";
        }
        if (!empty($params['periodical_id'])) {
            if (is_array($params['periodical_id'])) {
                array_walk($params['periodical_id'], function ($v) { if ($v !== null) { $v = (int) $v; } });
            } else {
                $params['periodical_id'] = [(int) $params['periodical_id']];
            }
            $q .= 'AND T.periodical_id ' . dcCore::app()->con->in($params['periodical_id']);
        }
        if (!empty($params['periodical_title'])) {
            $q .= "AND T.periodical_title = '" . dcCore::app()->con->escapeStr($params['periodical_title']) . "' ";
        }
        if (!empty($params['sql'])) {
            $q .= $params['sql'] . ' ';
        }
        if (!$count_only) {
            if (!empty($params['order'])) {
                $q .= 'ORDER BY ' . dcCore::app()->con->escapeStr($params['order']) . ' ';
            } else {
                $q .= 'ORDER BY T.periodical_id ASC ';
            }
        }
        if (!$count_only && !empty($params['limit'])) {
            $q .= dcCore::app()->con->limit($params['limit']);
        }

        return new dcRecord(dcCore::app()->con->select($q));
    }

    /**
     * Add a period
     */
    public static function addPeriod(cursor $cur): int
    {
        dcCore::app()->con->writeLock(self::table());

        try {
            $id = dcCore::app()->con->select(
                'SELECT MAX(periodical_id) FROM ' . self::table()
            )->f(0) + 1;

            $cur->setField('periodical_id', $id);
            $cur->setField('blog_id', self::blog());
            $cur->setField('periodical_type', 'post');
            $cur->insert();
            dcCore::app()->con->unlock();
        } catch (Exception $e) {
            dcCore::app()->con->unlock();

            throw $e;
        }

        return (int) $cur->getField('periodical_id');
    }

    /**
     * Update a period
     */
    public static function updPeriod(int $period_id, cursor $cur): void
    {
        $cur->update(
            "WHERE blog_id = '" . self::blog() . "' " .
            'AND periodical_id = ' . $period_id . ' '
        );
    }

    /**
     * Delete a period
     */
    public static function delPeriod(int $period_id): void
    {
        $params                  = [];
        $params['periodical_id'] = $period_id;
        $params['post_status']   = '';
        $rs                      = self::getPosts($params);

        if (!$rs->isEmpty()) {
            throw new Exception('Periodical is not empty');
        }

        dcCore::app()->con->execute(
            'DELETE FROM ' . self::table() . ' ' .
            "WHERE blog_id = '" . self::blog() . "' " .
            'AND periodical_id = ' . $period_id . ' '
        );
    }

    /**
     * Remove all posts related to a period
     */
    public static function delPeriodPosts(int $period_id): void
    {
        $params                  = [];
        $params['post_status']   = '';
        $params['periodical_id'] = $period_id;

        $rs = self::getPosts($params);

        if ($rs->isEmpty()) {
            return;
        }

        $ids = [];
        while ($rs->fetch()) {
            $ids[] = $rs->f('post_id');
        }

        if (empty($ids)) {
            return;
        }

        dcCore::app()->con->execute(
            'DELETE FROM ' . dcCore::app()->prefix . dcMeta::META_TABLE_NAME . ' ' .
            "WHERE meta_type = '" . My::META_TYPE . "' " .
            'AND post_id ' . dcCore::app()->con->in($ids)
        );
    }

    /**
     * Get posts related to periods
     */
    public static function getPosts(array $params = [], bool $count_only = false): dcRecord
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
        $params['columns'][] = 'T.periodical_curdt';
        $params['columns'][] = 'T.periodical_enddt';
        $params['columns'][] = 'T.periodical_pub_int';
        $params['columns'][] = 'T.periodical_pub_nb';

        $params['join'] .= 'LEFT JOIN ' . dcCore::app()->prefix . dcMeta::META_TABLE_NAME . ' R ON P.post_id = R.post_id ';
        $params['join'] .= 'LEFT JOIN ' . self::table() . ' T ON CAST(T.periodical_id as char) = CAST(R.meta_id as char) ';

        $params['sql'] .= "AND R.meta_type = '" . My::META_TYPE . "' ";
        $params['sql'] .= "AND T.periodical_type = 'post' ";

        if (!empty($params['periodical_id'])) {
            if (is_array($params['periodical_id'])) {
                array_walk($params['periodical_id'], function ($v) {
                    if ($v !== null) {
                        $v = (int) $v;
                    }
                });
            } else {
                $params['periodical_id'] = [(int) $params['periodical_id']];
            }
            $params['sql'] .= 'AND T.periodical_id ' . dcCore::app()->con->in($params['periodical_id']);
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
            $params['sql'] .= 'AND P.post_status = ' . dcBlog::POST_PENDING . ' ';
        }

        return dcCore::app()->blog->getPosts($params, $count_only);
    }

    /**
     * Add post to a period
     */
    public static function addPost(int $period_id, int $post_id): void
    {
        # Check if exists
        $rs = self::getPosts(['post_id' => $post_id, 'periodical_id' => $period_id]);
        if (!$rs->isEmpty()) {
            return;
        }

        $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . dcMeta::META_TABLE_NAME);
        dcCore::app()->con->writeLock(dcCore::app()->prefix . dcMeta::META_TABLE_NAME);

        try {
            $cur->setField('post_id', $post_id);
            $cur->setField('meta_id', $period_id);
            $cur->setField('meta_type', My::META_TYPE);
            $cur->insert();
            dcCore::app()->con->unlock();
        } catch (Exception $e) {
            dcCore::app()->con->unlock();

            throw $e;
        }
    }

    /**
     * Remove a post from periods
     */
    public static function delPost(int $post_id): void
    {
        dcCore::app()->con->execute(
            'DELETE FROM ' . dcCore::app()->prefix . dcMeta::META_TABLE_NAME . ' ' .
            "WHERE meta_type = '" . My::META_TYPE . "' " .
            "AND post_id = '" . $post_id . "' "
        );
    }

    /**
     * Remove all posts without pending status from periodical
     */
    public static function cleanPosts(?int $period_id = null): void
    {
        $params                = [];
        $params['post_status'] = '';
        $params['sql']         = 'AND post_status != ' . dcBlog::POST_PENDING . ' ';
        if ($period_id !== null) {
            $params['periodical_id'] = $period_id;
        }
        $rs = self::getPosts($params);

        if ($rs->isEmpty()) {
            return;
        }

        $ids = [];
        while ($rs->fetch()) {
            $ids[] = (int) $rs->f('post_id');
        }

        if (empty($ids)) {
            return;
        }

        dcCore::app()->con->execute(
            'DELETE FROM ' . dcCore::app()->prefix . dcMeta::META_TABLE_NAME . ' ' .
            "WHERE meta_type = '" . My::META_TYPE . "' " .
            'AND post_id ' . dcCore::app()->con->in($ids)
        );
    }

    /**
     * Lock a file to see if an update is ongoing
     */
    public static function lockUpdate(): bool
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
            $f_md5       = md5(self::blog());
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
            if (is_bool($cached_file)) {
                throw new Exception("Can't write in cache fodler");
            }
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
            self::$lock = $fp;

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Unlock update process
     */
    public static function unlockUpdate(): void
    {
        @fclose(self::$lock);
        self::$lock = null;
    }
}
