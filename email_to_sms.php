<?php
/************************************************************************/
// SMS To Email
// Take inbound email and forward the body as an SMS
// We are using Mailgun.com to host MX records and handle email for us
// Usage: 
//  File "contacts.csv" must be in same directory as this script. 
//  format of CSV is "e.164 formated phone number","email"
//  example: +15554442222,admin@mydomain.com
//
//  send email to 
//    <from number>@somedomain.com
//    subject: <to number>
//    body: <contents of SMS>
//    attachment: .gif, .jpg, or .png less than 5MB

//
// We currently load email / phone number mappings from a flat file 
// A better implementation would be to store that information in a database

require __DIR__ . '/vendor/autoload.php';
if (file_exists('./credentials.php')) {
    require_once('./credentials.php');
}

/************************************************************************/
// Credentials
//
// If you have not define() these in your credentials.php file, then 
// fill them in here.

if (!defined('BANDWIDTH_USER_ID')) {
    define('BANDWIDTH_USER_ID',     'u-xxxxxxxxxxxxxxxxxxxxxxx');    
    define('BANDWITH_API_TOKEN',    't-xxxxxxxxxxxxxxxxxxxxxxx');
    define('BANDWITH_API_SECRET',   'xxxxxxxxxxxxxxxxxxxxxxxxx');
    define('BANDWIDTH_API_URL',     "https://api.catapult.inetwork.com/v1/users/" . BANDWIDTH_USER_ID . "/");
    // Enter a URL here. If no callback desired, then leave blank
    define('BANDWIDTH_API_CALLBACK', ''); 
}
if (!defined('MAILGUN_KEY')) {
    define('MAILGUN_KEY',           'key-ZZZZZZZZZZZZZZZZZZZZZ');    
}
if (!defined('LOCALSERVER')) {
    // Server URL for file retreival
    define('LOCALSERVER',           'http://localhost');
}

// Email  <-> Phone Number Lookup Table
// This should be stored in a database.
// For this demo, we will load the data from a CSV
// format: +155544433333,email@mydomain.com
if (file_exists('contacts.csv')) {
    $smsMapping = loadCSV("contacts.csv");
} else {
    error_log("Failed to load contacts.csv");
    die();
}

/************************************************************************/
// Global Constants - Don't mess with this
date_default_timezone_set ('UTC');

/************************************************************************/
// Main Application
//
// Process inbound email notification from Mailgun. 
// - Match Sender to list of valid senders
// - Pull relevant fields from email.
// - Post to Bandwidth SMS

if (!empty($_POST["subject"])) {

    // extract phone number that we are sending SMS to.
    preg_match('/\+?1?-? *\.?\(?\d{3}\)?-?\.? *\d{3}-? *\.?\d{4}/', 
            $_POST["subject"], $smsTo);
    // format the to number into e.164
    $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
    $smsToE164 = $phoneUtil->format($phoneUtil->parse($smsTo[0], "US"),
                            \libphonenumber\PhoneNumberFormat::E164);

    // Extract the "from" number from the email address
    preg_match('/(\+?[0-9]+)@[a-zA-Z0-9]+/', $_POST["To"], $smsFrom);
    
    // format the from number into e.164
    // to-do: convert this to try/catch in case we receive a bad destination
    $smsFromE164 = $phoneUtil->format($phoneUtil->parse($smsFrom[1], "US"),
                            \libphonenumber\PhoneNumberFormat::E164);

    // Check that the phone number is an active number in our DB and matches the authorized email
    if (!isset($smsMapping[$smsFromE164])) {
        error_log ("Not a valid from number: " . $smsFromE164);
        die();
    } elseif ($smsMapping[$smsFromE164] != sanitizeEmailAddress($_POST["sender"])) {
        error_log ("Not a valid sender: " . $_POST["sender"]);
        die();
    }

	// Pull the first 1000 characters from the email body. Bandwidth will automatically break up
    // the SMS into multiple parts if it is longer than 160 characters. The Bandwidth API limit is 
    // 2048 characters.
    $smsBody = substr($_POST["stripped-text"], 0, 1000);

    // Send the SMS!
    $postData = array('to'  => $smsToE164,
                    'from'  => $smsFromE164,
                    'text' => $smsBody,
                    'callbackUrl' => BANDWIDTH_API_CALLBACK );

    // Attachment? Retreive media
    if(isset($_POST['attachments'])) {
        // Note that Mailgun passes attachments as a JSON list
        $mmsMediaUris = getMedia(json_decode($_POST['attachments']), true);
        if (!empty($mmsMediaUris)) {
            $postData['media'] = $mmsMediaUris;
        }
    }

    sendMessage($postData);
    
    if (!empty($mmsMediaUris)) {    
        // Lazy method to give Bandwidth enough time to download our files
        sleep(10); 

        // Delete the media from our local server
        deleteMedia($mmsMediaUris);
    }
        
}
else {
    error_log($_SERVER['PHP_SELF'] . " Email had an empty Subject line\n");
}


