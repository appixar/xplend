<?php
class update extends cmd
{
    const REPO_URL = "https://github.com/appixar/arion.git";
    const MANIFEST_URL = "https://raw.githubusercontent.com/appixar/arion/main/manifest.json";
    const COMMITS_URL = "https://api.github.com/repos/appixar/arion/commits";

    public function __construct()
    {
        // CURRENT VERSION
        global $_MAN;
        $version = @$_MAN['version'];
        $sha = @$_MAN['commit']['sha'];
        $this->say("Arion current version: $version");
        $this->say("Looking for updates...");

        $updateNow = 0;

        // 1. CHECK LAST VERSION
        $json = json_decode(file_get_contents(self::MANIFEST_URL), true);
        $lastVersion = $json['version'];
        $lastUpdatedFiles = $json['updated'];
        if ($lastVersion > $version) {
            $this->say("New version found: $lastVersion", false, "magenta");
            $updateNow++;
        }
        // OR... 2. CHECK LAST COMMIT DATE
        else {
            // GET LAST COMMIT
            $options = ['http' => ['method' => 'GET', 'header' => ['User-Agent: PHP']]];
            $context = stream_context_create($options);
            $json = json_decode(file_get_contents(self::COMMITS_URL, false, $context), true);
            $lastSha = @$json[0]['sha'];
            $lastDate = @$json[0]['commit']['committer']['date'];
            $lastAuthor = @$json[0]['commit']['committer']['name'];
            // VERIFY SHA
            if ($lastSha != $sha) {
                $this->say("New commit detected: $lastDate", false, "green");
                $this->say("Commiter: $lastAuthor", false, "green");
                $this->say("SHA: $lastSha", false, "green");
                $updateNow++;
            }
        }
        $manifest = file_get_contents('manifest.json');
        echo $manifest;
        exit;
        if ($updateNow) {
            // CREATE DIR
            shell_exec("mkdir .tmp");
            shell_exec("git clone " . self::REPO_URL . " .tmp"); //2>&1
            foreach ($lastUpdatedFiles as $file) {
                $this->say("Copying: '$file' ...", false, "magenta");
                if ($file === '.') exec("cp .tmp/* ./ 2>/dev/null"); // 2>/dev/null supress error
                else shell_exec("cp -R .tmp/$file ./");
            }
            shell_exec("rm -rf .tmp/");
            $this->say("Updating manifest ...", false, "magenta");
            $this->say("Done!", false, "green");
        } else $this->say("You are up to date.");
    }
}
