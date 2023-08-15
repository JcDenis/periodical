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

use dcCore;
use Dotclear\Core\Process;
use Dotclear\Database\Structure;
use Exception;

class Install extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        try {
            $t = new Structure(dcCore::app()->con, dcCore::app()->prefix);

            // create database table
            $t->__get(My::id())
                ->field('periodical_id', 'bigint', 0, false)
                ->field('blog_id', 'varchar', 32, false)
                ->field('periodical_type', 'varchar', 32, false, "'post'")
                ->field('periodical_title', 'varchar', 255, false, "''")
                ->field('periodical_curdt', 'timestamp', 0, false, ' now()')
                ->field('periodical_enddt', 'timestamp', 0, false, 'now()')
                ->field('periodical_pub_int', 'varchar', 32, false, "'day'")
                ->field('periodical_pub_nb', 'smallint', 0, false, 1)

                ->primary('pk_periodical', 'periodical_id')
                ->index('idx_periodical_type', 'btree', 'periodical_type');

            (new Structure(dcCore::app()->con, dcCore::app()->prefix))->synchronize($t);

            // set default settings
            $s = My::settings();
            $s->put('periodical_active', false, 'boolean', 'Enable extension', false, true);
            $s->put('periodical_upddate', true, 'boolean', 'Update post date', false, true);
            $s->put('periodical_updurl', false, 'boolean', 'Update post url', false, true);
            $s->put('periodical_pub_order', 'post_dt asc', 'string', 'Order of publication', false, true);

            return true;
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());

            return false;
        }
    }
}
