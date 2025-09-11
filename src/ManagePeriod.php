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

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Backend\Filter\FilterPosts;
use Dotclear\Helper\Html\Form\Datetime;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Number;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * @brief       periodical manage a period class.
 * @ingroup     periodical
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ManagePeriod
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::MANAGE) && ($_REQUEST['part'] ?? 'periods') === 'period');
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (!App::blog()->isDefined()) {
            return false;
        }

        // Default values
        $vars = ManageVars::init();

        // Get period
        if ($vars->bad_period_id) {
            App::error()->add(__('This period does not exist.'));
        }

        // Set period
        if ($vars->action == 'setperiod') {
            if ($vars->bad_period_curdt || $vars->bad_period_enddt) {
                App::error()->add(__('Invalid date'));
            }

            // Check period title and dates
            $old_titles = Utils::getPeriods([
                'periodical_title' => $vars->period_title,
            ]);
            if (!$old_titles->isEmpty()) {
                while ($old_titles->fetch()) {
                    if (!$vars->period_id || $old_titles->f('periodical_id') != $vars->period_id) {
                        App::error()->add(__('Period title is already taken'));
                    }
                }
            }
            if (empty($vars->period_title)) {
                App::error()->add(__('Period title is required'));
            }
            if (strtotime($vars->period_curdt) > strtotime($vars->period_enddt)) {
                App::error()->add(__('Start date must be older than end date'));
            }

            // If no error, set period
            if (!App::error()->flag()) {
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
                } else {
                    // Create period
                    $period_id = Utils::addPeriod($cur);

                    self::redirect($vars->redir, $period_id, '#period', __('Period successfully created.'));
                }
            }
        }

        // Actions on related posts
        if (!App::error()->flag() && $vars->period_id && $vars->action && !empty($vars->entries)) {
            // Publish posts
            if ($vars->action == 'publish') {
                try {
                    foreach ($vars->entries as $id) {
                        App::blog()->updPostStatus($id, 1);
                        Utils::delPost($id);
                    }

                    self::redirect($vars->redir, $vars->period_id, '#posts', __('Entries successfully published.'));
                } catch (Exception $e) {
                    App::error()->add($e->getMessage());
                }
            }

            // Unpublish posts
            if ($vars->action == 'unpublish') {
                try {
                    foreach ($vars->entries as $id) {
                        App::blog()->updPostStatus($id, 0);
                        Utils::delPost($id);
                    }

                    self::redirect($vars->redir, $vars->period_id, '#posts', __('Entries successfully unpublished.'));
                } catch (Exception $e) {
                    App::error()->add($e->getMessage());
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
                    App::error()->add($e->getMessage());
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
        if (!self::status()) {
            return;
        }

        // Default values
        $vars = ManageVars::init();

        $starting_script = '';

        // Prepare combos for posts list
        if ($vars->period_id > 0) {
            // Filters
            $post_filter = new FilterPosts();
            $post_filter->add('part', 'period');

            $params                  = $post_filter->params();
            $params['periodical_id'] = $vars->period_id;
            $params['no_content']    = true;

            // Get posts
            try {
                $posts     = Utils::getPosts($params);
                $counter   = Utils::getPosts($params, true);
                $post_list = new ManageList($posts, $counter->f(0));
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }

            $starting_script = My::jsLoad('checkbox') .
                $post_filter->js(My::manageUrl(['part' => 'period', 'period_id' => $vars->period_id], '&') . '#posts');
        }

        // Display
        Page::openModule(
            My::name(),
            My::jsLoad('dates') .
            $starting_script .
            Page::jsDatePicker() .
            Page::jsPageTabs()
        );

        echo
        Page::breadcrumb([
            __('Plugins')                                                      => '',
            My::name()                                                         => App::backend()->getPageURL() . '&amp;part=periods',
            (null === $vars->period_id ? __('New period') : __('Edit period')) => '',
        ]) .
        Notices::getNotices();

        // Period form
        echo
        (new Div('period'))->items([
            (new Text('h3', null === $vars->period_id ? __('New period') : __('Edit period'))),
            (new Form('periodicalbhv'))->method('post')->action(App::backend()->getPageURL())->fields([
                (new Para())->items([
                    (new Label(__('Title:')))->for('period_title'),
                    (new Input('period_title'))->size(65)->maxlength(255)->class('maximal')->value(Html::escapeHTML($vars->period_title)),
                ]),
                (new Div())->class('two-boxes')->items([
                    (new Div())->class('box odd')->items([
                        (new Para())->items([
                            (new Label(__('Next update:')))->for('period_curdt'),
                            (new Datetime('period_curdt', Html::escapeHTML(Dater::toUser($vars->period_curdt))))->class($vars->bad_period_curdt ? 'invalid' : ''),
                        ]),
                        (new Para())->items([
                            (new Label(__('End date:')))->for('period_enddt'),
                            (new Datetime('period_enddt', Html::escapeHTML(Dater::toUser($vars->period_enddt))))->class($vars->bad_period_enddt ? 'invalid' : ''),
                        ]),
                    ]),
                    (new Div())->class('box even')->items([
                        (new Para())->items([
                            (new Label(__('Publication frequency:'), Label::OUTSIDE_LABEL_BEFORE))->for('period_pub_int'),
                            (new Select('period_pub_int'))->default($vars->period_pub_int)->items(My::periodCombo()),
                        ]),
                        (new Para())->items([
                            (new Label(__('Number of entries to publish every time:'), Label::OUTSIDE_LABEL_BEFORE))->for('period_pub_nb'),
                            (new Number('period_pub_nb'))->min(1)->max(20)->value($vars->period_pub_nb),
                        ]),
                    ]),
                ]),
                (new Div())->class('clear')->items([
                    (new Para())->items([
                        (new Submit(['save']))->value(__('Save')),
                        ... My::hiddenFields([
                            'action'    => 'setperiod',
                            'period_id' => (string) $vars->period_id,
                            'part'      => 'period',
                        ]),
                    ]),
                ]),
            ]),
        ])->render();

        if ($vars->period_id && isset($post_filter) && isset($post_list) && !App::error()->flag()) {
            $base_url = App::backend()->getPageURL() .
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
                My::parsedHiddenFields([
                    'period_id' => $vars->period_id,
                    'part'      => 'period',
                ])
            );

            // Posts list
            $post_list->postDisplay(
                $post_filter,
                $base_url,
                '<form action="' . App::backend()->getPageURL() . '" method="post" id="form-entries">' .

                '%s' .

                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                (new Para())->class('col right')
                    ->items([
                        (new Label(__('Selected entries action:'), Label::OUTSIDE_LABEL_BEFORE))->for('post_action')->class('classic'),
                        (new Select(['action','post_action']))->items(My::entriesActionsCombo()),
                        (new Submit('do_post_action'))->value(__('ok')),
                        ... My::hiddenFields([
                            ... $post_filter->values(),
                            'period_id' => $vars->period_id,
                            'redir'     => sprintf($base_url, $post_filter->value('page', '')),
                        ]),
                    ])
                    ->render() .
                '</div>' .
                '</form>'
            );

            echo
            '</div>';
        }

        Page::helpBlock('periodical');

        Page::closeModule();
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
        Notices::addSuccessNotice($msg);

        if (!empty($redir)) {
            Http::redirect($redir);
        } else {
            My::redirect(['part' => 'period', 'period_id' => $id], $tab);
        }
    }
}
