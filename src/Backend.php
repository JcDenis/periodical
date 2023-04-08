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

use dcAdmin;
use dcAuth;
use dcCore;
use dcNsProcess;
use dcPage;

class Backend extends dcNsProcess
{
    public static function init(): bool
    {
        static::$init == defined('DC_CONTEXT_ADMIN')
            && My::phpCompliant()
            && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                dcAuth::PERMISSION_USAGE,
                dcAuth::PERMISSION_CONTENT_ADMIN,
            ]), dcCore::app()->blog->id);

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        dcCore::app()->addBehaviors([
            'adminBlogPreferencesFormV2'    => [BackendBehaviors::class, 'adminBlogPreferencesForm'],
            'adminBeforeBlogSettingsUpdate' => [BackendBehaviors::class, 'adminBeforeBlogSettingsUpdate'],
            'adminFiltersListsV2'           => [BackendBehaviors::class, 'adminFiltersLists'],
            'adminColumnsListsV2'           => [BackendBehaviors::class, 'adminColumnsLists'],
            'adminPostListHeaderV2'         => [BackendBehaviors::class, 'adminPostListHeader'],
            'adminPostListValueV2'          => [BackendBehaviors::class, 'adminPostListValue'],
            'adminBeforePostDelete'         => [BackendBehaviors::class, 'adminBeforePostDelete'],
        ]);

        if (dcCore::app()->blog->settings->get(My::id())->get('periodical_active')) {
            dcCore::app()->menu[dcAdmin::MENU_PLUGINS]->addItem(
                My::name(),
                dcCore::app()->adminurl->get('admin.plugin.' . My::id()),
                dcPage::getPF(My::id() . '/icon.svg'),
                preg_match('/' . preg_quote(dcCore::app()->adminurl->get('admin.plugin.' . My::id())) . '(&.*)?$/', $_SERVER['REQUEST_URI']),
                dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                    dcAuth::PERMISSION_USAGE,
                    dcAuth::PERMISSION_CONTENT_ADMIN,
                ]), dcCore::app()->blog->id)
            );

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