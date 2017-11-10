<?php
include_once('ApiClient/DeviceDataApi.php');
//require_once('/opt/fpp/www/common.php');

//Helper functions
function persistMainPlaylist()
{

}

function persistSparePlaylist()
{

}

/**
 * Writes the dynamic playlist data to the ini file
 *
 * @param $dynamic_playlist_data
 */
function persistDynamicPlaylist($dynamic_playlist_data)
{
    //Write the returned dynamic playlist into our setting storage
    setPluginSettings('DYNAMIC_PLAYLIST_DATA', $dynamic_playlist_data);
    setPluginSettings('DYNAMIC_PLAYLIST_LAST_UPDATE', time());
}

/**
 * Submits the now-playing sequence to the API
 * called through FPPD callbacks
 *
 * @param $sequence
 * @param $title
 * @param $length
 */
function submitNowPlaying($sequence, $title, $length)
{
    $nowPlayingData = array();

    //Get settings
    $settings = getPluginSettings();

    //api client
    $_apiClient = new DeviceDataApi($settings['API_SERVER'], $settings['API_KEY']);
    //Makre sure we have a title
    if (isset($title) && !empty($title)) {
        $nowPlayingData['name'] = $title;
    } else {
        //use the sequence name, clean it up first
        $clean_sequence_name = ucwords(strtolower(trim(str_replace("_", " ", str_replace(".fseq", "", $sequence)))));
        $nowPlayingData['name'] = $clean_sequence_name;
    }

    //Build up the length if minutes and seconds area supplied
    if ((isset($length))) {
        $nowPlayingData['duration'] = ($length);
    }

    //Call the api and submit the now playing data
    logEntry("Submit Now Playing data: " . json_encode($nowPlayingData));
    $_apiClient->nowPlaying($nowPlayingData);
}

/**
 * Retrieves the voted (whole) playlist  from the API
 */
function getVotedPlaylist()
{
    $voted_playlist_data = array();
    //Get settings
    $settings = getPluginSettings();

    //api client
    $_apiClient = new DeviceDataApi($settings['API_SERVER'], $settings['API_KEY']);

    $voted_playlist_data = $_apiClient->getDynamicPlaylist();
    //Call the api and get the most voted item
    logEntry("Retrieve Voted Playlist -- DATA: " . json_encode($voted_playlist_data));

    //Write dynamic playlist data into settings
    persistDynamicPlaylist($voted_playlist_data['data']['playlist']);

    return $voted_playlist_data;
}

/**
 * Clears the list of played sequences
 */
function clearPlayedSequences()
{
    global $pluginName;
    writeToConfig('PLAYED_SEQUENCES', urlencode(json_encode(array())), $pluginName);
}

/**
 * Creates a playlist for the 'voted playlist'
 * the voted playlist will always have the VOTE_CHECK event included so the playlist is updated every round
 * and keep playing the dynamic playlist
 *
 * @param $voted_playlist array
 * @return bool|int
 */
