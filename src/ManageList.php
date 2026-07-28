<?php

declare(strict_types=1);

namespace Dotclear\Plugin\periodical;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Filter\Filters;
use Dotclear\Core\Backend\Filter\FilterPosts;
use Dotclear\Core\Backend\Listing\Listing;
use Dotclear\Core\Backend\Listing\Pager;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Component;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;

/**
 * @brief       periodical periods list class.
 * @ingroup     periodical
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ManageList extends Listing
{
    /**
     * Display periods list.
     *
     * @param   Filters     $filter         The periods filter
     * @param   string      $enclose_block  The enclose block
     */
    public function periodDisplay(Filters $filter, string $enclose_block = ''): void
    {
        if ($this->rs->isEmpty()) {
            echo
            (new Text('p', $filter->show() ? __('No periods match the filter') : __('No periods')))
                ->class('info')
                ->render();

            return;
        }

        $pager = new Pager(
            is_numeric($filter->value('page')) ? (int) $filter->value('page') : 0,
            (int) $this->rs_count,
            is_numeric($filter->value('nb')) ? (int) $filter->value('nb') : 10,
            10
        );

        $periods = [];
        if (isset($_REQUEST['periods']) && is_array($_REQUEST['periods'])) {
            foreach ($_REQUEST['periods'] as $v) {
                if (is_numeric($v)) {
                    $periods[(int) $v] = true;
                }
            }
        }

        /**
         * @var ArrayObject<string, Component>
         */
        $cols = new ArrayObject([
            'name' => (new Text('th', __('Name')))
                ->class('first')
                ->extra('colspan="2"'),
            'curdt' => (new Text('th', __('Next update')))
                ->class('nowrap')
                ->extra('scope="col"'),
            'pub_int' => (new Text('th', __('Frequency')))
                ->class('nowrap')
                ->extra('scope="col"'),
            'pub_nb' => (new Text('th', __('Entries per update')))
                ->class('nowrap')
                ->extra('scope="col"'),
            'nbposts' => (new Text('th', __('Entries')))
                ->class('nowrap')
                ->extra('scope="col"'),
            'enddt' => (new Text('th', __('End date')))
                ->class('nowrap')
                ->extra('scope="col"'),
        ]);

        $this->userColumns(My::id(), $cols);

        $lines = [];
        while ($this->rs->fetch()) {
            $lines[] = $this->periodLine(isset($periods[$this->rs->intField('periodical_id')]));
        }

        echo
        $pager->getLinks() .
        sprintf(
            $enclose_block,
            (new Div())
                ->class('table-outer')
                ->items([
                    (new Para(null, 'table'))
                        ->items([
                            (new Text(
                                'caption',
                                $filter->show() ?
                                sprintf(__('List of %s periods matching the filter.'), $this->rs_count) :
                                sprintf(__('List of periods. (%s)'), $this->rs_count)
                            )),
                            (new Para(null, 'tr'))
                                ->items(iterator_to_array($cols)),
                            (new Para(null, 'tbody'))
                                ->items($lines),
                        ]),
                ])
                ->render()
        ) .
        $pager->getLinks();
    }

    /**
     * Display a period list line.
     *
     * @param   bool    $checked    Selected line
     */
    private function periodLine(bool $checked): Component
    {
        $tz       = is_string(App::auth()->getInfo('user_tz')) ? App::auth()->getInfo('user_tz') : 'UTC';
        $nb_posts = Utils::getPosts(['periodical_id' => $this->rs->intField('periodical_id')], true)->cardinal();
        $url      = My::manageUrl(['part' => 'period', 'period_id' => $this->rs->intField('periodical_id')]);
        $name     = '<a href="' . $url . '#period" title="' . __('edit period') . '">' . Html::escapeHTML($this->rs->strField('periodical_title')) . '</a>';
        $posts    = $nb_posts ? '<a href="' . $url . '#posts" title="' . __('view related entries') . '">' . $nb_posts . '</a>' : '0';
        $interval = in_array($this->rs->strField('periodical_pub_int'), My::periodCombo()) ?
            __((string) array_search($this->rs->strField('periodical_pub_int'), My::periodCombo())) : __('Unknow frequence');

        /**
         * @var ArrayObject<string, Component>
         */
        $cols = new ArrayObject([
            'check' => (new Para(null, 'td'))
                ->class('nowrap minimal')
                ->items([
                    (new Checkbox(['periods[]'], $checked))
                        ->value($this->rs->intField('periodical_id')),
                ]),
            'name' => (new Text('td', $name))
                ->class('maximal'),
            'curdt' => (new Text('td', Html::escapeHTML(Date::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->strField('periodical_curdt'), $tz))))
                ->class('nowrap minimal'),
            'pub_int' => (new Text('td', Html::escapeHTML($interval)))
                ->class('nowrap'),
            'pub_nb' => (new Text('td', $this->rs->strField('periodical_pub_nb')))
                ->class('nowrap count'),
            'nbposts' => (new Text('td', $posts))
                ->class('nowrap count'),
            'enddt' => (new Text('td', Html::escapeHTML(Date::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->strField('periodical_enddt'), $tz))))
                ->class('nowrap minimal'),
        ]);

        $this->userColumns(My::id(), $cols);

        return
        (new Para('p' . $this->rs->intField('periodical_id'), 'tr'))
            ->class('line' . ($nb_posts ? '' : ' offline'))
            ->items(iterator_to_array($cols));
    }

    /**
     * Display period posts list.
     *
     * @param   FilterPosts     $filter         The posts filter
     * @param   string          $base_url       The page base URL
     * @param   string          $enclose_block  The enclose block
     */
    public function postDisplay(FilterPosts $filter, string $base_url, string $enclose_block = ''): void
    {
        if ($this->rs->isEmpty()) {
            echo
            (new Text('p', $filter->show() ? __('No entries match the filter') : __('No entries')))
                ->class('info')
                ->render();

            return;
        }

        $pager = new Pager(
            is_numeric($filter->value('page')) ? (int) $filter->value('page') : 0,
            (int) $this->rs_count,
            is_numeric($filter->value('nb')) ? (int) $filter->value('nb') : 10,
            10
        );
        $pager->base_url = $base_url;

        $periodical_entries = [];
        if (isset($_REQUEST['periodical_entries']) && is_array($_REQUEST['periodical_entries'])) {
            foreach ($_REQUEST['periodical_entries'] as $v) {
                if (is_numeric($v)) {
                    $periodical_entries[(int) $v] = true;
                }
            }
        }

        /**
         * @var ArrayObject<string, Component>
         */
        $cols = new ArrayObject([
            'title' => (new Text('th', __('Title')))
                ->class('first')
                ->extra('colspan="2"'),
            'date' => (new Text('th', __('Date')))
                ->extra('scope="col"'),
            'category' => (new Text('th', __('Category')))
                ->extra('scope="col"'),
            'author' => (new Text('th', __('Author')))
                ->extra('scope="col"'),
            'status' => (new Text('th', __('Status')))
                ->extra('scope="col"'),
            'create' => (new Text('th', __('Create date')))
                ->class('nowrap')
                ->extra('scope="col"'),
        ]);
        $this->userColumns(My::id() . 'posts', $cols);

        $lines = [];
        while ($this->rs->fetch()) {
            $lines[] = $this->postLine(isset($periodical_entries[$this->rs->intField('post_id')]));
        }

        $img = '<img alt="%1$s" title="%1$s" src="images/%2$s" /> %1$s';

        echo
        $pager->getLinks() .
        sprintf(
            $enclose_block,
            (new Div())
                ->class('table-outer')
                ->items([
                    (new Para(null, 'table'))
                        ->items([
                            (new Text(
                                'caption',
                                $filter->show() ?
                                sprintf(__('List of %s entries matching the filter.'), $this->rs_count) :
                                sprintf(__('List of entries. (%s)'), $this->rs_count)
                            )),
                            (new Para(null, 'tr'))
                                ->items(iterator_to_array($cols)),
                            (new Para(null, 'tbody'))
                                ->items($lines),
                        ]),
                    (new Text('p', __('Legend: ') . implode(' - ', [
                        sprintf($img, __('Published'), 'check-on.png'),
                        sprintf($img, __('Unpublished'), 'check-off.png'),
                        sprintf($img, __('Scheduled'), 'scheduled.png'),
                        sprintf($img, __('Pending'), 'check-wrn.png'),
                        sprintf($img, __('Protected'), 'locker.png'),
                        sprintf($img, __('Selected'), 'selected.png'),
                        sprintf($img, __('Attachments'), 'attach.png'),
                    ]))),
                ])
                ->render()
        ) .
        $pager->getLinks();
    }

    /**
     * Display post list line.
     *
     * @param   bool    $checked    Selected line
     */
    private function postLine(bool $checked): Component
    {
        if (App::auth()->check(App::auth()->makePermissions([App::auth()::PERMISSION_CATEGORIES]), App::blog()->id())) {
            $cat_link = '<a href="category.php?id=%s">%s</a>';
        } else {
            $cat_link = '%2$s';
        }

        if ($this->rs->strField('cat_title')) {
            $cat_title = sprintf(
                $cat_link,
                $this->rs->intField('cat_id'),
                Html::escapeHTML($this->rs->strField('cat_title'))
            );
        } else {
            $cat_title = __('None');
        }

        $img_status = '';
        $img        = '<img alt="%1$s" title="%1$s" src="images/%2$s" />';
        switch ((int) $this->rs->intField('post_status')) {
            case App::blog()::POST_PUBLISHED:
                $img_status = sprintf($img, __('Published'), 'check-on.png');

                break;

            case App::blog()::POST_UNPUBLISHED:
                $img_status = sprintf($img, __('Unpublished'), 'check-off.png');

                break;

            case App::blog()::POST_SCHEDULED:
                $img_status = sprintf($img, __('Scheduled'), 'scheduled.png');

                break;

            case App::blog()::POST_PENDING:
                $img_status = sprintf($img, __('Pending'), 'check-wrn.png');

                break;
        }

        $protected = '';
        if ($this->rs->strField('post_password')) {
            $protected = sprintf($img, __('Protected'), 'locker.png');
        }

        $selected = '';
        if ($this->rs->intField('post_selected')) {
            $selected = sprintf($img, __('Selected'), 'selected.png');
        }

        $attach   = '';
        $nb_media = $this->rs->countMedia();
        if ($nb_media > 0) {
            $attach_str = $nb_media == 1 ? __('%d attachment') : __('%d attachments');
            $attach     = sprintf($img, sprintf($attach_str, $nb_media), 'attach.png');
        }

        $tz = is_string(App::auth()->getInfo('user_tz')) ? App::auth()->getInfo('user_tz') : 'UTC';

        /**
         * @var ArrayObject<string, Component>
         */
        $cols = new ArrayObject([
            'check' => (new Para(null, 'td'))
                ->class('nowrap minimal')
                ->items([
                    (new Checkbox(['periodical_entries[]'], $checked))
                        ->value($this->rs->intField('post_id')),
                ]),
            'title' => (new Td())
                ->class('maximal')
                ->items([
                    (new Link())
                        ->href(App::postTypes()->getPostAdminURL($this->rs->strField('post_type'), $this->rs->intField('post_id')))
                        ->text(Html::escapeHTML($this->rs->strField('post_title'))),
                ]),
            'date' => (new Text('td', Html::escapeHTML(Date::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->strField('post_dt')))))
                ->class('nowrap minimal'),
            'category' => (new Text('td', Html::escapeHTML($cat_title)))
                ->class('nowrap minimal'),
            'author' => (new Text('td', Html::escapeHTML($this->rs->getUserCN())))
                ->class('nowrap minimal'),
            'status' => (new Text('td', $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach))
                ->class('nowrap status'),
            'create' => (new Text('td', Html::escapeHTML(Date::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->strField('post_creadt'), $tz))))
                ->class('nowrap'),
        ]);
        $this->userColumns(My::id() . 'posts', $cols);

        return
        (new Para('p' . $this->rs->intField('post_id'), 'tr'))
            ->class('line')
            ->items(iterator_to_array($cols));
    }
}
