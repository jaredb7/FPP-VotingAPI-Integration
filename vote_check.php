#!/usr/bin/php
<?php
error_reporting(0);
//Include our required php files
include_once('/opt/fpp/www/common.php');
include_once("functions.inc.php");
include_once("commonFunctions.inc.php");

//Debug mode
$DEBUG = true;
$skipJSsettings = 1;
//Plugin name
$pluginName = "FPP-VotingAPI-Integration";
//Logfile
$logFile = $settings['logDirectory'] . "/" . $pluginName . ".log";
//config file
$pluginConfigFile = $settings['configDirectory'] . "/plugin." . $pluginName;
//Get php process id
$myPid = getmypid();

//Gather in some FPPD stuff
//$FPP_BIN = $settings['fppBinDir']."/fpp";
//Commands to stop and start the daemon
$FPC_FPPD_START = "/usr/bin/sudo /opt/fpp/scripts/fppd_start";
$FPC_FPPD_STOP = "/usr/bin/sudo /opt/fpp/scripts/fppd_stop";
//FPP Location Command - fppDir is worked out for us so will match the platform
///eg. pi is opt/fpp/bin.pi/fpp
$FPP_LOCATION = $settings['fppBinDir'] . "/fpp";

//FPP Commands
//FPP Status command
$FPP_STATUS = $FPP_LOCATION . " -s";
//Force Stop current Playlist
$FPP_FORCE_STOP = $FPP_LOCATION . " -d";
//Play Playlist in Non-Repeat mode
$FPP_PLAY_PLAYLIST_NR = $FPP_LOCATION . " -P";
//Play Playlist in Repeat mode
$FPP_PLAY_PLAYLIST_RM = $FPP_LOCATION . " -p";

//Maximum loop rotations waiting for FPP to become idle (if it wasn't idle directly after the force playlist stop command)
//each loop waits 1 second.
$max_loops_for_idle = 2;
$default_sleep_seconds = 2;

//Defaults
$ENABLED = '';
$API_KEY = '';
$SPARE_VOTING = $MAX_ITEM_REPEATS = $MAX_ITEM_ROTATIONS = -1;
$FULLY_DYNAMIC_ONLY = $HIGHEST_VOTED_ONLY = $MOST_VOTED_RESET = 'off';
$PLAYED_SEQUENCES = [];
$PL_TRANSITIONED = false;

//Read the config, work out if plugin is enabled
if (file_exists($pluginConfigFile)) {
    //Call our helper function to get plugin settings all processed /decoded
    $pluginSettings = getPluginSettings();
//    $pluginSettings = parse_ini_file($pluginConfigFile);
    $ENABLED = $pluginSettings['ENABLED'];
    $API_KEY = $pluginSettings['API_KEY'];
    //Sequence repeating options
    $SPARE_VOTING = $pluginSettings['SPARE_VOTING'];
    $MAX_ITEM_REPEATS = $pluginSettings['MAX_ITEM_REPEATS'];
    $MAX_ITEM_ROTATIONS = $pluginSettings['MAX_ITEM_ROTATIONS'];
    $MOST_VOTED_RESET = $pluginSettings['MOST_VOTED_RESET'];
    //Playlist options
    $FULLY_DYNAMIC_ONLY = $pluginSettings['FULLY_DYNAMIC_ONLY'];
    $HIGHEST_VOTED_ONLY = $pluginSettings['HIGHEST_VOTED_ONLY'];
    //
    $PLAYED_SEQUENCES = $pluginSettings['PLAYED_SEQUENCES'];
    $PL_TRANSITIONED = $pluginSettings['PL_TRANSITIONED'];
    //
    $MAIN_PLAYLIST = $pluginSettings['MAIN_PLAYLIST'];
    //
}
//Track whether FPP was stopped
$FPP_WAS_STOPPED = false;

