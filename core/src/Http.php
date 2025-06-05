<?php
class Http extends Xplend
{
    public function __construct() {}
    // check header authentication
    public static function auth($rules = array())
    {
        global $_APP, $_AUTH;
        $_AUTH = false;

        // AUTH MODULE
        $module = @$_APP['API_SERVER']['AUTH_CONTROLLER'];
        if ($module) {
            try {
                $res = new $module($rules);
            } catch (Error $e) {
                Http::die(406, "Auth controller not found: $module");
            }
            return $res;
        }
    }
    //
    public static function route($conf)
    {
        global $_AUTH, $_APP, $_HEADER, $_PAR, $_URI;

        // Build Headers
        if (@!$_HEADER['method']) {
            Api::buildApiHeaders();
        }

        // DEFAULT CONF
        if (!@$conf['controller']) Http::die(406, 'Route controller is missing');
        if (!@$conf['data']) $conf['data'] = 'body';

        // DATA FROM BODY OR POST?
        switch ($conf['data']) {
            case 'body':
                $data = Http::body();
                break;
            case 'post':
                $data = Http::post();
                break;
            case 'get':
                $data = $_GET;
                break;
        }

        // AUTH
        Http::auth($conf);

        // BUGFIX
        if (!empty($_GET)) $data = $_GET;
        //prex($data);

        // AUTO SANITIZE DATA
        if ($data) {
            if (@$_APP['API_SERVER']['SANITIZE_CONTROLLER']) {
                $sanitize_controller = explode(".", $_APP['API_SERVER']['SANITIZE_CONTROLLER'])[0];
                $sanitize_function = explode(".", $_APP['API_SERVER']['SANITIZE_CONTROLLER'])[1];
                if (!$sanitize_function) Http::die(406, "Sanitize method not found: $sanitize_controller.?");
                if (!method_exists($sanitize_controller, $sanitize_function)) {
                    Http::die(406, "Sanitize method not found: $sanitize_controller.$sanitize_function");
                }
                $sanitize_object = new $sanitize_controller();
                $data = $sanitize_object->$sanitize_function($data);
                if (@$sanitize_object->error) Http::die(406, $sanitize_object->error);
            }
            // SANITIZE CUSTOM FIELDS
            $functionPrefix = @$_APP['API_SERVER']['SANITIZE_FUNCTION_PREFIX'];
            if ($functionPrefix) {
                $data = self::sanitizeFunctions($data);
                if (@$data['validation_error']) Http::die(406, $data['validation_error']);
            }
        }

        // GET ROUTE MODULE NAME
        $class = explode('.', $conf['controller'])[0];

        // LOAD ROUTE MODULE
        try {
            $controller = new $class();
        } catch (Error $e) {
            Http::die(406, "Class not found: $class");
        }
        // EXPLICIT FUNCTION = ROUTE MODULE::FUNCTION NAME
        $function = @explode('.', $conf['controller'])[1];
        // ENDPOINT FUNCTION = URL/ROUTE/SMART_FUNCTION
        //prex($_PAR);
        if (!$function and @$_PAR[0]) {
            $endpointFunction = $_PAR[0];
            $endpointFunction = str_replace("-", "_", $endpointFunction);
            if (method_exists($class, $endpointFunction)) $function = $endpointFunction;
        } elseif (!$function) $function = low($_HEADER['method']);

        // Return
        if ($function) {
            // not found
            if (!method_exists($class, $function)) {
                Http::die(406, "Method not found: $class.$function");
            }
            // save data
            $controller->body = $data;
            if ($conf['params']) $controller->params = $conf['params'];
            // success
            $return = @$controller->$function($data);
            if (@$controller->res or is_array($controller->res)) Http::success($controller->res);
            elseif ($return) Http::success($return);
            // error
            else {
                if (!@$controller->error_code) $controller->error_code = 406;
                if (!@$controller->error) $controller->error = "Unknown error";
                Http::die($controller->error_code, $controller->error);
            }
        } else Http::die(406, "Empty controller function");
    }
    public static function get($params = "")
    {
        return self::reqType("GET", $params);
    }
    public static function put($params = "")
    {
        return self::reqType("PUT", $params);
    }
    public static function delete($params = "")
    {
        return self::reqType("DELETE", $params);
    }
    public static function post($params = "")
    {
        return self::reqType("POST", $params);
    }
    private static function checkMethod($type)
    {
        global $_HEADER;
        //echo "{$_HEADER['method']} $type";
        if ($_HEADER['method'] and $_HEADER['method'] !== $type) Http::die(405);
    }
    private static function reqType($type, $params = "")
    {
        self::checkMethod($type);
        return self::req($params);
    }
    // return params in array
    public static function req($params = "")
    {
        global $_PAR;
        if ($params == "") return true;
        $req = array();
        $e = explode("/", $params);
        for ($i = 0; $i < count($e); $i++) {
            $name = $e[$i];
            // param. optional
            if (strpos($name, '[') > -1) {
                $name = str_replace('[', '', $name);
                $name = str_replace(']', '', $name);
                $req[$name] = @$_PAR[$i];
            }
            // param. required
            else {
                if (!@$_PAR[$i]) Http::die(400, 'Missing parameters.');
                $req[$name] = $_PAR[$i];
            }
        }
        return $req;
    }
    public static function die($num = 500, $msg = '')
    {
        global $_APP;
        if ($num == 400) $str = 'Bad request';
        if ($num == 401) $str = 'Unauthorized'; // Your API key is wrong.
        if ($num == 402) $str = 'Payment required';
        if ($num == 403) $str = 'Forbidden'; // The kitten requested is hidden for administrators only.
        if ($num == 404) $str = 'Not found';
        if ($num == 405) $str = 'Method not allowed'; // You tried to access a kitten with an invalid method.
        if ($num == 406) $str = 'Not Acceptable'; // Received json data error format
        if ($num == 429) $str = 'Too Many Requests'; // You're requesting too many kittens! Slow down!
        if ($num == 500) $str = 'Internal Server Error'; // We had a problem with our server. Try again later.
        if ($num == 503) $str = 'Service Unavailable'; // We're temporarily offline for maintenance. Please try again later.
        if (@$_APP['API_SERVER']['ALWAYS_200'] === true) header("HTTP/1.1 200");
        header("HTTP/1.1 $num $str");
        if ($msg) $str = addslashes(strip_tags($msg));
        $json = json_encode(array(
            'error' => $num,
            'message' => $str
        ));
        die($json);
    }
    public static function success($msg = '')
    {
        global $_APP;
        header("HTTP/1.1 200");
        $json = [];
        // SUCCESS INDICATOR
        if (@$_APP['API_SERVER']['JSON_RESULT_INDICATOR'] == true) {
            $json['success'] = 1;
            $json['data'] = [];
            if ($msg) {
                if (is_array($msg)) {
                    foreach ($msg as $k => $v) $json['data'][$k] = $v;
                } elseif (gettype($msg) === 'string') $json['message'] = addslashes(strip_tags($msg));
            }
        }
        // ONLY DATA
        else {
            if ($msg) {
                if (is_array($msg)) {
                    foreach ($msg as $k => $v) $json[$k] = $v;
                } elseif (gettype($msg) === 'string') $json['message'] = addslashes(strip_tags($msg));
            }
        }
        $json_encoded = json_encode($json, true);
        // TRY FIX JSON MALFORMED
        if (json_last_error_msg() != 'No error') {
            if (json_last_error_msg() == 'Malformed UTF-8 characters, possibly incorrectly encoded') {
                $json_encoded = json_encode(utf8ize($json));
            }
        }
        // TRY FAIL
        if ($json_encoded == 'null' and json_last_error_msg()) Http::die(500, 'JSON format error: ' + json_last_error_msg());
        elseif ($json_encoded == 'null') Http::die(500, 'JSON format error');
        // RETURN
        die($json_encoded);
    }
    public static function body()
    {
        global $_HEADER, $_BODY;
        if ($_HEADER['method'] == 'GET') {
            $input = $_GET;
        }
        if (!@$input or $_HEADER['method'] == 'POST') {
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, TRUE); //convert JSON into array
        }
        $_BODY = $input;
        return $input;
    }
    public static function sanitizeFunctions($receivedData = [])
    {
        global $_APP;
        $prefix = @$_APP['API_SERVER']['SANITIZE_FUNCTION_PREFIX'];
        $fields = Database::getAllFields();
        $validatedData = $receivedData;
        foreach ($receivedData as $fieldName => $fieldValue) {
            // IS NOT DB FIELD
            if (!@$fields[$fieldName]) {
                $validatedData[$fieldName] = $fieldValue;
                continue;
            }
            if (!$fieldValue) continue;
            $params = explode(" ", $fields[$fieldName]);
            foreach ($params as $param) {
                $functionName = "{$prefix}{$param}";
                //echo "$functionName/";
                if (function_exists($functionName)) {
                    $validatedData[$fieldName] = $functionName($fieldValue, $fieldName);
                }
                // validate error?
                if (@isset($validatedData[$fieldName]['error'])) return ['validation_error' => $validatedData[$fieldName]['error']];
            }
        }
        return $validatedData;
    }
}
