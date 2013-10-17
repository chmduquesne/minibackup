<?php
/*
 * Copyright (c) 2013 Christophe-Marie Duquesne (chmd.fr)
 *
 * License: MIT

 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

require_once "lib/serversalt.php";

/*
 * Generates a pseudo-random data identifier, from the data, the time, and
 * the ip
 */
function generate_key($data) {
    $t = time();
    $ip = $_SERVER['REMOTE_ADDR'];
    $hash = hash_hmac('sha1', $data , $t . $ip);
    return substr($hash, 0, 16);
}

/*
 * Directory where to store a given key
 *
 * The idea (stolen from Zerobin) is to create subdirectories in order to
 * limit the number of files per directory.
 * (A high number of files in a single directory can slow things down.)
 * eg. "f468483c313401e8" will be stored in "data/f4/68/f468483c313401e8"
 */
function key2dir($key) {
    return 'data/' . substr($key,0,2) . '/' . substr($key,2,2) . '/';
}

/*
 * Make sure the IP address makes at most 1 request per second.
 * Will return false if IP address made a request less than 1 second ago.
 */
function must_wait($ip) {
    $logfile = './data/ip_logs.php';
    if (!is_file($logfile)) {
        file_put_contents($logfile, "<?php\n\$GLOBALS['ip_logs']=array();\n?>");
        chmod($logfile,0705);
    }
    require $logfile;
    $ip_logs = $GLOBALS['ip_logs'];
    $t = time();
    if (!empty($ip_logs[$ip]) && (($t - $ip_logs[$ip]) < 1)) {
        return true;
    }
    $ip_logs[$ip] = time();
    file_put_contents($logfile, "<?php\n\$GLOBALS['ip_logs']=" . var_export($ip_logs,true) . ";\n?>");
    return false;
}

/*
 * Check if the received token is valid
 */
function valid_token($admin_key, $token, $message) {
    // time rounded to 30 seconds
    $t = time();
    $timestamp_now = $t - $t % 30;
    $timestamp_prev = $timestamp_now - 30;

    // generate hash tokens (current and previous)
    $hash_now = hash_hmac('sha1', $timestamp_now . ":" . $message, $admin_key);
    $hash_prev = hash_hmac('sha1', $timestamp_prev . ":" . $message, $admin_key);

    // compare to the received token
    return ($token == $hash_now || $token == $hash_prev);
}

/*
 * Remove old files recursively (non php)
 *
 * A file is considered old if it has not been fetched/updated for more
 * than 30 days.
 */
function remove_old_files($dirname, $t){
    // default arguments
    if ($dirname == NULL) $dirname = "data";
    if ($t == NULL) $t = time();

    // list the directory
    $handle = opendir($dirname);
    while (false !== ($entry = readdir($handle))){
        $filename = $dirname . "/" . $entry;
        // recurse in directories
        if (is_dir($filename) && $entry != '.' && $entry != '..' && $entry != '.htaccess') {
            remove_old_files($filename, $t);
        } else {
            if (pathinfo($filename, PATHINFO_EXTENSION) != "php") {
                // delete files older than 30 days
                if ($t - fileatime($filename) > 30 * 24 * 3600) {
                    unlink($filename);
                }
            }
        }
    }
}

/*
 * Remove old ips from the logs
 *
 * An ip is old if it has not made a request for more than 30 days.
 */
function purge_old_ips($t){
    $ip_logfile = './data/ip_logs.php';
    if (is_file($ip_logfile)) {
        require $ip_logfile;
        $ip_logs = $GLOBALS['ip_logs'];
        // purge file of IPs older than one month
        foreach($ip_logs as $ip => $time) {
            if (($t - $time) > 24 * 30 * 3600) {
                unset($ip_logs[$ip]);
            }
        }
        file_put_contents($ip_logfile, "<?php\n\$GLOBALS['ip_logs']=" . var_export($ip_logs, true) . ";\n?>");
    }
}

/*
 * If it has been more than 24 hours since last cleanup, launch one.
 */
function autoclean(){
    $cleanup_logfile = './data/cleanup_logfile.php';
    if (!is_file($cleanup_logfile)){
        file_put_contents($cleanup_logfile, "<?php\n\$GLOBALS['last_cleanup']=0;\n?>");
        chmod($cleanup_logfile,0705);
    }
    require $cleanup_logfile;
    $last_cleanup = $GLOBALS['last_cleanup'];
    $t = time();
    // if it has been more than 24 hours since last cleanup
    if ($t - $last_cleanup > 24 * 3600) {
        // remove old files
        remove_old_files("data", $t);
        // clean old ip addresses
        purge_old_ips($t);
    }
    file_put_contents($cleanup_logfile, "<?php\n\$GLOBALS['last_cleanup']=" . $t . ";\n?>");
}

/*
 * Get a value in an array without php warning when the key does not exist
 */
function array_get($array, $key, $default_value=''){
    return isset($array[$key])?$array[$key]:$default_value;
}

/*
 * Exits on the appropriate HTTP status
 */
