<?php
/**
 * Update the plugin specified URL / Branch
 *
 * @param $gitURL
 * @param string $branch
 * @param $pluginName
 */
function updatePluginFromGitHub($gitURL, $branch = "master", $pluginName)
{
    global $settings;
    logEntry("updating plugin: " . $pluginName);
    logEntry("settings: " . $settings['pluginDirectory']);

    //create update script
    //$gitUpdateCMD = "sudo cd ".$settings['pluginDirectory']."/".$pluginName."/; sudo /usr/bin/git git pull ".$gitURL." ".$branch;

    $pluginUpdateCMD = "/opt/fpp/scripts/update_plugin " . $pluginName;

    logEntry("update command: " . $pluginUpdateCMD);

    exec($pluginUpdateCMD, $updateResult);

    //logEntry("update result: ".print_r($updateResult));

    //loop through result
    return;// ($updateResult);
}

/**
 * Returns a list of API servers from the api_servers.json file
 */
function getApiServers($env)
{
    global $settings, $pluginName;

    $api_server_json_path = $settings['pluginDirectory'] . "/" . $pluginName . "/api_servers.json";
    $api_server_json_contents_arr = array();

    if (file_exists($api_server_json_path)) {
        $api_server_json_contents = file_get_contents($api_server_json_path);
        if (isset($api_server_json_contents) && !empty($api_server_json_contents)) {
            $api_server_json_contents_arr = json_decode($api_server_json_contents, true);
            //if a environment has been supplied, then try to return the server for that environment
            if (isset($env) && !empty($env)) {
                foreach ($api_server_json_contents_arr as $id => $server) {
                    //if the server id matches the supplied environment, break and return only that environment
                    if (strtolower($server['_id']) == strtolower($env)) {
                        return $server;
                        break;
                    }
                }
            }

            return $api_server_json_contents_arr;
        }
    }
    return $api_server_json_contents_arr;
}

/**
 * Helper function to write to the plugins logfile
 *
 * @param $data
 */
function logEntry($data)
{
    global $logFile, $myPid, $settings, $pluginName;

    if (!isset($logFile) && empty($logFile)) {
        $logFile = $settings['logDirectory'] . "/" . $pluginName . ".log";
    }

    $data = $_SERVER['PHP_SELF'] . " : [" . $myPid . "] " . $data;

    $logWrite = fopen($logFile, "a") or die("Unable to open file!");
    fwrite($logWrite, date('Y-m-d h:i:s A', time()) . ": " . $data . "\n");
    fclose($logWrite);
}

/**
 * Fork PHP script into its own process
 * This "unhooks" it from the FPPD call and lets by itself
 *
 * Extracted from rdsToMatrix plugin
 *
 * @param $argv
 * @return string
 */
function fork($argv)
{
    global $DEBUG;

    $safe_arg = escapeshellarg($argv[4]);
    //$safe_arg["arg_2"] = escapeshellarg($arg_2);
    $pid = pcntl_fork();

    if ($pid == -1) {
        // Fork failed
        if ($DEBUG)
            logEntry("ERROR :: fork failed");

        exit(1);
    } else if ($pid) {
        // We are the parent
        if ($DEBUG) {
            logEntry("------------");
            logEntry("fork parent");
            logEntry("------------");
        }
        return "Parent";

        // Can no longer use $db because it will be closed by the child
        // Instead, make a new MySQL connection for ourselves to work with
    } else {
        if ($DEBUG) {
            logEntry("------------");
            logEntry("fork child");
            logEntry("------------");
        }
        //logEntry("sleeping 5 seconds, processing, thensleeping agin");
        //Process the callback
        processCallback($argv);
        return "Child";
    }
}

/**
 * Process the FPPD callback (via fork)
 *
 * modified from from rdsToMatrix plugin
 *
 * @param $argv
 */
