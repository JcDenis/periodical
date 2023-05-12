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

use ArrayObject;
use dcBlog;
use dcCore;
use dcMeta;
use Dotclear\Database\{
    Cursor,
    MetaRecord
};
use Dotclear\Database\Statement\{
    DeleteStatement,
    JoinStatement,
    SelectStatement
};
use Dotclear\Helper\File\{
    Files,
    Path
};
use Exception;

/**
 * Manage periodical records
 */
class Utils
{
    /** @var null|string  $lock   File lock for update */
    private static $lock = null;

    /**
     * Get periodical table cursor.
     *
     * @return  Cursor  The periodical table cursor
     */
    public static function openCursor(): Cursor
    {
        return dcCore::app()->con->openCursor(dcCore::app()->prefix . My::TABLE_NAME);
    }

    /**
     * Get periods.
     *
     * @param   array|ArrayObject   $params         Parameters
     * @param   bool                $count_only     Only counts results
     * @param   SelectStatement     $ext_sql        Optional SelectStatement instance
     *
     * @return  MetaRecord  A record with some more capabilities
     */
    public static function getPeriods(array|ArrayObject $params = [], bool $count_only = false, ?SelectStatement $ext_sql = null): MetaRecord
    {
        $params = new ArrayObject($params);
        $sql    = $ext_sql ? clone $ext_sql : new SelectStatement();

        if ($count_only) {
            $sql->column($sql->count('T.periodical_id'));
        } else {
            if (!empty($params['columns']) && is_array($params['columns'])) {
                $sql->columns($params['columns']);
            }
            $sql->columns([
                'T.periodical_id',
                'T.periodical_title',
                'T.periodical_curdt',
                'T.periodical_enddt',
                'T.periodical_pub_int',
                'T.periodical_pub_nb',

            ]);
        }

        $sql->from($sql->as(dcCore::app()->prefix . My::TABLE_NAME, 'T'), false, true);

        if (!empty($params['join'])) {
            $sql->join($params['join']);
        }

        if (!empty($params['from'])) {
            $sql->from($params['from']);
        }

        if (!empty($params['where'])) {
            $sql->where($params['where']);
            $sql->and('T.blog_id = ' . $sql->quote((string) dcCore::app()->blog?->id));
        } else {
            $sql->where('T.blog_id = ' . $sql->quote((string) dcCore::app()->blog?->id));
        }

        if (isset($params['periodical_type'])) {
            if (is_array($params['periodical_type']) || !empty($params['periodical_type'])) {
                $sql->and('T.periodical_type ' . $sql->in($params['periodical_type']));
            }
        } else {
            $sql->and("T.periodical_type = 'post' ");
        }

        if (!empty($params['periodical_id'])) {
            if (is_array($params['periodical_id'])) {
                array_walk($params['periodical_id'], function ($v) { if ($v !== null) { $v = (int) $v; } });
            } else {
                $params['periodical_id'] = [(int) $params['periodical_id']];
            }
            $sql->and('T.periodical_id ' . $sql->in($params['periodical_id']));
        }

        if (!empty($params['periodical_title'])) {
            $sql->and('T.periodical_title = ' . $sql->quote($params['periodical_title']));
        }

        if (!empty($params['sql'])) {
            $sql->sql($params['sql']);
        }

        if (!$count_only) {
            if (!empty($params['order'])) {
                $sql->order($sql->escape($params['order']));
            } else {
                $sql->order('T.periodical_id ASC');
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $sql->limit($params['limit']);
        }

        $rs = $sql->select();

        return is_null($rs) ? MetaRecord::newFromArray([]) : $rs;
    }

    /**
     * Add a period.
     *
     * @param   Cursor  $cur    The period cursor
     *
     * @return  int     The new period ID
     */
    public static function addPeriod(Cursor $cur): int
    {
        dcCore::app()->con->writeLock(dcCore::app()->prefix . My::TABLE_NAME);

        try {
            // get next id
            $sql = new SelectStatement();
            $rs  = $sql->from(dcCore::app()->prefix . My::TABLE_NAME)
                ->column($sql->max('periodical_id'))
                ->select();

            $id = is_null($rs) || $rs->isEmpty() ? 1 : (int) $rs->f(0) + 1;

            // insert
            $cur->setField('periodical_id', $id);
            $cur->setField('blog_id', (string) dcCore::app()->blog?->id);
            $cur->setField('periodical_type', 'post');
            $cur->insert();
            dcCore::app()->con->unlock();
        } catch (Exception $e) {
            dcCore::app()->con->unlock();

            throw $e;
        }

        return $id;
    }

    /**
     * Update a period.
     *
     * @param   int     $period_id  The period ID
     * @param   Cursor  $cur        The period cursor
     */
    public static function updPeriod(int $period_id, Cursor $cur): void
    {
        $cur->update(
            "WHERE blog_id = '" . dcCore::app()->con->escapeStr((string) dcCore::app()->blog?->id) . "' " .
            'AND periodical_id = ' . $period_id . ' '
        );
    }

    /**
     * Delete a period.
     *
     * @param   int     $period_id  The period ID
     */
    public static function delPeriod(int $period_id): void
    {
        $rs = self::getPosts([
            'periodical_id' => $period_id,
            'post_status'   => '',
        ]);

        if (!$rs->isEmpty()) {
            throw new Exception('Periodical is not empty');
        }

        $sql = new DeleteStatement();
        $sql->from(dcCore::app()->prefix . My::TABLE_NAME)
            ->where('blog_id = ' . $sql->quote((string) dcCore::app()->blog?->id))
            ->and('periodical_id = ' . $period_id)
            ->delete();
    }

    /**
     * Remove all posts related to a period.
     *
     * @param   int     $period_id  The period ID
     */
    public static function delPeriodPosts(int $period_id): void
    {
        $rs = self::getPosts([
            'post_status'   => '',
            'periodical_id' => $period_id,
        ]);

        if ($rs->isEmpty()) {
            return;
        }

        $ids = [];
        while ($rs->fetch()) {
            $ids[] = $rs->f('post_id');
        }

        $sql = new DeleteStatement();
        $sql->from(dcCore::app()->prefix . dcMeta::META_TABLE_NAME)
            ->where('meta_type = ' . $sql->quote(My::META_TYPE))
            ->and('post_id ' . $sql->in($ids))
            ->delete();
    }

    /**
     * Get posts related to periods.
     *
     * @param   array|ArrayObject   $params         Parameters
     * @param   bool                $count_only     Only counts results
     * @param   SelectStatement     $ext_sql        Optional SelectStatement instance
     *
     * @return  MetaRecord  A record with some more capabilities
     */
    public static function getPosts(array|ArrayObject $params = [], bool $count_only = false, ?SelectStatement $ext_sql = null): MetaRecord
    {
        $params = new ArrayObject($params);
        $sql    = $ext_sql ? clone $ext_sql : new SelectStatement();

        if (!isset($params['sql'])) {
            $params['sql'] = '';
        }

        if (!$count_only) {
            $sql->columns([
                'T.periodical_id',
                'T.periodical_title',
                'T.periodical_type',
                'T.periodical_curdt',
                'T.periodical_enddt',
                'T.periodical_pub_int',
                'T.periodical_pub_nb',

            ]);
        }
        $sql
            ->join(
                (new JoinStatement())
                    ->left()
                    ->from($sql->as(dcCore::app()->prefix . dcMeta::META_TABLE_NAME, 'R'))
                    ->on('P.post_id = R.post_id')
                    ->statement()
            )
            ->join(
                (new JoinStatement())
                    ->left()
                    ->from($sql->as(dcCore::app()->prefix . My::TABLE_NAME, 'T'))
                    ->on('CAST(T.periodical_id as char) = CAST(R.meta_id as char)')
                    ->statement()
            )
            ->and('R.meta_type = ' . $sql->quote(My::META_TYPE))
            ->and("T.periodical_type = 'post' ")
        ;

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
            $sql->and('T.periodical_id ' . $sql->in($params['periodical_id']));
            unset($params['periodical_id']);
        }
        if (dcCore::app()->auth?->check(dcCore::app()->auth->makePermissions([dcCore::app()->auth::PERMISSION_ADMIN]), dcCore::app()->blog?->id)) {
            if (isset($params['post_status'])) {
                if ($params['post_status'] != '') {
                    $sql->and('P.post_status = ' . (int) $params['post_status']);
                }
                unset($params['post_status']);
            }
        } else {
            $sql->and('P.post_status = ' . dcBlog::POST_PENDING);
        }

        $rs = dcCore::app()->blog?->getPosts($params, $count_only, $sql);

        return is_null($rs) ? MetaRecord::newFromArray([]) : $rs;
    }

