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

dcCore::app()->blog->settings->addNamespace('periodical');

dcCore::app()->addBehavior(
    'adminBlogPreferencesFormV2',
    ['adminPeriodical', 'adminBlogPreferencesForm']
);
dcCore::app()->addBehavior(
    'adminBeforeBlogSettingsUpdate',
    ['adminPeriodical', 'adminBeforeBlogSettingsUpdate']
);
dcCore::app()->addBehavior(
    'adminFiltersListsV2',
    ['adminPeriodical', 'adminFiltersLists']
);
dcCore::app()->addBehavior(
    'adminColumnsListsV2',
    ['adminPeriodical', 'adminColumnsLists']
);
dcCore::app()->addBehavior(
    'adminPostListHeaderV2',
    ['adminPeriodical', 'adminPostListHeader']
);
dcCore::app()->addBehavior(
    'adminPostListValueV2',
    ['adminPeriodical', 'adminPostListValue']
);

if (dcCore::app()->blog->settings->periodical->periodical_active) {

    dcCore::app()->menu[dcAdmin::MENU_PLUGINS]->addItem(
        __('Periodical'),
        dcCore::app()->adminurl->get('admin.plugin.periodical'),
        dcPage::getPF('periodical/icon.png'),
        preg_match('/' . preg_quote(dcCore::app()->adminurl->get('admin.plugin.periodical')) . '(&.*)?$/', $_SERVER['REQUEST_URI']),
        dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
            dcAuth::PERMISSION_USAGE,
            dcAuth::PERMISSION_CONTENT_ADMIN,
        ]), dcCore::app()->blog->id)
    );

    dcCore::app()->addBehavior(
        'adminDashboardFavoritesV2',
        ['adminPeriodical', 'adminDashboardFavorites']
    );
    dcCore::app()->addBehavior(
        'adminPostHeaders',
        ['adminPeriodical', 'adminPostHeaders']
    );
    dcCore::app()->addBehavior(
        'adminPostsActions',
        ['adminPeriodical', 'adminPostsActions']
    );
    dcCore::app()->addBehavior(
        'adminPostFormItems',
        ['adminPeriodical', 'adminPostFormItems']
    );
    dcCore::app()->addBehavior(
        'adminAfterPostUpdate',
        ['adminPeriodical', 'adminAfterPostSave']
    );
    dcCore::app()->addBehavior(
        'adminAfterPostCreate',
        ['adminPeriodical', 'adminAfterPostSave']
    );
}

dcCore::app()->addBehavior(
    'adminBeforePostDelete',
    ['adminPeriodical', 'adminBeforePostDelete']
);

/**
 * @ingroup DC_PLUGIN_PERIODICAL
 * @brief Periodical - admin methods.
 * @since 2.6
 */
class adminPeriodical
{
    public static $combo_period = null;
    protected static $per = null;

    public static function sortbyCombo()
    {
        return [
            __('Next update') => 'periodical_curdt',
            __('End date')    => 'periodical_enddt',
            __('Frequence')   => 'periodical_pub_int'
        ];
    }

    protected static function period()
    {
        if (self::$per === null) {
            self::$per = new periodical();
        }
        return self::$per;
    }