function deliver_response($status, $body){
    $message = array(
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        400 => 'Bad Request',
        403 => 'Forbidden',
        404 => 'Not Found',
        413 => 'Request Entity Too Large',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error'
    );
    header("HTTP/1.1 " . $status . " " . $message[$status]);
    if ($body != NULL) {
        $body["status"] = $status;
        echo json_encode($body);
    }
    exit;
}

// Limit the number or POST/PUT/DELETE requests per second
if ($_SERVER['REQUEST_METHOD'] != 'GET'){
    if (must_wait($_SERVER['REMOTE_ADDR'])){
        deliver_response(429, array("message" => "You are limited to 1 request/second"));
    }
}

autoclean();

switch($_SERVER['REQUEST_METHOD']) {

case "POST":

    $data = array_get($_POST, 'data');
    $key = generate_key($data);
    $storagedir = key2dir($key);

    // POST requests are only allowed on HTTPS
    if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on' ) {
        deliver_response(403, array("message" => "You can only POST on HTTPS"));
    }

    // Check content size
    if (empty($data)) {
        deliver_response(400, array("message" => "You cannot post empty data"));
    }
    if (strlen($data) > 65536) {
        deliver_response(413, array("message" => "Storage is limited to 64Kb"));
    }

    // Create storage directory if it does not exist.
    if (!is_dir($storagedir)){
        mkdir($storagedir, $mode=0705, $recursive=true);
    }

    // Handle collisions
    if (is_file($storagedir.$key)) {
        deliver_response(500, array("message" => "Unlucky hash collision. Try again."));
    }

    file_put_contents($storagedir . $key, $data);

    $admin_key = hash_hmac('sha1', $key , server_salt());
    deliver_response(201, array("key" => $key, "admin_key" => $admin_key));

case "GET":
    $key = array_get($_GET, 'key');
    $storagedir = key2dir($key);
    $filename = $storagedir . $key;

    // If no key is given, serve the README file
    if (empty($key)) {
        readfile("README.html");
        exit;
    }

    if (!is_file($filename)) {
        deliver_response(404, array('message'=>'Key not found.'));
    }

    // Updates the atime of the file, for maintainance purpose:
    // If a key is not read nor written for more than a certain time, it
    // will be deleted
    touch($filename);

    readfile($filename);
    exit;

case "PUT":
    parse_str(file_get_contents("php://input"), $_PUT);
    $key = array_get($_PUT, 'key');
    $token = array_get($_PUT, 'token');
    $data = array_get($_PUT, 'data');
    $admin_key = array_get($_PUT, 'admin_key');

    /* data size */
    if (empty($data)) {
        deliver_response(400, array("message" => "You cannot put empty data. Use delete instead."));
    }
    if (strlen($data) > 65536) {
        deliver_response(413, array("message" => "Storage is limited to 64Kb."));
    }

    /* authorization */

    // the admin key or a valid token must be provided (but not both)
    if (!(empty($token) xor empty($admin_key))) {
        deliver_response(400, array("message" => "You need to provide either the admin key or a valid token"));
    }

    // recompute the real admin key
    $real_admin_key = hash_hmac('sha1', $key , server_salt());

    if (!(empty($admin_key))){
        // if the admin key is provided, HTTPS is required
        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
            deliver_response(403, array("message" => "You can only use your admin key over HTTPS."));
        }
        // this key is compared to the real admin key
        if ($admin_key != $real_admin_key) {
            deliver_response(403, array("message" => "Wrong admin key"));
        }
    } else {
        // otherwise, the token is checked for validity
        $message = $key . ":" . $data;
        if (!valid_token($real_admin_key, $token, $message)) {
            deliver_response(403, array("message" => "Bad token."));
        }
    }

    $storagedir = key2dir($key);
    $filename = $storagedir . $key;

    if (!is_file($filename)) {
        deliver_response(404, array("message" => "Key not found."));
    }


    file_put_contents($filename, $data);
    deliver_response(200, array("message" => "Resource updated"));

case "DELETE":
    parse_str(file_get_contents("php://input"), $_DELETE);
    $key = array_get($_DELETE, 'key');
    $token = array_get($_DELETE, 'token');
    $admin_key = array_get($_DELETE, 'admin_key');

    /* authorization */

    // the admin key or a valid token must be provided (but not both)
    if (!(empty($token) xor empty($admin_key))) {
        deliver_response(400, array("message" => "You need to provide either the admin key or a valid token"));
    }

    // recompute the real admin key
    $real_admin_key = hash_hmac('sha1', $key , server_salt());

    if (!(empty($admin_key))){
        // this key is compared to the real admin key
        if ($admin_key != $real_admin_key) {
            deliver_response(403, array("message" => "Wrong admin key"));
        }
    } else {
        // otherwise, the token is checked for validity
        $message = $key;
        if (!valid_token($real_admin_key, $token, $message)) {
            deliver_response(403, array("message" => "Bad token."));
        }
    }

    $storagedir = key2dir($key);
    $filename = $storagedir . $key;
    if (!is_file($filename)) {
        deliver_response(404, array('message'=>'Key not found.'));
    }

    unlink($filename);
    deliver_response(200, array("message" => "Resource deleted"));
}

?>
