#!/bin/bash

function die() {
    echo "$@"
    exit
}

# Usage: sign_request <message> <key>
#
# returns a time-dependant signature of the given message, with the given
# key.
function sign_request() {
    message=$1
    admin_key=$2

    t=$(date +%s)
    # time rounded to 30s
    timestamp=$(($t-$((t%30))))

    echo -n "$timestamp":"$message" |\
        openssl dgst -sha1 -hmac $admin_key -binary |\
        xxd -p
}

# Usage: echo <json> | json_extract <key>
#
# Returns the value associated with a key in a json object
function json_extract() {
    key=$1
    python -c "import sys,json; print(json.load(sys.stdin)['$key'])"
}

curl_out=$(mktemp)
curl -s -X POST -d "data=hello" "https://minibackup.chmd.fr" > $curl_out
key=$(cat $curl_out | json_extract "key")
admin_key=$(cat $curl_out | json_extract "admin_key")

curl -s -X GET "https://minibackup.chmd.fr?key=$key" > $curl_out
value=$(cat $curl_out)
[ $value == "hello" ] || die "Failed to get result"
sleep 1

data="bye"
message="$key":"$data"
token=$(sign_request $message $admin_key)
curl -s -X PUT -d "key=$key&data=bye&token=$token" "https://minibackup.chmd.fr"
sleep 1

message="$key"
token=$(sign_request $message $admin_key)
curl -s -X DELETE -d "key=$key&token=$token" "https://minibackup.chmd.fr"