function createVoted_Playlist($voted_playlist)
{
    global $playlistDirectory, $DEBUG;

    //Playlist name
    $playlist_name = "VOTED_PLAYLIST";
    $first = $last = 0;//Play first entry once, play last entry once, 0 is false or off
    $written = false;
    $sequence_exists = false;
    $event_added = false;
    //make sure the supplied item is not empty
    //holds the rows of data for the playlist
    $playlist_item_data = array();

    //Get the plugin settings
    $settings = getPluginSettings();

    //if we have voted playlist data
    if (!empty($voted_playlist)) {
        //Voted playlist is an array so loop over it and process each item
        foreach ($voted_playlist as $vp_idx => $vp_data) {
            $sequence_exists = checkSequenceExists($vp_data);
            //If sequence exists then we can make the playlist
            if ($sequence_exists == true) {
                //we can use this playlist data, save it
                $playlist_item_data[] = $vp_data;
            } else {
                logEntry("createVoted_Playlist: Sequence DOES NOT Exists - SKIPPING - " . json_encode($vp_data));
            }
        }

        //we have playlist item rows
        if (isset($playlist_item_data) && !empty($playlist_item_data)) {
            logEntry("createVoted_Playlist: Sequence Exists - Creating playlist");

            //calculate the playlist path
            $voted_playlist_path = $playlistDirectory . '/' . $playlist_name;
            //remove the existing playlist file, we'll write a new one
            unlink($voted_playlist_path);

            //create the most voted playlist - taken from fppxml.php
            $f = fopen($voted_playlist_path, "w");
            //If w were able to open / get the file resource, then proceed with writing
            if ($f !== FALSE) {
                //write the playlist header that specifies whether first and/or last item plays once
                $entries = sprintf("%s,%s,\n", $first, $last);

                //first item should be the spacer sequence
                if (isset($settings['SPACER_SEQUENCE']) && !empty($settings['SPACER_SEQUENCE'])) {
                    //insert the sequence
                    logEntry("createVoted_Playlist: Inserting Spacer Sequence -- " . $settings['SPACER_SEQUENCE']);
                    $entries .= sprintf("%s,%s,\n", 's', $settings['SPACER_SEQUENCE']);
                }

                //loop over the $playlist_item_data, which contains the items to add to the playlist
                foreach ($playlist_item_data as $pi_idx => $pi_data) {
                    //now write the playlist line, we're supplied with the whole line so put that in
                    $entries .= $pi_data . "\n";
                }

                //Add the event at the end
                if ($event_added == false) {
                    //Find if the event exists already, returned value is true/false
                    $EVENT_FILE_NAME = getEventFileNameForKey("VOTE_CHECK_API");
                    //remove the file event file extension
                    $EVENT_FILE_NAME = trim(str_ireplace(".fevt", "", $EVENT_FILE_NAME));
                    if (isset($EVENT_FILE_NAME) && !empty($EVENT_FILE_NAME)) {
                        logEntry("createVoted_Playlist: Adding VOTE_CHECK_API event - " . $EVENT_FILE_NAME);
                        //add the event file. eg e,01_05,
                        $entries .= sprintf("%s,%s,\n", 'e', $EVENT_FILE_NAME);
                        $event_added = true;
                    } else {
                        logEntry("createVoted_Playlist: Could note find VOTE_CHECK_API event");
                    }
                }

                //Write the file out
                $written = fwrite($f, $entries);
                //close file
                fclose($f);

                logEntry("createVoted_Playlist: Creating Playlist for All Voted Items -- " . $entries);
            } else {
                logEntry("createVoted_Playlist: " . "Unable to open file! : " . $voted_playlist_path);
                exit("Unable to open file! : " . $voted_playlist_path);
            }

            //final double check that the playlist exists
            $written = file_exists($voted_playlist_path);
            //If it was written, then sync playlists, which copy the playlists to all the slave devices if we're in the correct mode
            if ($written !== false) {
                //Sync to slaves if required
                syncPlaylists();
            }
        }

        //Will also be false if sequence doesn't exists
        //return file write result
        return $written;
    }
    //we'll get here if the playlist_item was empty
    return $written;
}

/**
 * Retrieve the most voted item from the API
 *
 * @param bool $auto_reset
 * @return null|string
 */
function getMostVotedItem($auto_reset = false)
{
    $most_voted_data = array();
    //Get settings
    $settings = getPluginSettings();

    //api client
    $_apiClient = new DeviceDataApi($settings['API_SERVER'], $settings['API_KEY']);

    $most_voted_data = $_apiClient->getMostVoted($auto_reset);
    //Call the api and get the most voted item
    logEntry("Retrieve Most Voted Item: Reset [" . $auto_reset . "] -- DATA: " . json_encode($most_voted_data));

    return $most_voted_data;
}

