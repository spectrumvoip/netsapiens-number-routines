#!/usr/bin/php -q
<?PHP

$debug = ( (isset($argv[2]) ) ? $argv[2] : 0);

ini_set("memory_limit","500M");
error_reporting(E_ALL);
ini_set('display_errors', '1');

if ( isset($argv[1]) ) {
        $action      = $argv[1];
} else {
        echo "\n        usage:    $argv[0] <auto> <debug:0|1\n\n";
        exit;
}

$mailTo = 'your_email@your_domain.com';
$mailFrom = 'your_from_email@your_domain.com';

$cata_username = 'your_catapult_username';
$cata_password = 'your_catapult_password';

$cata_applicationid = 'your_catapult_applicationid';
$cata_providername  = 'your_catapult_provider_name';
$cata_provideraccountid = 'your_catapult_accountid';
$cata_providerusername = 'your_catapult_providerusername';
$cata_providerpassword = 'your_catapult_providerpassword';

$cata_apiuserid   = 'your_api_userid';
$cata_apitoken    = 'your_api_token';
$cata_apisecret   = 'your_api_secret';

# Get a token
$ns_url = "https://your_netsapiens_server.com/ns-api/";
$ns_client_id     = 'your_ns_client_id';
$ns_client_secret = 'your_ns_client_secret';
$ns_username      = 'your_ns_username';
$ns_password      = 'your_ns_password';
$ns_vars          = '';
$server_output = get_token($ns_vars, "https://your_netsapiens_server.com/ns-api/oauth2/token/", $ns_client_id, $ns_client_secret, $ns_username, $ns_password);
$token = $server_output->access_token;
$rtoken = $server_output->refresh_token;

if( $debug >= 2 ) print_r($server_output);

$body = "empty";
$output = "Success";

switch ( $action ) {
 case "auto":

   $ns_vars['object']   = "phonenumber";
   $ns_vars['format']   = "json";
   $ns_vars['action']   = "read";
   $ns_vars['dialplan'] = "DID Table";

   $phonenumbers = get_data($ns_vars, $url, $token);

   if ( $debug >=3 ) print_r($phonenumbers);

   foreach ( $phonenumbers as $x => $y ) {
    $body = "";
    $number = str_replace("@*", "", str_replace("sip:1", "", $y->matchrule));
    $subject = "Number Routines : ST1 : Add : SMS : 1$number";
    $to_user = $y->to_user;
    $to_host = $y->to_host;
    $domain_owner = $y->domain_owner;
    $plan_description = $y->plan_description;
    if ( preg_match('/addsms/i', $plan_description) ) {
     if ( $debug >= 1 ) echo "$number $plan_description\n";

     if ( $debug >= 1 ) print_r($y);

     $cata_added = 1;
     $cata_output = cata_sms_add_number($number);
     if ( $debug >= 2 ) print_r($cata_output);
     if ( isset($cata_output->code) ) {
      switch ( $cata_output->code ) {
       case 'duplicate-number':
        if ( $debug >= 2 ) print_r($cata_output);
        $cata_update = cata_sms_update_number($number, $debug);
        $output = print_r($cata_update,1);
        $new_plan_description = trim(str_ireplace("addsms", "smsAdded", $y->plan_description));
        break;
       default:
        $cata_added = 0;
        $output = $cata_output->message . "\n\n";
        $output .= print_r($cata_output,1);
        $new_plan_description = trim(str_ireplace("addsms", "smsNotAdded", $y->plan_description));
        break;
      } # switch
     }
     if ( $cata_added ) {
      unset($ns_vars);
      $ns_vars['object']   = "smsnumber";
      $ns_vars['format']   = "json";
      $ns_vars['action']   = "update";
      $ns_vars['domain']   = $y->domain_owner;
      $ns_vars['number']   = "1" . $number;
      $ns_vars['dest']     = $y->to_user;
      $sms_read = get_data($ns_vars, $url, $token);
      if ( $debug >= 2 ) print_r($sms_read);
      $new_plan_description = trim(str_ireplace("addsms", "smsAdded", $y->plan_description));

      unset($ns_vars);
      $ns_vars['object']   = "phonenumber";
      $ns_vars['format']   = "json";
      $ns_vars['action']   = "update";
      $ns_vars['matchrule'] = $y->matchrule;
      $ns_vars['plan_description'] = $new_plan_description;
      $ns_vars['dialplan']         = $y->dialplan;
      $ns_vars['dest_domain']           = $y->domain;
      $ph_updated = get_data($ns_vars, $url, $token);
      if ( $debug >= 2 ) print_r($ph_updated);
      $body = $output;
     }
     send_csv_mail ('', $body, $mailTo, $subject, $mailFrom, '');
    }
   } # foreach
  break;
 default:
 echo "Failure: Action not found\n";
} # switch

function cata_sms_update_number($tn, $debug=0) {

 global $cata_apiuserid;
 global $cata_applicationid;
 global $cata_providername;
 global $cata_provideraccountid;
 global $cata_providerusername;
 global $cata_providerpassword;
 global $cata_apitoken;
 global $cata_apisecret;

 $url = "https://your_catapult_server.com/v1/users/$cata_apiuserid/phoneNumbers/$tn";

 //Initiate cURL.
 $ch = curl_init($url);

 //The JSON data.
 $jsonData['number'] = $tn;
 $jsonData['applicationId'] = $cata_applicationid;

 //Encode the array into JSON.
 $jsonDataEncoded = json_encode($jsonData);

 //Tell cURL our username and password
 curl_setopt($ch,CURLOPT_USERPWD, "$cata_apitoken:$cata_apisecret");

 //Tell cURL that we want to send a POST request.
 curl_setopt($ch, CURLOPT_POST, 1);

 //Attach our encoded JSON string to the POST fields.
 curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);

 //Set the content type to application/json
 curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

 //Set curl so it doesn't output
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

 //Execute the request
 $result = curl_exec($ch);

