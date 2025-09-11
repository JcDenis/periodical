<?php

declare(strict_types=1);

namespace Dotclear\Plugin\periodical;

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief       periodical backend class.
 * @ingroup     periodical
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Backend
{
    use TraitProcess;

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
        App::behavior()->addBehaviors([
            'adminBlogPreferencesFormV2'    => BackendBehaviors::adminBlogPreferencesFormV2(...),
            'adminBeforeBlogSettingsUpdate' => BackendBehaviors::adminBeforeBlogSettingsUpdate(...),
            'adminFiltersListsV2'           => BackendBehaviors::adminFiltersListsV2(...),
            'adminColumnsListsV2'           => BackendBehaviors::adminColumnsListsV2(...),
            'adminPostListHeaderV2'         => BackendBehaviors::adminPostListHeaderV2(...),
            'adminPostListValueV2'          => BackendBehaviors::adminPostListValueV2(...),
            'adminBeforePostDelete'         => BackendBehaviors::adminBeforePostDelete(...),
        ]);

        if (My::settings()->get('periodical_active')) {
            // add backend sidebar icon
            My::addBackendMenuItem();

            // register bakend behaviors required user permissions
            App::behavior()->addBehaviors([
                'adminDashboardFavoritesV2' => BackendBehaviors::adminDashboardFavoritesV2(...),
                'adminPostHeaders'          => BackendBehaviors::adminPostHeaders(...),
                'adminPostsActions'         => BackendBehaviors::adminPostsActions(...),
                'adminPostFormItems'        => BackendBehaviors::adminPostFormItems(...),
                'adminAfterPostUpdate'      => BackendBehaviors::adminAfterPostSave(...),
                'adminAfterPostCreate'      => BackendBehaviors::adminAfterPostSave(...),
            ]);
        }

        return true;
    }
}
