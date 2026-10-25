<?php
/**
* Google Location Module for MajorDoMo
* Fork & Enhancements by mambursoft
*
* Features:
* - Google Family Link & Google Maps Location Sharing integration
* - Direct feed to MajorDoMo GPS Tracker (app_gpstrack via gps.php)
* - Direct binding to MajorDoMo Objects (e.g. Users.Son, Users.Admin)
* - Google Maps Reverse Geocoding API integration (optional)
* - Leaflet / OpenStreetMap visual tracking in Admin & User view
* - Resilient session handling without corrupting master cookie files
* - Netscape #HttpOnly_ unmasking and smart HTML auth error detection
*
* @package project
* @author mambursoft <https://github.com/mambursoft>
* @version 1.0.1
*/

class google_location extends module {

    /**
    * Module class constructor
    */
    function __construct() {
        $this->name = "google_location";
        $this->title = "Google Location";
        $this->module_category = "<#LANG_SECTION_APPLICATIONS#>";
        $this->checkInstalled();
    }

    /**
    * Save module parameters
    */
    function saveParams($data = 1) {
        $p = array();
        if (isset($this->id))        $p["id"] = $this->id;
        if (isset($this->view_mode)) $p["view_mode"] = $this->view_mode;
        if (isset($this->edit_mode)) $p["edit_mode"] = $this->edit_mode;
        if (isset($this->tab))       $p["tab"] = $this->tab;
        return parent::saveParams($p);
    }

    /**
    * Get module parameters
    */
    function getParams() {
        global $id, $mode, $view_mode, $edit_mode, $tab;
        if (isset($id))        $this->id = $id;
        if (isset($mode))      $this->mode = $mode;
        if (isset($view_mode)) $this->view_mode = $view_mode;
        if (isset($edit_mode)) $this->edit_mode = $edit_mode;
        if (isset($tab))       $this->tab = $tab;
    }

    /**
    * Main entry point
    */
    function run() {
        global $session;
        $out = array();

        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }

        if (isset($this->owner->action)) $out['PARENT_ACTION'] = $this->owner->action;
        if (isset($this->owner->name))   $out['PARENT_NAME']   = $this->owner->name;

        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE']      = $this->mode;
        $out['ACTION']    = $this->action;
        $this->data       = $out;

        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
    * Debug helper
    */
    function debug($content) {
        if (!empty($this->config['DEBUG'])) {
            $this->log(print_r($content, true));
        }
    }

    /**
    * Log to MajorDoMo system log
    */
    function log($message) {
        DebMes($message, 'google_location');
    }

