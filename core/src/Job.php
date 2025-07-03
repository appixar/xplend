<?php
// ➔ ↺ ✓ ✔ ✕ ✚ ☂ ☁ ☆ ★ ♥ ♺ ⚑ ⚔ ⚛ ⚬
class Job extends Xplend
{
    private $conf = array(
        "logDirRequired" => false,
        "logSys" => false, // dont log sys messages (start,end,etc)
        "logMaxSize" => 25, //mb
        "multiProcess" => false
    );
    //
    private $time_start = 0;
    private $time_total = 0;

    // loops
    private $current_loop = 0;
    private $loops_with_events = [];

    // caller file
    private $caller, $caller_path, $caller_fn;
    private $caller_content; // verify changes

    // log files
    private $id_file, $date_file, $log_file;
    private $colors; // log colors

    public function __construct($conf = [])
    {
        foreach ($conf as $k => $v) $this->conf[$k] = $v;
        $caller = debug_backtrace();
        // caller data
        $this->caller = $caller[0]['file'];
        $this->caller_path = dirname($this->caller);
        $this->caller_fn = basename($this->caller);
        $this->caller_content = md5_file($this->caller);
        // log files
        $this->id_file = $this->caller_path . "/log/" . $this->caller_fn . "@id";
        $this->date_file = $this->caller_path . "/log/" . $this->caller_fn . "@date";
        $this->log_file = $this->caller_path . "/log/" . $this->caller_fn . "@log";
        if ($this->conf["logDirRequired"] and !is_writable($this->log_file)) {
            $this->say("✕ No write access to log dir.", 'red');
            exit;
        }
        // log colors
        $this->colors = $this->getColors();
    }
    public static function run_all_jobs()
    {
        global $_APP;
        if (!@$_APP['JOBS']) return false;
        $total_jobs = count($_APP['JOBS']);
        Mason::say("∴ $total_jobs jobs from {$_APP['NAME']}", true, 'blue');
        // check if autoplay is available
        $stop_fn = realpath(Xplend::DIR_ROOT . '/src/jobs/stop');
        if (file_exists($stop_fn)) {
            Mason::say("<magenta>(!) autoplay is disabled</end>");
            Mason::say("remove: $stop_fn");
            exit;
        }
        foreach ($_APP['JOBS'] as $fn) {
            // already running
            if (self::check_fn_process($fn)) {
                Mason::say("✔ php {$fn} <magenta>(already running)</end>");
            }
            // run
            else {
                $dir = realpath(Xplend::DIR_ROOT);
                $exec = "php $dir/$fn";
                Mason::say("<green>► php {$fn}</end>");
                exec("$exec > /dev/null &");
            }
        }
    }
    public function start()
    {
        $this->check_caller_process();
        $this->check_caller_changes();
        $this->check_caller_status();
        $this->current_loop = $this->current_loop + 1;
        $this->setDate();
        set_time_limit(0);
        $this->time_start = microtime(true);
        if ($this->current_loop === 1) $this->say('⚬ Start.', false, $this->conf['logSys']);
        else {
            $back_1_loop = intval($this->current_loop - 1);
            $back_2_loops = intval($this->current_loop - 2);
            if (
                @!$this->loops_with_events[$back_1_loop]
                and @$this->loops_with_events[$back_2_loops]
            ) {
                echo "(" . date("H:i:s") . ") ♺ ..." . PHP_EOL; //⏳
            }
        }
        //file_put_contents($this->caller_lock, "");
    }
    private function check_caller_changes()
    {
        clearstatcache();
        $current_caller_content = md5_file($this->caller);
        if ($current_caller_content !== $this->caller_content) {
            $this->say("⚬ File has changed.", "yellow");
            $this->end();
        }
    }
    private function check_caller_status()
    {
        clearstatcache();
        if (file_exists("{$this->caller}-stop")) {
            $this->say("⚬ STOPPED BY DASHBOARD.", "red");
            $this->end();
        }
        if (file_exists("{$this->caller}-restart")) {
            @unlink("{$this->caller}-restart");
            $this->say("⚬ RESTARTED BY DASHBOARD. AWAITING NEW EXECUTION...", "blue");
            $this->end();
        }
    }
    private function setDate()
    {
        file_put_contents($this->date_file, time());
    }
    public function set_last_id($id, $say = true)
    {
        file_put_contents($this->id_file, $id);
        if ($say) $this->say("SET LAST ID: <blue>$id</end>", true, true, "pink");
    }
    public function get_last_id()
    {
        if (file_exists($this->id_file)) {
            $last_id = file_get_contents($this->id_file);
        } else {
            file_put_contents($this->id_file, 0);
            $last_id = 0;
        }
        $this->say("⚬ CONTINUE AFTER LAST ID: <blue>$last_id</end>...", true, true, "pink");
        return $last_id;
    }
    private function secToTime($seconds)
    {
        $t = (int) round($seconds); // Convertendo explicitamente para int após o arredondamento
        return @sprintf('%02d:%02d:%02d', (int)($t / 3600), (int)($t / 60 % 60), $t % 60);
    }
    public function log($message)
    {
        if (file_exists($this->log_file) and filesize($this->log_file) >= intval($this->conf['logMaxSize'] * 1024 * 1024)) {
            // clear log file
            file_put_contents($this->log_file, "", FILE_APPEND);
        }
        @file_put_contents($this->log_file, "[" . date("Y-m-d H:i:s") . "] $message" . PHP_EOL, FILE_APPEND);
    }
    public function end()
    {
        $this->time_total = number_format((microtime(true) - $this->time_start), 4);
        $this->say("⚬ End. Runtime: " . $this->secToTime($this->time_total), false, $this->conf['logSys']);
        //@unlink($this->caller_lock);
        exit;
    }
    public function check_file_running($fn)
    {
        $runningCount = 0;
        exec("ps aux | grep php | grep -v grep", $output);
        foreach ($output as $line) {
            $path = @explode("php ", $line)[1];
            if (!$path) continue;
            $path = @explode(" ", $path)[0];
            $parts = explode("/", $path);
            if (end($parts) == $fn) $runningCount++;
        }
        return $runningCount;
    }
    public function check_caller_process()
    {
        if (!$this->conf['multiProcess'] and $this->check_file_running($this->caller_fn) > 1) {
            Mason::say("<red>✕ Already running.</red>");
            exit;
        }
    }
    public function now()
    {
        return date("Y-m-d H:i:s");
    }
    public static function check_fn_process($fn)
    {
        exec("ps aux | grep '{$fn}' | grep -v grep | awk '{print $2}'", $findProcess);
        if (count($findProcess) > 0) return true;
        else return false;
    }
    public function validate($res)
    {
        // Check errors
        $return = json_decode($res['res']);
        if ($res['err']) {
            $this->say("(!) cURL Error: {$res['err']}", false, true, "red");
            exit;
        }
        if (isset($return->message)) {
            $this->say("(!) API Message: $return->message", false, true, "red");
            exit;
        }
        if (isset($return->api->error)) {
            $this->say("(!) API Error: $return->api->error", false, true, "red");
            exit;
        }
    }
    public function say($text, $color = '', $log = true)
    {
        $this->loops_with_events[$this->current_loop] = 1;
        $timeStamp = "(" . date("H:i:s") . ") ";
        $colorCode = @$color ? @$this->colors[$color] : '';

        if (is_array($text)) {
            echo $timeStamp . print_r($text, true) . PHP_EOL;
            if ($log) $this->log(print_r($text, true));
        } else {
            $text = $this->addTagColorsToText($text);
            $endColor = @$this->colors['end'];
            $formattedText = "{$colorCode}{$text}{$endColor}";
            echo $timeStamp . $formattedText . PHP_EOL;
            if ($log) $this->log($formattedText);
        }
    }
    public function header($text, $color = '')
    {
        $this->loops_with_events[$this->current_loop] = 1;
        $timeStamp = "(" . date("H:i:s") . ") ";
        $headerWidth = 50;
        $headerSymbol = "·";
        $colorCode = $color ? $this->colors[$color] : '';

        $headerLine = str_repeat($headerSymbol, $headerWidth);
        $formattedHeader = "{$colorCode}{$headerLine}{$this->colors['end']}";
        $formattedText = "{$colorCode}{$this->addTagColorsToText($text)}{$this->colors['end']}";

        echo $timeStamp . $formattedHeader . PHP_EOL;
        $this->log($formattedHeader);

        echo $timeStamp . $formattedText . PHP_EOL;
        $this->log($formattedText);

        echo $timeStamp . $formattedHeader . PHP_EOL;
        $this->log($formattedHeader);
    }
    private function addTagColorsToText($text)
    {
        if (@$this->colors) {
            foreach ($this->colors as $key => $value) {
                $text = str_replace("<$key>", $value, $text);
                $text = str_replace("</$key>", $this->colors['end'], $text);
            }
        }
        return $text;
    }
    private function getColors()
    {
        $colors = array(
            'header' => "\033[95m",
            //
            'blue' => "\033[94m",
            'cyan' => "\033[36m",
            'green' => "\033[92m",
            'yellow' => "\033[93m",
            'red' => "\033[91m",
            'pink' => "\033[35m",
            //
            'blink' => "\033[5m",
            'strong' => "\033[1m",
            'u' => "\033[4m",
            'end' => "\033[0m"
        );
        return $colors;
    }
    public function maxRunning($limitCount, $filename = false)
    {
        if (!$filename) $filename = $this->caller_fn;
        $running = $this->check_file_running($filename);
        if ($running > $limitCount) {
            $this->say("✕ Limit process: {$filename} ({$running})", 'red');
            exit;
        }
    }
    public function checkRunning($filename = false)
    {
        if (!$filename) $filename = $this->caller_fn;
        return $this->check_file_running($filename);
    }
    public function checkCpu()
    {
        return $this->getCpuUsage();
    }
    public function maxCpu($cpuPercentage)
    {
        $cpuUsage = $this->getCpuUsage();
        if ($cpuUsage > $cpuPercentage) {
            $this->say("✕ CPU usage: {$cpuUsage}% (limit: {$cpuPercentage}%)", 'red');
            exit;
        }
    }
    public function maxRam($ramPercentage)
    {
        $ramUsage = $this->getRamUsage();
        if ($ramUsage > $ramPercentage) {
            $this->say("✕ RAM usage: {$ramUsage}% (limit: {$ramPercentage}%)", 'red');
            exit;
        }
    }
    private function getRamUsage()
    {
        $meminfo = file('/proc/meminfo');
        $memTotal = 0;
        $memAvailable = 0;

        foreach ($meminfo as $line) {
            if (strpos($line, 'MemTotal:') === 0) {
                $memTotal = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
            }
            if (strpos($line, 'MemAvailable:') === 0) {
                $memAvailable = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
            }
        }

        if ($memTotal === 0) return 0;

        $memUsed = $memTotal - $memAvailable;
        $usagePercent = 100 * $memUsed / $memTotal;

        return round($usagePercent, 2);
    }
    private function getCpuUsage()
    {
        $stat1 = file('/proc/stat');
        usleep(500000); // 0.5 segundo
        $stat2 = file('/proc/stat');

        $cpus1 = array_filter($stat1, fn($line) => strpos($line, 'cpu') === 0);
        $cpus2 = array_filter($stat2, fn($line) => strpos($line, 'cpu') === 0);

        $totalUsage = 0;
        $cpuCount = 0;

        foreach ($cpus1 as $i => $line1) {
            if (!isset($cpus2[$i])) continue;

            $parts1 = preg_split('/\s+/', trim($line1));
            $parts2 = preg_split('/\s+/', trim($cpus2[$i]));

            $idle1 = $parts1[4] + $parts1[5];
            $idle2 = $parts2[4] + $parts2[5];

            $total1 = array_sum(array_slice($parts1, 1, 10));
            $total2 = array_sum(array_slice($parts2, 1, 10));

            $totalDiff = $total2 - $total1;
            $idleDiff = $idle2 - $idle1;

            if ($totalDiff === 0) continue;

            $usage = 100 * (1 - ($idleDiff / $totalDiff));
            $totalUsage += $usage;
            $cpuCount++;
        }

        return $cpuCount > 0 ? round($totalUsage / $cpuCount, 2) : 0;
    }
}
