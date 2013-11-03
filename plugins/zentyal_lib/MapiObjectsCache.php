<?php
    require_once(dirname(__FILE__) . '/OpenchangeConfig.php');
    require_once(dirname(__FILE__) . '/OpenchangeDebug.php');

class MapiObjectsCache
{
    private static $cache = array();

    public static function add($username, $mapiSessionObjects) {
        self::$cache[$username] = $mapiSessionObjects;
    }

    public static function get($username) {
        return isset(self::$cache[$username]) ? self::$cache[$username] : false;
    }

    public static function cacheDump() {
        $dumpString = "\nThe cache content is:\n";

        foreach(self::$cache as $key => $value) {
            $string .= "\tWe have the objects associated to the user: " . $key . "\n";
        }

        return $string;
    }
}
?>
