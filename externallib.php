<?php

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
 * External Web Service Template
 *
 * @package    localcustommm
 * @copyright  2013 Juan Leyva && Andrew Davis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->libdir . "/externallib.php");

class local_custommm_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_grades_parameters() {
        return new external_function_parameters(
            array(
                'grades' => new external_single_structure(
                    array(
                        'courseid' => new external_value(PARAM_INT, 'id of course'),
                        'component' => new external_value(PARAM_COMPONENT, 'A component, for example mod_forum or mod_quiz'),
                        'cmid' => new external_format_value(PARAM_INT, 'The ID of the component instance'),
                        'userids' => new external_multiple_structure(
                            new external_value(PARAM_INT, 'user ID'), 'A comma separated list of user IDs or empty to just retrieve grade item information', VALUE_OPTIONAL
                        )
                    )
                )
            )
        );
    }

    /**
     * Retrieve grade items and, optionally, student grades
     *
     * @param array $grades array of grade innformation
     * @return array of newly created groups
     * @since Moodle 2.5
     */
    public static function get_grades($grades) {
        global $CFG, $USER, $DB;
        require_once("$CFG->libdir/gradelib.php");

        $params = self::validate_parameters(self::get_grades_parameters(), array('grades'=>$grades));

        $courseid = $params['grades']['courseid'];
        list($itemtype, $itemmodule) = normalize_component($params['grades']['component']);
        $cmid = $params['grades']['cmid'];

        $userids = null;
        if (isset($params['grades']['userids'])) {
            $userids = $params['grades']['userids'];
        }

        $coursecontext = context_course::instance($courseid);

        try {
            self::validate_context($coursecontext);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $courseid;
            throw new moodle_exception('errorcoursecontextnotvalid' , 'webservice', '', $exceptionparam);
        }

        $course = $DB->get_record('course', array('id'=>$courseid));

        $access = false;
        if (has_capability('moodle/grade:viewall', $coursecontext)) {
            // Can view all user's grades in this course.
            $access = true;

        } else if ($course->showgrades && count($userids) == 1) {
            // Course showgrades == students/parents can access grades.

            if ( $userids[0] == $USER->id and has_capability('moodle/grade:view', $coursecontext)) {
                // Student can view their own grades in this course.
                $access = true;

            } else if ( has_capability('moodle/grade:viewall', context_user::instance($userids[0]))) {
                // User can view the grades of this user. Parent most probably.
                $access = true;
            }
        }

        if (!$access) {
            throw new moodle_exception('nopermissiontoviewgrades', 'error');
        }

        if (! $cm = get_coursemodule_from_id($itemmodule, $cmid)) {
            throw new moodle_exception('invalidcoursemodule');
        }

        if (! $activity = $DB->get_record($itemmodule, array("id" => $cm->instance))) {
            throw new moodle_exception('invalidforumid', 'forum');
        }
        
        $grades = grade_get_grades($courseid, $itemtype, $itemmodule, $activity->id, $userids);
        
        $response = array();
        $response['items'] = array();
        $response['outcomes'] = array();
        
        foreach ($grades->items as $key => $item) {
            $response['items'][$key] = (array) $item;
            $response['items'][$key]['grades'] = array();
            
            foreach ($item->grades as $grade) {
                $response['items'][$key]['grades'][] = (array) $grade;
            }
        }
        
        foreach ($grades->outcomes as $outcome) {
            $response['outcomes'][] = (array) $outcome;
            $response['outcomes'][$key]['grades'] = array();
            
            foreach ($outcome->grades as $grade) {
                $response['outcomes'][$key]['grades'][] = (array) $grade;
            }
        }

        return $response;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.5
     */
    public static function get_grades_returns() {
        return new external_single_structure(
            array(
                'items'  => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'itemnumber'  => new external_value(PARAM_INT, 'Will be 0 unless the module has multiple grades'),
                            'scaleid' => new external_value(PARAM_INT, 'The ID of the custom scale or 0'),
                            'name' => new external_value(PARAM_TEXT, 'The module name'),
                            'grademin' => new external_value(PARAM_FLOAT, 'Minimum grade'),
                            'grademax' => new external_value(PARAM_FLOAT, 'Maximum grade'),
                            'gradepass' => new external_value(PARAM_FLOAT, 'The passing grade threshold'),
                            'locked' => new external_value(PARAM_BOOL, 'Is the grade item locked?'),
                            'hidden' => new external_value(PARAM_BOOL, 'Is the grade item hidden?'),
                            'grades' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'grade' => new external_value(PARAM_FLOAT, 'Student grade'),
                                        'locked' => new external_value(PARAM_BOOL, 'Is the student\s grade locked?'),
                                        'hidden' => new external_value(PARAM_BOOL, 'Is the student\s grade hidden?'),
                                        'overridden' => new external_value(PARAM_BOOL, 'Is the student\s grade overriden?'),
                                        'feedback' => new external_value(PARAM_RAW, 'Feedback from the grader'),
                                        'feedbackformat' => new external_format_value('description'),
                                        'usermodified' => new external_value(PARAM_INT, 'The ID of the last user to modify this student grade'),
                                        'datesubmitted' => new external_value(PARAM_INT, 'A timestamp indicating when the student submitted the activity'), 
                                        'dategraded' => new external_value(PARAM_INT, 'The module name'),
                                        'str_grade' => new external_value(PARAM_TEXT, 'A string representation of the grade'),
                                        'str_long_grade' => new external_value(PARAM_TEXT, 'A nicely formatted string representation of the grade'),
                                        'str_feedback' => new external_value(PARAM_TEXT, 'A string representation of the feedback from the grader'),
                                    )
                                )
                            ),
                        )
                    )
                ),
                'outcomes'  => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'itemnumber'  => new external_value(PARAM_INT, 'Will be 0 unless the module has multiple grades'),
                            'scaleid' => new external_value(PARAM_INT, 'The ID of the custom scale or 0'),
                            'name' => new external_value(PARAM_INT, 'The module name'),
                            'locked' => new external_value(PARAM_BOOL, 'Is the grade item locked?'),
                            'hidden' => new external_value(PARAM_BOOL, 'Is the grade item hidden?'),
                            'grades' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'grade' => new external_value(PARAM_FLOAT, 'Student grade'),
                                        'locked' => new external_value(PARAM_BOOL, 'Is the student\s grade locked?'),
                                        'hidden' => new external_value(PARAM_BOOL, 'Is the student\s grade hidden?'),
                                        'feedback' => new external_value(PARAM_RAW, 'Feedback from the grader'),
                                        'feedbackformat' => new external_format_value('description'),
                                        'usermodified' => new external_value(PARAM_INT, 'The ID of the last user to modify this student grade'),
                                        'str_grade' => new external_value(PARAM_ALPHANUMEXT, 'A string representation of the grade'),
                                        'str_feedback' => new external_value(PARAM_TEXT, 'A string representation of the feedback from the grader'),
                                    )
                                )
                            ),
                        )
                    )
                )
            )
        );

    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function update_grade_parameters() {
        return new external_function_parameters(
            array(
                'grade' => new external_single_structure(
                    array(
                        'source' => new external_value(PARAM_TEXT, 'The source of the grade update'),
                        'courseid' => new external_value(PARAM_INT, 'id of course'),
                        'component' => new external_value(PARAM_COMPONENT, 'A component, for example mod_forum or mod_quiz'),
                        'cmid' => new external_format_value(PARAM_INT, 'The ID of the component instance'),
                        'itemnumber' => new external_value(PARAM_INT, 'grade item ID number for modules that have multiple grades. Typically this is 0.'),
                        'grades' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'studentid' => new external_value(PARAM_FLOAT, 'Student grade'),
                                    'grade' => new external_value(PARAM_FLOAT, 'Student grade'),
                                    'str_feedback' => new external_value(PARAM_TEXT, 'A string representation of the feedback from the grader', VALUE_OPTIONAL),
                                )
                        ), 'Any student grades to alter', VALUE_OPTIONAL),
                        'itemdetails' => new external_single_structure(
                            array(
                                'itemname' => new external_value(PARAM_ALPHANUMEXT, 'The grade item name', VALUE_OPTIONAL),
                                'idnumber' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                                'gradetype' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                                'grademax' => new external_value(PARAM_FLOAT, 'Maximum grade allowed', VALUE_OPTIONAL),
                                'grademin' => new external_value(PARAM_FLOAT, 'Minimum grade allowed', VALUE_OPTIONAL),
                                'scaleid' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                                'multfactor' => new external_value(PARAM_FLOAT, '', VALUE_OPTIONAL),
                                'plusfactor' => new external_value(PARAM_FLOAT, '', VALUE_OPTIONAL),
                                'deleted' => new external_value(PARAM_BOOL, 'True if the grade item should be deleted', VALUE_OPTIONAL),
                                'hidden' => new external_value(PARAM_BOOL, 'True if the grade item is hidden', VALUE_OPTIONAL),
                            ), 'Any grade item settings to alter', VALUE_OPTIONAL
                        )
                    )
                )
            )
        );
    }

    /**
     * Update a grade items and, optionally, student grades
     *
     * @param array $grade array of grade information
     * @since Moodle 2.5
     */
    public static function update_grade($grade) {
        global $CFG, $USER;

        require_once("$CFG->libdir/gradelib.php");

        $params = self::validate_parameters(self::update_grade_parameters(), array('grade' => $grade));
        //$transaction = $DB->start_delegated_transaction();

        $source = $params['grade']['source'];
        $courseid = $params['grade']['courseid'];
        list($itemtype, $itemmodule) = normalize_component($params['grade']['component']);
        $cmid = $params['grade']['cmid'];
        $itemnumber = $params['grade']['itemnumber'];

        $coursecontext = context_course::instance($courseid);

        try {
            self::validate_context($coursecontext);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $courseid;
            throw new moodle_exception('errorcoursecontextnotvalid' , 'webservice', '', $exceptionparam);
        }
        
        $hidinggrades = $editinggradeitem = $editinggrades = false;

        // optional elements
        $grades = $itemdetails = null;
        if ( isset($params['grade']['grades']) ) {
            $editinggrades = true;
            $grades = array();
            foreach ($params['grade']['grades'] as $e) {
                $grades[ $e['studentid'] ] = array('userid' => $e['studentid'], 'rawgrade' => $e['grade']);
            }
        }
        if ( isset($params['grade']['itemdetails']) ) {
            $itemdetails = $params['grade']['itemdetails'];

            if (isset($itemdetails['hidden'])) {
                $hidinggrades = true;
            } else {
                $editinggradeitem = true;
            }
        }

        if ($editinggradeitem && !has_capability('moodle/grade:manage', $coursecontext)) {
            throw new moodle_exception('nopermissiontoviewgrades', 'error');
        }
        if ($hidinggrades && !has_capability('moodle/grade:hide', $coursecontext) && !has_capability('moodle/grade:manage', $coursecontext)) {
            throw new moodle_exception('nopermissiontoviewgrades', 'error');
        }
        if ($editinggrades && !has_capability('moodle/grade:edit', $coursecontext)) {
            throw new moodle_exception('nopermissiontoviewgrades', 'error');
        }

        return grade_update($source, $courseid, $itemtype, $itemmodule, $cmid, $itemnumber, $grades, $itemdetails);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.5
     */
    public static function update_grade_returns() {
        return new external_single_structure(
            array("result" => new external_value(PARAM_INT, 'A value like GRADE_UPDATE_OK or GRADE_UPDATE_FAILED as defined in lib/grade/constants.php'))
        );
    }

    /**
     * Describes the parameters for get_forum.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_forums_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'course ID',
                        '', VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Course IDs', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Returns a list of forums in a provided list of courses,
     * if no list is provided all forums that the user can view
     * will be returned.
     *
     * @param array $courseids the course ids
     * @return array the forum details
     * @since Moodle 2.5
     */
    public static function get_forums_by_courses($courseids = array()) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/forum/lib.php");

        $params = self::validate_parameters(self::get_forums_by_courses_parameters(), array('courseids' => $courseids));

        if (empty($params['courseids'])) {
            // Get all the courses the user can view.
            $courseids = array_keys(enrol_get_my_courses());
        } else {
            $courseids = $params['courseids'];
        }

        // Array to store the forums to return.
        $arrforums = array();

        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Go through the courseids and return the forums.
            foreach ($courseids as $cid) {
                // Get the course context.
                $context = context_course::instance($cid);
                // Check the user can function in this context.
                self::validate_context($context);
                // Get the forums in this course.
                if ($forums = $DB->get_records('forum', array('course' => $cid))) {
                    // Get the modinfo for the course.
                    $modinfo = get_fast_modinfo($cid);
                    // Get the forum instances.
                    $foruminstances = $modinfo->get_instances_of('forum');
                    // Loop through the forums returned by modinfo.
                    foreach ($foruminstances as $forumid => $cm) {
                        // If it is not visible or present in the forums get_records call, continue.
                        if (!$cm->uservisible || !isset($forums[$forumid])) {
                            continue;
                        }
                        // Set the forum object.
                        $forum = $forums[$forumid];
                        // Get the module context.
                        $context = context_module::instance($cm->id);
                        // Check they have the view forum capability.
                        require_capability('mod/forum:viewdiscussion', $context);
                        // Format the intro before being returning using the format setting.
                        list($forum->intro, $forum->introformat) = external_format_text($forum->intro, $forum->introformat,
                            $context->id, 'mod_forum', 'intro', 0);
                        // Add the course module id to the object, this information is useful.
                        $forum->cmid = $cm->id;
                        // Add the forum to the array to return.
                        $arrforums[$forum->id] = (array) $forum;
                    }
                }
            }
        }

        return $arrforums;
    }

    /**
     * Describes the get_forum return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
     public static function get_forums_by_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'course' => new external_value(PARAM_TEXT, 'Course id'),
                    'type' => new external_value(PARAM_TEXT, 'The forum type'),
                    'name' => new external_value(PARAM_TEXT, 'Forum name'),
                    'intro' => new external_value(PARAM_RAW, 'The forum intro'),
                    'introformat' => new external_format_value('intro'),
                    'assessed' => new external_value(PARAM_INT, 'Aggregate type'),
                    'assesstimestart' => new external_value(PARAM_INT, 'Assess start time'),
                    'assesstimefinish' => new external_value(PARAM_INT, 'Assess finish time'),
                    'scale' => new external_value(PARAM_INT, 'Scale'),
                    'maxbytes' => new external_value(PARAM_INT, 'Maximum attachment size'),
                    'maxattachments' => new external_value(PARAM_INT, 'Maximum number of attachments'),
                    'forcesubscribe' => new external_value(PARAM_INT, 'Force users to subscribe'),
                    'trackingtype' => new external_value(PARAM_INT, 'Subscription mode'),
                    'rsstype' => new external_value(PARAM_INT, 'RSS feed for this activity'),
                    'rssarticles' => new external_value(PARAM_INT, 'Number of RSS recent articles'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'warnafter' => new external_value(PARAM_INT, 'Post threshold for warning'),
                    'blockafter' => new external_value(PARAM_INT, 'Post threshold for blocking'),
                    'blockperiod' => new external_value(PARAM_INT, 'Time period for blocking'),
                    'completiondiscussions' => new external_value(PARAM_INT, 'Student must create discussions'),
                    'completionreplies' => new external_value(PARAM_INT, 'Student must post replies'),
                    'completionposts' => new external_value(PARAM_INT, 'Student must post discussions or replies'),
                    'cmid' => new external_value(PARAM_INT, 'Course module id')
                ), 'forum'
            )
        );
    }

    /**
     * Describes the parameters for get_forum_discussions.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_forum_discussions_parameters() {
        return new external_function_parameters (
            array(
                'forumids' => new external_multiple_structure(new external_value(PARAM_INT, 'forum ID',
                        '', VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Forum IDs', VALUE_REQUIRED),
            )
        );
    }

    /**
     * Returns a list of forum discussions as well as a summary of the discussion
     * in a provided list of forums.
     *
     * @param array $forumids the forum ids
     * @return array the forum discussion details
     * @since Moodle 2.5
     */
    public static function get_forum_discussions($forumids) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/forum/lib.php");

        // Validate the parameter.
        $params = self::validate_parameters(self::get_forum_discussions_parameters(), array('forumids' => $forumids));
        $forumids = $params['forumids'];

        // Array to store the forum discussions to return.
        $arrdiscussions = array();
        // Keep track of the course ids we have performed a require_course_login check on to avoid repeating.
        $arrcourseschecked = array();
        // Store the modinfo for the forums in an individual courses.
        $arrcoursesforuminfo = array();
        // Keep track of the users we have looked up in the DB.
        $arrusers = array();

        // Loop through them.
        foreach ($forumids as $id) {
            // Get the forum object.
            $forum = $DB->get_record('forum', array('id' => $id), '*', MUST_EXIST);
            // Check that that user can view this course if check not performed yet.
            if (!in_array($forum->course, $arrcourseschecked)) {
                // Check the user can function in this context.
                self::validate_context(context_course::instance($forum->course));
                // Add to the array.
                $arrcourseschecked[] = $forum->course;
            }
            // Get the modinfo for the course if we haven't already.
            if (!isset($arrcoursesforuminfo[$forum->course])) {
                $modinfo = get_fast_modinfo($forum->course);
                $arrcoursesforuminfo[$forum->course] = $modinfo->get_instances_of('forum');
            }
            // Check if this forum does not exist in the modinfo array, should always be false unless DB is borked.
            if (empty($arrcoursesforuminfo[$forum->course][$forum->id])) {
                throw new moodle_exception('invalidmodule', 'error');
            }
            // We now have the course module.
            $cm = $arrcoursesforuminfo[$forum->course][$forum->id];
            // If the forum is not visible throw an exception.
            if (!$cm->uservisible) {
                throw new moodle_exception('nopermissiontoshow', 'error');
            }
            // Get the module context.
            $modcontext = context_module::instance($cm->id);
            // Check they have the view forum capability.
            require_capability('mod/forum:viewdiscussion', $modcontext);
            // Check if they can view full names.
            $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);
            // Get the unreads array, this takes a forum id and returns data for all discussions.
            $unreads = array();
            if ($cantrack = forum_tp_can_track_forums($forum)) {
                if ($forumtracked = forum_tp_is_tracked($forum)) {
                    $unreads = forum_get_discussions_unread($cm);
                }
            }
            // The forum function returns the replies for all the discussions in a given forum.
            $replies = forum_count_discussion_replies($id);
            // Get the discussions for this forum.
            if ($discussions = $DB->get_records('forum_discussions', array('forum' => $id))) {
                foreach ($discussions as $discussion) {
                    // If the forum is of type qanda and the user has not posted in the discussion
                    // we need to ensure that they have the required capability.
                    if ($forum->type == 'qanda' && !forum_user_has_posted($discussion->forum, $discussion->id, $USER->id)) {
                        require_capability('mod/forum:viewqandawithoutposting', $modcontext);
                    }
                    // If we don't have the users details then perform DB call.
                    if (empty($arrusers[$discussion->userid])) {
                        $arrusers[$discussion->userid] = $DB->get_record('user', array('id' => $discussion->userid),
                            'firstname, lastname, email, picture, imagealt', MUST_EXIST);
                    }
                    // Get the subject.
                    $subject = $DB->get_field('forum_posts', 'subject', array('id' => $discussion->firstpost), MUST_EXIST);
                    // Create object to return.
                    $return = new stdClass();
                    $return->id = (int) $discussion->id;
                    $return->course = $discussion->course;
                    $return->forum = $discussion->forum;
                    $return->name = $discussion->name;
                    $return->userid = $discussion->userid;
                    $return->groupid = $discussion->groupid;
                    $return->assessed = $discussion->assessed;
                    $return->timemodified = (int) $discussion->timemodified;
                    $return->usermodified = $discussion->usermodified;
                    $return->timestart = $discussion->timestart;
                    $return->timeend = $discussion->timeend;
                    $return->firstpost = (int) $discussion->firstpost;
                    $return->firstuserfullname = fullname($arrusers[$discussion->userid], $canviewfullname);
                    $return->firstuserimagealt = $arrusers[$discussion->userid]->imagealt;
                    $return->firstuserpicture = $arrusers[$discussion->userid]->picture;
                    $return->firstuseremail = $arrusers[$discussion->userid]->email;
                    $return->subject = $subject;
                    $return->numunread = '';
                    if ($cantrack && $forumtracked) {
                        if (isset($unreads[$discussion->id])) {
                            $return->numunread = (int) $unreads[$discussion->id];
                        }
                    }
                    // Check if there are any replies to this discussion.
                    if (!empty($replies[$discussion->id])) {
                         $return->numreplies = (int) $replies[$discussion->id]->replies;
                         $return->lastpost = (int) $replies[$discussion->id]->lastpostid;
                     } else { // No replies, so the last post will be the first post.
                        $return->numreplies = 0;
                        $return->lastpost = (int) $discussion->firstpost;
                     }
                    // Get the last post as well as the user who made it.
                    $lastpost = $DB->get_record('forum_posts', array('id' => $return->lastpost), '*', MUST_EXIST);
                    if (empty($arrusers[$lastpost->userid])) {
                        $arrusers[$lastpost->userid] = $DB->get_record('user', array('id' => $lastpost->userid),
                            'firstname, lastname, email, picture, imagealt', MUST_EXIST);
                    }
                    $return->lastuserid = $lastpost->userid;
                    $return->lastuserfullname = fullname($arrusers[$lastpost->userid], $canviewfullname);
                    $return->lastuserimagealt = $arrusers[$lastpost->userid]->imagealt;
                    $return->lastuserpicture = $arrusers[$lastpost->userid]->picture;
                    $return->lastuseremail = $arrusers[$lastpost->userid]->email;
                    // Add the discussion statistics to the array to return.
                    $arrdiscussions[$return->id] = (array) $return;
                }
            }
        }

        return $arrdiscussions;
    }

    /**
     * Describes the get_forum_discussions return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
     public static function get_forum_discussions_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'course' => new external_value(PARAM_INT, 'Course id'),
                    'forum' => new external_value(PARAM_INT, 'The forum id'),
                    'name' => new external_value(PARAM_TEXT, 'Discussion name'),
                    'userid' => new external_value(PARAM_INT, 'User id'),
                    'groupid' => new external_value(PARAM_INT, 'Group id'),
                    'assessed' => new external_value(PARAM_INT, 'Is this assessed?'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                    'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                    'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                    'firstpost' => new external_value(PARAM_INT, 'The first post in the discussion'),
                    'firstuserfullname' => new external_value(PARAM_TEXT, 'The discussion creators fullname'),
                    'firstuserimagealt' => new external_value(PARAM_TEXT, 'The discussion creators image alt'),
                    'firstuserpicture' => new external_value(PARAM_INT, 'The discussion creators profile picture'),
                    'firstuseremail' => new external_value(PARAM_TEXT, 'The discussion creators email'),
                    'subject' => new external_value(PARAM_TEXT, 'The discussion subject'),
                    'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                    'numunread' => new external_value(PARAM_TEXT, 'The number of unread posts, blank if this value is
                        not available due to forum settings.'),
                    'lastpost' => new external_value(PARAM_INT, 'The id of the last post in the discussion'),
                    'lastuserid' => new external_value(PARAM_INT, 'The id of the user who made the last post'),
                    'lastuserfullname' => new external_value(PARAM_TEXT, 'The last person to posts fullname'),
                    'lastuserimagealt' => new external_value(PARAM_TEXT, 'The last person to posts image alt'),
                    'lastuserpicture' => new external_value(PARAM_INT, 'The last person to posts profile picture'),
                    'lastuseremail' => new external_value(PARAM_TEXT, 'The last person to posts email'),
                ), 'discussion'
            )
        );
    }

    /**
     * Describes the parameters for get_forum_posts.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_forum_posts_parameters() {
        return new external_function_parameters (
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion ID','', VALUE_REQUIRED, '', NULL_NOT_ALLOWED),
            )
        );
    }

    /**
     * Returns a list of forum posts for a discussion
     *
     * @param int $discussionid the post ids
     * @return array the forum post details
     * @since Moodle 2.5
     */
    public static function get_forum_posts($discussionid) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/forum/lib.php");

        // Validate the parameter.
        $params = self::validate_parameters(self::get_forum_posts_parameters(), array('discussionid' => $discussionid));
        $discussionid = $params['discussionid'];

        if (!$forumid = $DB->get_field('forum_discussions', 'forum', array('id' => $discussionid))) {
            throw new moodle_exception("Invalid discussion id");
        }

        // Get the forum object.
        $forum = $DB->get_record('forum', array('id' => $forumid), '*', MUST_EXIST);
        // Check that that user can view this course if check not performed yet.

        self::validate_context(context_course::instance($forum->course));

        // Get the modinfo for the course if we haven't already.
        $modinfo = get_fast_modinfo($forum->course);
        $forums = $modinfo->get_instances_of('forum');
        
        // Check if this forum does not exist in the modinfo array, should always be false unless DB is borked.
        if (empty($forums[$forum->id])) {
            throw new moodle_exception('invalidmodule', 'error');
        }

        // We now have the course module.
        $cm = $forums[$forum->id];
        // If the forum is not visible throw an exception.
        if (!$cm->uservisible) {
            throw new moodle_exception('nopermissiontoshow', 'error');
        }

        // Get the module context.
        $modcontext = context_module::instance($cm->id);
        // Check they have the view forum capability.
        require_capability('mod/forum:viewdiscussion', $modcontext);
        // Check if they can view full names.
        $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);
        
        // If the forum is of type qanda and the user has not posted in the post
        // we need to ensure that they have the required capability.
        if ($forum->type == 'qanda' && !forum_user_has_posted($forumid, $discussionid, $USER->id)) {
            require_capability('mod/forum:viewqandawithoutposting', $modcontext);
        }

        $arrposts = array();
        $arrusers = array();

        // Get the posts for this discussion.
        if ($posts = $DB->get_records('forum_posts', array('discussion' => $discussionid))) {
            foreach ($posts as $post) {
                // If we don't have the users details then perform DB call.
                if (empty($arrusers[$post->userid])) {
                    $arrusers[$post->userid] = $DB->get_record('user', array('id' => $post->userid),
                        'firstname, lastname, email, picture, imagealt', MUST_EXIST);
                }
                $post->userfullname = fullname($arrusers[$post->userid], $canviewfullname);
                $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_forum', 'post', $post->id);
                
                $arrposts[] = (array) $post;
            }
        }
        return $arrposts;
    }

    /**
     * Describes the get_forum_posts return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
     public static function get_forum_posts_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Post id'),
                    'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                    'parent' => new external_value(PARAM_INT, 'Parent id'),
                    'userid' => new external_value(PARAM_INT, 'User id'),
                    'created' => new external_value(PARAM_INT, 'Creation time'),
                    'modified' => new external_value(PARAM_INT, 'Time modified'),
                    'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                    'subject' => new external_value(PARAM_TEXT, 'The post subject'),
                    'message' => new external_value(PARAM_RAW, 'The post message'),
                    'messageformat' => new external_value(PARAM_TEXT, 'The post message'),
                    'messagetrust' => new external_value(PARAM_INT, 'The post message'),
                    'attachment' => new external_value(PARAM_RAW, 'The post message'),
                    'totalscore' => new external_value(PARAM_INT, 'The post message'),
                    'mailnow' => new external_value(PARAM_INT, 'The post message'),
                    'userfullname' => new external_value(PARAM_TEXT, 'User id'),
                ), 'post'
            )
        );
    }

}