/**
 * Resets the vote count on the Most Voted item
 *
 * @param $playlist_item_id
 * @return bool
 */
function resetMostVotedItem($playlist_item_id)
{
    $playlist_item_post_reset = array();

    //Get settings
    $settings = getPluginSettings();

    //api client
    $_apiClient = new DeviceDataApi($settings['API_SERVER'], $settings['API_KEY']);

    $playlist_item_post_reset = $_apiClient->resetPlaylistItemVotes($playlist_item_id);
    //Call the api and get the most voted item
    logEntry("Reset Most Voted Item votes - ID [" . $playlist_item_id . "] -- DATA: " . json_encode($playlist_item_post_reset));

    return $playlist_item_post_reset;
}

/**
 * Creates a playlist for the most voted item
 * This playlist is then played by FPPD
 *
 * @param $playlist_item
 * @return bool|int
 */
function createMostVoted_Playlist($playlist_item)
{
    global $playlistDirectory, $settings, $DEBUG;

    //Playlist name
    $playlist_name = "MOST_VOTED";
    $first = $last = 0;//Play first entry once, play last entry once, 0 is false or off
    $written = false;
    $sequence_exists = false;

    //make sure the supplied item is not empty
    if (!empty($playlist_item)) {
        $sequence_exists = checkSequenceExists($playlist_item);
        //If sequence exists then we can make the playlist
        if ($sequence_exists == true) {
            logEntry("createMostVoted_Playlist: Sequence Exists - Creating playlist ");

            $most_voted_playlist_path = $playlistDirectory . '/' . $playlist_name;
            //remove the existing playlist file, we'll write a new one
            unlink($most_voted_playlist_path);

            //create the most voted playlist - taken from fppxml.php
            $f = fopen($most_voted_playlist_path, "w");
            //If w were able to open / get the file resource, then proceed with writing
            if ($f !== FALSE) {
                //write the playlist header that specifies whether first and/or last item plays once
                $entries = sprintf("%s,%s,\n", $first, $last);

                //no space sequence is required for the most voted playlist as once it ends
                //we return to the main playlist
                //but the main playlist should have the spacer sequence as the first item (after the check votes event)

                //now write the playlist line, we're supplied with the whole line so put that in
                $entries .= $playlist_item . "\n";
                //Write the file out
                $written = fwrite($f, $entries);
                //close file
                fclose($f);

                logEntry("createMostVoted_Playlist: Creating Playlist for Most Voted Item -- " . $entries);
            } else {
                logEntry("ERROR :: createMostVoted_Playlist: " . "Unable to open file! : " . $most_voted_playlist_path);

                exit("Unable to open file! : " . $most_voted_playlist_path);
            }

            //final double check that the playlist exists
            $written = file_exists($most_voted_playlist_path);
            //If it was written, then sync playlists, which copy the playlists to all the slave devices if we're in the correct mode
            if ($written !== false) {
                //Sync to slaves if required
                syncPlaylists();
            }
        } else {
            logEntry("ERROR :: createMostVoted_Playlist: Sequence DOES NOT Exists - Aborting");
            return $written;
        }
        //Will also be false if sequence doesn't exists
        //return file write result
        return $written;
    }
    //we'll get here if the playlist_item was empty
    return $written;
}


/**
 * If FPP is in a Master / Slave setup, then playlists will copied to all the slaved to keep everything in sync
 *
 */
function syncPlaylists()
{
    global $settings, $fppHome, $fppMode;
    $dir = "playlists";

    //if the fpp mode is master
    if (strtolower($fppMode) == "master") {
        if (isset($settings['MultiSyncRemotes']) && !empty($settings['MultiSyncRemotes'])) {
            $remotes = explode(',', $settings['MultiSyncRemotes']);

            if (!empty($remotes)) {
                foreach ($remotes as $remote_id => $remote_ip) {
                    $command = "rsync -av --stats $fppHome/media/$dir/ $remote_ip::media/$dir/ 2>&1";
                    $command_result = exec($command);
                    logEntry("syncPlaylists: Attempt Sync Playlists to (" . $remote_ip . ") : Result: " . $command_result);
                }
            }
        }
    }
}

