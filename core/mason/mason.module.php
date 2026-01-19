<?php
class module extends Mason
{
  public function __construct()
  {
    // autoload(...)
    // 0=$this,
    // if 1=true, append 2nd value to method. ex: $this->add(value)
    // if 2=true, 2nd value is required
    Mason::autoload($this, true);
  }
  public function rm()
  {
  }
  public function up($module)
  {
    // CREATE DIR
    $dir = self::DIR_MODULES . "/$module/";
    if (!is_dir($dir)) {
      $this->say("Module '$module' is not installed", "yellow");
      exit;
    }
    // CLONE
    $this->cloneRepo($module, true);
  }
  public function add($module)
  {
    // SET GIT URL
    $url = "https://github.com/appixar/x-" . $module . ".git";

    // CREATE DIR
    $dir = realpath(self::DIR_MODULES . "/$module/");
    if (file_exists($dir)) {
      $this->say("Module '$module' already installed", "yellow");
      exit;
    }
    // CLONE
    $this->cloneRepo($module);
  }
  private function cloneRepo($module, $update = false)
  {
    // VAR'S
    $repo_url = "https://github.com/appixar/x-" . $module . ".git";
    $targetDir = realpath(self::DIR_MODULES) . "/$module";
    //$rootDir = self::DIR_ROOT;

    // CHECK REPO
    $this->say("Looking for '$module' ...");
    if (!repo_exists($repo_url)) {
      $this->say("Not found", "red");
      exit;
    }
    $this->say("Found!");

    // CHECK LAST COMMIT TO UPDATE MANIFEST
    // LAST VERSION
    $this->say("Checking last commit ...");
    $lastCommit = $this->getLastCommit($module);
    $lastSha = @$lastCommit['sha'];
    $lastDate = @$lastCommit['commit']['committer']['date'];
    $lastAuthor = @$lastCommit['commit']['committer']['name'];

    //------------------------------------------------
    // UPDATE MODULE. COPY ONLY SPECIF FILES
    //------------------------------------------------
    if ($update) {

      // CURRENT VERSION
      $currManifest = json_decode(file_get_contents("$targetDir/manifest.json"), true);
      $currSha = $currManifest['commit']['sha'];

      // UPDATE NOW!
      if ($lastSha != $currSha) {
        $this->say("New commit detected: $lastDate", "green");
        $this->say("Commiter: $lastAuthor", "green");
        $this->say("SHA: $lastSha", "green");

        // CLONE REPO
        shell_exec("rm -rf .tmp");
        shell_exec("mkdir .tmp");
        shell_exec("git clone $repo_url .tmp"); //2>&1

        // GET UPDATED FILES ONLY
        if (!file_exists('.tmp/manifest.json')) {
          $this->say("manifest.json not found.", "red");
          shell_exec("rm -rf .tmp");
          exit;
        }
        $newManifest = json_decode(file_get_contents('.tmp/manifest.json'), true);
        $ignoreOnUpdate = @$newManifest['ignoreOnUpdate'];
        $deleteBeforeUpdate = @$newManifest['deleteBeforeUpdate'];

        if (@$deleteBeforeUpdate) {
          $this->say("");
          $this->say("<!> Need to remove module files before upgrade!", "yellow");
          $this->say("Removing: $targetDir/*", "yellow");
          if ($this->confirm()) {
            // backup files
            //$dir_backup = "$dir/backup-" . geraSenha(3);
            //shell_exec("mkdir $dir_backup");
            //shell_exec("mv $dir/* $dir_backup");
            shell_exec("rm -rf $targetDir");
          } else {
            shell_exec("rm -rf .tmp");
            $this->say("Aborted.");
            exit;
          }
        }
        // MOVE README & MANIFEST FROM ROOT -> TO MODULE FOLDER
        // ... TO PRESERVE MAIN ARION MANIFEST
        if (!file_exists($targetDir)) shell_exec("mkdir $targetDir");
        $targetDir = realpath($targetDir);

        // REMOVE IGNORED FILES FROM TMP
        if (@$ignoreOnUpdate[0]) {
          foreach ($ignoreOnUpdate as $file) {
            $file = $this->cleanPath($file);
            shell_exec("rm -rf .tmp/$file");
          }
        }
        shell_exec("rm -rf .tmp/.git");
        shell_exec('find .tmp/ -name "*.git*" -type f -delete');

        if (@!$deleteBeforeUpdate) {
          if (!$this->confirmChanges($targetDir)) {
            $this->say("Aborted.");
            shell_exec("rm -rf .tmp");
            exit;
          }
        }
        // COPY REMAINING FILES
        $this->copyFiles($targetDir);
      }
      // UP TO DATE!
      else {
        $this->say("Module is up to date.");
        exit;
      }
    }
    //------------------------------------------------
    // ... OR: INSTALL MODULE. COPY ALL FILES
    //------------------------------------------------
    else {
      // CLONE REPO
      shell_exec("rm -rf .tmp");
      shell_exec('mkdir "'.$targetDir.'"');
      shell_exec("mkdir .tmp");
      shell_exec("git clone $repo_url .tmp"); //2>&1
      // MOVE README & MANIFEST FROM ROOT -> TO MODULE FOLDER
      // ... TO PRESERVE MAIN ARION MANIFEST
      //shell_exec("mv .tmp/manifest.json $dir");
      //shell_exec("mv .tmp/README.md $dir");
      // COPY OTHER FILES
      $this->copyFiles($targetDir);
    }

    // UPDATE MANIFEST: COMMIT SHA & COMMIT DATE
    $this->say("Updating manifest ...", "magenta");
    $manifest = json_decode(file_get_contents("$targetDir/manifest.json"), true); // CHANGE PLAIN TEXT TO PREVENT MINIFY FILE
    $manifest['commit']['sha'] = $lastSha;
    $manifest['commit']['date'] = $lastDate;
    $manifest = json_encode($manifest);
    file_put_contents("$targetDir/manifest.json", $manifest);

    // DONE!
    $this->say("Done!", "green");
  }
  private function confirm()
  {
    echo PHP_EOL;
    echo "Are you sure you want to do this? ☝" . PHP_EOL;
    echo "0: No" . PHP_EOL;
    echo "1: Yes" . PHP_EOL;
    //echo "2: Yes to all" . PHP_EOL;
    echo "Choose an option: ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    return trim($line);
  }
  private function confirmChanges($targetDir)
  {
    Mason::say("");
    Mason::say("→ Please, verify:");
    //
    $rootDir = self::DIR_ROOT . "/.tmp/";
    $rootDir = realpath($rootDir);
    $diff = self::compareDirectories($targetDir, self::DIR_ROOT . "/.tmp/");
    //
    foreach ($diff['only_exist_2'] as $file) {
      $this->say("[+] " . self::shortPathInner($file, $targetDir), "green");
    }
    foreach ($diff['different'] as $file) {
      $this->say("[#] " . self::shortPathInner($file, $targetDir), "yellow");
    }
    foreach ($diff['only_exist_1'] as $file) {
      //$this->say("[-] " . self::shortPathInner($file, $targetDir), false, "red");
    }
    echo PHP_EOL;
    echo "Are you sure you want to do this? ☝" . PHP_EOL;
    echo "0: No" . PHP_EOL;
    echo "1: Yes" . PHP_EOL;
    //echo "2: Yes to all" . PHP_EOL;
    echo "Choose an option: ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    return trim($line);
  }
  public static function compareDirectories($dir1, $dir2)
  {
    $different = array();
    $only_exist_1 = array();
    $only_exist_2 = array();

    $files1 = scandir($dir1);
    $files2 = scandir($dir2);

    foreach ($files1 as $file) {
      if (in_array($file, array(".", ".."))) {
        continue;
      }

      if (!in_array($file, $files2)) {
        $only_exist_1[] = realpath("$dir1/$file");
      } else {
        $file1 = $dir1 . "/" . $file;
        $file2 = $dir2 . "/" . $file;
        if (is_dir($file1)) {
          $results = self::compareDirectories($file1, $file2);
          $only_exist_2 = array_merge($only_exist_2, $results['only_exist_2']);
          $different = array_merge($different, $results['different']);
          $only_exist_1 = array_merge($only_exist_1, $results['only_exist_1']);
        } else {
          $hash1 = md5(file_get_contents($file1));
          $hash2 = md5(file_get_contents($file2));
          if ($hash1 !== $hash2) {
            $different[] = realpath("$dir2/$file");
          }
        }
      }
    }
    foreach ($files2 as $file) {
      if (in_array($file, array(".", ".."))) {
        continue;
      }

      if (!in_array($file, $files1)) {
        $only_exist_2[] = realpath("$dir2/$file");
      }
    }

    return array(
      "only_exist_1" => $only_exist_1,
      "only_exist_2" => $only_exist_2,
      "different" => $different
    );
  }
  private function getLastCommit($module)
  {
    $commit_url = "https://api.github.com/repos/appixar/x-$module/commits";
    $options = ['http' => ['method' => 'GET', 'header' => ['User-Agent: PHP']]];
    $context = stream_context_create($options);
    $json = json_decode(file_get_contents($commit_url, false, $context), true);
    return $json[0];
  }
  private function shortPath($path)
  {
    $rootDir = realpath(self::DIR_ROOT);
    return str_replace("$rootDir/", "", $path);
  }
  private function shortPathInner($path, $targetDir = "")
  {
    if ($targetDir) $targetDir = $this->shortPath($targetDir);
    $path = str_replace(realpath(self::DIR_ROOT) . "/", "", $path);
    $path = str_replace(".tmp/", "", $path);
    $path = str_replace("src/", "", $path);
    if (strpos($path, $targetDir) === false) $path = "$targetDir/$path";
    return $path;
  }
  private function cleanPath($path)
  {
    $path = trim($path);
    $path = str_replace('..', '', $path);
    if (substr($path, 0, 1) === '/') $path = substr($path, 1);
    return $path;
  }
  private function copyFiles($targetDir)
  {
    $targetDir = realpath($targetDir);
    // REMOVE GIT FILES
    shell_exec("rm -rf .tmp/.git");
    shell_exec('find .tmp/ -name "*.git*" -type f -delete');
    // COPY REMAINING FILES
    $listFiles = getDirContents('.tmp/');
    shell_exec('cp -R .tmp/* "'.$targetDir.'"');
    $this->say("Copying files...", "magenta");
    $this->say("Target: $targetDir", "magenta");
    $listFilesNew = []; // clean git, etc
    foreach ($listFiles as $f) {
      if (!is_dir($f)) {
        $fn = explode(".tmp/", $f)[1];
        $this->say("* $fn");
        $listFilesNew[] = $f;
      }
    }
    $this->say("Total files: " . count($listFilesNew), "magenta");
    shell_exec("rm -rf .tmp");
  }
}