    /**
    * Admin panel logic
    */
    function admin(&$out) {
        $this->getConfig();
        $directory_cookies = ROOT . "cms/cached/google_location/";
        if (!file_exists($directory_cookies)) {
            mkdir($directory_cookies, 0777, true);
        }

        // Save module settings
        if ($this->view_mode == 'update_settings') {
            global $timeout_update, $min_limit_speed, $max_limit_speed, $google_api_key, $debug;

            $this->config['TIMEOUT_UPDATE']  = (int)$timeout_update;
            $this->config['MIN_LIMIT_SPEED']  = (float)$min_limit_speed;
            $this->config['MAX_LIMIT_SPEED']  = (float)$max_limit_speed;
            $this->config['GOOGLE_API_KEY']   = trim($google_api_key);
            $this->config['DEBUG']            = (int)$debug;

            if ($this->config['TIMEOUT_UPDATE'] <= 0) $this->config['TIMEOUT_UPDATE'] = 1;
            if ($this->config['MIN_LIMIT_SPEED'] < 0) $this->config['MIN_LIMIT_SPEED'] = 0;
            if ($this->config['MAX_LIMIT_SPEED'] <= 0) $this->config['MAX_LIMIT_SPEED'] = 200;

            $this->saveConfig();
            $this->redirect("?");
            return;
        }

        $out['TIMEOUT_UPDATE']  = isset($this->config['TIMEOUT_UPDATE']) ? $this->config['TIMEOUT_UPDATE'] : 1;
        $out['LAST_UPDATE']     = isset($this->config['LAST_UPDATE']) ? $this->config['LAST_UPDATE'] : '';
        $out['MIN_LIMIT_SPEED'] = isset($this->config['MIN_LIMIT_SPEED']) ? $this->config['MIN_LIMIT_SPEED'] : 0;
        $out['MAX_LIMIT_SPEED'] = isset($this->config['MAX_LIMIT_SPEED']) ? $this->config['MAX_LIMIT_SPEED'] : 200;
        $out['GOOGLE_API_KEY']  = isset($this->config['GOOGLE_API_KEY']) ? $this->config['GOOGLE_API_KEY'] : '';
        $out['DEBUG']           = isset($this->config['DEBUG']) ? $this->config['DEBUG'] : 0;

        // View last debug response dump
        if ($this->view_mode == 'view_debug_log') {
            $dump_file = $directory_cookies . 'last_response_dump.txt';
            if (file_exists($dump_file)) {
                $out['DEBUG_RESPONSE_DUMP'] = htmlspecialchars(file_get_contents($dump_file));
            } else {
                $out['DEBUG_RESPONSE_DUMP'] = 'Немає збереженої відповіді. Увімкніть Debug у налаштуваннях та натисніть "Оновити зараз".';
            }
        }

        // Edit user
        if ($this->view_mode == 'user_edit') {
            $this->edit_user($out, $this->id);
            return;
        }

        // Delete user
        if ($this->view_mode == 'user_delete') {
            $this->delete_user($this->id);
            $this->redirect("?");
            return;
        }

        // Upload cookie file
        if ($this->view_mode == 'upload_cookie') {
            global $file, $file_name;
            if (!empty($file) && !empty($file_name)) {
                $clean_name = basename($file_name);
                $target = $directory_cookies . $clean_name;
                copy($file, $target);
                chmod($target, 0666);

                // Remove stale session cache for this file if present
                $stale_session = $target . '.session';
                if (file_exists($stale_session)) {
                    unlink($stale_session);
                }

                // Immediately trigger sync
                $this->updateLocation();
            }
            $this->redirect("?");
            return;
        }

        // Delete cookie file
        if ($this->view_mode == 'delete_cookie') {
            global $name;
            $clean_name = basename($name);
            $file_to_del = $directory_cookies . $clean_name;
            if (file_exists($file_to_del)) {
                unlink($file_to_del);
            }
            if (file_exists($file_to_del . '.session')) {
                unlink($file_to_del . '.session');
            }
            $this->redirect("?");
            return;
        }

        // Manual trigger location update
        if ($this->view_mode == 'update_location') {
            $this->updateLocation();
            $this->redirect("?");
            return;
        }

        // Switch send to GPS Tracker
        if ($this->view_mode == 'send_switch') {
            global $id;
            $rec = SQLSelectOne("SELECT * FROM google_locations WHERE ID_USER='" . DBSafe($id) . "'");
            if (!empty($rec['ID'])) {
                $rec['SENDTOGPS'] = ($rec['SENDTOGPS'] == 1) ? 0 : 1;
                SQLUpdate('google_locations', $rec);
            }
            $this->redirect("?");
            return;
        }

        // Retrieve location records
        $locations = SQLSelect("SELECT * FROM google_locations ORDER BY NAME ASC");
        $total_locs = count($locations);
        for ($i = 0; $i < $total_locs; $i++) {
            if (!empty($locations[$i]["LASTUPDATE"])) {
                if (time() - strtotime($locations[$i]["LASTUPDATE"]) > 3600) {
                    $locations[$i]['WARNING'] = '1';
                }
            } else {
                $locations[$i]['WARNING'] = '1';
            }
        }
        $out['LOCATIONS'] = $locations;

        // List cookie files (ignoring internal session cache files)
        $cookies_files = array();
        if ($handle = opendir($directory_cookies)) {
            while (false !== ($cfile = readdir($handle))) {
                if ($cfile != "." && $cfile != ".." && !is_dir($directory_cookies . $cfile)) {
                    if (substr($cfile, -8) === '.session' || substr($cfile, -4) === '.bak' || substr($cfile, 0, 5) === 'last_') {
                        continue;
                    }
                    $cpath = $directory_cookies . $cfile;
                    $error_key = 'ERROR_' . $cfile;
                    $err_text = isset($this->config[$error_key]) ? $this->config[$error_key] : '';
                    $cookies_files[] = array(
                        "NAME"  => $cfile,
                        "DATE"  => date("Y-m-d H:i:s", filemtime($cpath)),
                        "SIZE"  => $this->sizeFilter(filesize($cpath)),
                        "ERROR" => $err_text
                    );
                }
            }
            closedir($handle);
        }
        if (!empty($cookies_files)) {
            $out['COOKIES_FILES'] = $cookies_files;
        }
    }

