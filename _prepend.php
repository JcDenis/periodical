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

# DB class
Clearbricks::lib()->autoload(['periodical' => __DIR__ . '/inc/class.periodical.php']);
# Admin list and pagers
Clearbricks::lib()->autoload(['adminPeriodicalList' => __DIR__ . '/inc/lib.index.pager.php']);