//plugin not enabled, then just quit. so we won't get to the registration callback
if (strtolower($ENABLED) != "on" && $ENABLED != "1") {
    logEntry("Plugin Status: DISABLED Please enable in Plugin Setup to use & Restart FPPD Daemon");
    //quit script
    exit(0);
}
//
//logEntry("FPP LOCATION: " . $FPP_LOCATION);
//logEntry("FPP STATUS: " . $FPP_STATUS);
//logEntry("FPP FORCE STOP: " . $FPP_FORCE_STOP);
//logEntry("FPP PL PLAY: " . $FPP_PLAY_PLAYLIST_NR);
//
//exit();

//Plugin enabled, do whatever
//Print some debug stuff
if ($DEBUG) {
    logEntry("API KEY: " . $API_KEY);
    logEntry("FULLY DYNAMIC PL: " . $FULLY_DYNAMIC_ONLY);
    logEntry("MOST VOTED ONLY: " . $HIGHEST_VOTED_ONLY);
    logEntry("MAX ITEM REPEATS: " . $MAX_ITEM_REPEATS);
    logEntry("MAX ITEM ROTATIONS: " . $MAX_ITEM_ROTATIONS);
    logEntry("MOST VOTED RESET: " . $MOST_VOTED_RESET);
    logEntry("PLAYED SEQUENCES: " . json_decode($PLAYED_SEQUENCES));
    logEntry("PL TRANSITIONED: " . $PL_TRANSITIONED);
}