    /**
    * Format bytes helper
    */
    public function sizeFilter($bytes) {
        $label = array('B', 'KB', 'MB', 'GB');
        for ($i = 0; $bytes >= 1024 && $i < (count($label) - 1); $bytes /= 1024, $i++);
        return (round($bytes, 2) . " " . $label[$i]);
    }

    /**
    * Edit user details
    */
    function edit_user(&$out, $id) {
        require(DIR_MODULES . $this->name . '/user_edit.inc.php');
    }

    /**
    * Delete user from local tracking table
    */
    function delete_user($id) {
        $rec = SQLSelectOne("SELECT * FROM google_locations WHERE ID='" . (int)$id . "'");
        if (!empty($rec['ID'])) {
            SQLExec("DELETE FROM google_locations WHERE ID='" . $rec['ID'] . "'");
        }
    }

    /**
    * Front-end widget & API calls
    */
    function usual(&$out) {
        global $ajax, $op;

        if ($ajax) {
            if (!headers_sent()) {
                header("HTTP/1.0: 200 OK\n");
                header('Content-Type: application/json; charset=utf-8');
            }

            if ($op == 'getlocations') {
                $data = array('LOCATIONS' => array());
                $markers = SQLSelect("SELECT * FROM google_locations");
                foreach ($markers as $m) {
                    $html = "<b>" . htmlspecialchars($m['FULLNAME']) . "</b><br/>";
                    $html .= (!empty($m['ADDRESS']) ? htmlspecialchars($m['ADDRESS']) . "<br/>" : "");
                    $html .= "Оновлено: " . $m['LASTUPDATE'] . "<br/>";
                    $html .= "Батарея: " . ($m['CHARGING'] == 1 ? "⚡ " : "") . $m['BATTLEVEL'] . "%";
                    $m['HTML'] = $html;
                    $data['LOCATIONS'][] = $m;
                }
                echo json_encode($data);
                exit;
            }
        }
    }

    /**
    * Periodic timer subscription (MINUTELY)
    */
    function processSubscription($event, $details = '') {
        $this->getConfig();
        if ($event == 'MINUTELY') {
            $timeout = isset($this->config['TIMEOUT_UPDATE']) ? (int)$this->config['TIMEOUT_UPDATE'] : 1;
            if ($timeout <= 0) return;
            $minute = (int)date('i');
            if ($minute % $timeout == 0) {
                $this->updateLocation();
            }
        }
    }

