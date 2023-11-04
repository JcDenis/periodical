<?php

declare(strict_types=1);

namespace Dotclear\Plugin\periodical;

use Dotclear\App;
use Dotclear\Module\MyPlugin;

/**
 * @brief       periodical My helper.
 * @ingroup     periodical
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class My extends MyPlugin
{
    public static function checkCustomContext(int $context): ?bool
    {
        return match ($context) {
            // Add usage perm to backend
            self::MANAGE, self::MENU => App::task()->checkContext('BACKEND')
            && App::auth()->check(App::auth()->makePermissions([
                App::auth()::PERMISSION_USAGE,
                App::auth()::PERMISSION_CONTENT_ADMIN,
            ]), App::blog()->id()),

            default => null,
        };
    }

    /**
     * Periods action combo.
     *
     * @return  array<string, string>
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
     * @return  array<string, array<string, string>>
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
     * @return  array<string, string>
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
     * @return  array<string, string>
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