/**
 * Checks if a sequence exists
 *
 * @param $playlist_item_row string Should be a string that represents the playlist item row,
 * @return boolean
 */
function checkSequenceExists($playlist_item_row)
{
    global $settings;
    $exists = false;

    if (isset($playlist_item_row) && !empty($playlist_item_row)) {
        //extract data out
        $row_data_array = explode(",", $playlist_item_row);
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

        //Make sure the sequence name is set and not empty if so then something is wrong
        if (isset($sequence_name) && !empty($sequence_name)) {
            //check return all the valid types for a sequence
            $sequence_types = playlistItemTypeMappings('sequence');

            //Doubly check that the type of this playlist item is a sequence type
            if (in_array($type, $sequence_types)) {
                //
//                logEntry("checkSequenceExists: Playlist Item is a sequence");
                //Build up the sequence path
                $sequence_path = $settings['sequenceDirectory'] . '/' . $sequence_name;
                //Check that it exists
                $exists = file_exists($sequence_path);
                return $exists;
            } else {
                //
                logEntry("WARNING :: checkSequenceExists: Playlist Item is NOT a sequence");
                return false;
            }
        } else {
            logEntry("ERROR :: checkSequenceExists: Could not extract sequence name from: " . $playlist_item_row);
            return $exists;
        }
    }

    //Exists will be false if we make it down here
    return $exists;
}

/**
 * Uploads the playilist(s) to the voting server
 * stores the response as the dynamic playlist (DYNAMIC_PLAYLIST_DATA).
 *
 * DYNAMIC_PLAYLIST_DATA is the playlist as it is on the server
 */
function uploadPlaylist()
{
    //Get settings
    $settings = getPluginSettings();

    //api client
    $_apiClient = new DeviceDataApi($settings['API_SERVER'], $settings['API_KEY']);

    $playlist_options = array();
    $dynamic_playlist_data = $playlist_data = array();

    //Build the playlist options up based on what our selected settings here
    if (!empty($settings['FULLY_DYNAMIC_ONLY']) && strtolower($settings['FULLY_DYNAMIC_ONLY']) == "on") {
        $playlist_options['auto_rearrange'] = true;
        $playlist_options['auto_weighting'] = true;
    } else if (!empty($settings['HIGHEST_VOTED_ONLY']) && strtolower($settings['HIGHEST_VOTED_ONLY']) == "on") {
        $playlist_options['auto_rearrange'] = false;
        $playlist_options['auto_weighting'] = false;
    }

    //Get the playlist data
    $playlist_data['main'] = $settings['MAIN_PLAYLIST_DATA'];

    //get the spare playlist data if spare voting
    if (isset($settings['SPARE_VOTING']) && strtolower($settings['SPARE_VOTING']) == 'on') {
        $playlist_data['spare'] = $settings['MAIN_PLAYLIST_DATA'];
    }

    //If we have playlist data... well at least the main playlist, we can upload it
    if (isset($playlist_data['main']) && isset($playlist_options)) {
        //Main playlist upload
        //make the call
        logEntry("Sending spare playlist data to api: " . json_encode(array('options' => $playlist_options, 'main' => $playlist_data['main'])));

        $playlist_upload_api = $_apiClient->upload($playlist_options, $playlist_data['main'], 'main');
    } elseif (isset($playlist_data['spare']) && isset($playlist_options)) {
        //Spare playlist upload
        //make the call
        logEntry("Sending main playlist data to api: " . json_encode(array('options' => $playlist_options, 'spare' => $playlist_data['spare'])));

        $playlist_upload_api = $_apiClient->upload($playlist_options, $playlist_data['main'], 'spare');
    }

    //From the response we want the playlist_main data, we'll store it as the dynamic playlist
    if (isset($playlist_upload_api['data']['device_data'])) {
        $dynamic_playlist_data = $playlist_upload_api['data']['device_data']['playlist_main'];
        persistDynamicPlaylist($dynamic_playlist_data);
    }
}

