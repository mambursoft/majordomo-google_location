<?php
/**
* Russian language file for Google Location module
* Author: mambursoft
*/

$dictionary = array(
    "GL_TITLE"              => "Google Локация",
    "GL_ABOUT"              => "О модуле",
    "GL_COOKIES"            => "Файлы Cookie (Сессии Google)",
    "GL_COOKIE_HELP"        => "Как получить файл cookie",
    "GL_SETTING_TIMEOUT"    => "Интервал обновления (мин)",
    "GL_LIMIT_SPEED_MIN"    => "Минимальный порог скорости (км/ч)",
    "GL_LIMIT_SPEED_MAX"    => "Максимальная дельта скорости (фильтр выбросов)",
    "GL_GOOGLE_API_KEY"     => "Google Maps API Key (опционально для геокодирования)",
    "GL_DEBUG"              => "Подробный лог (Debug)",
    "GL_SENDTOGPS"          => "Передавать в GPS Tracker (app_gpstrack)",
    "GL_FULLNAME"           => "Полное имя",
    "GL_LINKED_OBJECT"      => "Связанный объект (например, Users.Son)",
    "GL_BATTERY"            => "Заряд батареи",
    "GL_CHARGING"           => "Заряжается",
    "GL_ACCURACY"           => "Точность (м)",
    "GL_LAST_SEEN"          => "Последнее обновление",
    "GL_VIEW_MAP"           => "Показать на карте",
    "GL_NO_USERS_FOUND"     => "Пользователей с передачей геоданных пока нет.",
    "GL_SESSION_EXPIRED"    => "Сессия Google устарела или cookie недействительны!",
    "GL_UPDATED_SUCCESS"    => "Данные геолокации успешно обновлены.",
    "GL_MANUAL_UPDATE"      => "Обновить сейчас",
    "GL_SELECT_COOKIE_FILE" => "Выберите файл cookies.txt",
    "GL_STATUS_OK"          => "Активен",
    "GL_STATUS_WARNING"     => "Нет обновлений более часа",
    "GL_STATUS_ERROR"       => "Ошибка авторизации",
);

foreach ($dictionary as $k => $v) {
    if (!defined('LANG_' . $k)) {
        define('LANG_' . $k, $v);
    }
}
