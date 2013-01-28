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

}
