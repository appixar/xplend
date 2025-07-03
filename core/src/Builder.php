<?php
class Builder extends Xplend
{
    public $pageName;
    public $pageDir;
    public $pageRootUri;
    public $isSnippet;
    public $isApi;
    private static $instances = [];
    //
    public function __construct($snippet = "", $snippet_params = [])
    {
        self::$instances[] = $this;
        //
        global $_APP, $_APP_VAULT;
        global $_ORDER; // $files[] order to include
        global $_URI; // domain.com/ad/edit/123 => $_URI[0]=ad [1]=edit [2]=123
        // $_PAR = ROUTE URL PARAMS
        // IF ROUTE YML & API SERVER: domain.com/ad/edit/{id} => $_PARAM[id]=123 based on routes.yml
        // IF ROUTE FILE SYSTEM: domain.com/ad/edit/123 => $_PATH[0]=123 (first param after real directory) 
        global $_PAR;
        global $_HEADER; // api server
        global $_BODY; // api server
        global $_ROUTE, $_ROUTE_PERMISSION; // current route + permission
        global $_isAPI;
        global $_ALIAS;
        global $_FILES_WAIT_END; // files to append at the end
        //
        global $_BUILD_COUNT;
        $_BUILD_COUNT++;

        // GET ALL VARIABLES
        extract($GLOBALS, EXTR_REFS | EXTR_SKIP);

        //==================================
        // $PAGE PRE DEFINED? UPDATE $_URI
        //==================================
        if ($snippet) {
            $this->isSnippet = 1;
            $_URI = explode("/", $snippet);
        }
        // BUG FIX END "/" IF URL HAVE GET PARAMETERS
        if (!empty($_URI) and end($_URI) === '') array_pop($_URI);
        if (empty($_URI)) $_URI[] = 'home';
        //prex($_URI);
        //-
        // API FIRST.
        // FIRST OF ALL, TRY TO FIND ROUTE IN APP/CONFIG/ROUTES.YML
        // API SERVER?
        //-
        $this->handleApiServer();
        // IF ROUTE FOUND, STOP HERE.
        // IF NOT FOUND, CONTINUE... AND TRY FIND PAGE IN /PAGES
        // CONTINUE IN FILE SYSTEM INCLUDES...
        if (@$_APP['PAGES']) $_isAPI = false;
        //==================================
        // DEFINE $FILES
        // TARGET LIST EXISTS?
        //==================================
        if (!@$_APP['PAGES']) Xplend::err(404, "Not found", 404);
        $this->pageDir = $this->findPageDir();
        $this->pageRootUri = $this->getRootUri();
        $this->pageName = $this->getPageName();
        $yaml = $this->getYaml();

        // MERGE $YAML TO $_APP
        //if (is_array($yaml)) $_APP = array_merge($_APP, $yaml);
        if (is_array($yaml)) $this->mergeAppConf($yaml);
        //prex($_APP);

        // CREATE $_APP_REAL WITH REAL VARIABLES .ENV
        $_APP_VAULT = Xplend::replaceEnvValues($_APP);

        //==================================
        // GET URL ALIAS IF EXISTS (/.css, /.js)
        //==================================
        $_ALIAS = $this->getAliasFiles();
        // SET $_PAR
        //$_PAR = $this->getParamFromUri();
        //==================================
        // PATH_PARAM ENABLED?
        //==================================
        //if (!@$_APP["PAGES"]["URL_PARAMS"]) {
        if (@!$this->pageDir) {
            header("HTTP/1.1 404 Not found");
            echo "<h1>Page not found</h1>";
            exit;
            //Xplend::err("Not found", "Page '" . end($_URI) . "' not found.", 404);
        }
        // FAKE ALIAS BUGFIX
        if (@is_array($_APP["URL_MASK"])) {
            if (@array_key_exists(end($_URI), $_APP["URL_MASK"])) $aliasExt = end($_URI);
            if (@$aliasExt and !@$_ALIAS) {
                Xplend::err("Not found", "URL Mask '{$this->pageName}$aliasExt' not found.", 404);
            }
        }
        //}
        if (!$this->pageName) Xplend::err("Not found", "Page '{$_PAR[0]}' not found.", 404);

        //==================================
        // DEFAULT LIBS, CORE LIBS & DEFAULT MODULES
        //==================================
        //$this->loadLibs();
        //$this->loadModules();

        // DEFINE UTIL VARIABLES & CONSTANTS
        $this->setAppGlobals($snippet, $snippet_params);
        $_ORDER = $this->getFiles();

        //==================================
        // INCLUDE ONLY ALIAS IF EXISTS
        // TARGET FILE IS ALIAS (/.CSS/.JS/.POST)
        //==================================
        if ($_ALIAS && @$_APP['URL_MASK_ALONE']) {
            include $_ALIAS;
            exit;
        }
        //==================================
        // CONTENT
        //==================================
        $this->loadContent();
        if (@$_APP["SNIPPET"]) {
            $_APP["SNIPPETS"][] = $_APP["SNIPPET"];
            unset($_APP["SNIPPET"]);
        }
    }
    private function mergeAppConf($newYaml)
    {
        global $_APP;
        $originalSequence = @$_APP['PAGES']['FILE_SEQUENCE'];
        $newSequence = @$newYaml['PAGES']['FILE_SEQUENCE'];
        $newSequenceFixed = [];
        $dotIndex = 0;
        if ($newSequence and $originalSequence) {
            foreach ($newSequence as $file) {
                if ($file == '.') {
                    $newSequenceFixed[] = $originalSequence[$dotIndex];
                    $dotIndex++;
                    continue;
                }
                if ($file == '...') {
                    $i = 0;
                    foreach ($originalSequence as $file2) {
                        if ($i < $dotIndex) {
                            $i++;
                            continue;
                        }
                        $newSequenceFixed[] = $file2;
                    }
                    continue;
                }
                $newSequenceFixed[] = $file;
            }
        }
        $_APP = array_merge($_APP, $newYaml);
        if ($newSequenceFixed) $_APP['PAGES']['FILE_SEQUENCE'] = $newSequenceFixed;
    }
    public static function getLastInstance()
    {
        if (count(self::$instances) == 0) {
            return null; // ou lançar uma exceção, dependendo do seu caso de uso
        }
        return self::$instances[count(self::$instances) - 1];
    }
    public function getPostUrl()
    {
        return $this->getBaseUrl() . "/" . $this->getRootUri() . "/.post";
    }
    public function getRootUrl()
    {
        //return $this->getBaseUrl() . "/" . $this->getRootUri();
        global $_PAR, $_URI;

        // Obter o URI do diretório físico
        $rootUri = $this->getRootUri();

        // Substituir os marcadores de parâmetros (@param) pelos valores reais
        $segments = explode('/', $rootUri);
        $resultSegments = [];

        foreach ($segments as $segment) {
            if (substr($segment, 0, 1) === '@') {
                $paramName = substr($segment, 1);
                if (isset($_PAR[$paramName])) {
                    $resultSegments[] = $_PAR[$paramName];
                } else {
                    $resultSegments[] = $segment; // Manter original se param não existir
                }
            } else {
                $resultSegments[] = $segment;
            }
        }

        // Reconstruir a URL com os parâmetros reais
        $realRootUri = implode('/', $resultSegments);

        return $this->getBaseUrl() . "/" . $realRootUri;
    }
    private function showErrorsInJsonFormat()
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) return;
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Internal Server Error',
                'type' => 'Error',
                'message' => $message,
                'file' => basename($file),
                'line' => $line
            ]);
            exit;
        });

        set_exception_handler(function ($exception) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Internal Server Error',
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine()
            ]);
            exit;
        });

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Fatal Error',
                    'type' => $error['type'],
                    'message' => $error['message'],
                    'file' => basename($error['file']),
                    'line' => $error['line']
                ]);
                exit;
            }
        });
    }
    private function handleApiServer()
    {
        global $_APP, $_isAPI, $_HEADER;
        if (@$_APP['API_SERVER']) {
            $_isAPI = true;
            $this->showErrorsInJsonFormat();
            $this->checkApiServerRoute();
            // IF FOUND ROUTE. STOP HERE.
            if (@$_HEADER or @!$_APP['PAGES']) {
                $msg = "Not found";
                if (@!$_APP['ROUTES']) $msg = "Route config not found";
                if (@!$_APP['API_SERVER']['ALWAYS_200'] === true) header("HTTP/1.1 404 $msg");
                else header("HTTP/1.1 200 $msg");
                $json = json_encode(array(
                    'error' => 404,
                    'message' => $msg
                ));
                die($json);
            }
        }
    }
    private function checkApiServerRoute()
    {
        global $_APP, $_HEADER, $_URI, $_PAR, $_ROUTE, $_ROUTE_PERMISSION;
        if (@!$_APP['ROUTES']) return false;
        // multiple $matches_ to organize data and otimize array search
        $_matches_parts = [];
        $_matches_params = [];
        // loop routes
        foreach ($_APP['ROUTES'] as $endpoint => $controller) {
            $_method = up(explode(' ', $endpoint)[0]);
            $_url = @low(explode(' ', $endpoint)[1]);
            if (!$_url) {
                $_url = low($_method);
                $_method = 'ALL';
            }
            // clean url
            if ($_url[0] === '/') $_url = substr($_url, 1); // remove first '/'
            if (substr($_url[0], -1) === '/') $_url = substr($_url, 0, -1); // remove last '/'

            // util vars
            $_parts = explode('/', $_url);
            $_found = true;
            $_found_parts = 0;
            $_param_key = [];
            $_param_value = [];

            // different method? next!
            if ($_method !== 'ALL' and $_method !== $_SERVER["REQUEST_METHOD"]) continue;
            if ($_method !== 'ALL') $_found_parts++;

            // wildcard
            $_asterisk = false;

            // compare endpoints positions with current url
            for ($i = 0; $i < count($_parts); $i++) {
                // position is wildcard *
                if ($_parts[$i] === '*') {
                    $_asterisk = true;
                    break;
                }
                // position is <variable> (required)
                elseif (@$_URI[$i] and substr($_parts[$i], 0, 1) === '<' and substr($_parts[$i], -1) === '>') {
                    $_param_key[] = substr($_parts[$i], 1, -1); // remove <>
                    $_param_value[] = $_URI[$i];
                    //$_found = true;
                }
                // position is [variable] (optional)
                elseif (substr($_parts[$i], 0, 1) === '[' and substr($_parts[$i], -1) === ']') {
                    $_param_key[] = substr($_parts[$i], 1, -1); // remove []
                    $_param_value[] = @$_URI[$i];
                    $_found_optional_variable = true;
                    //$_found = true;
                }
                // position differs from url
                elseif ($_parts[$i] !== @$_URI[$i]) {
                    //echo "{$_parts[$i]} !== {$_URI[$i]}\r\n";
                    $_found = false;
                }
                $_found_parts++;
            }
            // compare url size with endpoint size
            if (!empty($_URI)) {
                if (!$_asterisk and count($_URI) !== count($_parts)) {
                    if (!@$_found_optional_variable) $_found = false;
                }
            }

            // SAVE POSSIBLE ENDPOINT
            if ($_found) {
                $_matches_parts[$endpoint] = $_found_parts;
                foreach ($_param_key as $i => $key) {
                    $_matches_params[$endpoint][$key] = @$_param_value[$i];
                }
            }
        }
        // FIND THE BIGGEST KEY (MORE FOUND "/" PARTS)
        //      THAN... INVOKE CONTROLLER AND EXIT!
        if (!empty($_matches_parts)) {
            Api::buildApiHeaders();
            $biggestKeyFound = array_search(max($_matches_parts), $_matches_parts);
            $controllerContent = $_APP['ROUTES'][$biggestKeyFound];
            $controller = @trim(@explode(" ", $controllerContent)[0]);
            $_ROUTE = $biggestKeyFound;
            // permission flag
            $flag = @$_APP['API_SERVER']['ROUTE_PERMISSION_FLAG'];
            if (!$flag) $flag = "⛊";
            $permissionContent = @trim(@explode($flag, $controllerContent)[1]);
            if ($permissionContent) {
                $_ROUTE_PERMISSION = trim($permissionContent);
            }
            $_PAR = @$_matches_params[$biggestKeyFound];
            // controler.{method} as variable?
            $method = @explode(".", $controller)[1];
            if (@$method and substr($method, 0, 1) === '<' and substr($method, -1) === '>') {
                $method_name = $_PAR[substr($method, 1, -1)];
                $controller_name = explode(".", $controller)[0];
                $controller = "$controller_name.$method_name";
            }
            if (@$method and substr($method, 0, 1) === '[' and substr($method, -1) === ']') {
                $method_name = @$_PAR[substr($method, 1, -1)];
                if (!$method_name) $method_name = 'index';
                $controller_name = explode(".", $controller)[0];
                $controller = "$controller_name.$method_name";
            }
            Http::route([
                'controller' => $controller,
                'params' => @$_PAR,
                'required' => true
            ]);
            exit;
        }
        //}
    }
    private function findRealPath($uriArray, $rootDir)
    {
        global $_PAR;
        // Remover o domínio base da URL e converter em um array de segmentos
        //$path = str_replace($baseUrl, '', $url);
        //$segments = explode('/', trim($path, '/'));
        $segments = $uriArray;
        $currentPath = $rootDir;
        $finalPath = '';
        // Navegar pelos segmentos da URL
        foreach ($segments as $segment) {
            //echo "*$currentPath/$segment<br>";
            if (is_dir($currentPath . '/' . $segment)) {
                // Se o segmento da URL corresponder a um diretório real
                $currentPath .= '/' . $segment;
                $finalPath .= '/' . $segment;
            } else {
                // Se não corresponder, tentamos substituir pelo diretório especial
                $foundSpecialDir = false;
                if (file_exists($currentPath)) {
                    $allDirs = scandir($currentPath);
                    // special dir <name>
                    $specialDirs = array_filter($allDirs, function ($dir) {
                        return $dir[0] === '<' && substr($dir, -1) === '>';
                    });
                    foreach ($specialDirs as $specialDir) {
                        if (is_dir($currentPath . '/' . $specialDir)) {
                            $specialDirClean = substr($specialDir, 1, -1);
                            $_PAR[$specialDirClean] = $segment;
                            //echo "$specialDir = $segment"; exit;
                            $currentPath .= '/' . $specialDir;
                            $finalPath .= '/' . $specialDir;
                            $foundSpecialDir = true;
                            break;
                        }
                    }
                    // special dir @name
                    $specialDirs = array_filter($allDirs, function ($dir) {
                        return $dir[0] === '@';
                    });
                    foreach ($specialDirs as $specialDir) {
                        if (is_dir($currentPath . '/' . $specialDir)) {
                            $specialDirClean = substr($specialDir, 1);
                            $_PAR[$specialDirClean] = $segment;
                            //echo "$specialDir = $segment"; exit;
                            $currentPath .= '/' . $specialDir;
                            $finalPath .= '/' . $specialDir;
                            $foundSpecialDir = true;
                            break;
                        }
                    }
                }
                if (!$foundSpecialDir) {
                    // Se nem o diretório especial foi encontrado, então a URL é inválida
                    return false;
                }
            }
        }
        return $rootDir . $finalPath;
    }
    private function findPageDir()
    {
        global $_APP, $_URI;
        $uri_page = implode("/", $_URI);
        $uri_page_arr = explode("/", $uri_page); // way to current page
        // CHECK IF ALIAS EXISTS IN URL
        $alias = @$_APP["URL_MASK"];
        if (is_array($alias) && @array_key_exists(end($_URI), $alias)) {
            array_pop($uri_page_arr); // REMOVE ALIAS TO FOUND DIR
        }
        // LOOP IN ALL ROUTES & SUB ROUTES
        $pages_path = $this->findPathsByType("pages");
        foreach ($pages_path as $path) {
            $realpath = $this->findRealPath($uri_page_arr, $path);
            if ($realpath) return realpath($realpath);
        }
        return false;
    }
    private function loadContent()
    {
        global $_APP, $_ORDER, $_FILES_WAIT_END, $_CACHE;

        // Inicializa o buffer de saída
        if (@$_CACHE['key']) ob_start();

        foreach ($GLOBALS as $k => $v) global ${$k};

        $_APP["FLOW_X"] = 0; // flow sort order

        foreach ($_ORDER as $file) {
            if (file_exists($file)) {
                $start = microtime(true); // inicia cronômetro
                new Debug(__CLASS__, "$file...", "muted");

                #echo $file."<br>";
                require_once($file);
                $_APP["FLOW_X"]++;

                $time_elapsed_secs = number_format((microtime(true) - $start), 4);
                new Debug(__CLASS__, "$file in $time_elapsed_secs s");
            }
        }
        if (!$this->isSnippet and @$_FILES_WAIT_END[0]) {
            foreach ($_FILES_WAIT_END as $file) {
                require_once($file);
            }
        }
        //$this->isSnippet = 0; // BACK TO GLOBAL PAGE STATUS

        // Captura o conteúdo do buffer e armazena em uma variável
        if (@$_CACHE['key']) {
            $renderedContent = ob_get_clean();
            $cache = new Cache();
            $cache->set(@$_CACHE['key'], $renderedContent, @$_CACHE['exp']);
            echo $renderedContent;
        }
    }
    private function getAliasFiles()
    {
        global $_APP, $_URI;
        $alias = @$_APP["URL_MASK"];
        if (!$alias) return false;
        $_ALIAS = false;

        // find alias in end of url
        if (is_array($alias) && @array_key_exists(end($_URI), $alias)) {
            $ext = end($_URI);
            array_pop($_URI); // remove last element (/.ext)
            $page = end($_URI);
            //$uri_page = implode("/", $_URI);
            $f_name = str_replace("<PAGE>", $page, $alias[$ext]);
            $f_alias = "{$this->pageDir}/$f_name";
            if (file_exists($f_alias)) {
                // if $f_alias is set, in the end of file will have a include + exit;
                if ($ext == ".css") header("Content-type: text/css; charset: UTF-8; Cache-control: must-revalidate");
                if ($ext == ".js") header('Content-Type: application/javascript');
                $_ALIAS = $f_alias;
                //if (function_exists('jwsafe')) jwsafe();
            }
        }
        return $_ALIAS;
    }
    private function getFiles()
    {
        // GLOB
        global $_APP, $_FILES_WAIT_END, $_ALIAS;
        $files = array();

        // GET PAGE DATA
        //$alias_fn = $this->getAliasFiles();
        //die($alias_fn);
        $page = $this->getPageName();
        $root = $this->getRootDir();
        $yaml = $this->getYaml();
        $yaml_fn = $this->getYaml(true);
        if ($yaml_fn) {
            $yaml_dir = explode("/", $yaml_fn);
            array_pop($yaml_dir);
            $yaml_dir = implode("/", $yaml_dir);
        }
        // MERGE YAML
        #if (is_array($yaml)) $_APP = array_merge($_APP, $yaml);
        $flow = @$_APP["PAGES"]["FILE_SEQUENCE"];

        // SNIPPET? INCLUDE ONLY .PHP & .TPL
        if (@$_APP["SNIPPET"]) {
            $flow = @$_APP["SNIPPETS"]["FILE_SEQUENCE"];
            if (!$flow) $flow = ["<PAGE>.php", "<PAGE>.tpl"];
        }

        // FLOW LOOP
        if ($flow) {
            foreach ($flow as $elem) {
                $wait_end = false;
                if (@explode("...", $elem)[1]) {
                    $wait_end = true;
                    $elem = trim(explode("...", $elem)[1]);
                }
                if ($_ALIAS && $elem == "<PAGE>.php") {
                    // Usamos o arquivo alias em vez do <PAGE>.php
                    $file = $_ALIAS;
                    $stopAfterAlias = 1;
                } else {
                    $fn = str_replace("<PAGE>", $page, $elem);
                    if (substr($fn, 0, 1) === "/") $file = "$root/$fn";
                    elseif (@$yaml_dir and substr($fn, 0, 2) === "./") $file = "$yaml_dir/$fn";
                    else $file = "{$this->pageDir}/$fn";
                }
                //$files[] = $file;
                if (file_exists($file)) {
                    if ($wait_end) $_FILES_WAIT_END[] = realpath($file);
                    else $files[] = realpath($file);
                    if (@$stopAfterAlias) break;
                }
            }
            //prex($files);
            //return $files;
        }

        return $files;
    }
    private function getYaml($returnFileNameOnly = false)
    {
        global $_APP;
        $yaml = [];
        $array_dir = array_filter(explode("/", $this->pageDir));
        $array_dir_pointer = $array_dir;
        // !!!
        // LOOP "/" ROUTE_DIR TO FIND .YML !!!
        // !!!
        foreach ($array_dir as $dir_name) {
            $page = end($array_dir_pointer);
            $dir = "/" . implode("/", $array_dir_pointer);
            if ($dir === realpath(self::DIR_PAGES)) continue;
            $fn = "$dir/$page.yml";
            // ROUTE HAVE HIS OWN YAML
            if (file_exists($fn)) {
                if ($returnFileNameOnly) {
                    if (file_exists($fn)) return $fn;
                    else return false;
                }
                $yaml = yaml_parse(file_get_contents($fn));
                return $yaml;
            }
            // ROUTE DONT HAVE YAML
            else {
                $dir_root = realpath(self::DIR_PAGES);
                #echo "$dir $dir_root";
                // CURRENT ROUTE IS A SUB ROUTE
                // SET A NEW YAML FLOW
                if ($dir !== '/' and strpos($dir, $dir_root) === false) {
                    #echo 1; exit;
                    $yaml["PAGES"]["FILE_SEQUENCE"] = ["<PAGE>.php", "<PAGE>.tpl"];
                }
                // CURRENT ROUTE IS A MAIN ROUTE
                else {
                    $yaml = $_APP;
                }
            }
            array_pop($array_dir_pointer);
        }
        if ($returnFileNameOnly) return false;
        return $yaml;
    }
    private function getPageName()
    {
        $page = array_filter(explode("/", $this->pageDir));
        $page = end($page);
        return $page;
    }
    private function getRootDir()
    {
        $array = array_filter(explode("/", $this->pageDir));
        $pos = array_search("pages", $array);
        $array = array_slice($array, 0, $pos);
        $page = implode("/", $array);
        return "/$page";
    }
    private function getRootUri()
    {
        $array = array_filter(explode("/", $this->pageDir));
        $pos = array_search("pages", $array);
        $array = array_slice($array, $pos);
        $page = implode("/", $array);
        return $page;
    }
    public function getBaseUrl()
    {
        $protocol = "http";
        // HTTPS BY CLOUDFLARE? (PROXY)
        if (isset($_SERVER["HTTP_CF_VISITOR"])) $protocol = json_decode($_SERVER["HTTP_CF_VISITOR"], true)['scheme'];
        // HTTPS DEFAULT?
        if (isset($_SERVER['HTTPS'])) $protocol = "https";

        $host = $_SERVER['HTTP_HOST'];
        //$uri = @explode("?", $_SERVER['REQUEST_URI'])[0];
        $current_url = $protocol . '://' . $host;
        return $current_url;
    }
    private function setAppGlobals($snippet = false, $snippet_params = [])
    {
        global $_APP, $_URI;
        //$route_root_uri = $this->getRootUriFromDir($route_dir);
        $route_dir = $this->pageDir;
        $route_root_uri = $this->pageRootUri;
        $page_name = $this->pageName;
        // get curr url
        $current_url = $this->getRootUrl();
        //
        // IS NOT A SNIPPET
        if (!$snippet) {
            $key = "PAGE";
            if (!defined('PAGE')) { // prevent warning if build inside another build
                define("URL", $_APP["URL"]);
                define("PAGE", $page_name);
                define("PAGE_DIR", $route_dir);
                define("PAGE_YAML", $route_dir);
                define("PAGE_POST", "$current_url/.post");
                define("PAGE_EXEC", "$current_url/.exec");
                define("PAGE_RUN", "$current_url/.run");
                define("PAGE_URL", $_APP["URL"] . "/$route_root_uri");
            }
        } else {
            $key = "SNIPPET";
        }
        // set $_APP[PAGE] for a build inside another build, 
        // define is only for parent build
        $page_array_parts = explode("/", $route_root_uri);
        $_APP[$key] = array(
            "NAME" => $page_name,
            "DIR" => $route_dir,
            "POST" => "$current_url/.post",
            "RUN" => "$current_url/.run",
            "EXEC" => "$current_url/.exec",
            "URL" => $_APP["URL"] . "/$route_root_uri",
            "PARTS" => $page_array_parts
        );
        if ($snippet) {
            $_APP[$key]["PAR"] = @$snippet_params;
        }
        //$_BUILDS[] = $_APP["PAGE"]; // for obstart in show.sort
    }
}
