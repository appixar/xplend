<?php
// Converte um nome para o formato "Title Case", deixando minúsculas
// as conjunções e preposições comuns (como "da", "de", "dos", etc).
function titleCase($name)
{
    $exceptions = ['da', 'de', 'do', 'das', 'dos', 'e'];
    $words = explode(' ', strtolower($name));
    foreach ($words as &$word) {
        if (!in_array($word, $exceptions)) {
            $word = ucfirst($word);
        }
    }
    return implode(' ', $words);
}
// Estados brasileiros
function uf()
{
    $uf = array(
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins'
    );
    return $uf;
}

// Limpa strings retirando símbolos e espaços
function clean($str)
{
    return preg_replace("/[^a-zA-Z0-9]/", "", $str);
}
// Gerar AppID usando UUID v4
function uuid4()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}
// Remover espaços
function cleanSpaces($str)
{
    return preg_replace('/\s+/', '', $str);
}
// Remover espaços extras
function removeExtraSpaces($string)
{
    return preg_replace('/ {2,}/', ' ', trim($string));
}
// Converter string em alfanumérico
function alphanumeric($data)
{
    // Substituir todos os caracteres que não sejam alfanuméricos por uma string vazia
    $formattedValue = preg_replace('/[^a-z0-9]/i', '', $data);

    // Converta a string resultante para minúsculas
    return strtolower($formattedValue);
}
// Verificar se a string é alfanumérica
function isAlphanumericOrUnderscore($string)
{
    // Verifica se a string está vazia
    if (empty($string)) {
        return false;
    }
    // Usa uma expressão regular para verificar se a string contém apenas letras, números e sublinhados
    return preg_match('/^[a-zA-Z0-9_]+$/', $string) === 1;
}
// Procurar valor em array associativo multidim, retornando a chave
function arrayFindKey($array, $value)
{
    foreach ($array as $k => $v) {
        if ($v == $value) {
            return $k;
        }
    }
}
// Hide chars from email
function emailObfuscate($email)
{
    $parts = explode('@', $email);
    $username = $parts[0];
    $domain = $parts[1];
    $username_length = strlen($username);

    // Exibir apenas a primeira e última letra do nome de usuário
    $username_hidden = $username[0] . str_repeat('*', $username_length - 2) . $username[$username_length - 1];

    return $username_hidden . '@' . $domain;
}
// Zeros a esquerda
function zeros($str, $total = 2)
{
    return str_pad($str, $total, "0", STR_PAD_LEFT);
}
// Remover subdominio
function getDomain()
{
    global $_SERVER;
    $d = explode(".", $_SERVER['SERVER_NAME']);
    if (count($d) > 2) {
        unset($d[0]);
    }
    $d = implode(".", $d);
    return $d;
}
// Verificar URL (tem http?)
function checkUrl($url)
{
    if (strpos($url, "http://") === false) {
        if (strpos($url, "https://") === false) {
            return false;
        }
    }
    return true;
}
// Procurar em uma string se contém pelo menos um valor (substring) de um array
// sem case-sensivite
function strFind($string, $find_array)
{
    foreach ($find_array as $k => $v) {
        if (stripos($string, $v) !== false) {
            return true;
        }
    }
    return false;
}
// Remover comentários do html
function removeHtmlComments($content = '')
{
    return preg_replace('/<!--(.|\s)*?-->/', '', $content);
}
// Copiar array inteiro para sessão
function sessionImportArray($arr, $prefix = "")
{
    global $_SESSION;
    foreach ($arr as $k => $v) {
        $_SESSION[$prefix . $k] = $v;
    }
}
function sessionClear()
{
    global $_SESSION;
    foreach ($_SESSION as $k => $v) unset($_SESSION[$k]);
}
// Preservar de $array apenas as chaves que importam ($keys), eliminar o restante no retorno.
// (xord envia campos a mais, esta função e economiza código se os campos recebidos estiverem formatados)
function preserveKeys($array = array(), $keys = array(), $all_keys_required = false)
{
    $return = array();
    foreach ($array as $k => $v) {
        if (in_array($k, $keys)) {
            $return[$k] = $v;
            // remove from $keys (util if $all_keys_req=true)
            if (($find = array_search($k, $keys)) !== false) {
                unset($keys[$find]);
            }
        }
    }
    if ($all_keys_required and $keys) return false;
    return $return;
}
// Substituir multiplas strings por array associativo de strings
function str_replace_assoc(array $replace, $subject)
{
    return str_replace(array_keys($replace), array_values($replace), $subject);
}
/* Use it for json_encode some corrupt UTF-8 chars
 * useful for = malformed utf-8 characters possibly incorrectly encoded by json_encode
 */
