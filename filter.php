<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Forum discussion embed filter
 *
 * @package     filter_forumembed
 * @author      Alex Morris <alex.morris@catalyst.net.nz>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_forumembed extends moodle_text_filter {
    /**
     * Replaces '{discussion:<discussion name>}' tags with a discussion embed.
     *
     * @param string $text
     * @param array $options
     */
    public function filter($text, array $options = array()) {
        global $USER, $CFG, $DB, $COURSE;

        if (empty($COURSE->id) || $COURSE->id == 0) {
            return $text;
        }

        if (stripos($text, '{discussion:') !== false) {
            // Discussion ID.
            preg_match_all('/\{discussion:(.+)\}/', $text, $matches);
            $name = $matches[1][0];
            unset($matches);

            $discussionrow = $DB->get_record('forum_discussions', array('name' => $name, 'course' => $COURSE->id));
            if ($discussionrow == null) {
                return preg_replace('/\{discussion:' . preg_quote($name, '/') . '\}/isuU',
                    get_string('missingdiscussion', 'filter_forumembed', $name), $text);
            }

            $vaultfactory = mod_forum\local\container::get_vault_factory();
            $discussionvault = $vaultfactory->get_discussion_vault();
            $discussion = $discussionvault->get_from_id($discussionrow->id);

            if ($discussion == null) {
                return preg_replace('/\{discussion:' . preg_quote($name, '/') . '\}/isuU',
                    get_string('missingdiscussion', 'filter_forumembed', $name), $text);
            }

            $forumvault = $vaultfactory->get_forum_vault();
            $forum = $forumvault->get_from_id($discussion->get_forum_id());

            $managerfactory = mod_forum\local\container::get_manager_factory();
            $capabilitymanager = $managerfactory->get_capability_manager($forum);

            $displaymode = get_user_preferences('forum_displaymode', $CFG->forum_displaymode);

            $postvault = $vaultfactory->get_post_vault();

            $parent = $discussion->get_first_post_id();
            $post = $postvault->get_from_id($parent);

            $rendererfactory = mod_forum\local\container::get_renderer_factory();
            $discussionrenderer = $rendererfactory->get_discussion_renderer($forum, $discussion, $displaymode);
            $orderpostsby = $displaymode == FORUM_MODE_FLATNEWEST ? 'created DESC' : 'created ASC';
            $replies =
                $postvault->get_replies_to_post($USER, $post, $capabilitymanager->can_view_any_private_reply($USER), $orderpostsby);

            $discussionlink = new moodle_url('/mod/forum/discuss.php', ['d' => $discussionrow->id]);

            $output = '<div class="path-filter-forumembed path-mod-forum">';
            $output .= '<div class="discussion-link"><a href="' . $discussionlink->out() . '">Link to discussion</a></div>';
            $output .= $discussionrenderer->render($USER, $post, $replies);
            $output .= '</div>';

            $text = preg_replace('/\{discussion:' . preg_quote($name, '/') . '\}/isuU', $output, $text);
        }

        return $text;
    }
}
