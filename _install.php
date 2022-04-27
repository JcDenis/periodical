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

$dc_min = '2.21';
$new_version = $core->plugins->moduleInfo('periodical', 'version');
$old_version = $core->getVersion('periodical');

if (version_compare($old_version, $new_version, '>=')) {
    return null;
}

try {
    # Check Dotclear version
    if (!method_exists('dcUtils', 'versionsCompare') 
        || dcUtils::versionsCompare(DC_VERSION, $dc_min, '<', false)
    ) {
        throw new Exception(sprintf(
            '%s requires Dotclear %s', 'periodical', $dc_min
        ));
    }

    # Tables
    $t = new dbStruct($core->con,$core->prefix);

    # Table principale des sondages
    $t->periodical
        ->periodical_id ('bigint', 0, false)
        ->blog_id('varchar', 32, false)
        ->periodical_type ('varchar', 32, false, "'post'")
        ->periodical_title ('varchar', 255, false, "''")
        ->periodical_tz ('varchar', 128, false, "'UTC'")
        ->periodical_curdt ('timestamp', 0, false,' now()')
        ->periodical_enddt ('timestamp', 0, false, 'now()')
        ->periodical_pub_int ('varchar', 32, false, "'day'")
        ->periodical_pub_nb ('smallint', 0, false, 1)

        ->primary('pk_periodical', 'periodical_id')
        ->index('idx_periodical_type', 'btree', 'periodical_type');

    $ti = new dbStruct($core->con, $core->prefix);
    $changes = $ti->synchronize($t);

    # Settings
    $core->blog->settings->addNamespace('periodical');
    $s = $core->blog->settings->periodical;
    $s->put('periodical_active', false, 'boolean', 'Enable extension', false, true);
    $s->put('periodical_upddate', true, 'boolean', 'Update post date', false, true);
    $s->put('periodical_updurl', false, 'boolean', 'Update post url', false, true);
    $s->put('periodical_pub_order', 'post_dt asc', 'string', 'Order of publication', false, true);

    # Version
    $core->setVersion('periodical', $new_version);

    return true;
} catch (Exception $e) {
    $core->error->add($e->getMessage());
}

return false;