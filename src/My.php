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
use Dotclear\Module\MyPlugin;

/**
 * This module definitions.
 */
class My extends MyPlugin
{
    /** @var    string  This module table name */
    public const TABLE_NAME = 'periodical';

    /** @var    string  This module meta type */
    public const META_TYPE = 'periodical';

    public static function checkCustomContext(int $context): ?bool
    {
        return in_array($context, [My::MANAGE, My::MENU]) ?
            defined('DC_CONTEXT_ADMIN')
            && !is_null(dcCore::app()->blog)
            && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                dcCore::app()->auth::PERMISSION_USAGE,
                dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
            ]), dcCore::app()->blog->id)
            : null;
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
