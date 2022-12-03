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
if (!defined('DC_CONTEXT_ADMIN')) {
    return null;
}

try {
    # Grab info
    $mod_id      = basename(__DIR__);
    $dc_min      = dcCore::app()->plugins->moduleInfo($mod_id, 'requires')[0][1];
    $new_version = dcCore::app()->plugins->moduleInfo($mod_id, 'version');

    # Check installed version
    if (version_compare(dcCore::app()->getVersion($mod_id), $new_version, '>=')) {
        return null;
    }

    # Check Dotclear version
    if (!method_exists('dcUtils', 'versionsCompare')
        || dcUtils::versionsCompare(DC_VERSION, $dc_min, '<', false)
    ) {
        throw new Exception(sprintf(
            '%s requires Dotclear %s',
            $mod_id,
            $dc_min
        ));
    }

    # Tables
    $t = new dbStruct(dcCore::app()->con, dcCore::app()->prefix);

    # Table principale des sondages
    $t->{initPeriodical::PERIOD_TABLE_NAME}
        ->periodical_id('bigint', 0, false)
        ->blog_id('varchar', 32, false)
        ->periodical_type('varchar', 32, false, "'post'")
        ->periodical_title('varchar', 255, false, "''")
        ->periodical_tz('varchar', 128, false, "'UTC'")
        ->periodical_curdt('timestamp', 0, false, ' now()')
        ->periodical_enddt('timestamp', 0, false, 'now()')
        ->periodical_pub_int('varchar', 32, false, "'day'")
        ->periodical_pub_nb('smallint', 0, false, 1)

        ->primary('pk_periodical', 'periodical_id')
        ->index('idx_periodical_type', 'btree', 'periodical_type');

    $ti      = new dbStruct(dcCore::app()->con, dcCore::app()->prefix);
    $changes = $ti->synchronize($t);

    # Settings
    dcCore::app()->blog->settings->addNamespace('periodical');
    $s = dcCore::app()->blog->settings->periodical;
    $s->put('periodical_active', false, 'boolean', 'Enable extension', false, true);
    $s->put('periodical_upddate', true, 'boolean', 'Update post date', false, true);
    $s->put('periodical_updurl', false, 'boolean', 'Update post url', false, true);
    $s->put('periodical_pub_order', 'post_dt asc', 'string', 'Order of publication', false, true);

    # Version
    dcCore::app()->setVersion($mod_id, $new_version);

    return true;
} catch (Exception $e) {
    dcCore::app()->error->add($e->getMessage());
}

return false;
