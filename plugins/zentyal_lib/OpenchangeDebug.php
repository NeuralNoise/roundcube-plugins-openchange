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
     * @param   int     $identantion : (4spaces) * indentantion will be prefixed
     * @param   string  $keyword: The prefix keyword of the message
     *
     */

    public function writeMessage($message, $indentation=0, $keyword="") {
        if (OpenchangeConfig::$debugEnabled && $this->handle) {
            $string = "";

            while ($indentation > 0) {
                $string .= "    ";
                $indentation --;
            }

            if ($keyword)
                $string = "[" . $keyword . "]: ";

            $string .= $message;

            $string .= "\n";
            fwrite($this->handle, $string);
        }
    }
}
?>
