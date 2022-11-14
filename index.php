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

$part = !empty($_REQUEST['part']) ? $_REQUEST['part'] : 'periods';

if ($part == 'period') {
    include __DIR__ . '/inc/index.period.php';
} else {
    include __DIR__ . '/inc/index.periods.php';
}
