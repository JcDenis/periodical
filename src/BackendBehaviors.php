<?php

declare(strict_types=1);

namespace Dotclear\Plugin\periodical;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Favorites;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Backend\Action\ActionsPosts;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Img;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\None;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;
use Dotclear\Interface\Core\BlogSettingsInterface;
use Exception;

/**
 * @brief       periodical backend behaviors class.
 * @ingroup     periodical
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class BackendBehaviors
{
    /**
     * Periods combo.
     *
     * @var     array<string, string>  $combo_period
     */
    private static array $combo_period = [];

    /**
     * Add settings to blog preference.
     *
     * @param   BlogSettingsInterface  $blog_settings  BlogSettingsInterface instance
     */
    public static function adminBlogPreferencesFormV2(BlogSettingsInterface $blog_settings): void
    {
        $s = $blog_settings->get(My::id());

        echo
        (new Fieldset(My::id() . '_params'))
            ->legend(new Legend((new Img(My::icons()[0]))->class('icon-small')->render() . ' ' . My::name()))
            ->items([
                (new Div())
                    ->class('two-cols')
                    ->items([
                        (new Div())
                            ->class('col')
                            ->items([
                                (new Para())
                                    ->items([
                                        (new Checkbox(My::id() . 'active', $s->getBool('periodical_active', false)))
                                            ->value(1)
                                            ->label(new Label(__('Enable periodical on this blog'), Label::IL_FT)),
                                    ]),
                            ]),
                        (new Div())
                            ->class('col')
                            ->items([
                                (new Para())
                                    ->items([
                                        (new Checkbox(My::id() . 'upddate', $s->getBool('periodical_upddate', false)))
                                            ->value(1)
                                            ->label(new Label(__('Update post date when publishing it'), Label::IL_FT)),
                                    ]),
                                (new Para())
                                    ->items([
                                        (new Checkbox(My::id() . 'updurl', $s->getBool('periodical_updurl', false)))
                                            ->value(1)
                                            ->label(new Label(__('Update post url when publishing it'), Label::IL_FT)),
                                    ]),
                            ]),
                    ]),
            ])
            ->render();
    }

    /**
     * Save blog settings.
     *
     * @param   BlogSettingsInterface  $blog_settings  BlogSettingsInterface instance
     */
    public static function adminBeforeBlogSettingsUpdate(BlogSettingsInterface $blog_settings): void
    {
        $blog_settings->get(My::id())->put('periodical_active', !empty($_POST[My::id() . 'active']));
        $blog_settings->get(My::id())->put('periodical_upddate', !empty($_POST[My::id() . 'upddate']));
        $blog_settings->get(My::id())->put('periodical_updurl', !empty($_POST[My::id() . 'updurl']));
    }

    /**
     * User pref for periods columns lists.
     *
     * @param   ArrayObject<string, mixed>  $cols   Columns
     */
    public static function adminColumnsListsV2(ArrayObject $cols): void
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

        if (!isset($cols['posts']) || !is_array($cols['posts'])) {
            $cols['posts'] = [];
        }
        if (!isset($cols['posts'][1]) || !is_array($cols['posts'][1])) {
            $cols['posts'][1] = [];
        }

        $cols['posts'][1]['period'] = [true, __('Period')];
    }

    /**
     * User pref periods filters options.
     *
     * @param   ArrayObject<string, mixed>  $sorts  Sort options
     */
    public static function adminFiltersListsV2(ArrayObject $sorts): void
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
     * @param   MetaRecord                  $rs     Record instance
     * @param   ArrayObject<string, mixed>  $cols   Columns
     */
    public static function adminPostListHeaderV2(MetaRecord $rs, ArrayObject $cols): void
    {
        if (My::settings()->getBool('periodical_active', false)) {
            $cols['period'] = '<th scope="col">' . __('Period') . '</th>';
        }
    }

    /**
     * Add columns period to posts list values.
     *
     * @param   MetaRecord                  $rs     Record instance
     * @param   ArrayObject<string, mixed>  $cols   Columns
     */
    public static function adminPostListValueV2(MetaRecord $rs, ArrayObject $cols): void
    {
        if (!My::settings()->getBool('periodical_active', false)) {
            return;
        }

        $r = Utils::getPosts(['post_id' => $rs->intField('post_id')]);
        if ($r->isEmpty()) {
            $name = '-';
        } else {
            $url  = My::manageUrl(['part' => 'period', 'period_id' => $r->intField('periodical_id')]);
            $name = '<a href="' . $url . '#period" title="' . __('edit period') . '">' . Html::escapeHTML($r->strField('periodical_title')) . '</a>';
        }
        $cols['period'] = '<td class="nowrap">' . $name . '</td>';
    }

    /**
     * Dashboard Favorites.
     *
     * @param   Favorites   $favs   Array of favorites
     */
    public static function adminDashboardFavoritesV2(Favorites $favs): void
    {
        $favs->register(My::id(), [
            'title'       => My::name(),
            'url'         => My::manageUrl(),
            'small-icon'  => My::icons(),
            'large-icon'  => My::icons(),
            'permissions' => App::auth()->makePermissions([
                App::auth()::PERMISSION_USAGE,
                App::auth()::PERMISSION_CONTENT_ADMIN,
            ]),
        ]);
    }

    /**
     * Add javascript for toggle.
     *
     * @return  string  HTML head
     */
    public static function adminPostHeaders(): string
    {
        return My::jsLoad('toggle');
    }

    /**
     * Delete relation between post and period.
     *
     * @param   int     $post_id    Post id
     */
    public static function adminBeforePostDelete(int $post_id): void
    {
        self::delPeriod($post_id);
    }

    /**
     * Add actions to posts page combo.
     *
     * @param   ActionsPosts    $pa     ActionsPosts instance
     */
    public static function adminPostsActions(ActionsPosts $pa): void
    {
        $pa->addAction(
            [My::name() => [__('Add to periodical') => 'periodical_add']],
            self::callbackAdd(...)
        );

        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_DELETE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $pa->addAction(
                [My::name() => [__('Remove from periodical') => 'periodical_remove']],
                self::callbackRemove(...)
            );
        }
    }

    /**
     * Posts actions callback to remove period.
     *
     * @param   ActionsPosts                $pa     ActionsPosts instance
     * @param   ArrayObject<string, mixed>  $post   _POST actions
     */
    public static function callbackRemove(ActionsPosts $pa, ArrayObject $post): void
    {
        // No entry
        $posts_ids = $pa->getIDs();
        if (empty($posts_ids)) {
            throw new Exception(__('No entries selected'));
        }

        // No right
        if (!App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_DELETE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            throw new Exception(__('No enough right'));
        }

        // Remove linked period
        foreach ($posts_ids as $post_id) {
            self::delPeriod((int) $post_id);
        }

        Notices::addSuccessNotice(__('Posts have been removed from periodical.'));
        $pa->redirect(true);
    }

    /**
     * Posts actions callback to add period.
     *
     * @param   ActionsPosts                $pa     ActionsPosts instance
     * @param   ArrayObject<string, mixed>  $post   _POST actions
     */
    public static function callbackAdd(ActionsPosts $pa, ArrayObject $post): void
    {
        // No entry
        $posts_ids = $pa->getIDs();
        if (empty($posts_ids)) {
            throw new Exception(__('No entries selected'));
        }

        //todo: check if selected posts is unpublished

        // Save action
        if (!empty($post['periodical']) && is_numeric($post['periodical'])) {
            foreach ($posts_ids as $post_id) {
                self::delPeriod((int) $post_id);
                self::addPeriod((int) $post_id, (int) $post['periodical']);
            }

            Notices::addSuccessNotice(__('Posts have been added to periodical.'));
            $pa->redirect(true);
        }

        // Display form
        else {
            $pa->beginPage(
                Page::breadcrumb([
                    Html::escapeHTML(App::blog()->name()) => '',
                    $pa->getCallerTitle()                 => $pa->getRedirection(true),
                    __('Add a period to this selection')  => '',
                ])
            );

            echo
            (new Form('periodicaladd'))->method('post')->action($pa->getURI())->fields([
                (new Text('', $pa->getCheckboxes())),
                self::formPeriod(0),
                (new Para())->items([
                    App::nonce()->formNonce(),
                    (new Hidden(['action'], 'periodical_add')),
                    (new Submit(['do']))->value(__('Save')),
                    ... $pa->hiddenFields(),
                ]),
            ])->render();

            $pa->endPage();
        }
    }

    /**
     * Add form to post sidebar.
     *
     * @param   ArrayObject<string, mixed>  $main_items     Main items
     * @param   ArrayObject<string, mixed>  $sidebar_items  Sidebar items
     * @param   null|MetaRecord             $post           Post record or null
     */
    public static function adminPostFormItems(ArrayObject $main_items, ArrayObject $sidebar_items, ?MetaRecord $post): void
    {
        // Get existing linked period
        $period = 0;
        if ($post !== null) {
            $rs     = Utils::getPosts(['post_id' => $post->intField('post_id')]);
            $period = $rs->isEmpty() ? 0 : $rs->intField('periodical_id');
        }

        // Set linked period form items
        if (!is_array($sidebar_items['options-box'])) {
            $sidebar_items['options-box'] = [];
        }
        if (!is_array($sidebar_items['options-box']['items'])) {
            $sidebar_items['options-box']['items'] = [];
        }
        $sidebar_items['options-box']['items']['period'] = (string) self::formPeriod($period)->render();
    }

    /**
     * Save linked period.
     *
     * @param   Cursor      $cur        Current post Cursor
     * @param   null|int    $post_id    Post id
     */
    public static function adminAfterPostSave(Cursor $cur, ?int $post_id): void
    {
        if (is_int($post_id) && isset($_POST['periodical']) && is_numeric($_POST['periodical'])) {
            $p = (int) $_POST['periodical'];

            // Delete old linked period
            self::delPeriod($post_id);

            // Add new linked period
            self::addPeriod($post_id, $p);
        }
    }

    /**
     * Posts period form field.
     *
     * @param   int         $period     Period
     *
     * @return  None|Para   Period form object
     */
    private static function formPeriod(int $period = 0): None|Para
    {
        $combo = self::comboPeriod();

        return empty($combo) ? new None() : (new Para())->items([
            (new Label(__('Period:')))->for('periodical'),
            (new Select('periodical'))->default((string) $period)->items($combo),
        ]);
    }

    /**
     * Combo of available periods.
     *
     * @return  array<string, string>  List of period
     */
    private static function comboPeriod(): array
    {
        if (empty(self::$combo_period)) {
            $periods = Utils::getPeriods();

            if (!$periods->isEmpty()) {
                $combo = ['-' => ''];
                while ($periods->fetch()) {
                    $combo[Html::escapeHTML($periods->strField('periodical_title'))] = $periods->strField('periodical_id');
                }
                self::$combo_period = $combo;
            }
        }

        return self::$combo_period;
    }

    /**
     * Remove period from posts.
     *
     * @param   int     $post_id    Post id
     */
    private static function delPeriod(int $post_id): void
    {
        Utils::delPost((int) $post_id);
    }

    /**
     * Add period to posts.
     *
     * @param   int     $post_id    Post id
     * @param   int     $period_id  Period
     */
    private static function addPeriod(int $post_id, int $period_id): void
    {
        // Get periods
        $period = Utils::getPeriods(['periodical_id' => $period_id]);

        // No period
        if ($period->isEmpty()) {
            return;
        }

        // Add relation
        Utils::addPost($period_id, $post_id);
    }
}