//Call the API, get either the entire playlist (Fully Dynamic) or most voted
if (isset($FULLY_DYNAMIC_ONLY) && strtolower($FULLY_DYNAMIC_ONLY) == "on") {
    //Fully dynamic
    logEntry("VOTE CHECK: FULL DYNAMIC PLAYLIST MODE");
    //Call our helper function which will call the API and return the most voted item
    $voted_item_api_data = getVotedPlaylist();

//  ['data']['playlist']
//    "data": {
//        "playlist": [
//            {
//                "name": "ABDC wizards in winter-tso",
//                "data": "b,2015-wizards in winter-tso.fseq,04-Wizards in Winter.mp3,",
//                "id": "4d7e94e5725ed836d34716dcb3312e4c4e4de29e",
//                "pos": 15,
//                "start_pos": 15,
//                "votes": 0,
//                "playlist": "main",
//                "updated_at": "2017-10-23T21:46:11+10:00"
//            },

    //if we have valid data then we should have a valid playlist
    //playlist will be empty if there is playlist
    if (isset($voted_item_api_data['data']['playlist']) && !empty($voted_item_api_data['data']['playlist'])) {
        //We CAN use the data field since that's the line from out of the playlist, and contains the entire setting
        $vote_item_data = $voted_item_api_data['data']['playlist'];

        logEntry("VOTE CHECK: Dynamic Playlist DATA - " . json_encode($vote_item_data));

        //Defaults
        $created_playlist = false;
        $valid_playlist_items = array();

        if (!empty($vote_item_data)) {
            //check return all the valid types for a sequence
            $sequence_types = playlistItemTypeMappings('sequence');

            //Loop over the array of playlist items and check we can use them
            foreach ($vote_item_data as $vi_idx => $vi_data) {
                //get the data
                $row_data_array = explode(",", $vi_data['data']);
                //extract data out
                //[0] - type
                // 'b' => Media and Sequence
                // 'm' => Media Only
                // 's' => Sequence Only
                $type = $row_data_array[0];
                //[1] - Sequence name
                $sequence_name = $row_data_array[1];
                //[2] - Media - if set
                if (isset($row_data_array[2])) {
                    $sequence_audio = $row_data_array[2];
                }

                //Check entry is a valid sequence item, collect it into an array
                if ((!empty($row_data_array) && !empty($type) && !empty($sequence_name) && !empty($sequence_audio)) && in_array($type, $sequence_types)) {
//                    logEntry("VOTE CHECK: Processing Voted Playlist Item (" . $vi_idx . ") - All data in check");
                    $valid_playlist_items[] = $vi_data['data'];
                } else {
                    logEntry("VOTE CHECK: Processing Voted Playlist Item (" . $vi_idx . ") - Item is NOT a sequence --" . json_encode($vi_data));
                }
            }

            //if we have some valid_items
            if (!empty($valid_playlist_items)) {
                //
                logEntry("VOTE CHECK: Dynamic Playlist - Create Playlist for items: " . json_encode($valid_playlist_items));

                //Create a playlist voted items
                $created_playlist = createVoted_Playlist($valid_playlist_items);
            } else {
                //
                logEntry("VOTE CHECK: No Valid Dynamic Playlist items");
            }

            //if we successfully created the playlist, then proceed with switching over to it
            if ($created_playlist == true) {
                //Playlist has been created
                logEntry("VOTE CHECK: DYNAMIC PLAYLIST CREATED: ");

                //get ready to stop FPP and switch to the new playlist
                $stop_command_result = exec($FPP_FORCE_STOP);
                logEntry("VOTE CHECK: !! FORCED STOPPED FPP !! - CMD - " . $FPP_FORCE_STOP);
                logEntry("VOTE CHECK: !! FORCED STOPPED FPP !! - RESULT - " . $stop_command_result);

                //stop result will be something like
                //2,1,Playlist Stopping Now,,,,,,,,,,

                //Sleep for 2 seconds to let FPP sort itself out
                sleep($default_sleep_seconds);

                //Poll status
                //check result and make sure we're idle after 2 seconds wait
                $stop_command_result = exec($FPP_STATUS);
                logEntry("VOTE CHECK: !! Slept for 2 seconds, Polling FPP status - RESULT - " . $stop_command_result);
                //process status
                $fpp_status = FPP_parseStatus($stop_command_result);
                //are we idle
                $fpp_idle = false;
                if (!empty($fpp_status)) {
                    $fpp_status_code = $fpp_status['status'];
                    $fpp_status_name = $fpp_status['status_name'];

                    if ((int)$fpp_status_code == 0 && strtolower($fpp_status_name) == "idle") {
                        $fpp_idle = true;
                    }
                }

                //If still we're not idle then we can loop around till we are upto the max number of loops
                $sleep_count = 0;
                //while fpp is not idle, and until we hit the max time we want to sleep, poll it status
                while (($fpp_idle == false)) {
                    //break if we're over the max loops we want to do searching for idle
                    if (($sleep_count > $max_loops_for_idle)) {
                        break;
                    }
                    logEntry("VOTE CHECK: !! Searching for idle - Polling FPP status on loop - " . $sleep_count);

                    //Poll status
                    $stop_command_result = exec($FPP_STATUS);
                    logEntry("VOTE CHECK: !! POLLING FPP STATUS !! - CMD - " . $FPP_STATUS);
                    logEntry("VOTE CHECK: !! Searching for idle -  Polling FPP status - RESULT - " . $stop_command_result);

                    //process status
                    $fpp_status = FPP_parseStatus($stop_command_result);

                    //we want the fpp status to be "status" = 0 or "status_name" = idle
                    //if not sleep another 1 sec.
                    if (!empty($fpp_status)) {
                        $fpp_status_code = $fpp_status['status'];
                        $fpp_status_name = $fpp_status['status_name'];

                        if ((int)$fpp_status_code == 0 && strtolower($fpp_status_name) == "idle") {
                            $fpp_idle = true;
                            //break the loop once found
                            break;
                        }
                    }
                    //log it
                    logEntry("VOTE CHECK: !! FPP Still not idle, waiting.. - " . $stop_command_result);
                    //increment loop count
                    $sleep_count++;
                    //Sleep 1 second
                    sleep(1);
                }

                //FPP is idle, tell FPP to play the most voted playlist
                if ($fpp_idle == true) {
                    logEntry("VOTE CHECK: FPP is idle, getting ready to play Dynamic / Voted Playlist");

                    //Command to play the most voted playlist in no repeat mode
                    $voted_playlist_play_command = $FPP_PLAY_PLAYLIST_RM . " VOTED_PLAYLIST";

                    //we could monitor for start, but not at the moment
                    //change playlists - that's all we need to go
                    $playlist_play_cmd_result = exec($voted_playlist_play_command);
                    logEntry("VOTE CHECK: !! FPP PLAY PLAYLIST !! - CMD - " . $voted_playlist_play_command);
                    logEntry("VOTE CHECK: !! FPP PLAY PLAYLIST !! - RESULT - " . $playlist_play_cmd_result);
                } else if ($fpp_idle == false) {
                    //Say we've transitioned (we haven't) so we don't somehow get stuck in a loop
                    //try and let FPP run the entire schedule playlist itself
                    writeToConfig('PL_TRANSITIONED', true, $pluginName);

                    //if FPP still not idle, we're in trouble.
                    // Abort & restart FPPD
                    logEntry("VOTE CHECK: !!WARNING FPPD RESTART!! FPP still not idle after 4 seconds - attempting FPP Daemon stop & restart");
                    //Stop FPPD
                    exec($FPC_FPPD_STOP);
                    $FPP_WAS_STOPPED = true;
                    logEntry("VOTE CHECK: !!WARNING FPPD RESTART!! Stopping FPPD.. wait 2 seconds");

                    //sleep 2 seconds
                    sleep($default_sleep_seconds);

                    //Check it's not runing & start it again
                    $status = exec("if ps cax | grep -q fppd; then echo \"true\"; else echo \"false\"; fi");
                    if ($status == 'false') {
                        logEntry("VOTE CHECK: !!WARNING FPPD RESTART!! FPPD has stopped, attempting FPP Daemon start");
                        //start FPPD now, we could do another status check but seems excessive
                        $status = exec($FPC_FPPD_START);
                        $FPP_WAS_STOPPED = false;
                    }
                }//end if fpp idle false
            }//end created playlist
        }//end !empty $vote_item_data
    }//end !empty playlist api result
} else if (isset($HIGHEST_VOTED_ONLY) && strtolower($HIGHEST_VOTED_ONLY) == "on") {
    logEntry("VOTE CHECK: MOST VOTED ITEM MODE");

    //If we're transitioned previously, then just bomb out
    if ((bool)$PL_TRANSITIONED == true || strtolower($PL_TRANSITIONED) == "true") {
        logEntry("VOTE CHECK: !!Quitting!! We've Previously transitioned to the Most Played playlist & returned to scheduled playlist.");
        logEntry("VOTE CHECK: !!Quitting!! We'll run again next time it starts over, [PL_TRANSITIONED] set false");

        //Reset Transitioned, once the schedule playlist repeats we can do work again
        writeToConfig('PL_TRANSITIONED', false, $pluginName);
        exit(0);
    }

    //Most voted
    $auto_reset = false;
    if (isset($MOST_VOTED_RESET) && strtolower($MOST_VOTED_RESET) == "on") {
        $auto_reset = true;
    }

    //Call our helper function which will call the API and return the most voted item
    $most_voted_item_data = getMostVotedItem($auto_reset);

//  ['data']['most_voted']
//    "most_voted": {
//        "name": "Skillrex_First_of_the_year",
//        "data": "b,2015-Skillrex_First_of_the_year.fseq,Dubstep Christmas House   First Of The Year Equinox   Skrillex.mp3,",
//        "id": "e0e9d1e4af56e2dc558d5ed84eff597326829dee",
//        "pos": 13,
//        "start_pos": 13,
//        "votes": 2,
//        "playlist": "main",
//        "updated_at": "2017-10-21T01:19:16+10:00"
//        },

    //Do something based on this data
    //if we have valid data then we should have a valid playlist item
    //most_voted will be empty if there is no most voted item
    if (isset($most_voted_item_data['data']['most_voted']) && !empty($most_voted_item_data['data']['most_voted'])) {
        //Now the tricky bit, we have name that has possibly been customized by the user
        //the ID is unique to this item, but is not used to track the item anywhere on the FPP (it is used heavily by the server to track the playlist item)

        //get the playiist item id
        $most_voted_item_id = $most_voted_item_data['data']['most_voted']['id'];

        //We CAN use the data field since that's the line from out of the playlist, and contains the entire setting
        $most_item_data = $most_voted_item_data['data']['most_voted']['data'];

        logEntry("VOTE CHECK: Most Voted Item - " . json_encode($most_voted_item_data));

        //process the row data
        $itemCanBePlayed = false;
        $item_play_count = 0;
        $was_the_last_played_sequence = true;
        if (!empty($most_item_data)) {
            $row_data_array = explode(",", $most_item_data);
            //extract data out
            //[0] - type
            // 'b' => Media and Sequence
            // 'm' => Media Only
            // 's' => Sequence Only
            $type = $row_data_array[0];
            //[1] - Sequence name
            $sequence_name = $row_data_array[1];
            //[2] - Media - if set
            if (isset($row_data_array[2])) {
                $sequence_audio = $row_data_array[2];
            }

            //Check the whether we should run
            //check that we can actaully play the item (respect repeat count)
//            $item_play_count = 0;
//            $was_the_last_played_sequence = true;
            if (is_array($PLAYED_SEQUENCES)) {
                $played_sequences_length = count($PLAYED_SEQUENCES);
                foreach ($PLAYED_SEQUENCES as $item) {
                    if (strtolower($item) === strtolower($sequence_name)) {
                        $item_play_count++;
                    }
                }
                //is it also the last played item? check the very last item int he array, -1 due to array 0 base
                //if it isn't, there was a break (other seq played)
                $last_played_item = $PLAYED_SEQUENCES[$played_sequences_length - 1];
                if (strtolower($last_played_item) != strtolower($sequence_name)) {
                    logEntry("VOTE CHECK: Sequence (" . $last_played_item . ") was the last played item.");
                    $was_the_last_played_sequence = false;
                }

                //if it wasn't last played but has ben repeated played the max number of times set,
                //because it wasn't the last played, there has at least been a break in the repeating
                //remove the very first item from the array

                if ($item_play_count >= $MAX_ITEM_REPEATS && strtolower($MOST_VOTED_RESET) == "on") {
                    logEntry("VOTE CHECK: Sequence ( " . $sequence_name . " ) was not last played, because it's reached it's repeat count (" . $item_play_count . "/" . $MAX_ITEM_REPEATS . ")");
                    logEntry("VOTE CHECK: Most Voted Auto Reset is ( " . $MOST_VOTED_RESET . " ), attempting reset of votes for playlist item (" . $most_voted_item_id . " | " . $sequence_name . ")");
                    //auto reset, then reset
                    if (isset($most_voted_item_id)) {
                        resetMostVotedItem($most_voted_item_id);
                        clearPlayedSequences();
                    } else {
                        logEntry("VOTE CHECK: Failed to reset votes, playlist item ID invalid");
                    }
                } else if ($was_the_last_played_sequence == false && $item_play_count >= $MAX_ITEM_REPEATS) {
                    logEntry("VOTE CHECK: Sequence ( " . $sequence_name . ") was not last played, but it's reached it's repeat count (" . $item_play_count . "/" . $MAX_ITEM_REPEATS . ")");
                    logEntry("VOTE CHECK: Since there has been a break (some other sequence played before), we're resetting it's play count");

                    //Could do the most-voted item vote reset somewhere here also

                    $tmp_played = $PLAYED_SEQUENCES;
                    foreach ($tmp_played as $k => $tmp_item) {
                        if (strtolower($tmp_item) === strtolower($sequence_name)) {
                            //remove all of them so we effectively remove
                            unset($tmp_played[$k]);
                            //remove one item only then break the loop
                            break;
                        }
                    }
                    $PLAYED_SEQUENCES = array_values($tmp_played);
                    $item_play_count = 0;
                }
            }
        }

        //Play count is less than the max repeats
        //make whether item can be played or not so we can enter the statement below
        if ($item_play_count < $MAX_ITEM_REPEATS) {
            $itemCanBePlayed = true;
            logEntry("VOTE CHECK: Playing " . $sequence_name . " as it is under the repeat count (" . ($item_play_count) . "/" . $MAX_ITEM_REPEATS . ")");

        } else {
            $itemCanBePlayed = false;
            logEntry("WARNING :: VOTE CHECK: NOT Playing " . $sequence_name . " as it is OVER the repeat ($item_play_count/$MAX_ITEM_REPEATS)");

        }

        //Once we know we have all this info, then we insert the item into the MAIN_PLAYLIST
        //this is done over creating
        //if type is b, then we actually require the sequence audio
        $created_playlist = false;
        if ((!empty($most_item_data) && !empty($type) && !empty($sequence_name) && !empty($sequence_audio)) && $itemCanBePlayed == true) {
            logEntry("VOTE CHECK: Processing Most Voted Item - All data in check");
            //check return all the valid types for a sequence
            $sequence_types = playlistItemTypeMappings('sequence');

            //Doubly check that the type of this most_voted item is a sequence type
            if (in_array($type, $sequence_types)) {
                //
                logEntry("VOTE CHECK: Most Voted Item is a sequence");
                //Create a playlist for the most voted item
                $created_playlist = createMostVoted_Playlist($most_item_data);
            } else {
                //
                logEntry("VOTE CHECK: Most Voted Item is NOT a sequence");
            }

            //if we successfully created the playlist, then proceed with switching over to it
            if ($created_playlist == true) {
                //Playlist has been created
                logEntry("VOTE CHECK: !! MOST-VOTED PLAYLIST CREATED");

                //get ready to stop FPP and switch to the new playlist
                $stop_command_result = exec($FPP_FORCE_STOP);
                logEntry("VOTE CHECK: !! FORCED STOPPED FPP !! - CMD - " . $FPP_FORCE_STOP);
                logEntry("VOTE CHECK: !! FORCED STOPPED FPP !! - RESULT - " . $stop_command_result);

                //stop result will be something like
                //2,1,Playlist Stopping Now,,,,,,,,,,

                //Sleep for 2 seconds to let FPP sort itself out
                sleep(2);

                //Poll status
                //check result and make sure we're idle after 2 seconds wait
                $stop_command_result = exec($FPP_STATUS);
                logEntry("VOTE CHECK: !! Slept for 2 seconds, Polling FPP status - RESULT - " . $stop_command_result);
                //process status
                $fpp_status = FPP_parseStatus($stop_command_result);
                //are we idle
                $fpp_idle = false;
                if (!empty($fpp_status)) {
                    $fpp_status_code = $fpp_status['status'];
                    $fpp_status_name = $fpp_status['status_name'];

                    if ((int)$fpp_status_code == 0 && strtolower($fpp_status_name) == "idle") {
                        $fpp_idle = true;
                    }
                }

                //If still we're not idle then we can loop around till we are upto the max number of loops
                $sleep_count = 0;
                //while fpp is not idle, and until we hit the max time we want to sleep, poll it status
                while (($fpp_idle == false)) {
                    //break if we're over the max loops we want to do searching for idle
                    if (($sleep_count > $max_loops_for_idle)) {
                        break;
                    }
                    logEntry("VOTE CHECK: !! Searching for idle - Polling FPP status on loop - " . $sleep_count);

                    //Poll status
                    $stop_command_result = exec($FPP_STATUS);
                    logEntry("VOTE CHECK: !! POLLING FPP STATUS !! - CMD - " . $FPP_STATUS);
                    logEntry("VOTE CHECK: !! Searching for idle -  Polling FPP status - RESULT - " . $stop_command_result);

                    //process status
                    $fpp_status = FPP_parseStatus($stop_command_result);

                    //we want the fpp status to be "status" = 0 or "status_name" = idle
                    //if not sleep another 1 sec.
                    if (!empty($fpp_status)) {
                        $fpp_status_code = $fpp_status['status'];
                        $fpp_status_name = $fpp_status['status_name'];

                        if ((int)$fpp_status_code == 0 && strtolower($fpp_status_name) == "idle") {
                            $fpp_idle = true;
                            //break the loop once found
                            break;
                        }
                    }
                    //log it
                    logEntry("VOTE CHECK: !! FPP Still not idle, waiting.. - " . $stop_command_result);
                    //increment loop count
                    $sleep_count++;
                    //Sleep 1 second
                    sleep(1);
                }

                //FPP is idle, tell FPP to play the most voted playlist
                if ($fpp_idle == true) {
                    logEntry("VOTE CHECK: FPP is idle, getting ready to play Most Voted item");

                    //Command to play the most voted playlist in no repeat mode
                    $most_voted_playlist_play_command = $FPP_PLAY_PLAYLIST_NR . " MOST_VOTED";

                    //Play count is less than the max repeats
                    if ($item_play_count < $MAX_ITEM_REPEATS) {
//                        logEntry("VOTE CHECK: Playing " . $sequence_name . " as it is under the repeat count (" . ($item_play_count) . "/" . $MAX_ITEM_REPEATS . ")");

                        //we could monitor for start, but not at the moment
                        $playlist_play_cmd_result = exec($most_voted_playlist_play_command);
                        logEntry("VOTE CHECK: !! FPP PLAY PLAYLIST !! - CMD - " . $most_voted_playlist_play_command);
                        logEntry("VOTE CHECK: !! FPP PLAY PLAYLIST !! - RESULT - " . $playlist_play_cmd_result);

                        //Update the played sequences array
                        if (is_array($PLAYED_SEQUENCES)) {
                            //append
                            $PLAYED_SEQUENCES[] = $sequence_name;
                        } else {
                            //new array
                            $PLAYED_SEQUENCES = [];
                            $PLAYED_SEQUENCES[] = $sequence_name;
                        }
                        //reorganize indexes
                        $PLAYED_SEQUENCES = array_values($PLAYED_SEQUENCES);

                        //Write played sequences to the config
                        writeToConfig('PLAYED_SEQUENCES', urlencode(json_encode($PLAYED_SEQUENCES)), $pluginName);
                        logEntry("VOTE CHECK: PLAYED_SEQUENCES as of this round: " . implode(",", $PLAYED_SEQUENCES));

                        //flag config to say we've transitioned over to the MOST_VOTED playlist.
                        //when that playlist finishes the schedule playlist will run, the event which checks the voted items will fire off again.
                        //we don't want to get stuck in a loop of playing most voted
                        writeToConfig('PL_TRANSITIONED', true, $pluginName);
                    } else {
////                        logEntry("VOTE CHECK: NOT Playing " . $sequence_name . " as it is OVER the repeat ($item_play_count/$MAX_ITEM_REPEATS)");
//
//                        //Say we've transitioned (we haven't) so we don't somehow get stuck in a loop of restarting fppd
//                        writeToConfig('PL_TRANSITIONED', true, $pluginName);
//
//                        //we've reached the repeat count, we'll effectively ignore the changing playlists
//                        //Stop FPPD
//                        exec($FPC_FPPD_STOP);
//                        $FPP_WAS_STOPPED = true;
//                        logEntry("VOTE CHECK: !!WARNING FPPD RESTART!! Stopping FPPD.. wait 2 seconds");
//
//                        //sleep 2 seconds
//                        sleep(2);
//
//                        //Check it's not runing & start it again
//                        $status = exec("if ps cax | grep -q fppd; then echo \"true\"; else echo \"false\"; fi");
//                        if ($status == 'false') {
//                            logEntry("VOTE CHECK: !!WARNING FPPD RESTART!! FPPD has stopped, attempting FPP Daemon start");
//                            //start FPPD now, we could do another status check but seems excessive
//                            $status = exec($FPC_FPPD_START);
//                            $FPP_WAS_STOPPED = false;
//                        }
                    }
                } else if ($fpp_idle == false) {
                    //Say we've transitioned (we haven't) so we don't somehow get stuck in a loop
                    //try and let FPP run the entire schedule playlist itself
                    writeToConfig('PL_TRANSITIONED', true, $pluginName);

                    //if FPP still not idle, we're in trouble.
                    // Abort & restart FPPD
                    logEntry("WARNING :: VOTE CHECK: !!WARNING FPPD RESTART!! FPP still not idle after 4 seconds - attempting FPP Daemon stop & restart");
                    //Stop FPPD
                    exec($FPC_FPPD_STOP);
                    $FPP_WAS_STOPPED = true;
                    logEntry("WARNING :: VOTE CHECK: !!WARNING FPPD RESTART!! Stopping FPPD.. wait 2 seconds");

                    //sleep 2 seconds
                    sleep(2);

                    //Check it's not runing & start it again
                    $status = exec("if ps cax | grep -q fppd; then echo \"true\"; else echo \"false\"; fi");
                    if ($status == 'false') {
                        logEntry("VOTE CHECK: !!WARNING FPPD RESTART!! FPPD has stopped, attempting FPP Daemon start");
                        //start FPPD now, we could do another status check but seems excessive
                        $status = exec($FPC_FPPD_START);
                        $FPP_WAS_STOPPED = false;
                    }
                }
            } else {
                //Do nothing just to be safe
                logEntry("VOTE CHECK: Tried creating playlist for Most Voted item, but failed for some reason. Stopping here!");
                //exit here
                exit(0);
            }

            //Things do can do
            //#1 - destructive (more so if something goes wrong)
            //Insert into main playlist, @ position after the check_votes event
            //restart the FPPD so the new entry takes effect
            //playlist starts over,
            //mark that this was done recently (so on restart above we don't reprocess & get stuck), next round it should be removed or replaced with the next most-voted item
            //could log entire entry row & find & replace it next round


            //#2 - least destructive
            // fpp -d - force stop
            // fpp -P "PLAYLIST" play playlist with no looping
            // after it's done, the schedule playlist starts again on schedule -- unsure how reliable this is
            //need a way to track that we have switched to play that playlist
            //so we don't get re-check again (thus playing the most voted item) again, and get stuck in a loop... same as above

            //with either method
            //If most voted item is teh same, follow the rules as set by the user & repeat it x number of times
            //once exceeded - remove
        }
        //end most voted item data check
    }
    //end most voted item api returned data check
}
//end most voted