function processCallback($argv)
{
    global $DEBUG, $pluginName;

//    $SEPARATOR = urldecode(ReadSettingFromFile("SEPARATOR", $pluginName));

    //if($DEBUG)
    //print_r($argv);

    //argv0 = program
    //argv2 should equal our registration // need to process all the registrations we may have, array??
    //argv3 should be --data
    //argv4 should be json data

    $registrationType = $argv[2];

    if ($DEBUG) {
        logEntry("registration type: " . $registrationType);
    }
    //Extract the data from position 4
    $data = $argv[4];

    logEntry("PROCESSING CALLBACK");

    //Switch block to handle
    switch ($registrationType) {
        case "media":
            if ($argv[3] == "--data") {
                $data = trim($data);
                logEntry("CALLBACK DATA: " . $data);
                //Decode the json to an array so we can work on it
                $obj = json_decode($data);

                $type = $obj->{'type'};

                switch ($type) {
                    case "sequence":
                        logEntry("WARNING :: SEQUENCE ENTRY: !!NOT!! SUBMITTING SEQUENCE NAME");

                        //Sequence only
                        $sequenceName = $obj->{'Sequence'};
                        //Process now playing item
//                        processSequenceName($obj->{'Sequence'});
                        if (isset($sequenceName) && !empty($sequenceName)) {
//                            logEntry("SENDING NOW PLAYING: (sequence) " . $sequenceName);
//                            submitNowPlaying($sequenceName, null, 60);
                        }

                        break;
                    case "media":
                        logEntry("MEDIA ENTRY: EXTRACTING TITLE AND ARTIST");

                        $songTitle = $obj->{'title'};
                        $songArtist = $obj->{'artist'};
                        $songLength = $obj->{'length'};

                        $songTitleFull = "";

                        //check to see if the title and artist are set (incase they for whatever reason have not been set in the tags
                        if (!empty($songTitle) && !empty($songArtist)) {
                            //Build to the song title
                            $songTitleFull = $songTitle . " - " . $songArtist;
                        }

                        //Process now playing item if we have a song title
                        if (isset($songTitleFull) && !empty($songTitleFull)) {
                            //Print log output
                            logEntry("SENDING NOW PLAYING: " . $songTitleFull);
                            submitNowPlaying(null, $songTitleFull, $songLength);
                        } else {
                            //log message to say we couldn't get the title and artist
                            //Print log output
                            logEntry("ERROR :: Could not extract Media Title or Artist please check your MP3 tags: " . $obj->{'Media'});
                        }

                        break;
                    case "both":
                        logEntry("MEDIA ENTRY: EXTRACTING TITLE AND ARTIST");

                        $songTitle = $obj->{'title'};
                        $songArtist = $obj->{'artist'};
                        $songLength = $obj->{'length'};

                        $songTitleFull = "";

                        //Build to the song title
                        if (!empty($songTitle) && !empty($songArtist)) {
                            //Build to the song title
                            $songTitleFull = $songTitle . " - " . $songArtist;
                        }

                        //Process now playing item
                        if (isset($songTitleFull) && !empty($songTitleFull)) {
                            logEntry("SENDING NOW PLAYING: " . $songTitleFull);
                            submitNowPlaying(null, $songTitleFull, $songLength);
                        } else {
                            //log message to say we couldn't get the title and artist
                            //Print log output
                            logEntry("ERROR :: Could not extract Media Title or Artist please check your MP3 tags: " . $obj->{'Media'});
                        }

                        break;
                    default:
                        logEntry("WARNING :: We do not understand: type: " . $obj->{'type'} . " at this time");
                        exit(0);
                        break;
                }
            }
            break;
            exit(0);
        default:
            exit(0);
    }
}

/**
 * Stop FPPD
 * Pulled from fppxml.php
 *
 */
function StopFPPD()
{
    global $SUDO;

    SendCommand('d'); // Ignore return and just kill if 'd' doesn't work...
    $status = exec($SUDO . " " . dirname(dirname(__FILE__)) . "/scripts/fppd_stop");
//    EchoStatusXML('true');
}

/**
 * START FPPD
 * Pulled from fppxml.php
 *
 */
function StartFPPD()
{
    global $settingsFile, $SUDO;

    $status = exec("if ps cax | grep -q fppd; then echo \"true\"; else echo \"false\"; fi");
    if ($status == 'false') {
        $status = exec($SUDO . " " . dirname(dirname(__FILE__)) . "/scripts/fppd_start");
    }
//    EchoStatusXML($status);
}

/**
 * Restart FPD
 *
 * Pulled from fppxml.php
 *
 */
function FPP_RestartFPPD()
{
    global $SUDO, $FPC_FPPD_START, $FPC_FPPD_STOP;

    if (!isset($FPC_FPPD_START)) {
        $FPC_FPPD_START = "/usr/bin/sudo /opt/fpp/scripts/fppd_start";
    }
    if (!isset($FPC_FPPD_STOP)) {
        $FPC_FPPD_STOP = "/usr/bin/sudo /opt/fpp/scripts/fppd_stop";
    }

    //Stop FPPD
    exec($FPC_FPPD_STOP);
    logEntry("FPP_RestartFPPD: !!WARNING FPPD RESTART!! Stopping FPPD.. wait 2 seconds");

    //sleep 2 seconds
    sleep(2);

    //Check it's not runing & start it again
    $status = exec("if ps cax | grep -q fppd; then echo \"true\"; else echo \"false\"; fi");
    if ($status == 'false') {
        logEntry("FPP_RestartFPPD: !!WARNING FPPD RESTART!! FPPD has stopped, attempting FPP Daemon start");
        //start FPPD now, we could do another status check but seems excessive
        $status = exec($FPC_FPPD_START);
    }
}

