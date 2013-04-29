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
 * Web service local plugin template external functions and service definitions.
 *
 * @package    localcustommm
 * @copyright  2013 Juan Leyva && Andrew Davis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.
$functions = array(

    // === grade related functions ===

    'local_custommm_get_grades' => array(
        'classname'   => 'local_custommm_external',
        'methodname'  => 'get_grades',
        'classpath'   => 'local/custommm/externallib.php',
        'description' => 'Returns grade item details and optionally student grades.',
        'type'        => 'read',
        'capabilities'=> 'moodle/grade:view, moodle/grade:viewall',
    ),
    
    'local_custommm_update_grade' => array(
        'classname'   => 'local_custommm_external',
        'methodname'  => 'update_grade',
        'classpath'   => 'local/custommm/externallib.php',
        'description' => 'Update one or more grade item and student grades.',
        'type'        => 'write',
        'capabilities'=> '',
    ),

    'local_custommm_get_forums_by_courses' => array(
        'classname' => 'local_custommm_external',
        'methodname' => 'get_forums_by_courses',
        'classpath' => 'local/custommm/externallib.php',
        'description' => 'Returns a list of forum instances in a provided set of courses, if
            no courses are provided then all the forum instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/forum:viewdiscussion'
    ),

    'local_custommm_get_forum_discussions' => array(
        'classname' => 'local_custommm_external',
        'methodname' => 'get_forum_discussions',
        'classpath' => 'local/custommm/externallib.php',
        'description' => 'Returns a list of forum discussions contained within a given set of forums.',
        'type' => 'read',
        'capabilities' => 'mod/forum:viewdiscussion, mod/forum:viewqandawithoutposting'
    ),

    'local_custommm_get_forum_posts' => array(
        'classname' => 'local_custommm_external',
        'methodname' => 'get_forum_posts',
        'classpath' => 'local/custommm/externallib.php',
        'description' => 'Returns a list of forum posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/forum:viewdiscussion, mod/forum:viewqandawithoutposting'
    )
    
);