/**
 * Returns the Device / API key from the settings
 *
 * @return mixed
 */
function getDeviceKey()
{
    $settings = getPluginSettings();
    return $settings['API_KEY'];
}

/**
 * Write plugin settings
 *
 * @param $setting_key
 * @param $setting_data
 */
function setPluginSettings($setting_key, $setting_data)
{
    $pluginName = "FPP-VotingAPI-Integration";

    //If setting data is any kind of array or object, json_encode it so we can put it in our settings file
    if (is_object($setting_data) or is_array($setting_data)) {
        $setting_data = urlencode(json_encode($setting_data));
    }

    WriteSettingToFile("$setting_key", $setting_data, $pluginName);
}

/**
 * Returns an array of our plugin settings, with decoded data etc.
 *
 * @return array
 */
function getPluginSettings()
{
    global $settings;

    //Build up the path tot he plugin settings
    $pluginName = "FPP-VotingAPI-Integration";
    $pluginConfigFile = $settings['configDirectory'] . "/plugin." . $pluginName;

    //settings array
    $settings_array = [];

    //load settings if plugin settings file exists
    if (file_exists($pluginConfigFile)) {
        $pluginSettings = parse_ini_file($pluginConfigFile);

        //Read checkbox settings in
        $settings_array['ENABLED'] = $pluginSettings['ENABLED'];
        //API / Device Key
        $settings_array['API_KEY'] = $pluginSettings['API_KEY'];
        //API server
        if (isset($pluginSettings['API_SERVER']) && !empty($pluginSettings['API_SERVER'])) {
            $settings_array['API_SERVER'] = urldecode($pluginSettings['API_SERVER']);
        } else {
            //default to production if not set
            $settings_array['API_SERVER'] = "http://christmaslightsnear.me/api/";
        }
        //Playlists -- all JSON encoded
        $settings_array['MAIN_PLAYLIST'] = ($pluginSettings['MAIN_PLAYLIST']);
        $settings_array['MAIN_PLAYLIST_DATA'] = json_decode(urldecode($pluginSettings['MAIN_PLAYLIST_DATA']), true);
        //Backup made of the main playlist before it's modified by the vote_check system
        $settings_array['MAIN_PLAYLIST_BACKUP'] = json_decode(urldecode($pluginSettings['MAIN_PLAYLIST_BACKUP']), true);
        //Spare playlist
        $settings_array['SPARE_PLAYLIST'] = ($pluginSettings['SPARE_PLAYLIST']);
        $settings_array['SPARE_PLAYLIST_DATA'] = json_decode(urldecode($pluginSettings['SPARE_PLAYLIST_DATA']), true);
        //Spacer sequence
        $settings_array['SPACER_SEQUENCE'] = ($pluginSettings['SPACER_SEQUENCE']);

        //Backup playlist is a copy of the main playlist
        $settings_array['BACKUP_PLAYLIST'] = json_decode(urldecode($pluginSettings['BACKUP_PLAYLIST']), true);
        //Dynamic Playlist is the playlist returned from the API,
        $settings_array['DYNAMIC_PLAYLIST_DATA'] = json_decode($pluginSettings['DYNAMIC_PLAYLIST_DATA'], true);
        $settings_array['DYNAMIC_PLAYLIST_LAST_UPDATE'] = $pluginSettings['DYNAMIC_PLAYLIST_LAST_UPDATE'];

        //Sequence repeating options
        $settings_array['SPARE_VOTING'] = $pluginSettings['SPARE_VOTING'];
        $settings_array['MAX_ITEM_REPEATS'] = $pluginSettings['MAX_ITEM_REPEATS'];
        $settings_array['MAX_ITEM_ROTATIONS'] = $pluginSettings['MAX_ITEM_ROTATIONS'];
        $settings_array['MOST_VOTED_RESET'] = $pluginSettings['MOST_VOTED_RESET'];
        //Playlist options
        $settings_array['FULLY_DYNAMIC_ONLY'] = $pluginSettings['FULLY_DYNAMIC_ONLY'];
        $settings_array['HIGHEST_VOTED_ONLY'] = $pluginSettings['HIGHEST_VOTED_ONLY'];
        //
        $settings_array['SET_MAIN_PL_NAMES_ADDED'] = $pluginSettings['SET_MAIN_PL_NAMES_ADDED'];
        $settings_array['SET_SPARE_PL_NAMES_ADDED'] = $pluginSettings['SET_SPARE_PL_NAMES_ADDED'];
        //Sequences played / tracking items (so we know what and how many times they've played)
        $settings_array['PLAYED_SEQUENCES'] = json_decode(urldecode($pluginSettings['PLAYED_SEQUENCES']), true);
        $settings_array['PL_TRANSITIONED'] = $pluginSettings['PL_TRANSITIONED'];

        //Dynamic Playlist Upate Sequence Number -- used to track if there were any updates to the playlist checked against the API value, if changed, pull down a new playlist
//    $DYNAMIC_PLAYLIST_USN = $pluginSettings['DYNAMIC_PLAYLIST_USN'];
    }

    return $settings_array;
}

