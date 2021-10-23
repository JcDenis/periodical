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

/**
 * @ingroup DC_PLUGIN_PERIODICAL
 * @brief Periodical - admin pager methods.
 * @since 2.6
 */
class adminPeriodicalList extends adminGenericList
{
    public function periodDisplay($filter, $enclose_block='')
    {
        $echo = '';
        if ($this->rs->isEmpty()) {
            if ($filter->show()) {
                echo '<p><strong>' . __('No period matches the filter') . '</strong></p>';
            } else {
                echo '<p><strong>' . __('No period') . '</strong></p>';
            }
        } else {
            $pager = new dcPager($filter->page, $this->rs_count, $filter->nb, 10);
            $pager->var_page = 'page';

            $periods = [];
            if (isset($_REQUEST['periods'])) {
                foreach ($_REQUEST['periods'] as $v) {
                    $periods[(integer) $v] = true;
                }
            }

            $html_block = '<div class="table-outer"><table><caption>' . ($filter->show() ? 
                sprintf(__('List of %s periods matching the filter.'), $this->rs_count) :
                sprintf(__('List of %s periods.'), $this->rs_count)
            ). '</caption>';

            $cols = new ArrayObject([
                'name'    => '<th colspan="2" class="first">' . __('Name') . '</th>',
                'curdt'   => '<th scope="col" class="nowrap">' . __('Next update') . '</th>',
                'pub_int' => '<th scope="col" class="nowrap">' . __('Frequency') . '</th>',
                'pub_nb'  => '<th scope="col" class="nowrap">' . __('Pub per update') . '</th>',
                'nbposts' => '<th scope="col" class="nowrap">' . __('Entries') . '</th>',
                'enddt'   => '<th scope="col" class="nowrap">' . __('End date') . '</th>'
            ]);

            $this->userColumns('periodical', $cols);

            $html_block .= '<tr>' . implode(iterator_to_array($cols)) . '</tr>%s</table>%s</div>';
            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }
            $blocks = explode('%s', $html_block);

            echo $pager->getLinks() . $blocks[0];

            while ($this->rs->fetch()) {
                echo $this->periodLine(isset($periods[$this->rs->periodical_id]));
            }

            echo $blocks[1] . $blocks[2] . $pager->getLinks();
        }
    }

    private function periodLine($checked)
    {
        $nb_posts = $this->rs->periodical->getPosts(['periodical_id' => $this->rs->periodical_id], true)->f(0);
        $url = $this->core->adminurl->get('admin.plugin.periodical', ['part' => 'period', 'period_id' => $this->rs->periodical_id]);

        $name = '<a href="' . $url . '#period" title="' . __('edit period') . '">' . html::escapeHTML($this->rs->periodical_title) . '</a>';

        $posts = $nb_posts ?  
            '<a href="' . $url . '#posts" title="' . __('view related entries') . '">' . $nb_posts . '</a>' :
            '0';

        $interval = in_array($this->rs->periodical_pub_int, $this->rs->periodical->getTimesCombo()) ? 
            __(array_search($this->rs->periodical_pub_int, $this->rs->periodical->getTimesCombo())) : __('Unknow frequence');

        $cols = new ArrayObject([
            'check'   => '<td class="nowrap">' . form::checkbox(['periods[]'], $this->rs->periodical_id, ['checked'  => $checked]) . '</td>',
            'name'    => '<td class="maximal">' . $name . '</td>',
            'curdt'   => '<td class="nowrap count">' . dt::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->periodical_curdt) . '</td>',
            'pub_int' => '<td class="nowrap">' . $interval . '</td>',
            'pub_nb'  => '<td class="nowrap count">' . $this->rs->periodical_pub_nb . '</td>',
            'nbposts' => '<td class="nowrap count">' . $posts. '</td>',
            'enddt'   => '<td class="nowrap count">' . dt::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->periodical_enddt) . '</td>'
        ]);

        $this->userColumns('periodical', $cols);

        return 
            '<tr class="line ' . ($nb_posts ? '' : ' offline') . '" id="p' . $this->rs->periodical_id . '">' .
            implode(iterator_to_array($cols)) . 
            '</tr>';
    }

    public function postDisplay($filter, $base_url, $enclose_block='')
    {
        $echo = '';
        if ($this->rs->isEmpty()) {
            if ($filter->show()) {
                echo '<p><strong>' . __('No entry matches the filter') . '</strong></p>';
            } else {
                echo '<p><strong>' . __('No entry') . '</strong></p>';
            }
        } else {
            $pager = new dcPager($filter->page, $this->rs_count, $filter->nb, 10);
            $pager->base_url = $base_url;
            $pager->var_page = 'page';

            $periodical_entries = [];
            if (isset($_REQUEST['periodical_entries'])) {
                foreach ($_REQUEST['periodical_entries'] as $v) {
                    $periodical_entries[(integer) $v] = true;
                }
            }

            $html_block =
            '<table class="clear"><tr>' .
            '<th colspan="2">' . __('Title') . '</th>' .
            '<th class="nowrap">' . __('Date') . '</th>' .
            '<th class="nowrap">' . __('Category') . '</th>' .
            '<th class="nowrap">' . __('Author') . '</th>' .
            '<th class="nowrap">' . __('Status') . '</th>' .
            '<th class="nowrap">' . __('Create date') . '</th>' .
            '</tr>%s</table>';

            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            $echo .= $pager->getLinks();

            $blocks = explode('%s', $html_block);

            $echo .= $blocks[0];

            while ($this->rs->fetch()) {
                $echo .= $this->postLine(isset($periodical_entries[$this->rs->post_id]));
            }

            $echo .= $blocks[1];

            $echo .= $pager->getLinks();
        }

        return $echo;
    }

    private function postLine($checked)
    {
        if ($this->core->auth->check('categories', $this->core->blog->id)) {
            $cat_link = '<a href="category.php?id=%s">%s</a>';
        } else {
            $cat_link = '%2$s';
        }

        if ($this->rs->cat_title) {
            $cat_title = sprintf(
                $cat_link,
                $this->rs->cat_id,
                html::escapeHTML($this->rs->cat_title)
            );
        } else {
            $cat_title = __('None');
        }

        $img = '<img alt="%1$s" title="%1$s" src="images/%2$s" />';
        switch ($this->rs->post_status)
        {
            case 1:
                $img_status = sprintf($img, __('published'), 'check-on.png');
            break;

            case 0:
                $img_status = sprintf($img, __('unpublished'), 'check-off.png');
            break;

            case -1:
                $img_status = sprintf($img, __('scheduled'), 'scheduled.png');
            break;

            case -2:
                $img_status = sprintf($img, __('pending'), 'check-wrn.png');
            break;
        }

        $protected = '';
        if ($this->rs->post_password) {
            $protected = sprintf($img, __('protected'), 'locker.png');
        }

        $selected = '';
        if ($this->rs->post_selected) {
            $selected = sprintf($img, __('selected'), 'selected.png');
        }

        $attach = '';
        $nb_media = $this->rs->countMedia();
        if ($nb_media > 0) {
            $attach_str = $nb_media == 1 ? __('%d attachment') : __('%d attachments');
            $attach = sprintf($img, sprintf($attach_str, $nb_media), 'attach.png');
        }

        $res = 
        '<tr class="line">' .
        '<td class="minimal">' . form::checkbox(['periodical_entries[]'], $this->rs->post_id, 0) . '</td>' .
        '<td class="maximal"><a href="' . $this->rs->core->getPostAdminURL($this->rs->post_type, $this->rs->post_id) . '" ' .
        'title="' . html::escapeHTML($this->rs->getURL()) . '">' .
        html::escapeHTML($this->rs->post_title) . '</a></td>' .
        '<td class="nowrap">' . dt::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->post_dt) . '</td>' .
        '<td class="nowrap">' . $cat_title . '</td>' .
        '<td class="nowrap">' . $this->rs->user_id . '</td>' .
        '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>' .
        '<td class="nowrap">' . dt::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->post_creadt, $this->rs->core->auth->getInfo('user_tz')) . '</td>' .
        '</tr>';

        return $res;
    }
}