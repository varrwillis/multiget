#!/usr/bin/php
<?php

error_reporting(0);
ini_set('display_errors', 0);

require 'get_chunks.php';

$defaults = [
    'o' => 'output.jar',
    'c' => 4,
    's' => 1024
];

$options = getopt('f:o:c:s:h');

//- "man page"
if (0 == count($options) ||
    (1 == count($options) && array_key_exists('h', $options))) {
    echo "usage: multiGet.php [-f] [-o] [-c] [-s] [-h]\n\n";
    echo "\t -f \t input file url to fetch (required)\n";
    echo "\t -o \t output file to save to\n";
    echo "\t -c \t number of file chunks to fetch\n";
    echo "\t -s \t size of each chunk to fetch\n";
    echo "\t -h \t this help\n\n";
    exit;
}

//- Make sure a file url was provided
$url_failed = false;
if (isset($options['f'])) {
    $url_file = filter_var($options['f'], FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED);

    if ($url_file === false) {
        $url_failed = true;
    } else {
        $path = parse_url($url_file, PHP_URL_PATH);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (!$ext) {
            $url_failed = true;
        }
    }
} else {
    $url_failed = true;
}

if ($url_failed) {
    echo "\nPlease provide a valid input file url (i.e. http://6fe832a6.bwtest-aws.pravala.com/384MB.jar)\n";
    exit;
} else {
    $options['f'] = $url_file;
}

//- If no output file path is provided,
//-      then name it "output" with the input file's extension
if (!array_key_exists('o', $options)) {
    $filename = isset($options['f']) ? $options['f'] : $defaults['f'];
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if ($ext) {
        $options['o'] = 'output.'.$ext;
    }
}

//- Merge defaults with user options
$options = array_replace_recursive($defaults, $options);

$getChunks = new GetChunks($options);
if ($getChunks->confirmOutputFile()) {
    $getChunks->downloadFile();
}
