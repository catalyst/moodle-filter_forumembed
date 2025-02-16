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
        global $USER, $CFG, $DB, $COURSE, $PAGE;

        if (empty($COURSE->id) || $COURSE->id == 0) {
            return $text;
        }

        if (stripos($text, '{discussion:') !== false) {

            // Try match on 3 parts {Discussion:forumName:DiscussionsName}
            preg_match_all('/\{discussion:(.+)\:(.+)\}/', $text, $matches);
            $discussionname = $matches[2][0];
            $forumname = $matches[1][0];
            unset($matches);

            if($forumname == null){
                
                // macthing failed on 3 parts, so revert to matching on 2 {Discussion:DiscussionsName}
                preg_match_all('/\{discussion:(.+)\}/', $text, $matches);
                $discussionname = $matches[1][0];

                //get the discussion where the discussion name matches, this assumes only 1 discussion with that name per course
                $discussionrow = $DB->get_record('forum_discussions', array('name' => $discussionname, 'course' => $COURSE->id));
                
                //setting a matching pattern to avoid duplicate code for rendering response
                $matchingPattern =  '/\{discussion:' . preg_quote($discussionname , '/') .  '\}/isuU';
                

            }else{
                
                //Get the forum, then the discussion where the forum is the parent
                $forumrow = $DB->get_record('forum', array('name' => $forumname, 'course' => $COURSE->id));
                $discussionrow = $DB->get_record('forum_discussions', array('name' => $discussionname, 'course' => $COURSE->id, 'forum' => $forumrow->id));

                //setting a matching pattern to avoid duplicate code for rendering response
                $matchingPattern = '/\{discussion:' . preg_quote($forumname, '/') . ':' . preg_quote($discussionname, '/') .  '\}/isuU';
            }

            // Failed to match and find the discussion row to render, so display error message
            if ($discussionrow == null) {
                return preg_replace($matchingPattern, '<span class="filter-forumembed-error">' . get_string('missingdiscussion', 'filter_forumembed', $discussionname) .
                    '</span>', $text);
            }

            //Sucessfully obtained the discussion, prepare for render
            $vaultfactory = mod_forum\local\container::get_vault_factory();
            $discussionvault = $vaultfactory->get_discussion_vault();
            $discussion = $discussionvault->get_from_id($discussionrow->id);

            // Failed to find the discussion to render, so display error message, considering we matched a discussion row above this is deeply concerning.
            if ($discussion == null) {
                return preg_replace($matchingPattern,
                    get_string('missingdiscussion', 'filter_forumembed', $name), $text);
            }


            //complete render of forum/discussion
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

            // Bool $CFG->embed_forum_show_dicusssion_link determines whether or not a link to the discussion is included at the top of the embeded forum, default to true as this is old behaviour
            if(isset($CFG->embed_forum_show_discussion_link) ? $CFG->embed_forum_show_discussion_link : true){
                $output = '<div class="discussion-link"><a href="' . $discussionlink->out() . '">Link to discussion 1</a></div>';
                $output .= $discussionrenderer->render($USER, $post, $replies);
                $output .= '</div>';
            }else{
                $output = $discussionrenderer->render($USER, $post, $replies);
            }

            //Apply a hidden class to the actionbar if the config specifies to hide it. targeted hidden style in filter/forumembed/style.css (this could be done better) , default to false as this is old behaviour
            if(!(isset($CFG->embed_forum_hide_forum_actionbar) ? $CFG->embed_forum_hide_forum_actionbar : false)){
                $output = str_replace('d-flex flex-column flex-sm-row mb-1','d-flex flex-column flex-sm-row mb-1 hidden', $output);
            }

            //inject the rendered discussion into the block
            $text = preg_replace($matchingPattern, $output, $text);

        }

        return $text;
    }
}