/**
 * called by vote_check to work out our current status when performing functions
 * Pulled from fppjson.php
 *
 * //{
 * //   "fppd": "running",
 * //    "mode": 2,
 * //    "mode_name": "player",
 * //    "status": 0,
 * //    "status_name": "idle",
 * //    "volume": 41,
 * //    "time": "Sun Oct 22 13:08:20 AEST 2017",
 * //    "current_playlist": {
 * //    "playlist": "",
 * //        "type": "",
 * //        "index": "0",
 * //        "count": "0"
 * //    },
 * //    "current_sequence": "",
 * //    "current_song": "",
 * //    "seconds_played": "0",
 * //    "seconds_remaining": "0",
 * //    "time_elapsed": "00:00",
 * //    "time_remaining": "00:00",
 * //    "next_playlist": {
 * //    "playlist": "2016_ITS_PIX_TREE",
 * //        "start_time": "Saturday @ 00:00:00 - (Weekends)\n"
 * //    },
 * //    "repeat_mode": 0
 * //}
 *
 * @param $status String status from fpp CLI client
 * @return array
 */
function FPP_parseStatus($status)
{
    $modes = [
        0 => 'unknown',
        1 => 'bridge',
        2 => 'player',
        6 => 'master',
        8 => 'remote'
    ];

    $statuses = [
        0 => 'idle',
        1 => 'playing',
        2 => 'stopping gracefully'
    ];

    $status = explode(',', $status, 14);
    $mode = (int)$status[0];
    $fppStatus = (int)$status[1];

    if ($mode == 1) {
        return [
            'fppd' => 'running',
            'mode' => $mode,
            'mode_name' => $modes[$mode],
            'status' => $fppStatus,
        ];
    }

    $baseData = [
        'fppd' => 'running',
        'mode' => $mode,
        'mode_name' => $modes[$mode],
        'status' => $fppStatus,
        'status_name' => $statuses[$fppStatus],
        'volume' => (int)$status[2],
        'time' => exec('date'),
    ];

    if ($mode == 8) {
        $data = [
            'playlist' => $status[3],
            'sequence_filename' => $status[3],
            'media_filename' => $status[4],
            'seconds_elapsed' => $status[5],
            'seconds_remaining' => $status[6],
            'time_elapsed' => parseTimeFromSeconds((int)$status[5]),
            'time_remaining' => parseTimeFromSeconds((int)$status[6]),
        ];

    } else {

        if ($fppStatus == 0) {
            $data = [
                'next_playlist' => [
                    'playlist' => $status[3],
                    'start_time' => $status[4]
                ],
                'repeat_mode' => 0,
            ];
        } else {

            $data = [
                'current_playlist' => [
                    'playlist' => pathinfo($status[3])['filename'],
                    'type' => $status[4],
                    'index' => $status[7],
                    'count' => $status[8]
                ],
                'current_sequence' => $status[5],
                'current_song' => $status[6],
                'seconds_played' => $status[9],
                'seconds_remaining' => $status[10],
                'time_elapsed' => parseTimeFromSeconds((int)$status[9]),
                'time_remaining' => parseTimeFromSeconds((int)$status[10]),
                'next_playlist' => [
                    'playlist' => $status[11],
                    'start_time' => $status[12]
                ],
                'repeat_mode' => (int)$status[13],
            ];
        }
    }

    return array_merge($baseData, $data);
}

/**
 * Creates the Event related to this plugin, to be used in playlists or other areas
 */