    /**
    * Core location sync logic
    */
    public function updateLocation() {
        $locations = array();
        $directory_cookies = ROOT . "cms/cached/google_location/";
        if (!file_exists($directory_cookies)) {
            $directory_cookies = ROOT . "cms/files/google_location/";
        }

        if (file_exists($directory_cookies)) {
            $files = array_diff(scandir($directory_cookies), array('..', '.'));
            foreach ($files as $cookie_file) {
                $full_path = $directory_cookies . $cookie_file;
                if (!is_dir($full_path)) {
                    // Ignore session caches and dumps
                    if (substr($cookie_file, -8) === '.session' || substr($cookie_file, -4) === '.bak' || substr($cookie_file, 0, 5) === 'last_') {
                        continue;
                    }
                    $parsed = $this->getLocation($full_path);
                    if (is_array($parsed) && count($parsed) > 0) {
                        $locations = array_merge($locations, $parsed);
                    }
                }
            }
        }

        foreach ($locations as $location) {
            if (empty($location['id'])) continue;

            $rec = SQLSelectOne("SELECT * FROM google_locations WHERE ID_USER='" . DBSafe($location["id"]) . "'");

            // Skip faulty 0,0 coordinates
            if ($location['lat'] == 0 && $location['lon'] == 0) {
                continue;
            }

            // Reverse Geocoding via Google Maps API if address is empty and key is provided
            if (empty($location['address']) && !empty($this->config['GOOGLE_API_KEY'])) {
                $location['address'] = $this->reverseGeocodeGoogle($location['lat'], $location['lon']);
            }

            // Existing user handling
            if (!empty($rec['ID'])) {
                $location_ts = (int)($location['timestamp'] / 1000);
                $new_lastupdate = date('Y-m-d H:i:s', $location_ts);

                if ($rec['LASTUPDATE'] != $new_lastupdate && $rec["SENDTOGPS"] == 1) {
                    $location['speed'] = $this->getSpeed($rec, $location);

                    if ($this->config['MIN_LIMIT_SPEED'] > $location['speed']) {
                        $location['speed'] = 0;
                    }

                    $delta_speed = abs($location['speed'] - (float)$rec['SPEED']);
                    if ($this->config['MAX_LIMIT_SPEED'] >= $delta_speed) {
                        $this->sendToGps($location);
                    }
                    $rec['SPEED'] = $location['speed'];
                }

                $rec['LASTUPDATE'] = $new_lastupdate;
                if (!empty($location['address'])) $rec['ADDRESS'] = $location['address'];
                $rec['LAT'] = $location['lat'];
                $rec['LON'] = $location['lon'];
                $rec['ACCURACY'] = $location['accuracy'];
                if ($location['battery'] !== null) {
                    $rec['BATTLEVEL'] = (int)$location['battery'];
                }
                $rec['CHARGING'] = (int)$location['charging'];

                SQLUpdate('google_locations', $rec);

                // Update linked MajorDoMo Object if defined
                $this->updateLinkedObject($rec);

            } else {
                // New user discovered
                $rec = array();
                $rec['ID_USER'] = $location['id'];
                $rec['NAME'] = !empty($location['name']) ? $location['name'] : 'User ' . substr($location['id'], 0, 6);
                $rec['FULLNAME'] = !empty($location['fullname']) ? $location['fullname'] : $rec['NAME'];
                $rec['IMAGE'] = !empty($location['image']) ? $location['image'] : '';
                $rec['LASTUPDATE'] = date('Y-m-d H:i:s', (int)($location['timestamp'] / 1000));
                $rec['ADDRESS'] = !empty($location['address']) ? $location['address'] : '';
                $rec['LAT'] = $location['lat'];
                $rec['LON'] = $location['lon'];
                $rec['ACCURACY'] = $location['accuracy'];
                $rec['SPEED'] = 0;
                $rec['BATTLEVEL'] = (int)$location['battery'];
                $rec['CHARGING'] = (int)$location['charging'];
                $rec['SENDTOGPS'] = 1; // Auto forward to app_gpstrack
                $rec['LINKED_OBJECT'] = '';

                $rec['ID'] = SQLInsert('google_locations', $rec);

                if (method_exists($this, 'sendnotification')) {
                    $this->sendnotification('Додано нового користувача Google Location: ' . $rec['FULLNAME'], 'info');
                }

                $this->sendToGps($location);
            }
        }

        $this->config['LAST_UPDATE'] = date('Y-m-d H:i:s');
        $this->saveConfig();
    }

    /**
    * Update linked MajorDoMo Object properties
    */
    public function updateLinkedObject($rec) {
        if (empty($rec['LINKED_OBJECT'])) return;

        $object_name = trim($rec['LINKED_OBJECT']);
        if (!objectExists($object_name)) return;

        setGlobal($object_name . '.Coordinates', $rec['LAT'] . ',' . $rec['LON']);
        setGlobal($object_name . '.Latitude', $rec['LAT']);
        setGlobal($object_name . '.Longitude', $rec['LON']);
        setGlobal($object_name . '.Accuracy', $rec['ACCURACY']);
        setGlobal($object_name . '.Speed', $rec['SPEED']);
        setGlobal($object_name . '.BatteryLevel', $rec['BATTLEVEL']);
        setGlobal($object_name . '.Charging', $rec['CHARGING']);
        setGlobal($object_name . '.Address', $rec['ADDRESS']);
        setGlobal($object_name . '.UpdatedTimestamp', time());
        setGlobal($object_name . '.UpdatedTime', $rec['LASTUPDATE']);

        // Execute object method if defined
        if (method_exists($this, 'callMethodSafe')) {
            $this->callMethodSafe($object_name . '.onLocationUpdated');
        } elseif (function_exists('callMethodSafe')) {
            callMethodSafe($object_name . '.onLocationUpdated');
        }
    }

