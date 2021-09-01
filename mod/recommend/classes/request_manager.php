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
 * Contains class mod_recommend_request_manager
 *
 * @package    mod_recommend
 * @copyright  2016 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class mod_recommend_request_manager
 *
 * @package    mod_recommend
 * @copyright  2016 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_recommend_request_manager {
    /** @var cm_info */
    protected $cm;
    /** @var stdClass */
    protected $object;
    /** @var array */
    protected $requests;
    /** @var array */
    protected $validatedrequests = [];

    /** Just created, will be sent by cron in 15 minutes */
    const STATUS_PENDING = 0;
    /** Recommendation request was sent but not yet completed */
    const STATUS_REQUEST_SENT = 1;
    /** Recommending person declined the request (not currently used) */
    const STATUS_RECOMMENDATION_DECLINED = 2;
    /** Recommending person completed the recommendation */
    const STATUS_RECOMMENDATION_COMPLETED = 3;
    /** Recommendation was rejected by the teacher */
    const STATUS_RECOMMENDATION_REJECTED = 4;
    /** Recommendation was accepted by the teacher */
    const STATUS_RECOMMENDATION_ACCEPTED = 5;

    /**
     * Constructor
     * @param cm_info $cm
     * @param stdClass $object
     */
    public function __construct(cm_info $cm, $object = null) {
        global $DB;
        $this->cm = $cm;
        if ($cm->modname !== 'recommend') {
            throw new coding_exception('Course module for recommend module is expected');
        }
        if ($object === null) {
            $object = $DB->get_record('recommend', ['id' => $cm->instance], '*', MUST_EXIST);
        }
        $this->object = $object;
    }

    /**
     * Returns the course module
     * @return cm_info
     */
    public function get_cm() {
        return $this->cm;
    }

    /**
     * Returns the current user
     * @return int
     */
    public function get_userid() {
        return $this->cm->get_modinfo()->userid;
    }

    /**
     * Returns the list of requests for the current user
     * @return array
     */
    public function get_requests() {
        global $DB;
        if ($this->requests === null) {
             $this->requests = $DB->get_records('recommend_request',
                     ['recommendid' => $this->object->id,
                         'userid' => $this->get_userid()]);
        }
        return $this->requests;
    }

    /**
     * Returns the number of requests that this user can add
     * @return int
     */
    public function can_add_request() {
        if (!has_capability('mod/recommend:request', $this->cm->context, null, false)) {
            return 0;
        }
        $canadd = $this->object->maxrequests - count($this->get_requests());
        return max($canadd, 0);
    }

    /**
     * Adds requests
     * @param stdClass $data data from form {@link mod_recommend_add_request_form}
     */
    public function add_requests($data) {
        $canadd = $this->can_add_request();
        for ($i = 1; $i <= $canadd; $i++) {
            if (!empty($data->{'email'.$i})) {
                $email = $data->{'email'.$i};
                $name = $data->{'name'.$i};
                $requests = $this->get_requests();
                foreach ($requests as $request) {
                    if (strtolower($request->email) === strtolower($email)) {
                        \core\notification::add(get_string('error_emailduplicated', 'mod_recommend'),
                                \core\output\notification::NOTIFY_ERROR);
                        continue 2;
                    }
                }
                $this->add_request($email, $name);
            }
        }
    }

    /**
     * Generates and returns a unique secret code
     * @param int $userid
     * @param string $email
     * @param string $name
     * @return string
     */
    public static function generate_secret($userid, $email, $name) {
        global $DB;
        while (true) {
            $secret = md5('secret/'.rand(1, 10000).'/'.$userid.'/'.$email.'/'.$name);
            if (!$DB->record_exists('recommend_request', ['secret' => $secret])) {
                return $secret;
            }
        }
    }

    /**
     * Adds a single request
     * @param string $email
     * @param string $name
     * @return stdClass
     */
    protected function add_request($email, $name) {
        global $DB;
        $this->get_requests();
        $record = (object)array(
            'recommendid' => $this->object->id,
            'userid' => $this->get_userid(),
            'email' => $email,
            'name' => $name,
            'status' => self::STATUS_PENDING,
            'timerequested' => time(),
            'timecompleted' => null,
            'secret' => $this->generate_secret($this->get_userid(), $email, $name)
        );
        $record->id = $DB->insert_record('recommend_request', $record);
        $this->requests[$record->id] = $record;
        \mod_recommend\event\request_created::create_from_request($this->cm, $record)->trigger();
        return $record;
    }

    /**
     * Requests table for the current user
     * @return \html_table
     */
    public function get_requests_table() {
        global $OUTPUT;
        $requests = $this->get_requests();
        if (!$requests) {
            return null;
        }
        $table = new html_table();
        $table->attributes['class'] = 'generaltable recommend-requests';
        $canviewrequests = $this->can_view_requests();
        foreach ($requests as $request) {
            $status = $OUTPUT->pix_icon('status'.$request->status,
                    '', 'mod_recommend');
            $status .= ' '. get_string('status'.$request->status, 'recommend');
            if ($request->status == self::STATUS_PENDING) {
                $deleteurl = new moodle_url('/mod/recommend/view.php', ['id' => $this->cm->id,
                    'action' => 'deleterequest', 'requestid' => $request->id,
                    'sesskey' => sesskey()]);
                $status .= '<br>'.html_writer::link($deleteurl, get_string('delete'),
                        ['class' => 'deleterequest']);
            }
            if ($request->status == self::STATUS_REQUEST_SENT) {
                $resendurl = new moodle_url('/mod/recommend/view.php', ['id' => $this->cm->id,
                    'action' => 'resendrequest', 'requestid' => $request->id,
                    'sesskey' => sesskey()]);
                $status .= '<br>'.html_writer::link($resendurl, get_string('resend', 'mod_recommend'),
                        ['class' => 'resendrequest']);
            }
            $name = $request->name;
            if ($canviewrequests && $request->status >= self::STATUS_RECOMMENDATION_COMPLETED) {
                $url = new moodle_url('/mod/recommend/view.php', ['id' => $this->cm->id,
                        'requestid' => $request->id, 'action' => 'viewrequest']);
                $status = html_writer::link($url, $status);
                $name = html_writer::link($url, $name);
            }
            $cells = [$name, $request->email, $status];
            $table->data[] = new html_table_row($cells);
        }
        return $table;
    }

    /**
     * Requests summary for the current user
     * @return array
     */
    public function get_requests_by_status() {
        $summary = [];
        $requests = $this->get_requests();
        foreach ($requests as $request) {
            $summary += [$request->status => 0];
            $summary[$request->status]++;
        }
        return $summary;
    }

    /**
     * Validates that given requestid exists and belongs to the current module
     * @param int $requestid
     * @return bool
     */
    public function validate_request($requestid) {
        global $DB;
        if (!is_int($requestid) || $requestid <= 0) {
            return null;
        }
        if (array_key_exists($requestid, $this->validatedrequests)) {
            return $this->validatedrequests[$requestid];
        }
        $requests = $this->get_requests();
        if (isset($requests[$requestid])) {
            return $requests[$requestid];
        }
        $this->validatedrequests[$requestid] = $DB->get_record('recommend_request',
                ['id' => $requestid, 'recommendid' => $this->object->id]);
        return $this->validatedrequests[$requestid];
    }

    /**
     * Can the request be deleted
     * @param int $requestid
     * @return bool
     */
    public function can_delete_request($requestid) {
        if (has_capability('mod/recommend:delete', $this->cm->context)) {
            // Can delete any request.
            return true;
        }
        // Check if it is the request for the current user and it is in pending status.
        if (!has_capability('mod/recommend:request', $this->cm->context)) {
            return false;
        }
        $requests = $this->get_requests();
        if (!isset($requests[$requestid])) {
            return false;
        }
        return $requests[$requestid]->status == self::STATUS_PENDING;
    }

    /**
     * Delete a request
     * @param int $requestid
     * @return bool
     */
    public function delete_request($requestid) {
        global $DB;
        $requests = $this->get_requests();
        if (!isset($requests[$requestid])) {
            if (!$requests = $DB->get_records('recommend_request',
                ['id' => $requestid, 'recommendid' => $this->object->id])) {
                return false;
            }
        }
        $DB->delete_records('recommend_reply', ['requestid' => $requestid]);
        $DB->delete_records('recommend_request', ['id' => $requestid]);
        \mod_recommend\event\request_deleted::create_from_request($this->cm, $requests[$requestid])->trigger();
        unset($this->requests[$requestid]);
        return true;
    }

    /**
     * Accept a request
     *
     * @param int $requestid
     * @return bool
     */
    public function accept_request($requestid) {
        return mod_recommend_recommendation::accept_request($this->cm,
                $this->object, $requestid);
    }

    /**
     * Reject a request
     *
     * @param int $requestid
     * @return bool
     */
    public function reject_request($requestid) {
        return mod_recommend_recommendation::reject_request($this->cm,
                $this->object, $requestid);
    }

    /**
     * Can $USER iew the request
     * @return bool
     */
    public function can_view_requests() {
        $caps = ['mod/recommend:viewdetails', 'mod/recommend:accept'];
        return has_any_capability($caps, $this->cm->context);
    }

    /**
     * Can $USER accept a request
     * @return bool
     */
    public function can_accept_requests() {
        return has_capability('mod/recommend:accept', $this->cm->context);
    }

    /**
     * List of all requests
     * @return \html_table
     */
    public function get_all_requests_table() {
        global $DB, $OUTPUT;
        $ufields = user_picture::fields('u', null, 'useridalias');
        $sql = "SELECT r.*, $ufields
                FROM {recommend_request} r
                JOIN {user} u ON u.id = r.userid
                WHERE r.recommendid = :recommendid
                ORDER BY r.userid, r.timerequested";
        $params['recommendid'] = $this->object->id;

        $result = $DB->get_records_sql($sql, $params);

        $data = [];
        $maxrequests = 0;
        foreach ($result as $record) {
            if (!isset($data[$record->userid])) {
                $user = user_picture::unalias($record, null, 'userid');
                $data[$record->userid] = ['user' => $user,
                    'fullname' => fullname($user), 'requests' => []];
            }
            $data[$record->userid]['requests'][] = $record;
            $maxrequests = max($maxrequests, count($data[$record->userid]['requests']));
        }

        $enrolledusers = get_enrolled_users($this->cm->context, 'mod/recommend:request');
        foreach ($enrolledusers as $user) {
            if (!isset($data[$user->id])) {
                $data[$user->id] = ['user' => $user,
                    'fullname' => fullname($user), 'requests' => []];
            }
        }

        usort($data, function($a, $b) {
            return strcmp($a['fullname'], $b['fullname']);
        });

        $table = new html_table();
        $table->attributes['class'] = 'generaltable recommend-requests-all';
        foreach ($data as $userdata) {
            $profilelink = new moodle_url('/user/view.php',
                ['id' => $userdata['user']->id, 'course' => $this->cm->course]);
            $userpic = $OUTPUT->user_picture($userdata['user'],
                ['courseid' => $this->cm->course, 'class' => 'profilepicture', 'size' => 35]);
            $cells = [$userpic . $OUTPUT->spacer(['width' => 5]) . $userdata['fullname']];
            foreach ($userdata['requests'] as $request) {
                $url = new moodle_url('/mod/recommend/view.php', ['id' => $this->cm->id,
                        'requestid' => $request->id, 'action' => 'viewrequest']);
                $status = $OUTPUT->pix_icon('status'.$request->status,
                        get_string('status'.$request->status, 'recommend'), 'mod_recommend');
                $cells[] = html_writer::link($url, $status);
            }
            while (count($cells) < $maxrequests + 1) {
                $cells[] = '';
            }
            $table->data[] = new html_table_row($cells);
        }

        return $table;
    }

    /**
     * Sends the emails to all requests pending for over 15 minutes
     * @return int nuber of emails sent
     */
    public static function email_scheduled() {
        global $DB;
        $cooldowntimeout = 15 * MINSECS; // TODO setting.

        $module = $DB->get_record('modules', ['name' => 'recommend', 'visible' => 1]);
        if (!$module) {
            return 0;
        }

        $userfields = user_picture::fields('u', null, 'userid', 'fromuser');
        $records = $DB->get_records_sql("
                SELECT r.*,
                    m.course,
                    m.requesttemplatesubject,
                    m.requesttemplatebody,
                    m.requesttemplatebodyformat,
                    $userfields
                FROM {recommend_request} r
                JOIN {user} u ON u.id = r.userid AND u.deleted = 0 AND u.suspended = 0
                JOIN {recommend} m ON m.id = r.recommendid
                WHERE r.status = ? AND r.timerequested < ?
                ORDER BY m.course, r.recommendid",
                [self::STATUS_PENDING, time() - $cooldowntimeout]);

        foreach ($records as $record) {
            context_helper::preload_from_record($record);
            $user = user_picture::unalias($record, null, 'userid', 'fromuser');
            $recommend = (object)['id' => $record->recommendid,
                'requesttemplatebody' => $record->requesttemplatebody,
                'requesttemplatebodyformat' => $record->requesttemplatebodyformat,
                'requesttemplatesubject' => $record->requesttemplatesubject,
                'course' => $record->course];
            self::email_request($record, $recommend, $user);
        }

        return count($records);
    }

    /**
     * Can current user resend the request
     * @param int $requestid
     * @return bool
     */
    public function can_resend_request($requestid) {
        if (!$request = $this->validate_request($requestid)) {
            return false;
        }
        if ($request->status > self::STATUS_REQUEST_SENT) {
            return false;
        }
        if (has_capability('mod/recommend:viewdetails', $this->cm->context)) {
            // Can view any request.
            return true;
        }
        // Check if it is the request for the current user and it is in pending status.
        if (!has_capability('mod/recommend:request', $this->cm->context)) {
            return false;
        }
        $requests = $this->get_requests();
        return array_key_exists($requestid, $requests);
    }

    /**
     * E-mails single recommendation request
     * @param stdClass $request
     * @param stdClass|null $recommend
     * @param stdClass|null $fromuser
     * @return bool
     */
    public static function email_request($request, $recommend = null, $fromuser = null) {
        global $DB;
        if (!is_object($request)) {
            $request = $DB->get_record('recommend_request', ['id' => $request]);
        }
        if ($request->status > self::STATUS_REQUEST_SENT) {
            return false;
        }
        if ($fromuser === null) {
            $fromuser = $DB->get_record('user', ['id' => $request->userid]);
        }
        if ($recommend === null) {
            $recommend = $DB->get_record('recommend',
                ['id' => $request->recommendid], '*', MUST_EXIST);
        }
        $modinfo = get_fast_modinfo($recommend->course);
        $cm = $modinfo->instances['recommend'][$recommend->id];
        $context = context_module::instance($cm->id);

        $site = get_site();
        $siteadmin = generate_email_signoff();

        $link = new moodle_url('/mod/recommend/recommend.php', ['s' => $request->secret]);
        $options = ['context' => $context];
        $replacements = [
            '{PARTICIPANT}' => fullname($fromuser, true),
            '{NAME}' => $request->name,
            '{LINK}' => $link->out(),
            '{SITE}' => format_string($site->fullname, true, $options),
            '{ADMIN}' => $siteadmin,
        ];
        $subject = str_replace(array_keys($replacements), array_values($replacements),
                format_string($recommend->requesttemplatesubject, true, $options));
        $body = str_replace(array_keys($replacements), array_values($replacements),
                format_text($recommend->requesttemplatebody,
                        $recommend->requesttemplatebodyformat, $options));

        $tempuser = fullclone(\core_user::get_support_user());
        $tempuser->firstname = $request->name;
        $tempuser->lastname = '';
        $tempuser->mailformat = FORMAT_HTML;
        $tempuser->id = $fromuser->id;
        $tempuser->email = $request->email;
        email_to_user($tempuser, \core_user::get_support_user(), $subject,
            html_to_text($body), $body);

        if ($request->status < self::STATUS_REQUEST_SENT) {
            $DB->update_record('recommend_request',
                ['id' => $request->id, 'status' => self::STATUS_REQUEST_SENT]);

            // Notify students that request was sent.
            $request->status = self::STATUS_REQUEST_SENT;
            mod_recommend_recommendation::notify($request, $cm);
        }
        \mod_recommend\event\request_sent::create_from_request($cm, $request)->trigger();

        return true;
    }
}