function createCheckVotingApi_Event()
{
    global $DEBUG, $settings, $eventDirectory, $pluginName;

    //check that the eventDirectory exists.. just in case
    if (file_exists($eventDirectory)) {
        logEntry("createCheckVotingApi_Event: Event directory exists!!");

        //Find if the event exists already, returned value is true/false
        $EVENT_FILE_CHECK = checkEventFilesForKey("VOTE_CHECK_API");

        //If no event
        if (!$EVENT_FILE_CHECK) {
            logEntry("createCheckVotingApi_Event: Event doesn't exist Creating!!");

            //Get the next avail event id's
            $nextEventFilename = getNextEventFilename();
//            $newIndex = $MAJOR_INDEX . "_" . $MINOR_INDEX . ".fevt";
            //remove ext, explode and get the major and miner index
            $tmp_event_filename = str_ireplace(".fevt", "", $nextEventFilename);
            $tmp_event_filename = explode("_", $tmp_event_filename);
            if (array_key_exists(0, $tmp_event_filename) && array_key_exists(1, $tmp_event_filename)) {
                $MAJOR = $tmp_event_filename[0];
                $MINOR = $tmp_event_filename[1];
            } else {
                $MAJOR = substr($nextEventFilename, 0, 2);
                $MINOR = substr($nextEventFilename, 3, 2);
            }
            //build the event file contents
            $eventData = "";
            $eventData = "majorID=" . (int)$MAJOR . "\n";
            $eventData .= "minorID=" . (int)$MINOR . "\n";
            $eventData .= "name='VOTE_CHECK_API'\n";
            $eventData .= "effect=''\n";
            $eventData .= "startChannel=\n";
            $eventData .= "script='voteCheckAPI.sh'\n";

            //	echo "eventData: ".$eventData."<br/>\n";

            logEntry("createCheckVotingApi_Event: EventData: " . json_encode($eventData));
            //write out the event file
            file_put_contents($eventDirectory . "/" . $nextEventFilename, $eventData);

            //Create the script file that points to our vote_check script
            $scriptCMD = $settings['pluginDirectory'] . "/" . $pluginName . "/" . "vote_check.php";

            logEntry("createCheckVotingApi_Event: ScriptCMD: " . json_encode($scriptCMD));

            createScriptFile("voteCheckAPI.sh", $scriptCMD);
        } else {
            logEntry("Existing event file present");
        }
    }

    //echo "$key => $val\n";
}

/**
 * Creates shell script file to run the specified php file
 *
 * Extracted from rdsToMatrix plugin
 *
 * @param $scriptFilename
 * @param $scriptCMD
 */
function createScriptFile($scriptFilename, $scriptCMD)
{
    global $scriptDirectory, $pluginName;

    $scriptFilename = $scriptDirectory . "/" . $scriptFilename;

    logEntry("Creating script: " . $scriptFilename);

    $ext = pathinfo($scriptFilename, PATHINFO_EXTENSION);

    $data = "";

    $data .= "#!/bin/sh\n";

    $data .= "\n";
    $data .= "#Script to run " . $pluginName . "\n";
    $data .= "#Created by " . $pluginName . "\n";
    $data .= "#\n";
    $data .= "/usr/bin/php " . $scriptCMD . "\n";

    logEntry($data);

    $fs = fopen($scriptFilename, "w");
    fputs($fs, $data);
    fclose($fs);
}

/**
 * Get the next available event filename
 * return the next event file available for use
 *
 * Extracted from rdsToMatrix plugin
 *
 * @return string
 */
function getNextEventFilename()
{

    $MAX_MAJOR_DIGITS = 2;
    $MAX_MINOR_DIGITS = 2;
    global $eventDirectory;

    //echo "Event Directory: ".$eventDirectory."<br/> \n";

    $MAJOR = array();
    $MINOR = array();

    $MAJOR_INDEX = 0;
    $MINOR_INDEX = 0;

    $EVENT_FILES = directoryToArray($eventDirectory, false);
    //print_r($EVENT_FILES);

    foreach ($EVENT_FILES as $eventFile) {

        $eventFileParts = explode("_", $eventFile);

        $MAJOR[] = (int)basename($eventFileParts[0]);
        //$MAJOR = $eventFileParts[0];

        $minorTmp = explode(".fevt", $eventFileParts[1]);

        $MINOR[] = (int)$minorTmp[0];

        //echo "MAJOR: ".$MAJOR." MINOR: ".$MINOR."\n";
        //print_r($MAJOR);
        //print_r($MINOR);

    }

    $MAJOR_INDEX = max(array_values($MAJOR));
    $MINOR_INDEX = max(array_values($MINOR));

    //echo "Major max: ".$MAJOR_INDEX." MINOR MAX: ".$MINOR_INDEX."\n";

    if ($MAJOR_INDEX <= 0) {
        $MAJOR_INDEX = 1;
    }
    if ($MINOR_INDEX <= 0) {
        $MINOR_INDEX = 1;

    } else {

        $MINOR_INDEX++;
    }

    $MAJOR_INDEX = str_pad($MAJOR_INDEX, $MAX_MAJOR_DIGITS, '0', STR_PAD_LEFT);
    $MINOR_INDEX = str_pad($MINOR_INDEX, $MAX_MINOR_DIGITS, '0', STR_PAD_LEFT);
    //for now just return the next MINOR index up and keep the same Major
    $newIndex = $MAJOR_INDEX . "_" . $MINOR_INDEX . ".fevt";
    //echo "new index: ".$newIndex."\n";
    return $newIndex;
}

