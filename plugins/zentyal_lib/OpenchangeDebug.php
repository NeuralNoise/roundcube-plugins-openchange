<?php
    require_once(dirname(__FILE__) . '/OpenchangeConfig.php');

    /**
     * Global debuging for all the Zentyal Openchange plugins
     *
     * - Share debug file
     * - Debugging helpers functions
     * - If it can't open the configured debug file, it will output to:
     *      /tmp/roundcube-openchange
     *
     * @author  Miguel Julian <mjulian@zentyal.com>
     */

class OpenchangeDebug
{

    private $handle;

    function __construct() {
        if (OpenchangeConfig::$debugEnabled) {
            $this->handle = fopen(OpenchangeConfig::$logLocation, 'a');

            if ($this->handle === false) {
                $this->handle = fopen("/tmp/roundcube-openchange", 'a');
                $this->writeMessage("Can't open " . OpenchangeConfig::$logLocation . "file", 0, "ERROR");
            }
        }
    }

    function __destruct() {
        if (OpenchangeConfig::$debugEnabled && $this->handle) {
            fclose($this->handle);
        }
    }

    /**
     * Writes a message in a line
     *
     * Writes the given message in the debuging file. It will also facilitate the
     * message indentation introducing some spaces at the beggining of the line.
     * You can also add a keyword so you could improve your log viewing with ccze
     * or similar tools.
     *
     * @param   string  $message: The message that will be written to the log file
     * @param   int     $indentantion : (4spaces) * indentantion will be prefixed
     * @param   string  $keyword: The prefix keyword of the message
     *
     */

    public function writeMessage($message, $indentation=0, $keyword="") {
        if (OpenchangeConfig::$debugEnabled && $this->handle) {
            $string = "";
            while ($indentation > 0) {
                $string .= "    ";
                $indentation--;
            }

            if ($keyword)
                $string .= "[" . $keyword . "]: ";

            $string .= $message;

            $string .= "\n";
            fwrite($this->handle, $string);
        }
    }

    /**
     * Dump a variable content into the log file
     *
     * It will also add some header information (even with a custom message)
     *
     * @param   string  $var: The variable to dump
     * @param   string  $message : A custom message to be added at the beginning
     *
     */

    public function dumpVariable($var, $message="") {
        ob_start();
        var_dump($var);
        $string = "[VARIABLE DUMP START]\n";
        if ($message) $string .= $message . "\n";
        $string .= ob_get_clean();
        $string .= "[VARIABLE DUMP END]\n";
        $this->writeMessage($string);
    }

    // The date output format
    private $dateFormat = "Y-m-d H:i:s - T";

    /**
     *  Convert a timestamp into a DateTime and returns it
     *
     *  @param   integer $timestamp: The unix timestamp
     *
     */
    public function getStringFromTimestamp($timestamp)
    {
        $date = new DateTime();
        $date->setTimestamp($timestamp);

        return $date->format($this->dateFormat);
    }

    /**
     *  Returns the string of the given DateTime object using $dateFormat
     *
     * @param   DateTime $date: The date we want to convert to string
     *
     */
    public function getFormatedDateString($date)
    {
        return $date->format($this->dateFormat);
    }
}
?>
