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

use dbStruct;
use dcCore;
use dcNsProcess;
use Exception;

class Install extends dcNsProcess
{
    public static function init(): bool
    {
        static::$init = defined('DC_CONTEXT_ADMIN')
            && My::phpCompliant()
            && dcCore::app()->newVersion(My::id(), dcCore::app()->plugins->moduleInfo(My::id(), 'version'));

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        try {
            # Tables
            $t = new dbStruct(dcCore::app()->con, dcCore::app()->prefix);

            # Table principale des sondages
            $t->{My::TABLE_NAME} // @phpstan-ignore-line
                ->periodical_id('bigint', 0, false)
                ->blog_id('varchar', 32, false)
                ->periodical_type('varchar', 32, false, "'post'")
                ->periodical_title('varchar', 255, false, "''")
                ->periodical_curdt('timestamp', 0, false, ' now()')
                ->periodical_enddt('timestamp', 0, false, 'now()')
                ->periodical_pub_int('varchar', 32, false, "'day'")
                ->periodical_pub_nb('smallint', 0, false, 1)

                ->primary('pk_periodical', 'periodical_id')
                ->index('idx_periodical_type', 'btree', 'periodical_type');

            (new dbStruct(dcCore::app()->con, dcCore::app()->prefix))->synchronize($t);

            # Settings
            $s = dcCore::app()->blog->settings->get(My::id());
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
