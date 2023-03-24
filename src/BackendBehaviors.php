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

use ArrayObject;
use cursor;
use dcAuth;
use dcCore;
use dcFavorites;
use dcPage;
use dcPostsActions;
use dcRecord;
use dcSettings;
use Exception;
use form;
use html;

/**
 * @ingroup DC_PLUGIN_PERIODICAL
 * @brief Periodical - admin methods.
 * @since 2.6
 */
class BackendBehaviors
{
    private static array $combo_period = [];

    /**
     * Add settings to blog preference
     *
     * @param  dcSettings   $blog_settings  dcSettings instance
     */
    public static function adminBlogPreferencesForm(dcSettings $blog_settings): void
    {
        $s = $blog_settings->get('periodical');

        echo
        '<div class="fieldset"><h4 id="periodical_params">' . __('Periodical') . '</h4>' .
        '<div class="two-cols">' .
        '<div class="col">' .
        '<p><label class="classic" for="periodical_active">' .
        form::checkbox('periodical_active', 1, (bool) $s->get('periodical_active')) .
        __('Enable periodical on this blog') . '</label></p>' .
        '</div>' .
        '<div class="col">' .
        '<p><label for="periodical_upddate">' .
        form::checkbox('periodical_upddate', 1, (bool) $s->get('periodical_upddate')) .
        __('Update post date when publishing it') . '</label></p>' .
        '<p><label for="periodical_updurl">' .
        form::checkbox('periodical_updurl', 1, (bool) $s->get('periodical_updurl')) .
        __('Update post url when publishing it') . '</label></p>' .
        '</div>' .
        '</div>' .
        '<br class="clear" />' .
        '</div>';
    }

    /**
     * Save blog settings
     *
     * @param  dcSettings   $blog_settings  dcSettings instance
     */
    public static function adminBeforeBlogSettingsUpdate(dcSettings $blog_settings): void
    {
        $blog_settings->get('periodical')->put('periodical_active', !empty($_POST['periodical_active']));
        $blog_settings->get('periodical')->put('periodical_upddate', !empty($_POST['periodical_upddate']));
        $blog_settings->get('periodical')->put('periodical_updurl', !empty($_POST['periodical_updurl']));
    }

    /**
     * User pref for periods columns lists.
     *
     * @param    arrayObject $cols Columns
     */
    public static function adminColumnsLists(ArrayObject $cols): void
    {
        $cols[My::id()] = [
            My::name(),
            [
                'curdt'   => [true, __('Next update')],
                'pub_int' => [true, __('Frequency')],
                'pub_nb'  => [true, __('Entries per update')],
                'nbposts' => [true, __('Entries')],
                'enddt'   => [true, __('End date')],
            ],
        ];

        $cols['posts'][1]['period'] = [true, __('Period')];
    }

    /**
     * User pref periods filters options.
     *
     * @param    arrayObject $sorts Sort options
     */
    public static function adminFiltersLists(ArrayObject $sorts): void
    {
        $sorts[My::id()] = [
            My::name(),
            My::sortbyCombo(),
            'periodical_curdt',
            'desc',
            [__('periods per page'), 10],
        ];
    }

    /**
     * Add columns period to posts list header.
     *
     * @param    dcRecord    $rs    record instance
     * @param    ArrayObject $cols  Columns
     */
    public static function adminPostListHeader(dcRecord $rs, ArrayObject $cols): void
    {
        if (dcCore::app()->blog->settings->get('periodical')->get('periodical_active')) {
            $cols['period'] = '<th scope="col">' . __('Period') . '</th>';
        }
    }

    /**
     * Add columns period to posts list values.
     *
     * @param    dcRecord    $rs    record instance
     * @param    ArrayObject $cols  Columns
     */
    public static function adminPostListValue(dcRecord $rs, ArrayObject $cols): void
    {
        if (!dcCore::app()->blog->settings->get('periodical')->get('periodical_active')) {
            return;
        }

        $r = Utils::getPosts(['post_id' => $rs->f('post_id')]);
        if ($r->isEmpty()) {
            $name = '-';
        } else {
            $url  = dcCore::app()->adminurl->get('admin.plugin.periodical', ['part' => 'period', 'period_id' => $r->f('periodical_id')]);
            $name = '<a href="' . $url . '#period" title="' . __('edit period') . '">' . html::escapeHTML($r->f('periodical_title')) . '</a>';
        }
        $cols['period'] = '<td class="nowrap">' . $name . '</td>';
    }

    /**
     * Dashboard Favorites.
     *
     * @param   dcFavorites $favs Array of favorites
     */
    public static function adminDashboardFavoritesV2(dcFavorites $favs): void
    {
        $favs->register(My::id(), [
            'title'       => My::name(),
            'url'         => dcCore::app()->adminurl->get('admin.plugin.' . My::id()),
            'small-icon'  => dcPage::getPF(My::id() . '/icon.svg'),
            'large-icon'  => dcPage::getPF(My::id() . '/icon.svg'),
            'permissions' => dcCore::app()->auth->makePermissions([
                dcAuth::PERMISSION_USAGE,
                dcAuth::PERMISSION_CONTENT_ADMIN,
            ]),
        ]);
    }

    /**
     * Add javascript for toggle
     *
     * @return string HTML head
     */
    public static function adminPostHeaders(): string
    {
        return dcPage::jsModuleLoad(My::id() . '/js/toggle.js');
    }

    /**
     * Delete relation between post and period
     *
     * @param  integer $post_id Post id
     */
    public static function adminBeforePostDelete(int $post_id): void
    {
        self::delPeriod($post_id);
    }

