<?php
/**
* Ukrainian language file for Google Location module
* Author: mambursoft
*/

$dictionary = array(
    /* Загальні */
    "GL_TITLE"              => "Google Локація",
    "GL_ABOUT"              => "Про модуль",
    "GL_COOKIES"            => "Файли Cookie (Сесії Google)",
    "GL_COOKIE_HELP"        => "Як отримати файл cookie",
    "GL_SETTING_TIMEOUT"    => "Інтервал оновлення (хв)",
    "GL_LIMIT_SPEED_MIN"    => "Мінімальний поріг швидкості (км/год)",
    "GL_LIMIT_SPEED_MAX"    => "Максимальна дельта швидкості (фільтр викидів)",
    "GL_GOOGLE_API_KEY"     => "Google Maps API Key (опціонально для геокодування)",
    "GL_DEBUG"              => "Детальне логування (Debug)",
    "GL_SENDTOGPS"          => "Передавати в GPS Tracker (app_gpstrack)",
    "GL_FULLNAME"           => "Повне ім'я",
    "GL_LINKED_OBJECT"      => "Прив'язаний об'єкт (наприклад, Users.Son)",
    "GL_BATTERY"            => "Заряд батареї",
    "GL_CHARGING"           => "Заряджається",
    "GL_ACCURACY"           => "Точність (м)",
    "GL_LAST_SEEN"          => "Останнє оновлення",
    "GL_VIEW_MAP"           => "Показати на карті",
    "GL_NO_USERS_FOUND"     => "Користувачів із передачею геоданих ще не знайдено. Перевірте Google акаунт.",
    "GL_SESSION_EXPIRED"    => "Сесія Google застаріла або cookie недійсні!",
    "GL_UPDATED_SUCCESS"    => "Дані геолокації успішно оновлено.",
    "GL_MANUAL_UPDATE"      => "Оновити зараз",
    "GL_SELECT_COOKIE_FILE" => "Оберіть файл cookies.txt",
    "GL_STATUS_OK"          => "Активний",
    "GL_STATUS_WARNING"     => "Немає оновлень понад годину",
    "GL_STATUS_ERROR"       => "Помилка авторизації",
);

foreach ($dictionary as $k => $v) {
    if (!defined('LANG_' . $k)) {
        define('LANG_' . $k, $v);
    }
}