    /**
     * Add post to a period.
     *
     * @param   int     $period_id  The period ID
     * @param   int     $post_id    The post ID
     */
    public static function addPost(int $period_id, int $post_id): void
    {
        // Check if exists
        $rs = self::getPosts([
            'post_id'       => $post_id,
            'periodical_id' => $period_id,
        ]);

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
     * Remove a post from periods.
     *
     * @param   int     $post_id    The post ID
     */
    public static function delPost(int $post_id): void
    {
        $sql = new DeleteStatement();
        $sql->from(dcCore::app()->prefix . dcMeta::META_TABLE_NAME)
            ->where('meta_type = ' . $sql->quote(My::META_TYPE))
            ->and('post_id = ' . $post_id)
            ->delete();
    }

    /**
     * Remove all posts without pending status from periodical.
     *
     * @param   null|int    $period_id  The optionnal period ID
     */
    public static function cleanPosts(?int $period_id = null): void
    {
        // hack post status of dcBlog::getPost()
        $params = [
            'post_status' => '',
            'sql'         => 'AND post_status != ' . dcBlog::POST_PENDING . ' ',
        ];
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

        $sql = new DeleteStatement();
        $sql->from(dcCore::app()->prefix . dcMeta::META_TABLE_NAME)
            ->where('meta_type = ' . $sql->quote(My::META_TYPE))
            ->and('post_id ' . $sql->in($ids))
            ->delete();
    }

    /**
     * Lock a file to see if an update is ongoing.
     *
     * @return  bool    True if file is locked
     */
    public static function lockUpdate(): bool
    {
        try {
            # Cache writable ?
            if (!is_writable(DC_TPL_CACHE)) {
                throw new Exception("Can't write in cache fodler");
            }
            # Set file path
            $f_md5 = md5((string) dcCore::app()->blog?->id);
            $file  = sprintf(
                '%s/%s/%s/%s/%s.txt',
                DC_TPL_CACHE,
                My::id(),
                substr($f_md5, 0, 2),
                substr($f_md5, 2, 2),
                $f_md5
            );

            $file = Lock::lock($file);
            if (is_null($file) || empty($file)) {
                return false;
            }

            self::$lock = $file;

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Unlock file of update process.
     */
    public static function unlockUpdate(): void
    {
        if (!is_null(self::$lock)) {
            Lock::unlock(self::$lock);
            self::$lock = null;
        }
    }
}
