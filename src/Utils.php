<?php

declare(strict_types=1);

namespace Dotclear\Plugin\periodical;

use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Exception;

/**
 * @brief       periodical utils class.
 * @ingroup     periodical
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Utils
{
    /**
     * File lock for update.
     *
     * @var     null|string     $lock
     */
    private static ?string $lock = null;

    /**
     * Get periodical table cursor.
     *
     * @return  Cursor  The periodical table cursor
     */
    public static function openCursor(): Cursor
    {
        return App::db()->con()->openCursor(App::db()->con()->prefix() . My::id());
    }

    /**
     * Get periods.
     *
     * @param   array<string, mixed>|ArrayObject<string, mixed>     $params         Parameters
     * @param   bool                                                $count_only     Only counts results
     * @param   SelectStatement                                     $ext_sql        Optional SelectStatement instance
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
                $sql->columns(array_filter($params['columns'], fn (mixed $v): bool => is_string($v)));
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

        $sql->from($sql->as(App::db()->con()->prefix() . My::id(), 'T'), false, true);

        if (!empty($params['join'])) {
            if (!is_array($params['join'])) {
                $params['join'] = [$params['join']];
            }
            $sql->join(array_filter($params['join'], fn (mixed $v): bool => is_string($v)));
        }

        if (!empty($params['from'])) {
            if (!is_array($params['from'])) {
                $params['from'] = [$params['from']];
            }
            $sql->from(array_filter($params['from'], fn (mixed $v): bool => is_string($v)));
        }

        if (!empty($params['where'])) {
            if (!is_array($params['where'])) {
                $params['where'] = [$params['where']];
            }
            $sql->where(array_filter($params['where'], fn (mixed $v): bool => is_string($v)));
            $sql->and('T.blog_id = ' . $sql->quote(App::blog()->id()));
        } else {
            $sql->where('T.blog_id = ' . $sql->quote(App::blog()->id()));
        }

        if (isset($params['periodical_type'])) {
            if (!empty($params['periodical_type'])) {
                if (!is_array($params['periodical_type'])) {
                    $params['periodical_type'] = [$params['periodical_type']];
                }
                $sql->and('T.periodical_type ' . $sql->in(array_filter($params['periodical_type'], fn (mixed $v): bool => is_string($v))));
            }
        } else {
            $sql->and("T.periodical_type = 'post' ");
        }

        if (!empty($params['periodical_id'])) {
            if (!is_array($params['periodical_id'])) {
                $params['periodical_id'] = [$params['periodical_id']];
            }
            foreach ($params['periodical_id'] as $k => $v) {
                $params['periodical_id'][$k] = is_numeric($v) ? (int) $v : 0;
            }
            $sql->and('T.periodical_id ' . $sql->in($params['periodical_id']));
        }

        if (!empty($params['periodical_title']) && is_string($params['periodical_title'])) {
            $sql->and('T.periodical_title = ' . $sql->quote($params['periodical_title']));
        }

        if (!empty($params['sql'])) {
            if (!is_array($params['sql'])) {
                $params['sql'] = [$params['sql']];
            }
            $sql->sql(array_filter($params['sql'], fn (mixed $v): bool => is_string($v)));
        }

        if (!$count_only) {
            if (!empty($params['order']) && is_string($params['order'])) {
                $sql->order($sql->escape($params['order']));
            } else {
                $sql->order('T.periodical_id ASC');
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $values = is_array($params['limit']) ? array_values($params['limit']) : [$params['limit']];
            // Make $values an array of integer values
            $values = array_map(fn (mixed $v): int => is_numeric($v) ? (int) $v : 0, $values);

            /**
             * @var array{0: int, 1?: int}  $limit
             */
            $limit = [
                $values[0],
            ];
            if (isset($values[1])) {
                $limit[1] = $values[1];
            }

            $sql->limit($limit);
        }

        return $sql->select() ?? MetaRecord::newFromArray([]);
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
        App::db()->con()->writeLock(App::db()->con()->prefix() . My::id());

        try {
            // get next id
            $sql = new SelectStatement();
            $rs  = $sql->from(App::db()->con()->prefix() . My::id())
                ->column($sql->max('periodical_id'))
                ->select();

            $id = is_null($rs) || $rs->isEmpty() ? 1 : (int) $rs->cardinal() + 1;

            // insert
            $cur->setField('periodical_id', $id);
            $cur->setField('blog_id', App::blog()->id());
            $cur->setField('periodical_type', 'post');
            $cur->insert();
            App::db()->con()->unlock();
        } catch (Exception $e) {
            App::db()->con()->unlock();

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
            "WHERE blog_id = '" . App::db()->con()->escapeStr(App::blog()->id()) . "' " .
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
        $sql->from(App::db()->con()->prefix() . My::id())
            ->where('blog_id = ' . $sql->quote(App::blog()->id()))
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
            $ids[] = $rs->intField('post_id');
        }

        $sql = new DeleteStatement();
        $sql->from(App::db()->con()->prefix() . App::meta()::META_TABLE_NAME)
            ->where('meta_type = ' . $sql->quote(My::id()))
            ->and('post_id ' . $sql->in($ids))
            ->delete();
    }

    /**
     * Get posts related to periods.
     *
     * @param   array<string, mixed>|ArrayObject<string, mixed>     $params         Parameters
     * @param   bool                                                $count_only     Only counts results
     * @param   SelectStatement                                     $ext_sql        Optional SelectStatement instance
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
                    ->from($sql->as(App::db()->con()->prefix() . App::meta()::META_TABLE_NAME, 'R'))
                    ->on('P.post_id = R.post_id')
                    ->statement()
            )
            ->join(
                (new JoinStatement())
                    ->left()
                    ->from($sql->as(App::db()->con()->prefix() . My::id(), 'T'))
                    ->on('CAST(T.periodical_id as char) = CAST(R.meta_id as char)')
                    ->statement()
            )
            ->and('R.meta_type = ' . $sql->quote(My::id()))
            ->and("T.periodical_type = 'post' ")
        ;

        if (!empty($params['periodical_id'])) {
            if (!is_array($params['periodical_id'])) {
                $params['periodical_id'] = [$params['periodical_id']];
            }
            array_walk($params['periodical_id'], function (&$v) {
                $v = ($v !== null && is_numeric($v)) ? (int) $v : 0;
            });

            $sql->and('T.periodical_id ' . $sql->in($params['periodical_id']));
            unset($params['periodical_id']);
        }
        if (App::auth()->check(App::auth()->makePermissions([App::auth()::PERMISSION_ADMIN]), App::blog()->id())) {
            if (isset($params['post_status'])) {
                if ($params['post_status'] != '' && is_numeric($params['post_status'])) {
                    $sql->and('P.post_status = ' . (int) $params['post_status']);
                }
                unset($params['post_status']);
            }
        } else {
            $sql->and('P.post_status = ' . App::blog()::POST_PENDING);
        }

        return App::blog()->getPosts($params, $count_only, $sql);
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

        $cur = App::meta()->openMetaCursor();
        App::db()->con()->writeLock(App::db()->con()->prefix() . App::meta()::META_TABLE_NAME);

        try {
            $cur->setField('post_id', $post_id);
            $cur->setField('meta_id', $period_id);
            $cur->setField('meta_type', My::id());
            $cur->insert();
            App::db()->con()->unlock();
        } catch (Exception $e) {
            App::db()->con()->unlock();

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
        $sql->from(App::db()->con()->prefix() . App::meta()::META_TABLE_NAME)
            ->where('meta_type = ' . $sql->quote(My::id()))
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
        // hack post status of App::blog()->getPost()
        $params = [
            'post_status' => '',
            'sql'         => 'AND post_status != ' . App::blog()::POST_PENDING . ' ',
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
            $ids[] = $rs->intField('post_id');
        }

        $sql = new DeleteStatement();
        $sql->from(App::db()->con()->prefix() . App::meta()::META_TABLE_NAME)
            ->where('meta_type = ' . $sql->quote(My::id()))
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
            if (!is_writable(App::config()->cacheRoot())) {
                throw new Exception("Can't write in cache fodler");
            }
            # Set file path
            $f_md5 = md5(App::blog()->id());
            $file  = sprintf(
                '%s/%s/%s/%s/%s.txt',
                App::config()->cacheRoot(),
                My::id(),
                substr($f_md5, 0, 2),
                substr($f_md5, 2, 2),
                $f_md5
            );

            $file = Files::lock($file);
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
            Files::unlock(self::$lock);
            self::$lock = null;
        }
    }

    /**
     * Get a string value.
     */
    public static function strVal(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }
}
