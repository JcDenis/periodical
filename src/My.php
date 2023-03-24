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

/**
 * Plugin def
 */
class My
{
    /** @var string Required php version */
    public const PHP_MIN = '8.1';

    /** @var string This plugin table name */
    public const TABLE_NAME = 'periodical';

    /** @var string This plugin meta type */
    public const META_TYPE = 'periodical';

    /**
     * This module id
     */
    public static function id(): string
    {
        return basename(dirname(__DIR__));
    }

    /**
     * This module name
     */
    public static function name(): string
    {
        return __((string) dcCore::app()->plugins->moduleInfo(self::id(), 'name'));
    }

    /**
     * Check php version
     */
    public static function phpCompliant(): bool
    {
        return version_compare(phpversion(), self::PHP_MIN, '>=');
    }

    /**
     * Periods action combo
     */
    public static function periodsActionCombo(): array
    {
        return [
            __('empty periods')  => 'emptyperiods',
            __('delete periods') => 'deleteperiods',
        ];
    }

    /**
     * Period entries action combo
     */
    public static function entriesActionsCombo(): array
    {
        return [
            __('Entries') => [
                __('Publish')   => 'publish',
                __('Unpublish') => 'unpublish',
            ],
            __('Periodical') => [
                __('Remove from periodical') => 'remove_post_periodical',
            ],
        ];
    }

    /**
     * Periods sortby combo
     */
    public static function sortbyCombo(): array
    {
        return [
            __('Next update') => 'periodical_curdt',
            __('End date')    => 'periodical_enddt',
            __('Frequence')   => 'periodical_pub_int',
        ];
    }

    /**
     * Period combo
     */
    public static function periodCombo(): array
    {
        return [
            __('Hourly')      => 'hour',
            __('twice a day') => 'halfday',
            __('Daily')       => 'day',
            __('Weekly')      => 'week',
            __('Monthly')     => 'month',
        ];
    }
}