    /**
    * Forward location point to MajorDoMo GPS Tracker (app_gpstrack)
    */
    public function sendToGps($location) {
        $params = array(
            'latitude'  => $location['lat'],
            'longitude' => $location['lon'],
            'deviceid'  => $location['id'],
            'provider'  => 'google_location',
            'accuracy'  => $location['accuracy'],
            'speed'     => isset($location['speed']) ? $location['speed'] : 0,
        );

        if (!empty($location['address'])) {
            $params['address'] = $location['address'];
        }

        if (isset($location['battery']) && $location['battery'] > 0) {
            $params['battlevel'] = $location['battery'];
            $params['charging']  = $location['charging'];
        }

        $req = BASE_URL . "/gps.php?" . http_build_query($params);
        $this->debug("Sending to gps.php: " . $req);

        if (function_exists('getURLBackground')) {
            getURLBackground($req, 0);
        } else {
            getURL($req, 0);
        }
    }

    /**
    * Calculate speed in km/h between two points
    */
    public function getSpeed($last, $new) {
        $time_last = strtotime($last['LASTUPDATE']);
        $time_new  = (int)($new['timestamp'] / 1000);
        $dist      = $this->calculateTheDistance($last['LAT'], $last['LON'], $new['lat'], $new['lon']);
        $diff      = $time_new - $time_last;

        if ($diff <= 0) return 0;
        return round(($dist / $diff) * 3.6, 2);
    }

    /**
    * Calculate distance between two coordinates in meters
    */
    function calculateTheDistance($latA, $lonA, $latB, $lonB) {
        $earth_radius = 6372795;

        $lat1  = $latA * M_PI / 180;
        $lat2  = $latB * M_PI / 180;
        $long1 = $lonA * M_PI / 180;
        $long2 = $lonB * M_PI / 180;

        $cl1 = cos($lat1);
        $cl2 = cos($lat2);
        $sl1 = sin($lat1);
        $sl2 = sin($lat2);

        $delta  = $long2 - $long1;
        $cdelta = cos($delta);
        $sdelta = sin($delta);

        $y = sqrt(pow($cl2 * $sdelta, 2) + pow($cl1 * $sl2 - $sl1 * $cl2 * $cdelta, 2));
        $x = $sl1 * $sl2 + $cl1 * $cl2 * $cdelta;

        $ad = atan2($y, $x);
        return round($ad * $earth_radius);
    }

    /**
    * Parse Google Location RPC response
    */
    public function getLocation($cookie_file) {
        $path_parts = pathinfo($cookie_file);
        // Skip session cache files
        if (substr($path_parts['basename'], -8) === '.session' || substr($path_parts['basename'], -4) === '.bak' || substr($path_parts['basename'], 0, 5) === 'last_') {
            return array();
        }

        try {
            $result = $this->getLocationData($cookie_file);
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            $this->log("Помилка обробки " . $path_parts['basename'] . ": " . $error_msg);
            $this->config['ERROR_' . $path_parts['basename']] = $error_msg;
            $this->saveConfig();
            return array();
        }

        $this->config['ERROR_' . $path_parts['basename']] = '';
        $this->saveConfig();
        $return = array();

        // Account owner coordinates (Index 9)
        if (isset($result[9]) && is_array($result[9])) {
            $owner = $result[9];
            $return[] = array(
                'id'        => 'owner_' . md5($cookie_file),
                'name'      => $path_parts['filename'],
                'fullname'  => $path_parts['filename'] . ' (Власник)',
                'image'     => '',
                'address'   => isset($owner[1][4]) ? $owner[1][4] : '',
                'timestamp' => isset($owner[1][2]) ? $owner[1][2] : (time() * 1000),
                'lat'       => isset($owner[1][1][2]) ? $owner[1][1][2] : 0,
                'lon'       => isset($owner[1][1][1]) ? $owner[1][1][1] : 0,
                'accuracy'  => isset($owner[1][3]) ? $owner[1][3] : 0,
                'battery'   => 0,
                'charging'  => 0,
            );
        }

        // Shared users (Index 0: Family Link members, friends, other shared devices)
        if (isset($result[0]) && is_array($result[0])) {
            foreach ($result[0] as $user) {
                if (!isset($user[6][0])) continue;

                $user_id   = $user[6][0];
                $name      = isset($user[6][3]) ? $user[6][3] : (isset($user[6][2]) ? $user[6][2] : 'User');
                $fullname  = isset($user[6][2]) ? $user[6][2] : $name;
                $image     = isset($user[0][1]) ? $user[0][1] : '';
                $address   = isset($user[1][4]) ? $user[1][4] : '';
                $timestamp = isset($user[1][2]) ? $user[1][2] : (time() * 1000);
                $lat       = isset($user[1][1][2]) ? $user[1][1][2] : 0;
                $lon       = isset($user[1][1][1]) ? $user[1][1][1] : 0;
                $accuracy  = isset($user[1][3]) ? $user[1][3] : 0;

                // Battery and Charging status (Family Link / Location Sharing)
                $battery  = 0;
                $charging = 0;
                if (isset($user[13]) && is_array($user[13])) {
                    $charging = isset($user[13][0]) ? (int)$user[13][0] : 0;
                    $battery  = isset($user[13][1]) ? (int)$user[13][1] : 0;
                }

                $return[] = array(
                    'id'        => $user_id,
                    'name'      => $name,
                    'fullname'  => $fullname,
                    'image'     => $image,
                    'address'   => $address,
                    'timestamp' => $timestamp,
                    'lat'       => $lat,
                    'lon'       => $lon,
                    'accuracy'  => $accuracy,
                    'battery'   => $battery,
                    'charging'  => $charging,
                );
            }
        }

        $this->debug($return);
        return $return;
    }