/**
 * Returns a full date format to be used by the php date_format function
 * Current: Y-m-d H:i:s
 *
 * @return string
 */
function getDateTimeFormatFull()
{
    $date_time_format_full = "Y-m-d H:i:s";
    return $date_time_format_full;
}

/**
 * Returns a date format to be used by the php date_format function
 * Current: Y-m-d
 *
 * @return string
 */
function getDateFormat()
{
    $date_time_format_full = "Y-m-d";
    return $date_time_format_full;
}

/**
 * Returns a date format to be used by the php date_format function
 * Current: H:i:s
 *
 * @return string
 */
function getTimeFormat()
{
    $date_time_format_full = "H:i:s";
    return $date_time_format_full;
}


/**
 * Returns the item types used in the playlist files for sequences, event, etc etc.
 *
 * @param $type
 * @return array|mixed
 */
function playlistItemTypeMappings($type)
{
// 'b' => Media and Sequence
// 'm' => Media Only
// 's' => Sequence Only
// 'p' => Pause
// 'e' => Event
// 'P' => Plugin
    //convert type to all lower characters
    $type_lower = strtolower($type);
    //mappings array
    $playlist_type_mappings = array(
        'sequence' => array(
            'b',
            'm',
            'a'
        ),
        'event' => array('e'),
        'pause' => array('p'),
        'plugin' => array('P')
    );
    //if supplied type exists in the array, return it's values
    if (array_key_exists($type_lower, $playlist_type_mappings)) {
        return $playlist_type_mappings[$type_lower];
    }
    return null;
}

/**
 * Processes the supplied playlist item entry and returns a easy to deal with array
 *
 * @param $row_data
 * @return array
 */
function processPlaylistItemRow($row_data)
{
//b,2015-wizards in winter-tso.fseq,04-Wizards in Winter.mp3,
    //Make sure we've been supplied with data
    if (isset($row_data)) {
        //Explode array
        $row_data_array = explode(",", $row_data);

        //If array data is now empty
        if (!empty($row_data_array)) {
            $sequence_name_clean = '';
            //extract data out
            $type = $row_data_array[0];
            //Sequence name
            if (isset($row_data_array[1])) {
                $sequence_name = $row_data_array[1];
                $sequence_name_clean = str_replace(".fseq", "", $row_data_array[1]);
            }
            //Media name
            if (isset($row_data_array[2])) {
                $song_name = $row_data_array[2];
            }

            //Get the sequence types for playlists
            $_sequence_types = playlistItemTypeMappings('sequence');

            //If the item type is one of sequences then we want to keep this row
            if (in_array($type, $_sequence_types)) {
                //return a nicely formatted array
                return array('name' => $sequence_name_clean, 'data' => $row_data);
            }
        }
    }
    return null;
}