/************************************************************************/
// Supporting Functions

function sendMessage($array) {
    // We are expecting an array with minimally the following paramaters:
    //    "from" => $argv[1],
    //    "to" => $argv[2],
    //    "text" => $argv[3],
    // We'll blindly take any other valid paramaters passed in the array and
    // POST them to Bandwidth like media, callbackUrl, etc.


    // Pack the data into a JSON-friendly array
    $data_string = json_encode($array); 

    // Now we need to build the HTTP POST and execute it
    // Since we are createing an SMS, we need to POST to the "messages" resource
    $ch=curl_init(BANDWIDTH_API_URL ."messages");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);    // Stop if an error occurred
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    // We need to authenticate to Catapult here
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, BANDWIDTH_API_TOKEN . ":" . BANDWIDTH_API_SECRET);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data_string)
        )); 
    curl_exec($ch);
    curl_close($ch);

    // No error handling. Being lazy here.

}

function loadCSV($filename) {
    // Assume there is no header row.
    // Assume we are following the format of:
    // +15554442222,admin@mydomain.com
    $csvArray = array(); 
    if (($handle = fopen($filename, "r")) !== FALSE) {
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
           $csvArray["$row[0]"] = "$row[1]";
        }
        if (!feof($handle)) {
            error_log("Error: unexpected fgets() fail\n");
        }
    }
    fclose($handle);

    return $csvArray;
}

function deleteMedia($mediaUris) {
/* Takes an array of URIs from getMedia() and deletes the local file
* associated with each URI. 
*
* @param array $mediaUris - array with url-encoded resources we need to delete
*
*/
    foreach ($mediaUris as $key => $uri) {
        // Extract the filname
        $media = rawurldecode(substr(strrchr($uri, "/"),1));
        // Delete it
        unlink(realpath($media));
    }
    
}

function getMedia($attachmentList) {
/* Takes an array of attachments from MailGun and returns the
*  first valid media. 
*
* @param array $attachmentList - associative array with POST data from MailGun
*
* @return array $result - returns and associative array with keys 'url' 
*                   and 'name'. Returns FALSE if no results found
*/  
    // Let's define the list of media types we are willing to accept.
    // This list is smaller than the fully allowed MMS media list, but
    // should cover the majority of use cases.
    $allowedMedia = array("image/gif",
                        "image/jpeg",
                        "image/png");

    // Iterate through each attachment until we find an acceptable media type
    foreach ($attachmentList as $key => $mediaDetails) {       
        // We are only accepting attachments less than 5MB here
        // ToDo: throw error if aggregate of all attachments > 5MB
        if (in_array($mediaDetails->{'content-type'}, $allowedMedia) 
                && $mediaDetails->{'size'} < 5120000) {
        
            downloadMedia($mediaDetails);

            // PHP does not have a convenient variable to tell us our URL path. :(
            $path = substr($_SERVER['REQUEST_URI'], 0, (strlen($_SERVER['REQUEST_URI']) - strlen(strrchr($_SERVER['REQUEST_URI'], '/')) + 1));
            $result[] = LOCALSERVER . $path . rawurlencode($mediaDetails->{'name'});
        }
   }
   return $result;
}

function downloadMedia($mailgunMedia) {
/* Takes an array with Mailgun of media resource generated by the getMedia() function
*  and downloads the content to the local server
*
* @param array $array - Associative array generated by getMedia()
*           The array must have the following keys:
*           $mailgunMedia['url']
*           $mailgunMedia['name']
*/

    $ch = curl_init($mailgunMedia->{'url'});
    $fp = fopen($mailgunMedia->{'name'}, "xb");

    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);         // We don't want the response
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "api:" . MAILGUN_KEY);
    curl_setopt($ch, CURLOPT_VERBOSE, false); 
    curl_setopt($ch, CURLOPT_FILE, $fp);    // Write results to file
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($cp, $data) use ($fp) 
        { return fwrite($fp, $data); });

    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}
function sanitizeEmailAddress($emailString) {
/* Takes an email address and strips extranous information.
*  Currently strips:
*   - Strips BATV fields, if present. See https://en.wikipedia.org/wiki/Bounce_Address_Tag_Validation
*
* @param string $emailString - the raw "sender" string passed from Mailgun
*
* @return string $result - Sanitized email address
*/    
    preg_match('/(?:prvs=\S*=)?([a-zA-Z0-9.!#$%&’*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$)/', $emailString, $result);
    
    // The pattern match will contain the email address in Group 1
    return $result[1];
}
?>