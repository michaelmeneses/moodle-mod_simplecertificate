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
 * Add Watermark and send files
 *
 * @package mod
 * @subpackage simplecertificate
 * @copyright 2014 © Carlos Alexandre Soares da Fonseca
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
$code = required_param('code', PARAM_TEXT); // Issued Code.

$issuedcert = $DB->get_record("simplecertificate_issues", array('code' => $code));
if (!$issuedcert) {
    print_error(get_string('issuedcertificatenotfound', 'simplecertificate'));
} else {
    send_certificate_file($issuedcert);
}

function send_certificate_file(stdClass $issuedcert)
{
    global $CFG, $USER, $DB, $PAGE;

    if ($issuedcert->haschange) {
        // This issue have a haschange flag, try to reissue.
        if (empty($issuedcert->timedeleted)) {
            require_once($CFG->dirroot . '/mod/simplecertificate/locallib.php');
            try {
                // Try to get cm.
                $cm = get_coursemodule_from_instance('simplecertificate', $issuedcert->certificateid, 0, false, MUST_EXIST);

                $context = context_module::instance($cm->id);

                // Must set a page context to issue .
                $PAGE->set_context($context);
                $simplecertificate = new simplecertificate($context, null, null);
                $file = $simplecertificate->get_issue_file($issuedcert);

            } catch (moodle_exception $e) {
                // Only debug, no errors.
                debugging($e->getMessage(), DEBUG_DEVELOPER, $e->getTrace());
            }
        } else {
            // Have haschange and timedeleted, somehting wrong, it will be impossible to reissue
            // add wraning.
            debugging("issued certificate [$issuedcert->id], have haschange and timedeleted");
        }
        $issuedcert->haschange = 0;
        $DB->update_record('simplecertificate_issues', $issuedcert);
    }

    if (empty($file)) {
        $fs = get_file_storage();
        if (!$fs->file_exists_by_hash($issuedcert->pathnamehash)) {
            print_error(get_string('filenotfound', 'simplecertificate', ''));
        }

        $file = $fs->get_file_by_hash($issuedcert->pathnamehash);
    }

    send_stored_file($file, 0, 0, true);
}