    /**
    * Perform Google Maps RPC request with modern headers and session handling
    */
    public function getLocationData($cookie_file) {
        $lang = defined('SETTINGS_SITE_LANGUAGE') && SETTINGS_SITE_LANGUAGE ? SETTINGS_SITE_LANGUAGE : 'uk';
        $url = 'https://www.google.com/maps/rpc/locationsharing/read?authuser=0&hl=' . $lang . '&gl=' . $lang . '&pb=';

        $raw_content = file_get_contents($cookie_file);
        if (empty(trim($raw_content))) {
            throw new Exception("Файл cookie порожній. Будь ласка, завантажте валідний файл cookies.txt.");
        }

        // Normalize Netscape HttpOnly prefixes if present
        $clean_cookie_content = preg_replace('/^#HttpOnly_/m', '', $raw_content);

        // Separate working session file so the master user file is NEVER corrupted or overwritten
        $session_cookie_file = $cookie_file . '.session';
        if (!file_exists($session_cookie_file) || filesize($session_cookie_file) < 50) {
            file_put_contents($session_cookie_file, $clean_cookie_content);
            chmod($session_cookie_file, 0666);
        }

        $ch = curl_init($url);

        $headers = array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'Accept: */*',
            'Accept-Language: uk-UA,uk;q=0.9,en-US;q=0.8,en;q=0.7',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Dest: empty',
            'X-Same-Domain: 1',
            'Referer: https://www.google.com/maps',
            'Origin: https://www.google.com',
        );

        $first_char = trim($clean_cookie_content);
        $first_char = !empty($first_char) ? $first_char[0] : '';

        if ($first_char == '#' || strpos($clean_cookie_content, "\t") !== false) {
            // Netscape cookies format
            curl_setopt($ch, CURLOPT_COOKIEFILE, $session_cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $session_cookie_file);
        } else {
            // Raw header cookies string format
            $headers[] = 'Cookie: ' . trim($raw_content);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $session_cookie_file);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $cached_dir = ROOT . "cms/cached/google_location/";
        if (!file_exists($cached_dir)) {
            mkdir($cached_dir, 0777, true);
        }

        if (empty($info['http_code'])) {
            throw new Exception("Не вдалося підключитися до Google Maps (порожня HTTP відповідь, перевірте зв'язок/DNS).");
        }

        $body = substr($response, $info['header_size']);
        $trimmed_body = trim($body);

        // Save debug response dump for diagnostics
        if (!empty($this->config['DEBUG'])) {
            file_put_contents($cached_dir . 'last_response_dump.txt', "HTTP Code: " . $info['http_code'] . "\n\n" . substr($response, 0, 4000));
        }