/**
 *  Returns a list of playlists from the the /playlists directory
 *
 * @return array
 */
function retrievePlaylists()
{
    global $settings;

    $playlists = read_playlist_directory_files($settings['mediaDirectory'] . "/playlists", false);
    $playlist_names = array();

    //find the plugin configs
    foreach ($playlists as $fname => $fdata) {
        if ((stripos(strtolower($fname), ".json") == false)) {
            //split the string to get jsut the plugin name
//            $playlist_name = explode(".", $fname);
//            $playlist_name = $playlist_name[1];
//            $playlist_name = str_replace("_"," ",$fname);
            $playlist_name = $fname;
            $playlist_names[$playlist_name] = array('type' => 'file', 'location' => $settings['mediaDirectory'] . "/playlists" . "/" . $fname);
        }
    }

    return $playlist_names;
}

/**
 * Retruns a list of sequences from the sequences directory
 *
 * @return array
 */
function retrieveSequences()
{
    global $settings;

    $sequences = read_directory_files($settings['sequenceDirectory'] . "/", false);
    $sequence_names = array();

    //find the plugin configs
    foreach ($sequences as $fname => $fdata) {
        if ((stripos(strtolower($fname), ".json") == false)) {
            //split the string to get jsut the plugin name
//            $playlist_name = explode(".", $fname);
//            $playlist_name = $playlist_name[1];
//            $playlist_name = str_replace("_"," ",$fname);
            $sequence_name = $fname;
            $sequence_names[$sequence_name] = array('type' => 'file', 'location' => $settings['sequenceDirectory'] . "/" . $fname);
        }
    }

    return $sequence_names;
}


/**
 * Returns a contents of the specified playlist
 *
 * @param string $playlist_name
 * @return array
 */
function retrievePlaylistContents($playlist_name)
{
    global $settings;
    $playlist_data = null;

    //check the supplied playlist name isn't empty
    if (!empty($playlist_name)) {
        $playlist_contents = read_playlist_directory_files($settings['mediaDirectory'] . "/playlists", true);

        //find the plugin configs
        foreach ($playlist_contents as $fname => $fdata) {
            if ((stripos(strtolower($fname), ".json") == false)) {
                //Find the playlist supplied, $fname is the filename found in the playlist directory
                if (strtolower($fname) == strtolower($playlist_name)) {
                    //split the string to get jsut the plugin name
//            $playlist_name = explode(".", $fname);
//            $playlist_name = $playlist_name[1];
//            $playlist_name = str_replace("_"," ",$fname);
                    $playlist_name = $fname;
                    $playlist_data[$playlist_name] = array('type' => 'file', 'data' => $fdata);
                }
            }
        }
    }

    return $playlist_data;
}

/**
 * Looks in a directory and if specified reads file contents of files within it
 *
 * @param $directory String directory to search in
 * @param $return_data Boolean switch to include file data
 * @return array Array of file names and respective data
 */
function read_playlist_directory_files($directory, $return_data = false)
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
 * Writes settings to the plugin config
 *
 * @param $setting
 * @param $payload
 * @param $pluginName
 * @param bool $encode
 */
function writeToConfig($setting, $payload, $pluginName, $encode = false)
{
    if ((isset($setting) && is_string($setting)) && isset($payload)) {
        if ($encode == true) {
            $payload = json_encode($payload);
        }
        WriteSettingToFile($setting, $payload, $pluginName);
    }
}

