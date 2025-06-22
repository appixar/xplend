<?php
/*
* Example:
*      > php mason domain.com --restart www.domain.com test --blau=12345
*
* Results:
*      $argx
*          ['--restart'] = true
*          ['--blau'] = 12345
*      $args
*          [0] = domain.com
*          [1] = www.domain.com (1! jumps --restart)
*          [2] = test
*/
class Mason extends Xplend
{
    const DIR_CMD = __DIR__ . '/../mason/';
    public static $colors = [
        'header' => "\033[95m",
        //
        'pink' => "\033[94m",
        'cyan' => "\033[36m",
        'green' => "\033[92m",
        'yellow' => "\033[93m",
        'red' => "\033[91m",
        'blue' => "\033[1m",
        'magenta' => "\033[35m",
        //
        'blink' => "\033[5m",
        'strong' => "\033[1m",
        'u' => "\033[4m",
        'end' => "\033[0m"
    ];

    public function __construct()
    {
        global $argv, $_APP, $_MAN;

        // TERMINAL ONLY
        if (PHP_SAPI !== 'cli' or isset($_SERVER['HTTP_USER_AGENT'])) die($this->say('Console only.'));
        if (!isset($argv[1])) die("Xplend {$_MAN['version']}" . PHP_EOL);

        // INCLUDE ALL CORE CMD
        $files = scandir(self::DIR_CMD);
        foreach ($files as $file) {
            $f = self::DIR_CMD . $file;
            if (is_file($f)) {
                //echo "$f\r\n";
                require_once($f);
            }
        }
        //exit;
        // INCLUDE ALL MODULES CMD
        $files = array_diff(scandir(self::DIR_MODULES), [".", ".."]);
        foreach ($files as $file) {
            $f = self::DIR_MODULES . $file;
            if (is_dir($f) and file_exists("$f/mason")) {
                $files_cmd = array_diff(scandir("$f/mason"), [".", ".."]);
                foreach ($files_cmd as $file_cmd) {
                    $f_cmd = "$f/mason/$file_cmd";
                    if (is_file($f_cmd)) {
                        //echo "$f_cmd\r\n";
                        require_once($f_cmd);
                    }
                }
            }
        }

        // INVOKE CMD CLASS
        //echo $argv[1]; exit;
        $className = str_replace("-", "_", $argv[1]);
        if (class_exists($className)) new $className();
    }
    // RETURN ARGS OR ARGX
    public static function argx()
    {
        return self::args(true);
    }
    // RETURN ARGS OR ARGX
    public static function args($return_argx = false)
    {
        global $argv;
        $args = array();
        $argx = array();

        // BUILD ARGX & ARGS
        for ($i = 1; $i < count($argv); $i++) {
            $param = $argv[$i];
            if (substr($param, 0, 2) === '--') {
                $equal = @explode('=', $param)[1];
                if ($equal) $argx[explode('=', $param)[0]] = $equal;
                else $argx[$param] = true;
            } else $args[] = $param;
        }

        // RETURN ARGS OR ARGX?
        if ($return_argx) return $argx;
        else return $args;
    }
    // AUTOLOAD METHOD BASED IN FIRST PARAM
    public static function autoload($parentClass, $appendArg = false, $valueRequired = false)
    {
        $args = self::args();
        if (!@$args[1]) die(self::say('Missing parameters.'));
        if (!method_exists(get_class($parentClass), @$args[1])) die(self::say('Command not found.'));
        if ($appendArg) {
            if ($valueRequired and !@$args[2]) die(self::say('Missing parameters.'));
            if (@$args[2]) $parentClass->{$args[1]}($args[2]);
            else $parentClass->{$args[1]}();
        } else $parentClass->{$args[1]}();
    }
    public static function header($text, $color = '', $returnOnly = false)
    {
        if (PHP_SAPI !== 'cli') return;
        $header_width = 50;
        $header_symbol = "·";
        $_content = "";

        // GET COLOR
        $colorCode = @self::$colors[$color];

        // REPLACE TAG COLORS IN TEXT
        foreach (self::$colors as $k => $v) {
            $text = str_replace("<$k>", $v, $text);
            $text = str_replace("</$k>", self::$colors['end'], $text);
        }

        // OPEN HEADER BAR
        $_content .= $colorCode . str_repeat($header_symbol, $header_width) . self::$colors['end'] . PHP_EOL;

        // TEXT
        $_content .= $colorCode . $text . self::$colors['end'] . PHP_EOL;

        // CLOSE HEADER BAR
        $_content .= $colorCode . str_repeat($header_symbol, $header_width) . self::$colors['end'] . PHP_EOL;

        // ECHO OR RETURN
        if ($returnOnly) {
            return $_content;
        } else echo $_content;
    }
    public static function say($text, $color = '', $returnOnly = false)
    {
        if (PHP_SAPI !== 'cli') return;
        $timeStamp = "(" . date("H:i:s") . ") ";

        // IS ARRAY
        if (is_array($text)) {
            $formattedText = $timeStamp . print_r($text, true) . PHP_EOL;
            if ($returnOnly) return $formattedText;
            else {
                echo $formattedText;
                return;
            }
        }
        // REPLACE TAG COLORS IN TEXT
        foreach (self::$colors as $k => $v) {
            $text = str_replace("<$k>", $v, $text);
            $text = str_replace("</$k>", self::$colors['end'], $text);
        }
        // ADD FUNCTION PARAM COLOR DO TEXT
        $colorCode = @$color ? @self::$colors[$color] : '';
        $endColor = @self::$colors['end'];
        $formattedText = "{$colorCode}{$text}{$endColor}";
        $formattedText = $timeStamp . $formattedText . PHP_EOL;
        // RETURN 
        if ($returnOnly) {
            return $formattedText;
        } else echo $formattedText;
    }
}