//If we get here and FPPD was stopped but not started then attempt to stop and start it again
//this SHOULD catch cases where for whatever reason FPPD took to long to stop (still running after the 2second sleep) and was not started
if ($FPP_WAS_STOPPED == true) {
    //Say we've transitioned (we haven't) so we don't somehow get stuck in a loop
    //try and let FPP run the entire schedule playlist itself
    writeToConfig('PL_TRANSITIONED', true, $pluginName);

    //if FPP still not idle, we're in trouble.
    // Abort & restart FPPD
    logEntry("WARNING :: VOTE CHECK: !!WARNING FPPD RESTART!! FPP still not idle after 4 seconds - attempting FPP Daemon stop & restart");
    //Stop FPPD
    exec($FPC_FPPD_STOP);
    $FPP_WAS_STOPPED = true;
    logEntry("WARNING :: VOTE CHECK: !!WARNING FPPD RESTART!! Stopping FPPD.. wait 2 seconds");

    //sleep 2 seconds
    sleep(2);

    //Check it's not runing & start it again
    $status = exec("if ps cax | grep -q fppd; then echo \"true\"; else echo \"false\"; fi");
    if ($status == 'false') {
        logEntry("WARNING :: VOTE CHECK: !!WARNING FPPD RESTART!! FPPD has stopped, attempting FPP Daemon start");
        //start FPPD now, we could do another status check but seems excessive
        $status = exec($FPC_FPPD_START);
        $FPP_WAS_STOPPED = true;
    }
}

logEntry("VOTE CHECK: !! Script Ending...");
?>