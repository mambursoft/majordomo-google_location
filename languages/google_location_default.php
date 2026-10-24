<?php
/**
* Default (English) language file for Google Location module
* Author: mambursoft
*/

$dictionary = array(
    /* General */
    "GL_TITLE"              => "Google Location",
    "GL_ABOUT"              => "About",
    "GL_COOKIES"            => "Cookie files",
    "GL_COOKIE_HELP"        => "How to get cookie file",
    "GL_SETTING_TIMEOUT"    => "Update interval (min)",
    "GL_LIMIT_SPEED_MIN"    => "Min speed threshold (km/h)",
    "GL_LIMIT_SPEED_MAX"    => "Max speed delta filter (km/h)",
    "GL_GOOGLE_API_KEY"     => "Google Maps API Key (optional for Geocoding)",
    "GL_DEBUG"              => "Debug logging",
    "GL_SENDTOGPS"          => "Forward to GPS Tracker (app_gpstrack)",
    "GL_FULLNAME"           => "Full name",
    "GL_LINKED_OBJECT"      => "Linked Object (e.g. Users.Son)",
    "GL_BATTERY"            => "Battery",
    "GL_CHARGING"           => "Charging",
    "GL_ACCURACY"           => "Accuracy (m)",
    "GL_LAST_SEEN"          => "Last updated",
    "GL_VIEW_MAP"           => "View on map",
    "GL_NO_USERS_FOUND"     => "No shared locations found yet. Check your Google account settings.",
    "GL_SESSION_EXPIRED"    => "Google session expired or cookies are invalid!",
    "GL_UPDATED_SUCCESS"    => "Location data updated successfully.",
    "GL_MANUAL_UPDATE"      => "Update now",
    "GL_SELECT_COOKIE_FILE" => "Select cookies.txt or session file",
    "GL_STATUS_OK"          => "Active",
    "GL_STATUS_WARNING"     => "No updates for over an hour",
    "GL_STATUS_ERROR"       => "Authentication error",
);

foreach ($dictionary as $k => $v) {
    if (!defined('LANG_' . $k)) {
        define('LANG_' . $k, $v);
    }
}
