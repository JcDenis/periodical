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
    return null;
}

# Check Dotclear version
if (!method_exists('dcUtils', 'versionsCompare') 
 || dcUtils::versionsCompare(DC_VERSION, '2.18', '<', false)) {
    return null;
}

$d = dirname(__FILE__) . '/inc/';

# DB class
$__autoload['periodical'] = $d . 'class.periodical.php';
# Admin list and pagers
$__autoload['adminPeriodicalList'] = $d . 'lib.index.pager.php';

# Add to plugn soCialMe (writer part)
$__autoload['periodicalSoCialMeWriter'] = $d . 'lib.periodical.socialmewriter.php';

$core->addBehavior(
    'soCialMeWriterMarker',
    ['periodicalSoCialMeWriter', 'soCialMeWriterMarker']
);
$core->addBehavior(
    'periodicalAfterPublishedPeriodicalEntry',
    ['periodicalSoCialMeWriter', 'periodicalAfterPublishedPeriodicalEntry']
);