if ( $debug >= 2 ) echo "$result\n";

 if ( $result ) {
  $returner = json_decode($result);
 } else {
  $returner = "Success: Updated $tn to Catapult SMS\n";
 }

 //close connection
 curl_close($ch);

 return $returner;
}

function cata_sms_add_number($tn) {

 global $cata_apiuserid;
 global $cata_applicationid;
 global $cata_providername;
 global $cata_provideraccountid;
 global $cata_providerusername;
 global $cata_providerpassword;
 global $cata_apitoken;
 global $cata_apisecret;

 $url = "https://your_catapult_server.com/v1/users/$cata_apiuserid/phoneNumbers/";

 //Initiate cURL.
 $ch = curl_init($url);

 //The JSON data.
 $jsonData['number'] = $tn;
 $jsonData['applicationId'] = $cata_applicationid;
 $jsonData['provider']['providerName'] = $cata_providername;
 $jsonData['provider']['properties']['accountId'] = $cata_provideraccountid;
 $jsonData['provider']['properties']['userName'] = $cata_providerusername;
 $jsonData['provider']['properties']['password'] = $cata_providerpassword;

 //Encode the array into JSON.
 $jsonDataEncoded = json_encode($jsonData);

 //Tell cURL our username and password
 curl_setopt($ch,CURLOPT_USERPWD, "$cata_apitoken:$cata_apisecret");

 //Tell cURL that we want to send a POST request.
 curl_setopt($ch, CURLOPT_POST, 1);

 //Attach our encoded JSON string to the POST fields.
 curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);

 //Set the content type to application/json
 curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

 //Set curl so it doesn't output
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

 //Execute the request
 $result = curl_exec($ch);

 if ( $result ) {
  $returner = json_decode($result);
 } else {
  $returner = "Success: Added $tn to Catapult SMS\n";
 }

 //close connection
 curl_close($ch);

 return $returner;
}

function get_data($vars, $url, $token) {

 global $debug;

 if ( $debug >= 2 ) {
  print_r($vars);
  print_r($url);
  echo "\n";
  print_r($token);
  echo "\n";
 }

 $ch = curl_init();
 curl_setopt($ch, CURLOPT_URL, $url);
 curl_setopt($ch, CURLOPT_POST, 1);
 curl_setopt($ch, CURLOPT_POSTFIELDS,$vars);  //Post Fields
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

 $headers[] = "Authorization: Bearer $token";

 curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

 $server_output = curl_exec ($ch);
 $success = curl_getinfo($ch, CURLINFO_HTTP_CODE);

 if($server_output === false || $success != "200") {
  echo "API returned a $success " . curl_error($ch) . " when trying to obtain data\n";
  exit;
 }

 curl_close ($ch);

 return json_decode($server_output);

}

function get_token($vars, $url="https://your_netsapiens_server.com/ns-api/oauth2/token/", $client_id, $client_secret, $username, $password) {

 $vars = Array('format' => 'json', 'grant_type' => 'password', 'client_id' => $client_id, 'client_secret' => $client_secret, 'username' => $username, 'password' => $password);

 $ch = curl_init();
 curl_setopt($ch, CURLOPT_URL, $url);
 curl_setopt($ch, CURLOPT_POST, 1);
 curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);  //Post Fields
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

 $returner = curl_exec($ch);

 $success = curl_getinfo($ch, CURLINFO_HTTP_CODE);

 if($returner === false || $success != "200") {
  echo "API returned a $success " . curl_error($ch) . " when trying to obtain a token\n";
  exit;
 }

 curl_close ($ch);

 $returner = json_decode($returner);

 return $returner;

}

function send_csv_mail ($csvData, $body, $to = 'your_email@your_domain.com', $subject = 'Number Routines email', $from = 'your_from_email@your_domain.com', $filename='file.csv') {

  // This will provide plenty adequate entropy
  $multipartSep = '-----'.md5(time()).'-----';

  // Arrays are much more readable
  $headers = array(
    "From: $from",
    "Reply-To: $from",
    "Content-Type: multipart/mixed; boundary=\"$multipartSep\""
  );

  // Make the attachment
#  $attachment = chunk_split(base64_encode(create_csv_string($csvData)));
  $attachment = chunk_split(base64_encode($csvData));

  // Make the body of the message
  $body = "--$multipartSep\r\n"
        . "Content-Type: text/html; charset=ISO-8859-1; format=flowed\r\n"
        . "Content-Transfer-Encoding: 7bit\r\n"
        . "\r\n"
        . "$body\r\n"
        . "--$multipartSep\r\n"
        . "Content-Type: text/txt\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . "Content-Disposition: attachment; filename=\"$filename\"\r\n"
        . "\r\n"
        . "$attachment\r\n"
        . "--$multipartSep--";

   // Send the email, return the result
   return @mail($to, $subject, $body, implode("\r\n", $headers));

}

exit;

?>
