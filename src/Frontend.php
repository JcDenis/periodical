<?php

declare(strict_types=1);

namespace Dotclear\Plugin\periodical;

use Dotclear\App;
use Dotclear\Core\Process;
use Exception;

/**
 * @brief       periodical frontend class.
 * @ingroup     periodical
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Frontend extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::FRONTEND) && in_array((string) App::url()->type, ['default', 'feed']));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        App::behavior()->addBehavior('publicBeforeDocumentV2', function (): void {
            if (!App::blog()->isDefined()) {
                return;
            }

            try {
                $s = My::settings();

                Utils::lockUpdate();

                // Get periods
                $periods = App::auth()->sudo(Utils::getPeriods(...));

                // No period
                if ($periods->isEmpty()) {
                    Utils::unlockUpdate();

                    return;
                }

                $now_ts      = (int) Dater::toDate('now', 'U');
                $posts_order = $s->get('periodical_pub_order');
                if (!preg_match('/^(post_dt|post_creadt|post_id) (asc|desc)$/', $posts_order)) {
                    $posts_order = 'post_dt asc';
                }
                $cur_period = App::con()->openCursor(App::con()->prefix() . My::id());

                while ($periods->fetch()) {
                    // Check if period is ongoing
                    $cur_ts = (int) Dater::toDate($periods->f('periodical_curdt'), 'U');
                    $end_ts = (int) Dater::toDate($periods->f('periodical_enddt'), 'U');

                    if ($cur_ts < $now_ts && $now_ts < $end_ts) {
                        $max_nb  = (int) $periods->f('periodical_pub_nb');
                        $last_nb = 0;
                        $last_ts = $loop_ts = $cur_ts;
                        $limit   = 0;

                        try {
                            while (1) {
                                if ($loop_ts > $now_ts) {
                                    break;
                                }
                                $loop_ts = Dater::getNextTime($loop_ts, $periods->f('periodical_pub_int'));
                                $limit += 1;
                            }
                        } catch (Exception $e) {
                        }

                        // If period need update
                        if ($limit > 0) {
                            // Get posts to publish related to this period
                            $posts_params                  = [];
                            $posts_params['periodical_id'] = $periods->f('periodical_id');
                            $posts_params['post_status']   = App::blog()::POST_PENDING;
                            $posts_params['order']         = $posts_order;
                            $posts_params['limit']         = $limit * $max_nb;
                            $posts_params['no_content']    = true;
                            $posts                         = App::auth()->sudo(Utils::getPosts(...), $posts_params);

                            if (!$posts->isEmpty()) {
                                $cur_post = App::blgo()->openPostCursor();

                                while ($posts->fetch()) {
                                    // Publish post with right date
                                    $cur_post->clean();
                                    $cur_post->setField('post_status', App::blog()::POST_PUBLISHED);

                                    // Update post date with right date
                                    if ($s->get('periodical_upddate')) {
                                        $cur_post->setField('post_dt', Dater::toDate($last_ts, 'Y-m-d H:i:00', $posts->post_tz));
                                    } else {
                                        $cur_post->setField('post_dt', $posts->f('post_dt'));
                                    }

                                    // Also update post url with right date
                                    if ($s->get('periodical_updurl')) {
                                        $cur_post->setField('post_url', App::blog()->getPostURL(
                                            '',
                                            $cur_post->getField('post_dt'),
                                            $posts->f('post_title'),
                                            $posts->f('post_id')
                                        ));
                                    }

                                    $cur_post->update(
                                        'WHERE post_id = ' . $posts->f('post_id') . ' ' .
                                        "AND blog_id = '" . App::con()->escapeStr(App::blog()->id()) . "' "
                                    );

                                    // Delete post relation to this period
                                    Utils::delPost((int) $posts->f('post_id'));

                                    $last_nb++;

                                    // Increment upddt if nb of publishing is to the max
                                    if ($last_nb == $max_nb) {
                                        $last_ts = Dater::getNextTime($last_ts, $periods->f('periodical_pub_int'));
                                        $last_nb = 0;
                                    }

                                    // --BEHAVIOR-- periodicalAfterPublishedPeriodicalEntry
                                    App::behavior()->callBehavior('periodicalAfterPublishedPeriodicalEntry', $posts, $periods);
                                }
                                App::blog()->triggerBlog();
                            }
                        }

                        // Update last published date of this period even if there's no post to publish
                        $cur_period->clean();
                        $cur_period->setField('periodical_curdt', Dater::toDate($loop_ts, 'Y-m-d H:i:00'));
                        $cur_period->update(
                            'WHERE periodical_id = ' . $periods->f('periodical_id') . ' ' .
                            "AND blog_id = '" . App::con()->escapeStr(App::blog()->id()) . "' "
                        );
                    }
                }
                Utils::unlockUpdate();
            } catch (Exception $e) {
                Utils::unlockUpdate();
            }
        });

        return true;
    }
}
