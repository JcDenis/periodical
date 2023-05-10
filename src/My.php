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
 * This module definitions.
 */
class My
{
    /** @var    string  This module table name */
    public const TABLE_NAME = 'periodical';

    /** @var    string  This module meta type */
    public const META_TYPE = 'periodical';

    /**
     * This module id.
     */
    public static function id(): string
    {
        return basename(dirname(__DIR__));
    }

    /**
     * This module name.
     */
    public static function name(): string
    {
        $name = dcCore::app()->plugins->moduleInfo(self::id(), 'name');

        return __(is_string($name) ? $name : self::id());
    }

    /**
     * This module path.
     */
    public static function path(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Periods action combo.
     * 
     * @return  array<string,sting>
     */
    public static function periodsActionCombo(): array
    {
        return [
            __('empty periods')  => 'emptyperiods',
            __('delete periods') => 'deleteperiods',
        ];
    }

    /**
     * Period entries action combo.
     * 
     * @return  array<string,array{string,string}>
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
     * Periods sortby combo.
     * 
     * @return  array<string,string>
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
     * Period combo.
     * 
     * @return  array<string,string>
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