        // Detect HTML redirect / Auth expiration
        if (stripos($trimmed_body, '<html') !== false || stripos($trimmed_body, '<!doctype') !== false || stripos($trimmed_body, 'accounts.google.com') !== false) {
            // Reset cached session file to re-try from master on next run
            if (file_exists($session_cookie_file)) {
                unlink($session_cookie_file);
            }

            preg_match('/<title>(.*?)<\/title>/is', $trimmed_body, $matches);
            $page_title = !empty($matches[1]) ? trim($matches[1]) : 'Сторінка авторизації';

            throw new Exception("Сесія Google застаріла! Google повернув сторінку входу ('{$page_title}') замість координат. Токени (__Secure-1PSIDTS) закінчилися або акаунт вийшов із системи. Оновіть cookies.txt!");
        }

        if ($info['http_code'] != 200) {
            throw new Exception('Google Maps HTTP Error ' . $info['http_code'] . '. Сесію відхилено сервером.');
        }

        // Google returns XSSI protection prefix ")]}'\n"
        $clean_json = preg_replace('/^\)\]\}\'\s*/', '', $trimmed_body);

        if (empty($clean_json)) {
            throw new Exception("Отримано порожнє тіло відповіді від Google Maps.");
        }

        $result = json_decode($clean_json, true);

        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            $err_prefix = substr($clean_json, 0, 120);
            throw new Exception("Помилка структури JSON (" . json_last_error_msg() . "): " . $err_prefix);
        }

        if (!is_array($result)) {
            throw new Exception("Відповідь Google не є валідним масивом: " . substr($clean_json, 0, 100));
        }

        if (!isset($result[0]) && !isset($result[9])) {
            $available_keys = implode(', ', array_keys($result));
            // Save payload to examine structure
            file_put_contents($cached_dir . 'last_unexpected_json.txt', $clean_json);
            throw new Exception("Google не повернув список локацій (наявні ключі: [{$available_keys}]). Можливо, для цього акаунта ще ніхто не передав геопозицію або Google змінив структуру.");
        }

        return $result;
    }

    /**
    * Optional Reverse Geocoding via official Google Maps Platform API
    */
    public function reverseGeocodeGoogle($lat, $lon) {
        $key = trim($this->config['GOOGLE_API_KEY']);
        if (empty($key)) return '';

        $lang = defined('SETTINGS_SITE_LANGUAGE') && SETTINGS_SITE_LANGUAGE ? SETTINGS_SITE_LANGUAGE : 'uk';
        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lon}&key={$key}&language={$lang}";

        $res = getURL($url, 15);
        if ($res) {
            $data = json_decode($res, true);
            if (isset($data['status']) && $data['status'] == 'OK' && !empty($data['results'][0]['formatted_address'])) {
                return $data['results'][0]['formatted_address'];
            }
        }
        return '';
    }

    /**
    * Module installation routine
    */
    function install($data = '') {
        subscribeToEvent($this->name, 'MINUTELY');
        parent::install();
    }

    /**
    * Module uninstall routine
    */
    function uninstall() {
        unsubscribeFromEvent($this->name, 'MINUTELY');
        SQLExec('DROP TABLE IF EXISTS google_locations');
        parent::uninstall();
    }

    /**
    * Database installation routine
    */
    function dbInstall($data) {
        $data = <<<EOD
 google_locations: ID int(10) unsigned NOT NULL auto_increment
 google_locations: ID_USER varchar(255) NOT NULL DEFAULT ''
 google_locations: LINKED_OBJECT varchar(255) NOT NULL DEFAULT ''
 google_locations: NAME varchar(255) NOT NULL DEFAULT ''
 google_locations: FULLNAME varchar(255) NOT NULL DEFAULT ''
 google_locations: IMAGE varchar(255) NOT NULL DEFAULT ''
 google_locations: LASTUPDATE datetime
 google_locations: ADDRESS varchar(512) NOT NULL DEFAULT ''
 google_locations: LAT float DEFAULT '0' NOT NULL
 google_locations: LON float DEFAULT '0' NOT NULL
 google_locations: ACCURACY float DEFAULT '0' NOT NULL
 google_locations: SPEED float DEFAULT '0' NOT NULL
 google_locations: BATTLEVEL int(3) NOT NULL DEFAULT '0'
 google_locations: CHARGING int(3) NOT NULL DEFAULT '0'
 google_locations: SENDTOGPS int(3) NOT NULL DEFAULT '1'

EOD;
        parent::dbInstall($data);
    }
}
