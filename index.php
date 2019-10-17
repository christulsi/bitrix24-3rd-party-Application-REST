<?
function redirect($url)
{
    Header("HTTP 302 Found");
    Header("Location: ".$url);
    die();
}

define('APP_ID', 'local.5d9cecd8a01469.24539712'); // Application ID. Taken from Bitrix24 after adding a new application
define('APP_SECRET_CODE', 'AMKGjTcGj8y8xD22b2DQD9l4ad2KHfqdQZDKzKKrDJW21iSsE6'); // Application Secret. Taken from Bitrix24 after adding a new application
define('APP_REG_URL', 'http://172.17.0.2/report/'); // the same URL you should set when adding a new application in Bitrix24


$domain = isset($_REQUEST['portal']) ? $_REQUEST['portal'] : ( isset($_REQUEST['domain']) ? $_REQUEST['domain'] : 'empty');

$step = 0;

if (isset($_REQUEST['portal'])) $step = 1;
if (isset($_REQUEST['code']))$step = 2;

$btokenRefreshed = null;

$arScope = array('user, entity'); // the scope of the application, it can be found when creating the application in the  

switch ($step) {
    case 1:
        // we need to get the first authorization code from Bitrix24 where our application is _already_ installed
        requestCode($domain);
        break;

    case 2:
        //we've got the first authorization code and use it to get an access_token and a refresh_token (if you need it later)
        echo "step 2 (getting an authorization code):<pre>";
        print_r($_REQUEST);
        echo "</pre><br/>";

        $arAccessParams = requestAccessToken($_REQUEST['code'], $_REQUEST['server_domain']);
        echo "step 3 (getting an access token):<pre>";
        print_r($arAccessParams);
        echo "</pre><br/>";

        $arCurrentB24User = executeREST($arAccessParams['client_endpoint'], 'user.current', array(),
            $arAccessParams['access_token']);
        echo "step 4 (getting an current user data using the token):<pre>";
        print_r($arCurrentB24User);
        echo "</pre><br/>";

        //Bitrix seem to restrict access to infomation block for 3rd access, 3rd Party apps can only access data under the MP Applications entity Storage folder using their application ID as the mnemonic name
        $stores = executeREST($arAccessParams['client_endpoint'], 'entity.item.get', array('ENTITY' => 'Mnemonic code', 'NAME' => 'name'),
            $arAccessParams['access_token']);
        echo "step 5 (getting an storesrequest data back using the restAPI):<pre>";
        print_r($stores);
        echo "</pre><br/>";

        $storesitem = executeREST($arAccessParams['client_endpoint'], 'entity.item.property.get', array('ENTITY' => 'Mnemonic code', 'NAME' => 'name'),
            $arAccessParams['access_token']);
        echo "step 6 (getting an storesItems data back using the restAPI):<pre>";
        print_r($storesitem);
        echo "</pre><br/>";


        break;
    default:
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quick start. Local server-side application without UI in Bitrix24</title>

</head>
<body>
<?if ($step == 0) {?>
    step 1 (redirecting to Bitrix24):<br/>
    <form action="" method="post">
        <input type="text" name="portal" placeholder="Bitrix24 URL">
        <input type="submit" value="Authorize">
    </form>
    <?
}
elseif ($step == 2) {
    echo $arCurrentB24User["result"]["NAME"] . " " . $arCurrentB24User["result"]["LAST_NAME"];
}
?>
</body>
</html>
<?

//Making a request to Bitrix RestAPI
function executeHTTPRequest ($queryUrl, array $params = array()) {
    $result = array();
    $queryData = http_build_query($params);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));
    $curlResult = curl_exec($curl);
    curl_close($curl);

    if ($curlResult != '') $result = json_decode($curlResult, true);

    return $result;
}
//Making a request to your bitrix server  /oauth/authorize/ to retrive the authorization code
function requestCode ($domain) {
    $url = 'https://' . $domain . '/oauth/authorize/' .
        '?client_id=' . urlencode(APP_ID);
    redirect($url);
}

//Making a request to your bitrix server  /oauth/token/ to retrive the acess token
function requestAccessToken ($code, $server_domain) {
    $url = 'https://' . $server_domain . '/oauth/token/?' .
        'grant_type=authorization_code'.
        '&client_id='.urlencode(APP_ID).
        '&client_secret='.urlencode(APP_SECRET_CODE).
        '&code='.urlencode($code);
    return executeHTTPRequest($url);
}
//Making a request to your bitrix server RestAPI using the access token and method
function executeREST ($rest_url, $method, $params, $access_token) {
    $url = $rest_url.$method.'.json';
    return executeHTTPRequest($url, array_merge($params, array("auth" => $access_token)));
}