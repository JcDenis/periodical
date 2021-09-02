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

dcPage::check('usage,contentadmin');

# Objects
$s = $core->blog->settings->periodical;
$per = new periodical($core);

# Default values
$action = isset($_POST['action']) ? $_POST['action'] : '';
$part = isset($_REQUEST['part']) && $_REQUEST['part'] == 'period' ? 'period' : 'periods';

############################################################
#
# One period
#
############################################################

if ($part == 'period') {

    $starting_script = '';

    # Default value for period
    $period_id = null;
    $period_title = __('One post per day');
    $period_pub_nb = 1;
    $period_pub_int = 'day';
    $period_curdt = date('Y-m-d H:i:00', time());
    $period_enddt = date('Y-m-d H:i:00', time() + 31536000); //one year

    # Get period
    if (!empty($_REQUEST['period_id'])) {
        $rs = $per->getPeriods([
            'periodical_id' => $_REQUEST['period_id']
        ]);
        if ($rs->isEmpty()) {
            $core->error->add(__('This period does not exist.'));
            $period_id = null;
        } else {
            $period_id = $rs->periodical_id;
            $period_title = $rs->periodical_title;
            $period_pub_nb = $rs->periodical_pub_nb;
            $period_pub_int = $rs->periodical_pub_int;
            $period_curdt = date('Y-m-d H:i', strtotime($rs->periodical_curdt));
            $period_enddt = date('Y-m-d H:i', strtotime($rs->periodical_enddt));

            //todo load related posts
        }
    }

    # Set period
    if ($action == 'setperiod') {

        # Get POST values
        if (!empty($_POST['period_title'])) {
            $period_title = $_POST['period_title'];
        }
        if (!empty($_POST['period_pub_nb'])) {
            $period_pub_nb = abs((integer) $_POST['period_pub_nb']);
        }
        if (!empty($_POST['period_pub_int']) 
         && in_array($_POST['period_pub_int'], $per->getTimesCombo())) {
            $period_pub_int = $_POST['period_pub_int'];
        }
        if (!empty($_POST['period_curdt'])) {
            $period_curdt = date('Y-m-d H:i:00', strtotime($_POST['period_curdt']));
        }
        if (!empty($_POST['period_enddt'])) {
            $period_enddt = date('Y-m-d H:i:00', strtotime($_POST['period_enddt']));
        }

        # Check period title and dates
        $old_titles = $per->getPeriods([
            'periodical_title' => $period_title
        ]);
        if (!$old_titles->isEmpty()) {
            while($old_titles->fetch()) {
                if (!$period_id || $old_titles->periodical_id != $period_id) {
                    $core->error->add(__('Period title is already taken'));
                }
            }
        }
        if (empty($period_title)) {
            $core->error->add(__('Period title is required'));
        }
        if (strtotime($period_curdt) > strtotime($period_enddt)) {
            $core->error->add(__('Start date must be older than end date'));
        }

        # If no error, set period
        if (!$core->error->flag()) {

            $cur = $per->openCursor();
            $cur->periodical_title = $period_title;
            $cur->periodical_curdt = $period_curdt;
            $cur->periodical_enddt = $period_enddt;
            $cur->periodical_pub_int = $period_pub_int;
            $cur->periodical_pub_nb = $period_pub_nb;

            # Update period
            if ($period_id) {

                $per->updPeriod($period_id, $cur);

                dcPage::addSuccessNotice(
                    __('Period successfully updated.')
                );
            # Create period
            } else {

                $period_id = $per->addPeriod($cur);

                dcPage::addSuccessNotice(
                    __('Period successfully created.')
                );
            }

            http::redirect(empty($_POST['redir']) ? 
                $p_url . '&part=period&period_id=' . $period_id . '#period' : 
                $_POST['redir']
            );
        }
    }

    # Actions on related posts
    if (!$core->error->flag() && $period_id && $action && !empty($_POST['periodical_entries'])) {

        # Publish posts
        if ($action == 'publish') {
            try {
                foreach($_POST['periodical_entries'] as $id) {
                    $id = (integer) $id;
                    $core->blog->updPostStatus($id, 1);
                    $per->delPost($id);
                }

                dcPage::addSuccessNotice(
                    __('Entries successfully published.')
                );

                http::redirect(empty($_POST['redir']) ? 
                    $p_url . '&part=period&period_id=' . $period_id . '#posts' : 
                    $_POST['redir']
                );
            } catch (Exception $e) {
                $core->error->add($e->getMessage());
            }
        }

        # Unpublish posts
        if ($action == 'unpublish') {
            try {
                foreach($_POST['periodical_entries'] as $id) {
                    $id = (integer) $id;
                    $core->blog->updPostStatus($id,0);
                    $per->delPost($id);
                }

                dcPage::addSuccessNotice(
                    __('Entries successfully unpublished.')
                );

                http::redirect(empty($_POST['redir']) ? 
                    $p_url . '&part=period&period_id=' . $period_id . '#posts' : 
                    $_POST['redir']
                );
            } catch (Exception $e) {
                $core->error->add($e->getMessage());
            }
        }

        # Remove posts from periodical
        if ($action == 'remove_post_periodical') {
            try {
                foreach($_POST['periodical_entries'] as $id) {
                    $id = (integer) $id;
                    $per->delPost($id);
                }

                dcPage::addSuccessNotice(
                    __('Entries successfully removed.')
                );

                http::redirect(empty($_POST['redir']) ? 
                    $p_url . '&part=period&period_id=' . $period_id . '#posts' : 
                    $_POST['redir']
                );
            } catch (Exception $e) {
                $core->error->add($e->getMessage());
            }
        }
    }

    # Prepare combos for posts list
    if ($period_id) {
        try {
            # Getting categories
            $categories = $core->blog->getCategories(['post_type' => 'post']);

            # Getting authors
            $users = $core->blog->getPostsUsers();

            # Getting dates
            $dates = $core->blog->getDates(['type' => 'month']);

            # Getting langs
            $langs = $core->blog->getLangs();
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }
    }

    # Creating filter combo boxes
    if ($period_id && !$core->error->flag()) {

        # Users combo
        $users_combo = array_merge(
            ['-' => ''],
            dcAdminCombos::getUsersCombo($users)
        );

        # Categories combo
        $categories_combo = array_merge(
            [
                new formSelectOption('-', ''),
                new formSelectOption(__('(No cat)'), 'NULL')
            ],      
            dcAdminCombos::getCategoriesCombo($categories, false)
        );
        $categories_values = [];
        foreach ($categories_combo as $cat) {
            if (isset($cat->value)) {
                $categories_values[$cat->value] = true;
            }
        }

        # Status combo
        $status_combo = array_merge(
            ['-' => ''],
            dcAdminCombos::getPostStatusesCombo()   
        );

        # Selection combo
        $selected_combo = [
            '-' => '',
            __('Selected') => '1',
            __('Not selected') => '0'
        ];

        # Attachments combo
        $attachment_combo = [
            '-' => '',
            __('With attachments') => '1',
            __('Without attachments') => '0'
        ];

        # Months combo
        $dt_m_combo = array_merge(
            ['-' => ''],
            dcAdminCombos::getDatesCombo($dates)
        );

        # Langs combo
        $lang_combo = array_merge(
            ['-' => ''],
            dcAdminCombos::getLangsCombo($langs, false) 
        );

        # Sort_by combo
        $sortby_combo = [
            __('Date') => 'post_dt',
            __('Title') => 'post_title',
            __('Category') => 'cat_title',
            __('Author') => 'user_id',
            __('Status') => 'post_status',
            __('Selected') => 'post_selected',
            __('Number of comments') => 'nb_comment',
            __('Number of trackbacks') => 'nb_trackback'
        ];

        # order combo
        $order_combo = [
            __('Descending') => 'desc',
            __('Ascending') => 'asc'
        ];

        # parse filters
        $user_id = !empty($_GET['user_id']) ? $_GET['user_id'] : '';
        $cat_id = !empty($_GET['cat_id']) ? $_GET['cat_id'] : '';
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $selected = isset($_GET['selected']) ? $_GET['selected'] : '';
        $attachment = isset($_GET['attachment']) ? $_GET['attachment'] : '';
        $month = !empty($_GET['month']) ? $_GET['month'] : '';
        $lang = !empty($_GET['lang']) ? $_GET['lang'] : '';
        $sortby = !empty($_GET['sortby']) ? $_GET['sortby'] : 'post_dt';
        $order = !empty($_GET['order']) ? $_GET['order'] : 'desc';

        $show_filters = false;

        $page = !empty($_GET['page']) ? max(1, (integer) $_GET['page']) : 1;
        $nb_per_page =  30;

        if (!empty($_GET['nb']) && (integer) $_GET['nb'] > 0) {
            if ($nb_per_page != $_GET['nb']) {
                $show_filters = true;
            }
            $nb_per_page = (integer) $_GET['nb'];
        }

        $params['limit'] = [(($page-1)*$nb_per_page), $nb_per_page];
        $params['no_content'] = true;
        $params['periodical_id'] = $period_id;

        # - User filter
        if ($user_id !== '' && in_array($user_id, $users_combo)) {
            $params['user_id'] = $user_id;
            $show_filters = true;
        } else {
            $user_id='';
        }

        # - Categories filter
        if ($cat_id !== '' && isset($categories_values[$cat_id])) {
            $params['cat_id'] = $cat_id;
            $show_filters = true;
        } else {
            $cat_id='';
        }

        # - Status filter
        if ($status !== '' && in_array($status, $status_combo)) {
            $params['post_status'] = $status;
            $show_filters = true;
        } else {
            $status='';
        }

        # - Selected filter
        if ($selected !== '' && in_array($selected, $selected_combo)) {
            $params['post_selected'] = $selected;
            $show_filters = true;
        } else {
            $selected='';
        }

        # - Selected filter
        if ($attachment !== '' && in_array($attachment, $attachment_combo)) {
            $params['media'] = $attachment;
            $params['link_type'] = 'attachment';
            $show_filters = true;
        } else {
            $attachment='';
        }

        # - Month filter
        if ($month !== '' && in_array($month, $dt_m_combo)) {
            $params['post_month'] = substr($month, 4, 2);
            $params['post_year'] = substr($month, 0, 4);
            $show_filters = true;
        } else {
            $month='';
        }

        # - Lang filter
        if ($lang !== '' && in_array($lang, $lang_combo)) {
            $params['post_lang'] = $lang;
            $show_filters = true;
        } else {
            $lang='';
        }

        # - Sortby and order filter
        if ($sortby !== '' && in_array($sortby, $sortby_combo)) {
            if ($order !== '' && in_array($order, $order_combo)) {
                $params['order'] = $sortby.' '.$order;
            } else {
                $order='desc';
            }

            if ($sortby != 'post_dt' || $order != 'desc') {
                $show_filters = true;
            }
        } else {
            $sortby='post_dt';
            $order='desc';
        }

        # Get posts
        try {
            $posts = $per->getPosts($params);
            $counter = $per->getPosts($params, true);
            $post_list = new adminPeriodicalList($core, $posts, $counter->f(0));
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }

        $starting_script =
            dcPage::jsLoad(
                'index.php?pf=periodical/js/postsfilter.js'
            ) .
            '<script type="text/javascript">' . "\n" .
            "//<![CDATA[" . "\n" .
            dcPage::jsVar(
                'dotclear.msg.show_filters',
                $show_filters ? 'true':'false'
            ) . "\n" .
            dcPage::jsVar(
                'dotclear.msg.filter_posts_list',
                __('Show filters and display options')
            ) . "\n" .
            dcPage::jsVar(
                'dotclear.msg.cancel_the_filter',
                __('Cancel filters and display options')
            ) . "\n" .
            "//]]>\n" .
            "</script>\n";
    }

    # Display
    echo '
    <html><head><title>' . __('Periodical') . '</title>' .
    dcPage::jsLoad('index.php?pf=periodical/js/dates.js') .
    $starting_script .
    dcPage::jsDatePicker() .
    dcPage::jsPageTabs() .
    '</head>
    <body>';

    echo
    dcPage::breadcrumb(
        [
            html::escapeHTML($core->blog->name) => '',
            __('Periodical') => $p_url . '&amp;part=periods',
            (null === $period_id ? __('New period') : __('Edit period')) => ''
        ]
    ) .
    dcPage::notices();

    # Period form
    echo '
    <div class="multi-part" title="' .
    (null === $period_id ? __('New period') : __('Edit period')) .
    '" id="period">
    <form method="post" action="' . $p_url . '">

    <p><label for="period_title">' . __('Title:') . '</label>' .
    form::field('period_title', 60, 255, html::escapeHTML($period_title), 'maximal') . '</p>

    <div class="two-boxes">

    <p><label for="period_curdt">' . __('Next update:') . '</label>' .
    form::field('period_curdt', 16, 16, date('Y-m-d H:i', strtotime($period_curdt))) . '</p>

    <p><label for="period_enddt">' . __('End date:') . '</label>' .
    form::field('period_enddt', 16, 16, date('Y-m-d H:i', strtotime($period_enddt))) . '</p>

    </div><div class="two-boxes">

    <p><label for="period_pub_int">' . __('Publication frequency:') . '</label>' .
    form::combo('period_pub_int',$per->getTimesCombo(), $period_pub_int) . '</p>

    <p><label for="period_pub_nb">' . __('Number of entries to publish every time:') . '</label>' .
    form::field('period_pub_nb', 10, 3, html::escapeHTML($period_pub_nb)) . '</p>

    </div>

    <div class="clear">
    <p><input type="submit" name="save" value="' . __('Save') . '" />' .
    $core->formNonce() .
    form::hidden(['action'], 'setperiod') .
    form::hidden(['period_id'], $period_id) .
    form::hidden(['part'], 'period') .'
    </p>
    </div>
    </form>
    </div>';

    if ($period_id && !$core->error->flag()) {

        # Actions combo box
        $combo_action = array();
        $combo_action[__('Entries')][__('Publish')] = 'publish';
        $combo_action[__('Entries')][__('Unpublish')] = 'unpublish';
        $combo_action[__('Periodical')][__('Remove from periodical')] = 'remove_post_periodical';

        $base_url = $p_url .
            '&amp;period_id=' .$period_id .
            '&amp;part=period' .
            '&amp;user_id=' . $user_id .
            '&amp;cat_id=' . $cat_id .
            '&amp;status=' . $status .
            '&amp;selected=' . $selected .
            '&amp;attachment=' . $attachment .
            '&amp;month=' . $month .
            '&amp;lang=' . $lang .
            '&amp;sortby=' . $sortby .
            '&amp;order=' . $order .
            '&amp;nb=' . $nb_per_page .
            '&amp;page=%s' .
            '#posts';

        echo '
        <div class="multi-part" title="' .
        __('Entries linked to this period') .
        '" id="posts">';

        # Filters
        echo 
        '<form action="' . $p_url . '#posts" method="get" id="filters-form">' .

        '<h3 class="out-of-screen-if-js">' .
        __('Cancel filters and display options') .
        '</h3>' .

        '<div class="table">' .
        '<div class="cell">' .
        '<h4>' . __('Filters') . '</h4>' .
        '<p><label for="user_id" class="ib">' . __('Author:') . '</label> ' .
        form::combo('user_id', $users_combo, $user_id) . '</p>' .
        '<p><label for="cat_id" class="ib">' . __('Category:') . '</label> ' .
        form::combo('cat_id', $categories_combo, $cat_id) . '</p>' .
        '<p><label for="status" class="ib">' . __('Status:') . '</label> ' .
        form::combo('status', $status_combo, $status) . '</p> ' .
        '</div>' .

        '<div class="cell filters-sibling-cell">' .
        '<p><label for="selected" class="ib">' . __('Selected:') . '</label> ' .
        form::combo('selected', $selected_combo, $selected) .'</p>' .
        '<p><label for="attachment" class="ib">' . __('Attachments:') . '</label> ' .
        form::combo('attachment', $attachment_combo, $attachment) . '</p>' .
        '<p><label for="month" class="ib">' . __('Month:') . '</label> ' .
        form::combo('month', $dt_m_combo,$month) . '</p>' .
        '<p><label for="lang" class="ib">' . __('Lang:') . '</label> ' .
        form::combo('lang', $lang_combo, $lang) . '</p> ' .
        '</div>'.

        '<div class="cell filters-options">' .
        '<h4>' . __('Display options') . '</h4>' .
        '<p><label for="sortby" class="ib">' . __('Order by:') . '</label> ' .
        form::combo('sortby', $sortby_combo, $sortby) . '</p>' .
        '<p><label for="order" class="ib">' . __('Sort:') . '</label> ' .
        form::combo('order', $order_combo, $order) . '</p>' .
        '<p><span class="label ib">' . __('Show') . '</span> <label for="nb" class="classic">' .
        form::field('nb', 3, 3, $nb_per_page) . ' ' .
        __('entries per page') . '</label></p>' .
        '</div>' .
        '</div>' .

        '<p><input type="submit" value="' . __('Apply filters and display options') . '" />' .
        form::hidden(['p'], 'periodical') .
        form::hidden(['part'], 'period') .
        form::hidden(['period_id'], $period_id) .
        '<br class="clear" /></p>' . //Opera sucks
        '</form>';

        # Posts list
        echo 
        $post_list->postDisplay($page, $nb_per_page, $base_url, 
            '<form action="' . $p_url . '" method="post" id="form-entries">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right">' . __('Selected entries action:') . ' ' .
            form::combo('action', $combo_action) .
            '<input type="submit" value="' . __('ok') . '" /></p>' .
            form::hidden(['period_id'], $period_id) .
            form::hidden(['user_id'], $user_id) .
            form::hidden(['cat_id'], $cat_id) .
            form::hidden(['status'], $status) .
            form::hidden(['selected'], $selected) .
            form::hidden(['attachment'], $attachment) .
            form::hidden(['month'], $month) .
            form::hidden(['lang'], $lang) .
            form::hidden(['sortby'], $sortby) .
            form::hidden(['order'], $order) .
            form::hidden(['page'], $page) .
            form::hidden(['nb'], $nb_per_page) .
            form::hidden(['p'], 'periodical') .
            form::hidden(['part'], 'period') .
            form::hidden(['redir'], sprintf($base_url, $page)) .
            $core->formNonce() .
            '</div>' .
            '</form>'
        );

        echo
        '</div>';
    }

############################################################
#
# All periods
#
############################################################

} else {

    # Delete periods and related posts links
    if ($action == 'deleteperiods' && !empty($_POST['periods'])) {
        try {
            foreach($_POST['periods'] as $id) {
                $id = (integer) $id;
                $per->delPeriodPosts($id);
                $per->delPeriod($id);
            }

            dcPage::addSuccessNotice(
                __('Periods removed.')
            );

            http::redirect(empty($_POST['redir']) ? 
                $p_url.'&part=periods' : 
                $_POST['redir']
            );
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }
    }
    # Delete periods related posts links (without delete periods)
    if ($action == 'emptyperiods' && !empty($_POST['periods'])) {
        try {
            foreach($_POST['periods'] as $id) {
                $id = (integer) $id;
                $per->delPeriodPosts($id);
            }

            dcPage::addSuccessNotice(
                __('Periods emptied.')
            );

            http::redirect(empty($_POST['redir']) ? 
                $p_url.'&part=periods' : 
                $_POST['redir']
            );
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }
    }

    # Combos
    $sortby_combo = [
        __('Next update') => 'periodical_curdt',
        __('End date') => 'periodical_enddt',
        __('Frequence') => 'periodical_pub_int'
    ];

    $order_combo = [
        __('Descending') => 'desc',
        __('Ascending') => 'asc'
    ];

    $combo_action = [];
    $combo_action[__('empty periods')] = 'emptyperiods';
    $combo_action[__('delete periods')] = 'deleteperiods';

    # Filters
    $sortby = !empty($_GET['sortby']) ? $_GET['sortby'] : 'periodical_curdt';
    $order = !empty($_GET['order']) ? $_GET['order'] : 'desc';

    $show_filters = false;

    $page = !empty($_GET['page']) ? (integer) $_GET['page'] : 1;
    $nb_per_page =  30;

    if (!empty($_GET['nb']) && (integer) $_GET['nb'] > 0) {
        if ($nb_per_page != $_GET['nb']) {
            $show_filters = true;
        }
        $nb_per_page = (integer) $_GET['nb'];
    }

    $params['limit'] = [(($page-1)*$nb_per_page), $nb_per_page];

    if ($sortby !== '' && in_array($sortby, $sortby_combo)) {
        if ($order !== '' && in_array($order, $order_combo)) {
            $params['order'] = $sortby . ' ' . $order;
        }

        if ($sortby != 'periodical_curdt' || $order != 'desc') {
            $show_filters = true;
        }
    }

    # Get periods
    try {
        $periods = $per->getPeriods($params);
        $counter = $per->getPeriods($params, true);
        $period_list = new adminPeriodicalList($core, $periods, $counter->f(0));
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }

    # Display
    echo 
    '<html><head><title>' . __('Periodical') . '</title>' .
    dcPage::jsLoad(
        'index.php?pf=periodical/js/periodsfilter.js'
    ) .
    '<script type="text/javascript">' . "\n" .
    "//<![CDATA[\n" .
    dcPage::jsVar(
        'dotclear.msg.show_filters',
        $show_filters ? 'true':'false'
    ) . "\n" .
    dcPage::jsVar(
        'dotclear.msg.filter_posts_list',
        __('Show filters and display options')
    ) . "\n" .
    dcPage::jsVar(
        'dotclear.msg.cancel_the_filter',
        __('Cancel filters and display options')
    ) . "\n" .
    "//]]>\n" .
    "</script>\n" .
    '</head>' .
    '<body>' .

    dcPage::breadcrumb(
        [
            html::escapeHTML($core->blog->name) => '',
            __('Periodical') => ''
        ]
    ) .
    dcPage::notices() .

    '<p class="top-add">
    <a class="button add" href="' . $p_url . '&amp;part=period">' . __('New period') . '</a>
    </p>';

    # Filter
    echo 
    '<form action="' . $p_url . '" method="get" id="filters-form">' .

    '<h3 class="out-of-screen-if-js">' .
    __('Show filters and display options') .
    '</h3>' .

    '<div class="table">' .

    '<div class="cell">' .
    '<p><label for="sortby">' . __('Order by:') . '</label>' .
    form::combo('sortby', $sortby_combo, $sortby) . '</p>' .
    '</div>' .

    '<div class="cell">' .
    '<p><label for="order">' . __('Sort:') . '</label>' .
    form::combo('order', $order_combo, $order) . '</p>' .
    '</div>' .

    '<div class="cell">' .
    '<p><label for="nb">' . __('Results per page :') . '</label>' .
    form::field('nb', 3, 3, $nb_per_page) . '</p>' .
    '</div>' .

    '</div>' .

    '<p>' .
    '<input type="submit" value="' . __('Apply filters and display options') . '" />' .
    form::hidden(['p'], 'periodical') .
    form::hidden(['part'], 'periods') .
    '<br class="clear" />' . //Opera sucks
    '</p>' .

    '</form>';

    # Posts list
    echo $period_list->periodDisplay($page, $nb_per_page,
        '<form action="' . $p_url . '" method="post" id="form-periods">' .

        '%s' .

        '<div class="two-cols">' .
        '<p class="col checkboxes-helpers"></p>' .

        '<p class="col right">' . __('Selected periods action:') . ' ' .
        form::combo('action', $combo_action) .
        '<input type="submit" value="' . __('ok') . '" /></p>' .
        form::hidden(['sortby'], $sortby) .
        form::hidden(['order'], $order) .
        form::hidden(['page'], $page) .
        form::hidden(['nb'], $nb_per_page) .
        form::hidden(['p'], 'periodical') .
        form::hidden(['part'], 'periods') .
        $core->formNonce() .
        '</div>' .
        '</form>'
    );

}

dcPage::helpBlock('periodical');

# Page footer
echo 
'<hr class="clear"/><p class="right modules">
periodical - '. $core->plugins->moduleInfo('periodical', 'version') . '&nbsp;
<img alt="' . __('periodical') . '" src="index.php?pf=periodical/icon.png" />
</p>
</body></html>';