function utf8ize($mixed)
{
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = utf8ize($value);
        }
    } elseif (is_string($mixed)) {
        return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
    }
    return $mixed;
}
// Converter valores de array em utf8
// (dados recebidos de mysql sem codificação correta)
function utf8_encode_array($array)
{
    $return = array();
    // array multivetorial ?
    if (is_array($array[0])) {
        for ($i = 0; $i < count($array); $i++) {
            foreach ($array[$i] as $k => $v) {
                $return[$i][$k] = utf8_encode($v);
            }
        }
    }
    // array mono vetorial ?
    else {
        foreach ($array as $k => $v) {
            $return[$k] = utf8_encode($v);
        }
    }
    return $return;
}
// Remover acentos
function cleanAccents($string)
{
    return preg_replace(array("/(ç)/", "/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/", "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/", "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/"), explode(" ", "c a A e E i I o O u U n N"), $string);
}
// json_encode dá erro com acentos,
// usar esta função para corrigir
function raw_json_encode($input)
{
    return preg_replace_callback(
        '/\\\\u([0-9a-zA-Z]{4})/',
        function ($matches) {
            return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UTF-16');
        },
        json_encode($input)
    );
}
// Formatar numero de celular
function formatPhone($cel)
{
    $ddd = substr($cel, 0, 2);
    $prefix = substr($cel, 2, 5);
    $sufix = substr($cel, 7, 4);
    return "($ddd) $prefix-$sufix";
}
/* --------------------------------------------
* Buscar valor único em array múltiplo
* --------------------------------------------
*/
function mArraySearch($key, $value, $array, $return_id = false)
{
    for ($i = 0; $i < count($array); $i++) {
        if ($array[$i][$key] == $value) {
            if (!$return_id) {
                return true;
            } else {
                return $i;
            }
        }
    }
    return false;
}
/* --------------------------------------------
* Buscar valores multiplos em array múltiplo
* --------------------------------------------
* $array_find_this
*      = $find[nome]="joao";
*      = $find[idade]=27;
*
* $array_find_here
*      = $here[0][nome]=carlos
*      = $here[0][idade]=32
*      = $here[1][nome]=joao
*      = $here[1][idade]=27
*      ...
*/
function mArrayFind($this_, $here, $return_id = false)
{
    for ($i = 0; $i < count($here); $i++) {
        $notFound = 0;
        foreach ($this_ as $k => $v) {
            if ($here[$i][$k] != $v) {
                $notFound++;
            }
        }
        if ($notFound == 0) {
            if ($return_id) {
                return $i;
            } else {
                return true;
            }
        }
    }
    return false;
}
// Gerador de senhas
function randomString($tamanho = 6, $maiusculas = true, $numeros = true, $simbolos = false)
{
    $lmin = 'abcdefghijklmnopqrstuvwxyz';
    $lmai = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $num = '1234567890'; //0
    $simb = '!.,_@#$%*-?&|:;><=';
    $retorno = '';
    $caracteres = '';

    $caracteres .= $lmin;
    if ($maiusculas) {
        $caracteres .= $lmai;
    }
    if ($numeros) {
        $caracteres .= $num;
    }
    if ($simbolos) {
        $caracteres .= $simb;
    }

    $len = strlen($caracteres);
    for ($n = 1; $n <= $tamanho; $n++) {
        $rand = mt_rand(1, $len);
        $retorno .= $caracteres[$rand - 1];
    }
    return $retorno;
}
// ucwords for utf-8
function mb_ucwords($str)
{
    return mb_convert_case($str, MB_CASE_TITLE, "UTF-8");
}
function up($var)
{
    return strtoupper(strtr($var, "áéíóúâêôãõàèìòùç", "ÁÉÍÓÚÂÊÔÃÕÀÈÌÒÙÇ"));
}
function low($var)
{
    return strtolower(strtr($var, "ÁÉÍÓÚÂÊÔÃÕÀÈÌÒÙÇ", "áéíóúâêôãõàèìòùç"));
}
function fileurl()
{
    return basename($_SERVER['REQUEST_URI'], '?' . $_SERVER['QUERY_STRING']);
}
// A color dictionary of 269 maximally distinct colors from all previous colors
function randcolor($id, $max = true)
{
    global $randarray;
    if ($max) {
        $colors = array("#000000", "#FFFF00", "#1CE6FF", "#FF34FF", "#FF4A46", "#008941", "#006FA6", "#A30059", "#FFDBE5", "#7A4900", "#0000A6", "#63FFAC", "#B79762", "#004D43", "#8FB0FF", "#997D87", "#5A0007", "#809693", "#FEFFE6", "#1B4400", "#4FC601", "#3B5DFF", "#4A3B53", "#FF2F80", "#61615A", "#BA0900", "#6B7900", "#00C2A0", "#FFAA92", "#FF90C9", "#B903AA", "#D16100", "#DDEFFF", "#000035", "#7B4F4B", "#A1C299", "#300018", "#0AA6D8", "#013349", "#00846F", "#372101", "#FFB500", "#C2FFED", "#A079BF", "#CC0744", "#C0B9B2", "#C2FF99", "#001E09", "#00489C", "#6F0062", "#0CBD66", "#EEC3FF", "#456D75", "#B77B68", "#7A87A1", "#788D66", "#885578", "#FAD09F", "#FF8A9A", "#D157A0", "#BEC459", "#456648", "#0086ED", "#886F4C", "#34362D", "#B4A8BD", "#00A6AA", "#452C2C", "#636375", "#A3C8C9", "#FF913F", "#938A81", "#575329", "#00FECF", "#B05B6F", "#8CD0FF", "#3B9700", "#04F757", "#C8A1A1", "#1E6E00", "#7900D7", "#A77500", "#6367A9", "#A05837", "#6B002C", "#772600", "#D790FF", "#9B9700", "#549E79", "#FFF69F", "#201625", "#72418F", "#BC23FF", "#99ADC0", "#3A2465", "#922329", "#5B4534", "#FDE8DC", "#404E55", "#0089A3", "#CB7E98", "#A4E804", "#324E72", "#6A3A4C", "#83AB58", "#001C1E", "#D1F7CE", "#004B28", "#C8D0F6", "#A3A489", "#806C66", "#222800", "#BF5650", "#E83000", "#66796D", "#DA007C", "#FF1A59", "#8ADBB4", "#1E0200", "#5B4E51", "#C895C5", "#320033", "#FF6832", "#66E1D3", "#CFCDAC", "#D0AC94", "#7ED379", "#012C58", "#7A7BFF", "#D68E01", "#353339", "#78AFA1", "#FEB2C6", "#75797C", "#837393", "#943A4D", "#B5F4FF", "#D2DCD5", "#9556BD", "#6A714A", "#001325", "#02525F", "#0AA3F7", "#E98176", "#DBD5DD", "#5EBCD1", "#3D4F44", "#7E6405", "#02684E", "#962B75", "#8D8546", "#9695C5", "#E773CE", "#D86A78", "#3E89BE", "#CA834E", "#518A87", "#5B113C", "#55813B", "#E704C4", "#00005F", "#A97399", "#4B8160", "#59738A", "#FF5DA7", "#F7C9BF", "#643127", "#513A01", "#6B94AA", "#51A058", "#A45B02", "#1D1702", "#E20027", "#E7AB63", "#4C6001", "#9C6966", "#64547B", "#97979E", "#006A66", "#391406", "#F4D749", "#0045D2", "#006C31", "#DDB6D0", "#7C6571", "#9FB2A4", "#00D891", "#15A08A", "#BC65E9", "#FFFFFE", "#C6DC99", "#203B3C", "#671190", "#6B3A64", "#F5E1FF", "#FFA0F2", "#CCAA35", "#374527", "#8BB400", "#797868", "#C6005A", "#3B000A", "#C86240", "#29607C", "#402334", "#7D5A44", "#CCB87C", "#B88183", "#AA5199", "#B5D6C3", "#A38469", "#9F94F0", "#A74571", "#B894A6", "#71BB8C", "#00B433", "#789EC9", "#6D80BA", "#953F00", "#5EFF03", "#E4FFFC", "#1BE177", "#BCB1E5", "#76912F", "#003109", "#0060CD", "#D20096", "#895563", "#29201D", "#5B3213", "#A76F42", "#89412E", "#1A3A2A", "#494B5A", "#A88C85", "#F4ABAA", "#A3F3AB", "#00C6C8", "#EA8B66", "#958A9F", "#BDC9D2", "#9FA064", "#BE4700", "#658188", "#83A485", "#453C23", "#47675D", "#3A3F00", "#061203", "#DFFB71", "#868E7E", "#98D058", "#6C8F7D", "#D7BFC2", "#3C3E6E", "#D83D66", "#2F5D9B", "#6C5E46", "#D25B88", "#5B656C", "#00B57F", "#545C46", "#866097", "#365D25", "#252F99", "#00CCFF", "#674E60", "#FC009C", "#92896B");
    } else {
        $colors = array(
            "#FF0000",
            "#00FF00",
            "#0000FF",
            "#FFFF00",
            "#FF00FF",
            "#00FFFF",
            "#000000",
            "800000",
            "#008000",
            "#000080",
            "#808000",
            "#800080",
            "#008080",
            "#808080",
            "C00000",
            "#00C000",
            "#0000C0",
            "#C0C000",
            "#C000C0",
            "#00C0C0",
            "#C0C0C0",
            "400000",
            "#004000",
            "#000040",
            "#404000",
            "#400040",
            "#004040",
            "#404040",
            "200000",
            "#002000",
            "#000020",
            "#202000",
            "#200020",
            "#002020",
            "#202020",
            "600000",
            "#006000",
            "#000060",
            "#606000",
            "#600060",
            "#006060",
            "#606060",
            "A00000",
            "#00A000",
            "#0000A0",
            "#A0A000",
            "#A000A0",
            "#00A0A0",
            "#A0A0A0",
            "E00000",
            "#00E000",
            "#0000E0",
            "#E0E000",
            "#E000E0",
            "#00E0E0",
            "#E0E0E0"
        );
    }

    if (!$randarray[$id]) {
        $randarray[$id] = $colors;
    }
    $r = array_rand($randarray[$id]);
    $return = $randarray[$id][$r];
    unset($randarray[$id][$r]);
    return $return;
}
function validaCPF($cpf)
{
    // Extrai somente os números
    $cpf = preg_replace('/[^0-9]/is', '', $cpf);

    // Verifica se foi informado todos os digitos corretamente
    if (strlen($cpf) != 11) {
        return false;
    }

    // Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    // Faz o calculo para validar o CPF
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}
function validaCNPJ($cnpj)
{
    // Remove caracteres indesejados
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

    // Verifica se tem o tamanho correto
    if (strlen($cnpj) !== 14) return false;

    // Verifica sequências inválidas
    for ($t = 0; $t < 10; $t++) {
        if ($cnpj === str_repeat((string)$t, 14)) {
            return false;
        }
    }

    // Calcula e verifica o primeiro dígito verificador
    for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    $resto = $soma % 11;
    if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) return false;

    // Calcula e verifica o segundo dígito verificador
    for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    $resto = $soma % 11;
    return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
}
function validaMail($email)
{
    $er = "/^(([0-9a-zA-Z]+[-._+&])*[0-9a-zA-Z]+@([-0-9a-zA-Z]+[.])+[a-zA-Z]{2,6}){0,1}$/";
    if (preg_match($er, $email)) {
        return true;
    } else {
        return false;
    }
}
//--------------
// PRE FUNCTION
//--------------
function pre($array, $title = "")
{
    echo "<pre>";
    echo "<strong>*** $title</strong><br/><br/>";
    echo print_r($array);
    echo "</pre>";
}
function prex($array)
{
    global $_HEADER;
    if (@$_HEADER) {
        echo json_encode($array);
        exit;
    } else {
        echo "<pre>";
        echo print_r($array);
        echo "</pre>";
        exit;
    }
}
