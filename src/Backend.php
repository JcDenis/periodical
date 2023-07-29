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
use Dotclear\Core\Process;

class Backend extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // register backend behaviors
        dcCore::app()->addBehaviors([
            'adminBlogPreferencesFormV2'    => [BackendBehaviors::class, 'adminBlogPreferencesFormV2'],
            'adminBeforeBlogSettingsUpdate' => [BackendBehaviors::class, 'adminBeforeBlogSettingsUpdate'],
            'adminFiltersListsV2'           => [BackendBehaviors::class, 'adminFiltersListsV2'],
            'adminColumnsListsV2'           => [BackendBehaviors::class, 'adminColumnsListsV2'],
            'adminPostListHeaderV2'         => [BackendBehaviors::class, 'adminPostListHeaderV2'],
            'adminPostListValueV2'          => [BackendBehaviors::class, 'adminPostListValueV2'],
            'adminBeforePostDelete'         => [BackendBehaviors::class, 'adminBeforePostDelete'],
        ]);

        if (My::settings()->get('periodical_active')) {
            // add backend sidebar icon
            My::addBackendMenuItem();

            // register bakend behaviors required user permissions
            dcCore::app()->addBehaviors([
                'adminDashboardFavoritesV2' => [BackendBehaviors::class, 'adminDashboardFavoritesV2'],
                'adminPostHeaders'          => [BackendBehaviors::class, 'adminPostHeaders'],
                'adminPostsActions'         => [BackendBehaviors::class, 'adminPostsActions'],
                'adminPostFormItems'        => [BackendBehaviors::class, 'adminPostFormItems'],
                'adminAfterPostUpdate'      => [BackendBehaviors::class, 'adminAfterPostSave'],
                'adminAfterPostCreate'      => [BackendBehaviors::class, 'adminAfterPostSave'],
            ]);
        }

        return true;
    }
}
