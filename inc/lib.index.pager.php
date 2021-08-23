<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of periodical, a plugin for Dotclear 2.
# 
# Copyright (c) 2009-2021 Jean-Christian Denis and contributors
# 
# Licensed under the GPL version 2.0 license.
# A copy of this license is available in LICENSE file or at
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------

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
    public function periodDisplay($page, $nb_per_page, $enclose_block='')
    {
        $echo = '';
        if ($this->rs->isEmpty()) {
            $echo .= '<p><strong>' .__('No period') .'</strong></p>';
        } else {
            $pager = new dcPager($page, $this->rs_count, $nb_per_page, 10);
            $pager->html_prev = $this->html_prev;
            $pager->html_next = $this->html_next;
            $pager->var_page = 'page';

            $html_block =
            '<div class="table-outer">' .
            '<table class="clear">' .
            '<tr>' .
            '<th colspan="2" class="nowrap">' . __('Name') . '</th>' .
            '<th class="nowrap">' . __('Next update') . '</th>' .
            '<th class="nowrap">' . __('Frequency') . '</th>' .
            '<th class="nowrap">' . __('Publications') . '</th>' .
            '<th class="nowrap">' . __('Entries') . '</th>' .
            '<th class="nowrap">' . __('End date') . '</th>' .
            '</tr>%s</table>' .
            '</div>';

            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            $echo .= $pager->getLinks();

            $blocks = explode('%s', $html_block);

            $echo .= $blocks[0];

            while ($this->rs->fetch()) {
                $echo .= $this->periodLine();
            }

            $echo .= $blocks[1];

            $echo .= $pager->getLinks();
        }

        return $echo;
    }
    
    private function periodLine()
    {
        $nb_posts = $this->rs->periodical->getPosts(['periodical_id' => $this->rs->periodical_id], true);
        $nb_posts = $nb_posts->f(0);
        $style = !$nb_posts ? ' offline' : '';
        $posts_links = !$nb_posts ? 
            '0' : 
            '<a href="plugin.php?p=periodical&amp;part=period&amp;period_id=' . $this->rs->periodical_id . '#posts" title="' . __('view related entries') . '">' . $nb_posts . '</a>';

        $pub_int = in_array($this->rs->periodical_pub_int, $this->rs->periodical->getTimesCombo()) ? 
            __(array_search($this->rs->periodical_pub_int, $this->rs->periodical->getTimesCombo())) : __('Unknow frequence');

        $res = 
        '<tr class="line' . $style . '">' .
        '<td class="nowrap">' . form::checkbox(['periods[]'], $this->rs->periodical_id) . '</td>' .
        '<td class="maximal"><a href="plugin.php?p=periodical&amp;part=period&amp;period_id=' . $this->rs->periodical_id . '#period" title="' . 
            __('edit period') . '">' . html::escapeHTML($this->rs->periodical_title) . '</a></td>' .
        '<td class="nowrap">' . dt::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->periodical_curdt) . '</td>' .
        '<td class="nowrap">' . $pub_int . '</td>' .
        '<td class="nowrap">' . $this->rs->periodical_pub_nb .'</td>' .
        '<td class="nowrap">' . $posts_links . '</td>' .
        '<td class="nowrap">' . dt::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->periodical_enddt) . '</td>' .
        '</tr>';

        return $res;
    }

    public function postDisplay($page, $nb_per_page, $base_url, $enclose_block='')
    {
        $echo = '';
        if ($this->rs->isEmpty()) {
            $echo .= '<p><strong>' . __('No entry') . '</strong></p>';
        } else {
            $pager = new dcPager($page, $this->rs_count, $nb_per_page, 10);
            $pager->html_prev = $this->html_prev;
            $pager->html_next = $this->html_next;
            $pager->base_url = $base_url;
            $pager->var_page = 'page';

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
                $echo .= $this->postLine();
            }
            
            $echo .= $blocks[1];

            $echo .= $pager->getLinks();
        }

        return $echo;
    }
    
    private function postLine()
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