    /**
     * Add settings to blog preference
     * 
     * @param  dcSettings   $blog_settings  dcSettings instance
     */
    public static function adminBlogPreferencesForm(dcSettings $blog_settings)
    {
        $s_active = (boolean) $blog_settings->periodical->periodical_active;
        $s_upddate = (boolean) $blog_settings->periodical->periodical_upddate;
        $s_updurl = (boolean) $blog_settings->periodical->periodical_updurl;

        echo
        '<div class="fieldset"><h4 id="periodical_params">' . __('Periodical') . '</h4>' .
        '<div class="two-cols">' .
        '<div class="col">' .
        '<p><label class="classic" for="periodical_active">' .
        form::checkbox('periodical_active', 1, $s_active) .
        __('Enable periodical on this blog') . '</label></p>' .
        '</div>' .
        '<div class="col">' .
        '<p><label for="periodical_upddate">' .
        form::checkbox('periodical_upddate', 1, $s_upddate) .
        __('Update post date when publishing it') . '</label></p>' .
        '<p><label for="periodical_updurl">' .
        form::checkbox('periodical_updurl', 1, $s_updurl) .
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
    public static function adminBeforeBlogSettingsUpdate(dcSettings $blog_settings)
    {
        $blog_settings->periodical->put('periodical_active', !empty($_POST['periodical_active']));
        $blog_settings->periodical->put('periodical_upddate', !empty($_POST['periodical_upddate']));
        $blog_settings->periodical->put('periodical_updurl', !empty($_POST['periodical_updurl']));
    }

    /**
     * User pref for periods columns lists.
     *
     * @param    arrayObject $cols Columns
     */
    public static function adminColumnsLists($cols)
    {
        $cols['periodical'] = [
            __('Periodical'),
            [
                'curdt'   => [true, __('Next update')],
                'pub_int' => [true, __('Frequency')],
                'pub_nb'  => [true, __('Entries per update')],
                'nbposts' => [true, __('Entries')],
                'enddt'   => [true, __('End date')]
            ]
        ];

        $cols['posts'][1]['period'] = [true, __('Period')];
    }

    /**
     * User pref periods filters options.
     *
     * @param    arrayObject $sorts Sort options
     */
    public static function adminFiltersLists($sorts)
    {
        $sorts['periodical'] = [
            __('Periodical'),
            self::sortbyCombo(),
            'periodical_curdt',
            'desc',
            [__('periods per page'), 10]
        ];
    }

    /**
     * Add columns period to posts list header.
     *
     * @param    record      $rs    record instance
     * @param    arrayObject $cols  Columns
     */
    public static function adminPostListHeader($rs, $cols)
    {
        if (dcCore::app()->blog->settings->periodical->periodical_active) {
            $cols['period'] = '<th scope="col">' . __('Period') . '</th>';
        }
    }

    /**
     * Add columns period to posts list values.
     *
     * @param    record      $rs    record instance
     * @param    arrayObject $cols  Columns
     */
    public static function adminPostListValue($rs, $cols)
    {
        if (!dcCore::app()->blog->settings->periodical->periodical_active) {
            return null;
        }

        $r = self::period()->getPosts(['post_id' => $rs->post_id]);
        if ($r->isEmpty()) {
            $name = '-';
        } else {
            $url  = dcCore::app()->adminurl->get('admin.plugin.periodical', ['part' => 'period', 'period_id' => $r->periodical_id]);
            $name = '<a href="' . $url . '#period" title="' . __('edit period') . '">' . html::escapeHTML($r->periodical_title) . '</a>';
        }
        $cols['period'] = '<td class="nowrap">' . $name . '</td>';
    }

    /**
     * Favorites.
     *
     * @param   arrayObject $favs Array of favorites
     */
    public static function adminDashboardFavorites(dcFavorites $favs)
    {
        $favs->register('periodical', [
            'title' => __('Periodical'),
            'url' => 'plugin.php?p=periodical',
            'small-icon' => 'index.php?pf=periodical/icon.png',
            'large-icon' => 'index.php?pf=periodical/icon-big.png',
            'permissions' => dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                    dcAuth::PERMISSION_USAGE,
                    dcAuth::PERMISSION_CONTENT_ADMIN,
                ]),
                dcCore::app()->blog->id
            ),
            'active_cb' => [
                'adminPeriodical', 
                'adminDashboardFavoritesActive'
            ]
        ]);
    }

    /**
     * Favorites selection.
     *
     * @param   string $request Requested page
     * @param   array  $params  Requested parameters
     */
    public static function adminDashboardFavoritesActive($request, $params)
    {
        return $request == 'plugin.php' 
            && isset($params['p']) 
            && $params['p'] == 'periodical';
    }

    /**
     * Add javascript for toggle
     * 
     * @return string HTML head
     */
    public static function adminPostHeaders()
    {
        return dcPage::jsLoad('index.php?pf=periodical/js/toggle.js');
    }

    /**
     * Delete relation between post and period
     * 
     * @param  integer $post_id Post id
     */
    public static function adminBeforePostDelete($post_id)
    {
        self::delPeriod($post_id);
    }

    /**
     * Add actions to posts page combo
     * 
     * @param  dcPostsActions   $ap   dcPostsActions instance
     */
    public static function adminPostsActions(dcPostsActions $pa)
    {
        $pa->addAction(
            [__('Periodical') => [__('Add to periodical') => 'periodical_add']],
            ['adminPeriodical', 'callbackAdd']
        );

        if (!dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
            dcAuth::PERMISSION_DELETE,
            dcAuth::PERMISSION_CONTENT_ADMIN,
        ]), dcCore::app()->blog->id)) {
            return null;
        }
        $pa->addAction(
            [__('Periodical') => [__('Remove from periodical') => 'periodical_remove']],
            ['adminPeriodical', 'callbackRemove']
        );
    }

    /**
     * Posts actions callback to remove period
     * 
     * @param  dcPostsActions   $pa   dcPostsActions instance
     * @param  ArrayObject        $post _POST actions
     */
    public static function callbackRemove(dcPostsActions $pa, ArrayObject $post)
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
        foreach($posts_ids as $post_id) {
            self::delPeriod($post_id);
        }

        dcAdminNotices::addSuccessNotice(__('Posts have been removed from periodical.'));
        $pa->redirect(true);
    }

    /**
     * Posts actions callback to add period
     * 
     * @param  dcPostsActions   $pa   dcPostsActions instance
     * @param  ArrayObject        $post _POST actions
     */
    public static function callbackAdd(dcPostsActions $pa, ArrayObject $post)
    {
        # No entry
        $posts_ids = $pa->getIDs();
        if (empty($posts_ids)) {
            throw new Exception(__('No entry selected'));
        }

        //todo: check if selected posts is unpublished

        # Save action
        if (!empty($post['periodical'])) {
            foreach($posts_ids as $post_id) {
                self::delPeriod($post_id);
                self::addPeriod($post_id, $post['periodical']);
            }

            dcAdminNotices::addSuccessNotice(__('Posts have been added to periodical.'));
            $pa->redirect(true);
        }

        # Display form
        else {
            $pa->beginPage(
                dcPage::breadcrumb(array(
                    html::escapeHTML(dcCore::app()->blog->name) => '',
                    $pa->getCallerTitle() => $pa->getRedirection(true),
                    __('Add a period to this selection') => '' 
                ))
            );

            echo
            '<form action="' . $pa->getURI() . '" method="post">' .
            $pa->getCheckboxes() .

            self::formPeriod() .

            '<p>'.
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
     * @param  record      $post          Post record or null
     */
    public static function adminPostFormItems(ArrayObject $main_items, ArrayObject $sidebar_items, $post)
    {
        # Get existing linked period
        $period = '';
        if ($post) {
            $rs = self::period()->getPosts(['post_id' => $post->post_id]);
            $period = $rs->isEmpty() ? '' : $rs->periodical_id;
        }

        # Set linked period form items
        $sidebar_items['options-box']['items']['period'] =
            self::formPeriod($period);
    }

    /**
     * Save linked period
     * 
     * @param  cursor  $cur     Current post cursor
     * @param  integer $post_id Post id
     */
    public static function adminAfterPostSave(cursor $cur, $post_id)
    {
        if (!isset($_POST['periodical'])) {
            return null;
        }

        # Delete old linked period
        self::delPeriod($post_id);

        # Add new linked period
        self::addPeriod($post_id, $_POST['periodical']);
    }

    /**
     * Posts period form field
     * 
     * @param  string $period Period
     * @return string         Period form content
     */
    protected static function formPeriod($period='')
    {
        $combo = self::comboPeriod();

        if (empty($combo)) {
            return null;
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
    protected static function comboPeriod()
    {
        if (adminPeriodical::$combo_period === null) {
            $periods = self::period()->getPeriods();

            if ($periods->isEmpty()) {
                adminPeriodical::$combo_period = [];
            } else {
                $combo = ['-' => ''];
                while ($periods->fetch()) {
                    $combo[html::escapeHTML($periods->periodical_title)] = $periods->periodical_id;
                }
            }
            adminPeriodical::$combo_period = $combo;
        }

        return adminPeriodical::$combo_period;
    }

    /**
     * Remove period from posts.
     * 
     * @param  integer $post_id Post id
     */
    protected static function delPeriod($post_id)
    {
        if ($post_id === null) {
            return null;
        }

        $post_id = (integer) $post_id;
        self::period()->delPost($post_id);
    }

    /**
     * Add period to posts
     * 
     * @param  integer $post_id Post id
     * @param  array   $period  Period
     */
    protected static function addPeriod($post_id, $period)
    {
        # Not saved
        if ($post_id === null || empty($period)) {
            return null;
        }

        # Get periods
        $period = self::period()->getPeriods(['periodical_id' => $period]);

        # No period
        if ($period->isEmpty()) {
            return null;
        }

        $post_id = (integer) $post_id;

        # Add relation
        self::period()->addPost($period->periodical_id, $post_id);
    }
}