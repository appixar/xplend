<?php
class Routes extends Xplend
{
    public $error = false;
    public $error_code = false;
    public $res = false;
    public $body = [];
    public $params = [];

    // required fields
    public function required($requiredFields)
    {
        // CHECK REQUIRED FIELDS
        if ($requiredFields) {
            if (is_array($requiredFields)) $fields = $requiredFields;
            else $fields = explode(",", $requiredFields);
            foreach ($fields as $field) {
                $field = trim($field);
                if (!@$this->body[$field]) Http::die(400, "Missing required field: $field");
            }
        }
    }
    // error return
    public function error($error = '', $error_code = 406)
    {
        $this->error = $error;
        $this->error_code = $error_code;
        return false;
    }
    // success return
    public function res($data = [])
    {
        $this->res = $data;
        return $this->res;
    }
    // controller return
    public function return($objectFromController)
    {
        if ($objectFromController->error) return $this->error($objectFromController->error);
        elseif ($objectFromController->res) return $this->res($objectFromController->res);
        else return false;
    }
}
class Controllers extends Xplend
{
    public $error = false;
    public $error_code = false;
    public $res = false;
    //
    public function error($error = '', $error_code = 406)
    {
        $this->error = $error;
        $this->error_code = $error_code;
        return false;
    }
    // required fields
    public function required($requiredFields, $data)
    {
        // CHECK REQUIRED FIELDS
        if ($requiredFields) {
            if (is_array($requiredFields)) $fields = $requiredFields;
            else $fields = explode(",", $requiredFields);
            foreach ($fields as $field) {
                $field = trim($field);
                if (!@$data[$field]) Http::die(400, "Missing required field: $field");
            }
        }
    }
    public function res($data = [])
    {
        $this->res = $data;
        return $this->res;
    }
    public function now()
    {
        return date("Y-m-d H:i:s");
    }
}
class Services extends Xplend
{
    public $error = false;
    public $error_code = false;
    public $res = false;
    //
    public function error($error = '')
    {
        $this->error = $error;
        return false;
    }
    public function res($data = [])
    {
        $this->res = $data;
        return $this->res;
    }
    public function now()
    {
        return date("Y-m-d H:i:s");
    }
}
