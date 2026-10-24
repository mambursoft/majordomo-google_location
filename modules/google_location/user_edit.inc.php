<?php
/**
* User edit handler for Google Location
* Author: mambursoft
*/

$table_name = 'google_locations';
$rec = SQLSelectOne("SELECT * FROM $table_name WHERE ID='" . (int)$id . "'");

if ($this->mode == 'update') {
    $ok = 1;

    global $name, $fullname, $linked_object, $sendtogps;
    $rec['NAME']          = trim($name);
    $rec['FULLNAME']      = trim($fullname);
    $rec['LINKED_OBJECT'] = trim($linked_object);
    $rec['SENDTOGPS']     = (int)$sendtogps;

    global $file;
    if (!empty($file)) {
        $dir = ROOT . "cms/cached/google_location_img/";
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $file_avatar = $dir . $rec['ID_USER'] . ".png";
        copy($file, $file_avatar);
        $rec['IMAGE'] = "/cms/cached/google_location_img/" . $rec['ID_USER'] . ".png";
    }

    if ($rec['ID']) {
        SQLUpdate($table_name, $rec);
        $out['OK'] = 1;
    } else {
        $out['ERR'] = 1;
    }
}

// Fetch list of objects of class 'Users' for quick dropdown selection
$users_class_id = (int)current(SQLSelectOne("SELECT ID FROM classes WHERE TITLE='Users'"));
if ($users_class_id) {
    $user_objects = SQLSelect("SELECT ID, TITLE FROM objects WHERE CLASS_ID='$users_class_id' ORDER BY TITLE ASC");
    $out['USER_OBJECTS'] = $user_objects;
}

outHash($rec, $out);
