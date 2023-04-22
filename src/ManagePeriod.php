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

use adminPostFilter;
use dcCore;
use dcNsProcess;
use dcPage;
use Dotclear\Helper\Html\Form\{
    Datetime,
    Div,
    Form,
    Hidden,
    Input,
    Label,
    Number,
    Para,
    Select,
    Submit,
    Text
};
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * Admin page for a period
 */
class ManagePeriod extends dcNsProcess
{
    public static function init(): bool
    {
        static::$init == defined('DC_CONTEXT_ADMIN')
            && My::phpCompliant()
            && !is_null(dcCore::app()->auth) && !is_null(dcCore::app()->blog)
            && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                dcCore::app()->auth::PERMISSION_USAGE,
                dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
            ]), dcCore::app()->blog->id)
            && ($_REQUEST['part'] ?? 'periods') === 'period';

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        if (is_null(dcCore::app()->blog)) {
            return false;
        }

        // Default values
        $vars = ManageVars::init();

        // Get period
        if ($vars->bad_period_id) {
            dcCore::app()->error->add(__('This period does not exist.'));
        }

        // Set period
        if ($vars->action == 'setperiod') {
            if ($vars->bad_period_curdt || $vars->bad_period_enddt) {
                dcCore::app()->error->add(__('Invalid date'));
            }

            // Check period title and dates
            $old_titles = Utils::getPeriods([
                'periodical_title' => $vars->period_title,
            ]);
            if (!$old_titles->isEmpty()) {
                while ($old_titles->fetch()) {
                    if (!$vars->period_id || $old_titles->f('periodical_id') != $vars->period_id) {
                        dcCore::app()->error->add(__('Period title is already taken'));
                    }
                }
            }
            if (empty($vars->period_title)) {
                dcCore::app()->error->add(__('Period title is required'));
            }
            if (strtotime($vars->period_curdt) > strtotime($vars->period_enddt)) {
                dcCore::app()->error->add(__('Start date must be older than end date'));
            }

            // If no error, set period
            if (!dcCore::app()->error->flag()) {
                $cur = Utils::openCursor();
                $cur->setField('periodical_title', $vars->period_title);
                $cur->setField('periodical_curdt', $vars->period_curdt);
                $cur->setField('periodical_enddt', $vars->period_enddt);
                $cur->setField('periodical_pub_int', $vars->period_pub_int);
                $cur->setField('periodical_pub_nb', $vars->period_pub_nb);

                // Update period
                if ($vars->period_id) {
                    Utils::updPeriod($vars->period_id, $cur);

                    self::redirect($vars->redir, $vars->period_id, '#period', __('Period successfully updated.'));
                // Create period
                } else {
                    $period_id = Utils::addPeriod($cur);

                    self::redirect($vars->redir, $period_id, '#period', __('Period successfully created.'));
                }
            }
        }

        // Actions on related posts
        if (!dcCore::app()->error->flag() && $vars->period_id && $vars->action && !empty($vars->entries)) {
            // Publish posts
            if ($vars->action == 'publish') {
                try {
                    foreach ($vars->entries as $id) {
                        dcCore::app()->blog->updPostStatus($id, 1);
                        Utils::delPost($id);
                    }

                    self::redirect($vars->redir, $vars->period_id, '#posts', __('Entries successfully published.'));
                } catch (Exception $e) {
                    dcCore::app()->error->add($e->getMessage());
                }
            }

            // Unpublish posts
            if ($vars->action == 'unpublish') {
                try {
                    foreach ($vars->entries as $id) {
                        dcCore::app()->blog->updPostStatus($id, 0);
                        Utils::delPost($id);
                    }

                    self::redirect($vars->redir, $vars->period_id, '#posts', __('Entries successfully unpublished.'));
                } catch (Exception $e) {
                    dcCore::app()->error->add($e->getMessage());
                }
            }

            // Remove posts from periodical
            if ($vars->action == 'remove_post_periodical') {
                try {
                    foreach ($vars->entries as $id) {
                        Utils::delPost($id);
                    }

                    self::redirect($vars->redir, $vars->period_id, '#posts', __('Entries successfully removed.'));
                } catch (Exception $e) {
                    dcCore::app()->error->add($e->getMessage());
                }
            }
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!static::$init) {
            return;
        }

        // nullsafe
        if (is_null(dcCore::app()->adminurl)) {
            return;
        }

        // Default values
        $vars = ManageVars::init();

        $starting_script = '';

        // Prepare combos for posts list
        if ($vars->period_id > 0) {
            // Filters
            $post_filter = new adminPostFilter();
            $post_filter->add('part', 'period');

            $params                  = $post_filter->params();
            $params['periodical_id'] = $vars->period_id;
            $params['no_content']    = true;

            // Get posts
            try {
                $posts     = Utils::getPosts($params);
                $counter   = Utils::getPosts($params, true);
                $post_list = new ManageList(dcCore::app(), $posts, $counter->f(0));
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());
            }

            $starting_script = dcPage::jsModuleLoad(My::id() . '/js/checkbox.js') .
                $post_filter->js(dcCore::app()->adminurl->get('admin.plugin.' . My::id(), ['part' => 'period', 'period_id' => $vars->period_id], '&') . '#posts');
        }

        // Display
        dcPage::openModule(
            My::name(),
            dcPage::jsModuleLoad(My::id() . '/js/dates.js') .
            $starting_script .
            dcPage::jsDatePicker() .
            dcPage::jsPageTabs()
        );

        echo
        dcPage::breadcrumb([
            __('Plugins')                                                      => '',
            My::name()                                                         => dcCore::app()->admin->getPageURL() . '&amp;part=periods',
            (null === $vars->period_id ? __('New period') : __('Edit period')) => '',
        ]) .
        dcPage::notices();

        // Period form
        echo
        (new Div('period'))->items([
            (new Text('h3', null === $vars->period_id ? __('New period') : __('Edit period'))),
            (new Form('periodicalbhv'))->method('post')->action(dcCore::app()->admin->getPageURL())->fields([
                (new Para())->items([
                    (new Label(__('Title:')))->for('period_title'),
                    (new Input('period_title'))->size(65)->maxlenght(255)->class('maximal')->value(Html::escapeHTML($vars->period_title)),
                ]),
                (new Div())->class('two-boxes')->items([
                    (new Para())->items([
                        (new Label(__('Next update:')))->for('period_curdt'),
                        (new Datetime('period_curdt', Html::escapeHTML(Dater::toUser($vars->period_curdt))))->class($vars->bad_period_curdt ? 'invalid' : ''),
                    ]),
                    (new Para())->items([
                        (new Label(__('End date:')))->for('period_enddt'),
                        (new Datetime('period_enddt', Html::escapeHTML(Dater::toUser($vars->period_enddt))))->class($vars->bad_period_enddt ? 'invalid' : ''),
                    ]),
                ]),
                (new Div())->class('two-boxes')->items([
                    (new Para())->items([
                        (new Label(__('Publication frequency:'), Label::OUTSIDE_LABEL_BEFORE))->for('period_pub_int'),
                        (new Select('period_pub_int'))->default($vars->period_pub_int)->items(My::periodCombo()),
                    ]),
                    (new Para())->items([
                        (new Label(__('Number of entries to publish every time:'), Label::OUTSIDE_LABEL_BEFORE))->for('period_pub_nb'),
                        (new Number('period_pub_nb'))->min(1)->max(20)->value($vars->period_pub_nb),
                    ]),
                ]),

                (new Div())->class('clear')->items([
                    (new Para())->items([
                        (new Submit(['save']))->value(__('Save')),
                        dcCore::app()->formNonce(false),
                        (new Hidden(['action'], 'setperiod')),
                        (new Hidden(['period_id'], (string) $vars->period_id)),
                        (new Hidden(['part'], 'period')),
                    ]),
                ]),
            ]),
        ])->render();

        if ($vars->period_id && isset($post_filter) && isset($post_list) && !dcCore::app()->error->flag()) {
            $base_url = dcCore::app()->admin->getPageURL() .
                '&amp;period_id=' . $vars->period_id .
                '&amp;part=period' .
                '&amp;user_id=' . $post_filter->value('user_id', '') .
                '&amp;cat_id=' . $post_filter->value('cat_id', '') .
                '&amp;status=' . $post_filter->value('status', '') .
                '&amp;selected=' . $post_filter->value('selected', '') .
                '&amp;attachment=' . $post_filter->value('attachment', '') .
                '&amp;month=' . $post_filter->value('month', '') .
                '&amp;lang=' . $post_filter->value('lang', '') .
                '&amp;sortby=' . $post_filter->value('sortby', '') .
                '&amp;order=' . $post_filter->value('order', '') .
                '&amp;nb=' . $post_filter->value('nb', '') .
                '&amp;page=%s' .
                '#posts';

            echo '
            <div id="posts"><h3>' . __('Entries linked to this period') . '</h3>';

            // Filters
            $post_filter->display(
                ['admin.plugin.periodical', '#posts'],
                dcCore::app()->adminurl->getHiddenFormFields('admin.plugin.periodical', [
                    'period_id' => $vars->period_id,
                    'part'      => 'period',
                ])
            );

            // Posts list
            $post_list->postDisplay(
                $post_filter,
                $base_url,
                '<form action="' . dcCore::app()->admin->getPageURL() . '" method="post" id="form-entries">' .

                '%s' .

                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right">' . __('Selected entries action:') . ' ' .
                (new Select('action'))->items(My::entriesActionsCombo())->redner() .
                '<input type="submit" value="' . __('ok') . '" /></p>' .
                dcCore::app()->adminurl->getHiddenFormFields('admin.plugin.periodical', array_merge($post_filter->values(), [
                    'period_id' => $vars->period_id,
                    'redir'     => sprintf($base_url, $post_filter->value('page', '')),
                ])) .
                dcCore::app()->formNonce() .
                '</div>' .
                '</form>'
            );

            echo
            '</div>';
        }

        dcPage::helpBlock('periodical');

        dcPage::closeModule();
    }

    /**
     * Do a Http redirection.
     *
     * @param   string  $redir  Previous redirection
     * @param   int     $id     The period ID
     * @param   string  $tab    The page tab
     * @param   string  $msg    The notice message
     */
    private static function redirect(string $redir, int $id, string $tab, string $msg): void
    {
        dcPage::addSuccessNotice($msg);

        if (!empty($redir)) {
            Http::redirect($redir);
        } else {
            dcCore::app()->adminurl?->redirect('admin.plugin.' . My::id(), ['part' => 'period', 'period_id' => $id], $tab);
        }
    }
}