    /**
     * Add actions to posts page combo
     *
     * @param  dcPostsActions   $pa   dcPostsActions instance
     */
    public static function adminPostsActions(dcPostsActions $pa): void
    {
        $pa->addAction(
            [My::name() => [__('Add to periodical') => 'periodical_add']],
            [self::class, 'callbackAdd']
        );

        if (dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
            dcAuth::PERMISSION_DELETE,
            dcAuth::PERMISSION_CONTENT_ADMIN,
        ]), dcCore::app()->blog->id)) {
            $pa->addAction(
                [My::name() => [__('Remove from periodical') => 'periodical_remove']],
                [self::class, 'callbackRemove']
            );
        }
    }

    /**
     * Posts actions callback to remove period
     *
     * @param  dcPostsActions   $pa   dcPostsActions instance
     * @param  ArrayObject        $post _POST actions
     */
    public static function callbackRemove(dcPostsActions $pa, ArrayObject $post): void
    {
        # No entry
        $posts_ids = $pa->getIDs();
        if (empty($posts_ids)) {
            throw new Exception(__('No entry selected'));
        }

        # No right
        if (!dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
            dcAuth::PERMISSION_DELETE,
            dcAuth::PERMISSION_CONTENT_ADMIN,
        ]), dcCore::app()->blog->id)) {
            throw new Exception(__('No enough right'));
        }

        # Remove linked period
        foreach ($posts_ids as $post_id) {
            self::delPeriod($post_id);
        }

        dcPage::addSuccessNotice(__('Posts have been removed from periodical.'));
        $pa->redirect(true);
    }

    /**
     * Posts actions callback to add period
     *
     * @param  dcPostsActions   $pa   dcPostsActions instance
     * @param  ArrayObject        $post _POST actions
     */
    public static function callbackAdd(dcPostsActions $pa, ArrayObject $post): void
    {
        # No entry
        $posts_ids = $pa->getIDs();
        if (empty($posts_ids)) {
            throw new Exception(__('No entry selected'));
        }

        //todo: check if selected posts is unpublished

        # Save action
        if (!empty($post['periodical'])) {
            foreach ($posts_ids as $post_id) {
                self::delPeriod($post_id);
                self::addPeriod($post_id, (int) $post['periodical']);
            }

            dcPage::addSuccessNotice(__('Posts have been added to periodical.'));
            $pa->redirect(true);
        }

        # Display form
        else {
            $pa->beginPage(
                dcPage::breadcrumb([
                    html::escapeHTML(dcCore::app()->blog->name) => '',
                    $pa->getCallerTitle()                       => $pa->getRedirection(true),
                    __('Add a period to this selection')        => '',
                ])
            );

            echo
            '<form action="' . $pa->getURI() . '" method="post">' .
            $pa->getCheckboxes() .

            self::formPeriod() .

            '<p>' .
            dcCore::app()->formNonce() .
            $pa->getHiddenFields() .
            form::hidden(['action'], 'periodical_add') .
            '<input type="submit" value="' . __('Save') . '" /></p>' .
            '</form>';

            $pa->endPage();
        }
    }

    /**
     * Add form to post sidebar
     *
     * @param  ArrayObject $main_items    Main items
     * @param  ArrayObject $sidebar_items Sidebar items
     * @param  dcRecord    $post          Post record or null
     */
    public static function adminPostFormItems(ArrayObject $main_items, ArrayObject $sidebar_items, ?dcRecord $post): void
    {
        # Get existing linked period
        $period = '';
        if ($post !== null) {
            $rs     = Utils::getPosts(['post_id' => $post->f('post_id')]);
            $period = $rs->isEmpty() ? '' : $rs->f('periodical_id');
        }

        # Set linked period form items
        $sidebar_items['options-box']['items']['period'] = self::formPeriod((int) $period);
    }

    /**
     * Save linked period
     *
     * @param  cursor   $cur     Current post cursor
     * @param  null|int $post_id Post id
     */
    public static function adminAfterPostSave(cursor $cur, ?int $post_id): void
    {
        if (!isset($_POST['periodical']) || $post_id === null) {
            return;
        }

        # Delete old linked period
        self::delPeriod($post_id);

        # Add new linked period
        self::addPeriod($post_id, (int) $_POST['periodical']);
    }

    /**
     * Posts period form field
     *
     * @param  int      $period Period
     * @return string           Period form content
     */
    private static function formPeriod(int $period = 0): string
    {
        $combo = self::comboPeriod();

        if (empty($combo)) {
            return '';
        }

        return
        '<p><label for="periodical">' .
        __('Periodical') . '</label>' .
        form::combo('periodical', $combo, $period) .
        '</p>';
    }

    /**
     * Combo of available periods
     *
     * @return array       List of period
     */
    private static function comboPeriod(): array
    {
        if (empty(self::$combo_period)) {
            $periods = Utils::getPeriods();

            if (!$periods->isEmpty()) {
                $combo = ['-' => ''];
                while ($periods->fetch()) {
                    $combo[html::escapeHTML($periods->f('periodical_title'))] = $periods->f('periodical_id');
                }
                self::$combo_period = $combo;
            }
        }

        return self::$combo_period;
    }

    /**
     * Remove period from posts.
     *
     * @param  int $post_id Post id
     */
    private static function delPeriod(int $post_id): void
    {
        Utils::delPost((int) $post_id);
    }

    /**
     * Add period to posts
     *
     * @param  int $post_id    Post id
     * @param  int $period_id  Period
     */
    private static function addPeriod(int $post_id, int $period_id): void
    {
        # Get periods
        $period = Utils::getPeriods(['periodical_id' => $period_id]);

        # No period
        if ($period->isEmpty()) {
            return;
        }

        # Add relation
        Utils::addPost($period_id, $post_id);
    }
}