/**
 * Returns the file contents of the supplied directory
 *
 * Extracted from rdsToMatrix plugin
 *
 * @param $directory
 * @param $recursive
 * @return array
 */
function directoryToArray($directory, $recursive)
{
    $array_items = array();
    if ($handle = opendir($directory)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                if (is_dir($directory . "/" . $file)) {
                    if ($recursive) {
                        $array_items = array_merge($array_items, directoryToArray($directory . "/" . $file, $recursive));
                    }
                    $file = $directory . "/" . $file;
                    $array_items[] = preg_replace("/\/\//si", "/", $file);
                } else {
                    $file = $directory . "/" . $file;
                    $array_items[] = preg_replace("/\/\//si", "/", $file);
                }
            }
        }
        closedir($handle);
    }
    return $array_items;
}

/**
 * check all the event files for a string matching this and return true/false if exist
 *
 * Extracted from rdsToMatrix plugin
 *
 * @param $keyCheckString
 * @return bool
 */
function checkEventFilesForKey($keyCheckString)
{
    global $eventDirectory;

    $keyExist = false;
    $eventFiles = array();

    $eventFiles = directoryToArray($eventDirectory, false);
    foreach ($eventFiles as $eventFile) {

        if (strpos(file_get_contents($eventFile), $keyCheckString) !== false) {
            // do stuff
            $keyExist = true;
            break;
            // return $keyExist;
        }
    }

    return $keyExist;
}


/**
 * check all the event files for a string matching this and returns the filename of the associated event
 *
 * @param $keyCheckString
 * @return bool
 */
function getEventFileNameForKey($keyCheckString)
{
    global $eventDirectory;

    $keyExist = false;
    $eventFiles = array();
    $event_file_found = null;

    $eventFiles = directoryToArray($eventDirectory, false);
    foreach ($eventFiles as $eventFile) {
        //look into the file and try to find the check string
        if (strpos(file_get_contents($eventFile), $keyCheckString) !== false) {
            //get the event filename, it should be after the last forward slash /
            $event_file_bits = explode("/", $eventFile);
            $event_file_bits_length = count($event_file_bits) - 1;
            //
            if (array_key_exists($event_file_bits_length, $event_file_bits)) {
                $event_file_found = $event_file_bits[$event_file_bits_length];
                $keyExist = true;
                break;
            }
        }
    }
    return $event_file_found;
}

/**
 * Looks in a directory and reads file contents of files within it
 * @param $directory String directory to search in
 * @param $return_data Boolean switch to include file data
 * @return array Array of file names and respective data
 */
function read_directory_files($directory, $return_data = true)
{
    $file_list = array();
    $file_data = false;

    if ($handle = opendir($directory)) {
        while (false !== ($file = readdir($handle))) {
            // do something with the file
            // note that '.' and '..' is returned even
            // if file isn't this directory or its parent, add it to the results
            if ($file != "." && $file != "..") {
                // collect the filenames & data
                if ($return_data == true) {
                    $file_data = explode("\n", file_get_contents($directory . '/' . $file));
                }

                $file_list[$file] = $file_data;
            }
        }
        closedir($handle);
    }
    return $file_list;
}


/**
 * Tail a log file
 *
 * @param $filename
 * @param int $lines
 * @param int $buffer
 * @return string
 */
function tail($filename, $lines = 10, $buffer = 4096)
{
    $output = '';
    if (file_exists($filename)) {

        // Open the file
        $f = fopen($filename, "rb");

        // Jump to last character
        fseek($f, -1, SEEK_END);

        // Read it and adjust line number if necessary
        // (Otherwise the result would be wrong if file doesn't end with a blank line)
        if (fread($f, 1) != "\n") $lines -= 1;

        // Start reading
        $output = '';
        $chunk = '';

        // While we would like more
        while (ftell($f) > 0 && $lines >= 0) {
            // Figure out how far back we should jump
            $seek = min(ftell($f), $buffer);

            // Do the jump (backwards, relative to where we are)
            fseek($f, -$seek, SEEK_CUR);

            // Read a chunk and prepend it to our output
            $output = ($chunk = fread($f, $seek)) . $output;

            // Jump back to where we started reading
            fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);

            // Decrease our line counter
            $lines -= substr_count($chunk, "\n");
        }

        // While we have too many lines
        // (Because of buffer size we might have read too many)
        while ($lines++ < 0) {
            // Find first newline and remove all text before that
            $output = substr($output, strpos($output, "\n") + 1);
        }

        // Close file and return
        fclose($f);
    }
    return $output;
}

?>