<?php
class Cache extends Xplend
{
    public $status; // connection status
    public $redis; // instance
    public function __construct()
    {
        global $_APP_VAULT;
        $this->redis = new Redis();
        $this->status = false;
        try {
            if (@$_APP_VAULT['CACHE']['TLS']) {
                $this->redis->pconnect(
                    'tls://' . $_APP_VAULT['CACHE']['HOST'],
                    $_APP_VAULT['CACHE']['PORT'],
                    2.5
                );
            } else $this->redis->connect(@$_APP_VAULT['CACHE']['HOST'], @$_APP_VAULT['CACHE']['PORT']);
            if (@$_APP_VAULT['CACHE']['PASS']) {
                if (@$_APP_VAULT['CACHE']['USER']) $this->redis->auth(['user' => @$_APP_VAULT['CACHE']['USER'], 'pass' => $_APP_VAULT['CACHE']['PASS']]);
                else $this->redis->auth($_APP_VAULT['CACHE']['PASS']);
            }
            $this->status = true;
        } catch (Exception $e) {
            #echo "Não foi possível conectar ao Redis: ", $e->getMessage();
            Xplend::err("Redis", $e->getMessage());
            #$this->status = false;
        }
    }
    static function render($exp = 0, $condition = [])
    {
        global $_CACHE, $_APP;
        if (!@$_APP['CACHE']['ENABLED']) return false;
        if (is_array($condition)) $condition = json_encode($condition);
        $url = "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        $key = "cache|" . $condition . "|" . $url;
        $_CACHE = [
            'exp' => $exp,
            'key' => $key
        ];
        $cache = new Cache();
        $data = $cache->get($key);
        if ($data) {
            echo "cached";
            echo $data;
            exit;
        }
    }
    public function get($key)
    {
        if ($this->status) {
            $data = $this->redis->get($key);
            // is json? return array.
            $tryJsonDecoded = @json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $tryJsonDecoded;
            }
            // return string.
            else return $data;
        } else return false;
    }
    public function set($key, $val, $exp = 0)
    {
        if ($this->status) {
            if (is_array($val)) $val = json_encode($val);
            if ($exp) return $this->redis->setex($key, $exp, $val);
            else $this->redis->set($key, $val);
        } else return false;
    }
    public function getAll()
    {
        $data = [];
        if ($this->status) {
            $allKeys = $this->redis->keys('*');
            foreach ($allKeys as $key) {
                $data[$key] = $this->get($key);
            }
        }
        return $data;
    }
    public function update($key, $array_part = [], $exp = 0)
    {
        if ($this->status) {
            $currentVal = $this->redis->get($key);
            foreach ($array_part as $k => $v) {
                $currentVal[$k] = $v;
            }
            if ($exp) return $this->redis->setex($key, $exp, $currentVal);
            else $this->redis->set($key, $currentVal);
        } else return false;
    }
    public function flush()
    {
        return $this->redis->flushAll();
    }
}
