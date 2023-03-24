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
use dcAuth;
use dcCore;
use dcNsProcess;
use dcPage;
use Exception;
use form;
use html;
use http;

/**
 * Admin page for a period
 */
class ManagePeriod extends dcNsProcess
{
    public static function init(): bool
    {
        static::$init == defined('DC_CONTEXT_ADMIN')
            && My::phpCompliant()
            && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                dcAuth::PERMISSION_USAGE,
                dcAuth::PERMISSION_CONTENT_ADMIN,
            ]), dcCore::app()->blog->id)
            && ($_REQUEST['part'] ?? 'periods') === 'period';

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        # Default values
        $vars = ManageVars::init();

        # Get period
        if ($vars->bad_period_id) {
            dcCore::app()->error->add(__('This period does not exist.'));
        }

        # Set period
        if ($vars->action == 'setperiod') {
            if ($vars->bad_period_curdt || $vars->bad_period_enddt) {
                dcCore::app()->error->add(__('Invalid date'));
            }

            # Check period title and dates
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

            # If no error, set period
            if (!dcCore::app()->error->flag()) {
                $cur = Utils::openCursor();
                $cur->setField('periodical_title', $vars->period_title);
                $cur->setField('periodical_curdt', $vars->period_curdt);
                $cur->setField('periodical_enddt', $vars->period_enddt);
                $cur->setField('periodical_pub_int', $vars->period_pub_int);
                $cur->setField('periodical_pub_nb', $vars->period_pub_nb);

                # Update period
                if ($vars->period_id) {
                    Utils::updPeriod($vars->period_id, $cur);

                    self::redirect($vars->redir, $vars->period_id, '#period', __('Period successfully updated.'));
                # Create period
                } else {
                    $period_id = Utils::addPeriod($cur);

                    self::redirect($vars->redir, $period_id, '#period', __('Period successfully created.'));
                }
            }
        }

        # Actions on related posts
        if (!dcCore::app()->error->flag() && $vars->period_id && $vars->action && !empty($vars->entries)) {
            # Publish posts
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

            # Unpublish posts
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

            # Remove posts from periodical
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

        # Default values
        $vars = ManageVars::init();

        $starting_script = '';

        # Prepare combos for posts list
        if ($vars->period_id > 0) {
            # Filters
            $post_filter = new adminPostFilter();
            $post_filter->add('part', 'period');

            $params                  = $post_filter->params();
            $params['periodical_id'] = $vars->period_id;
            $params['no_content']    = true;

            # Get posts
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

        # Display
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

        # Period form
        echo '
        <div id="period"><h3>' . (null === $vars->period_id ? __('New period') : __('Edit period')) . '</h3>
        <form method="post" action="' . dcCore::app()->admin->getPageURL() . '">

        <p><label for="period_title">' . __('Title:') . '</label>' .
                form::field('period_title', 60, 255, html::escapeHTML($vars->period_title), 'maximal') . '</p>

        <div class="two-boxes">

        <p><label for="period_curdt">' . __('Next update:') . '</label>' .
                form::datetime('period_curdt', [
                    'default' => html::escapeHTML(Dater::toUser($vars->period_curdt)),
                    'class'   => ($vars->bad_period_curdt ? 'invalid' : ''),
                ]) . '</p>

        <p><label for="period_enddt">' . __('End date:') . '</label>' .
                form::datetime('period_enddt', [
                    'default' => html::escapeHTML(Dater::toUser($vars->period_enddt)),
                    'class'   => ($vars->bad_period_enddt ? 'invalid' : ''),
                ]) . '</p>

        </div><div class="two-boxes">

        <p><label for="period_pub_int">' . __('Publication frequency:') . '</label>' .
                form::combo('period_pub_int', My::periodCombo(), $vars->period_pub_int) . '</p>

        <p><label for="period_pub_nb">' . __('Number of entries to publish every time:') . '</label>' .
                form::number('period_pub_nb', ['min' => 1, 'max' => 20, 'default' => $vars->period_pub_nb]) . '</p>

        </div>

        <div class="clear">
        <p><input type="submit" name="save" value="' . __('Save') . '" />' .
                dcCore::app()->formNonce() .
                form::hidden(['action'], 'setperiod') .
                form::hidden(['period_id'], $vars->period_id) .
                form::hidden(['part'], 'period') . '
        </p>
        </div>
        </form>
        </div>';

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

            # Filters
            $post_filter->display(
                ['admin.plugin.periodical', '#posts'],
                dcCore::app()->adminurl->getHiddenFormFields('admin.plugin.periodical', [
                    'period_id' => $vars->period_id,
                    'part'      => 'period',
                ])
            );

            # Posts list
            $post_list->postDisplay(
                $post_filter,
                $base_url,
                '<form action="' . dcCore::app()->admin->getPageURL() . '" method="post" id="form-entries">' .

                '%s' .

                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right">' . __('Selected entries action:') . ' ' .
                form::combo('action', My::entriesActionsCombo()) .
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

    private static function redirect(string $redir, int $id, string $tab, string $msg): void
    {
        dcPage::addSuccessNotice($msg);

        if (!empty($redir)) {
            http::redirect($redir);
        } else {
            dcCore::app()->adminurl->redirect('admin.plugin.' . My::id(), ['part' => 'period', 'period_id' => $id], $tab);
        }
    }
}
