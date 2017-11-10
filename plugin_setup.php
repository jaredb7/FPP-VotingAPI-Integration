<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

//"/opt/fpp/www/common.php";
include_once("functions.inc.php");
include_once("commonFunctions.inc.php");

/**
 * Plugin descriptors
 */
$pluginName = "FPP-VotingAPI-Integration";
$pluginVersion = "1.0";
define('VOTING_API_PLUGIN_VERSION', $pluginVersion);

//$DEBUG=true;
/**
 * PHP Process ID running under
 */
$myPid = getmypid();
/**
 * Plugin Github URL
 */
$gitURL = "https://github.com/jaredb7/FPP-VotingAPI-Integration.git";
/**
 * Plugin Update file location
 */
$pluginUpdateFile = $settings['pluginDirectory'] . "/" . $pluginName . "/" . "pluginUpdate.inc";
/**
 * Plugin Logfile
 */
$logFile = $settings['logDirectory'] . "/" . $pluginName . ".log";

//Log what our plugin update file is
//logEntry("Plugin update file: " . $pluginUpdateFile);

/**
 * If POST vars contain 'updatePlugin' then we should probably update the plugin
 */
if (isset($_POST['updatePlugin'])) {
    $updateResult = updatePluginFromGitHub($gitURL, $branch = "master", $pluginName);
    echo $updateResult . "<br/> \n";
}

//API server
$API_SERVER = "http://christmaslightsnear.me/api";
//Defaults
$SET_MAIN_PL_NAMES = false;
$RESET_MAIN_PL_NAMES = false;
$SET_SPARE_PL_NAMES = false;
$RESET_SPARE_PL_NAMES = false;

/**
 * Do setting of the API separately do
 */
if (isset($_POST['SET_API_SERVER'])) {
    //Set API server
    //API Server endpoint
    WriteSettingToFile("API_SERVER", urlencode($_POST["API_SERVER"]), $pluginName);
}

/**
 * If POST vars contain 'submit' then the Save button was clicked
 * save select items from the POST data
 */
if (isset($_POST['submit']) || ((isset($_POST['SET_MAIN_PL_NAMES']) || isset($_POST['SET_SPARE_PL_NAMES'])) || (isset($_POST['RESET_MAIN_PL_NAMES']) || isset($_POST['RESET_SPARE_PL_NAMES'])))) {
    //Toggle input fields to edit playlist names
    if (isset($_POST['SET_MAIN_PL_NAMES'])) {
        $SET_MAIN_PL_NAMES = true;
    } elseif (isset($_POST['SET_SPARE_PL_NAMES'])) {
        $SET_SPARE_PL_NAMES = true;
    }

    //If any of the reset buttons have been clicked
    if (isset($_POST['RESET_MAIN_PL_NAMES'])) {
        $RESET_MAIN_PL_NAMES = true;
    } elseif (isset($_POST['RESET_SPARE_PL_NAMES'])) {
        $RESET_SPARE_PL_NAMES = true;
    }

    //Capture and set all values
    WriteSettingToFile("API_KEY", urldecode($_POST["API_KEY"]), $pluginName);
    WriteSettingToFile("MAIN_PLAYLIST", urldecode($_POST["MAIN_PLAYLIST"]), $pluginName);
    WriteSettingToFile("SPARE_PLAYLIST", urldecode($_POST["SPARE_PLAYLIST"]), $pluginName);
    //Spacer sequence
    WriteSettingToFile("SPACER_SEQUENCE", urldecode($_POST["SPACER_SEQUENCE"]), $pluginName);

    //Start and end events
    if (isset($_POST['START_EVENT'])) {
        WriteSettingToFile("START_EVENT_SET", true, $pluginName);
        WriteSettingToFile("START_EVENT", urldecode($_POST["START_EVENT"]), $pluginName);
    } else {
        WriteSettingToFile("START_EVENT_SET", false, $pluginName);
    }
    if (isset($_POST['END_EVENT'])) {
        WriteSettingToFile("END_EVENT_SET", true, $pluginName);
        WriteSettingToFile("END_EVENT", urldecode($_POST["END_EVENT"]), $pluginName);
    } else {
        WriteSettingToFile("END_EVENT_SET", false, $pluginName);
    }

    //Control Items
    WriteSettingToFile("MAX_ITEM_REPEATS", urldecode($_POST["MAX_ITEM_REPEATS"]), $pluginName);
    WriteSettingToFile("MAX_ITEM_ROTATIONS", urldecode($_POST["MAX_ITEM_ROTATIONS"]), $pluginName);
    //Checkbox items
    WriteSettingToFile("SPARE_VOTING", strtoupper(urldecode($_POST["SPARE_VOTING"])), $pluginName);
    WriteSettingToFile("MOST_VOTED_RESET", strtoupper(urldecode($_POST["MOST_VOTED_RESET"])), $pluginName);
    //Process the radio button for playlist vote mode
    if (isset($_POST['PLAYLIST_VOTE_MODE']) && strtoupper(urldecode($_POST['PLAYLIST_VOTE_MODE'])) == "FULLY_DYNAMIC") {
        WriteSettingToFile("FULLY_DYNAMIC_ONLY", strtoupper("ON"), $pluginName);
        WriteSettingToFile("HIGHEST_VOTED_ONLY", strtoupper("OFF"), $pluginName);

    } elseif (isset($_POST['PLAYLIST_VOTE_MODE']) && strtoupper(urldecode($_POST['PLAYLIST_VOTE_MODE'])) == "HIGHEST_VOTED_ONLY") {
        WriteSettingToFile("FULLY_DYNAMIC_ONLY", strtoupper("OFF"), $pluginName);
        WriteSettingToFile("HIGHEST_VOTED_ONLY", strtoupper("ON"), $pluginName);
    }

    ///Capture the main_playlist item names if this specific hidden field exists, but only on submit
    if (isset($_POST['SET_MAIN_PL_NAMES_COUNT']) && isset($_POST['submit'])) {
        $_MAIN_PL_NAMES_COUNT = $_POST['SET_MAIN_PL_NAMES_COUNT'];
        //double check
        if ($_MAIN_PL_NAMES_COUNT > 0) {
            //Then we want to find all the input fields, with names and data
            $_MAIN_PL_NAMES_ARRAY = array();
            for ($pi = 0; $pi < $_MAIN_PL_NAMES_COUNT; $pi++) {
                //playlist_main_item_name_$loop_count
                //playlist_main_item_data_$loop_count

                //try to find the playlist items name and data
                $_field_playlist_name = "playlist_main_item_name_" . $pi;
                $_field_playlist_data = "playlist_main_item_data_" . $pi;
                //Check the post fields actually exist, then get the data
                if (isset($_POST[$_field_playlist_name]) && isset($_POST[$_field_playlist_data])) {
                    $_MAIN_PL_NAMES_ARRAY[$pi] = array(
                        "name" => urldecode($_POST[$_field_playlist_name]),
                        "data" => urldecode($_POST[$_field_playlist_data])
                    );
                }
            }
            //Fix the indexes
            $_MAIN_PL_NAMES_ARRAY = array_values($_MAIN_PL_NAMES_ARRAY);
            //once done, write our the playlist names array to the config file
            WriteSettingToFile("MAIN_PLAYLIST_DATA", urlencode(json_encode($_MAIN_PL_NAMES_ARRAY)), $pluginName);
            WriteSettingToFile("SET_MAIN_PL_NAMES_ADDED", true, $pluginName);
        }
    }

    //If upload checkbox is ticked, this should be the last thing that happens.
    //we want all the data saved before we do this
    if (isset($_POST['SYNC_PLAYLIST']) && strtolower($_POST['SYNC_PLAYLIST']) == "on") {
        uploadPlaylist();
        //Reset the playlist sequences list after the playlist is uploaded
        clearPlayedSequences();
    }

    //Backup playlist is a copy of the main playlist
//    WriteSettingToFile("BACKUP_PLAYLIST", urldecode($_POST["MAIN_PLAYLIST"]), $pluginName);
}

/**
 * Plugin config file location
 */
$pluginConfigFile = $settings['configDirectory'] . "/plugin." . $pluginName;

//Set defaults
$API_KEY = false;
$SET_MAIN_PL_NAMES_ADDED = false;
$MAIN_PLAYLIST = $SPARE_PLAYLIST = $SPACER_SEQUENCE = '';
$MAIN_PLAYLIST_DATA = $SPARE_PLAYLIST_DATA = array();
$START_EVENT = $END_EVENT = 'none';
$START_EVENT_SET = $END_EVENT_SET = false;
$DYNAMIC_PLAYLIST_DATA = [];//array
$DYNAMIC_PLAYLIST_LAST_UPDATE = '';
$MAX_ITEM_REPEATS = $MAX_ITEM_ROTATIONS = 1;
$MOST_VOTED_RESET = $SPARE_VOTING = $FULLY_DYNAMIC_ONLY = $HIGHEST_VOTED_ONLY = 'OFF';
$PLUGIN_LOG_FILE = "/tmp/FPP-VotingAPI-Integration.log";


//load settings if plugin settings file exists
if (file_exists($pluginConfigFile)) {
    $pluginSettings = parse_ini_file($pluginConfigFile);

    //Read checkbox settings in
    $ENABLED = $pluginSettings['ENABLED'];
    //API / Device Key
    $API_KEY = $pluginSettings['API_KEY'];
    if (isset($pluginSettings['API_SERVER']) && !empty($pluginSettings['API_SERVER'])) {
        $API_SERVER = $pluginSettings['API_SERVER'];
    }
    //Playlists -- all JSON encoded
    $MAIN_PLAYLIST = ($pluginSettings['MAIN_PLAYLIST']);
    $MAIN_PLAYLIST_DATA = json_decode(urldecode($pluginSettings['MAIN_PLAYLIST_DATA']), true);
    $SPARE_PLAYLIST = ($pluginSettings['SPARE_PLAYLIST']);
    $SPARE_PLAYLIST_DATA = json_decode(urldecode($pluginSettings['SPARE_PLAYLIST_DATA']), true);
    //Spacer sequence
    $SPACER_SEQUENCE = ($pluginSettings['SPACER_SEQUENCE']);

    //Start / End events
    $START_EVENT_SET = ($pluginSettings['START_EVENT_SET']);
    if (isset($pluginSettings['START_EVENT']) && !empty($pluginSettings['START_EVENT'])) {
        $START_EVENT = ($pluginSettings['START_EVENT']);
    }
    $END_EVENT_SET = ($pluginSettings['END_EVENT_SET']);
    if (isset($pluginSettings['END_EVENT']) && !empty($pluginSettings['END_EVENT'])) {
        $END_EVENT = ($pluginSettings['END_EVENT']);
    }

    //Backup playlist is a copy of the main playlist
    $BACKUP_PLAYLIST = json_decode(urldecode($pluginSettings['BACKUP_PLAYLIST']), true);
    //Dynamic Playlist is the playlist returned from the API,
    $DYNAMIC_PLAYLIST_DATA = json_decode(urldecode($pluginSettings['DYNAMIC_PLAYLIST_DATA']), true);
    $DYNAMIC_PLAYLIST_LAST_UPDATE = $pluginSettings['DYNAMIC_PLAYLIST_LAST_UPDATE'];

    //Sequence repeating options
    $SPARE_VOTING = $pluginSettings['SPARE_VOTING'];
    $MAX_ITEM_REPEATS = $pluginSettings['MAX_ITEM_REPEATS'];
    $MAX_ITEM_ROTATIONS = $pluginSettings['MAX_ITEM_ROTATIONS'];
    $MOST_VOTED_RESET = $pluginSettings['MOST_VOTED_RESET'];
    //Playlist options
    $FULLY_DYNAMIC_ONLY = $pluginSettings['FULLY_DYNAMIC_ONLY'];
    $HIGHEST_VOTED_ONLY = $pluginSettings['HIGHEST_VOTED_ONLY'];

    //Tracker to know if names have been set for the main playlist
    $SET_MAIN_PL_NAMES_ADDED = $pluginSettings['SET_MAIN_PL_NAMES_ADDED'];
    $SET_SPARE_PL_NAMES_ADDED = $pluginSettings['SET_SPARE_PL_NAMES_ADDED'];

    //Dodgy override so we can reload the selected main playlist data and show it to the user
    if ($RESET_MAIN_PL_NAMES == true) {
        $SET_MAIN_PL_NAMES = true; //set true to minic "Set Main Playlist Names" button
        $SET_MAIN_PL_NAMES_ADDED = false;
    } elseif ($RESET_SPARE_PL_NAMES == true) {
        $SET_SPARE_PL_NAMES = true; //set true to minic "Set Spare Playlist Names" button
        $SET_SPARE_PL_NAMES_ADDED = false;
    }

    //Dynamic Playlist Upate Sequence Number -- used to track if there were any updates to the playlist checked against the API value, if changed, pull down a new playlist
//    $DYNAMIC_PLAYLIST_USN = $pluginSettings['DYNAMIC_PLAYLIST_USN'];
}
?>
<html>
<head>
</head>

<div id="VotingApi">
    <div id="tabs">
        <ul>
            <li><a href="#tab-plugin-settings">Plugin Settings</a></li>
            <li><a href="#tab-playlist-view">Playlist Viewer</a></li>
            <li><a href="#tab-log-view">Log</a></li>
            <li><a href="#tab-advanced-settings">Advanced Settings</a></li>
        </ul>
        <div id="tab-plugin-settings">
            <div class="settings vote_plugin">
                <fieldset>
                    <legend>ChristmasDisplaysNear.Me Voting API Support Instructions (v<? echo $pluginVersion ?>)
                    </legend>
                    <span>This plugin enables viewers to vote on playlist / sequence items in your display via a publicly
            accessible website @ <a href="https://ChristmasDisplaysNear.Me">ChristmasDisplaysNear.Me</a></span>

                    <p>Known Issues:
                    <ul>
                        <li>May Cause funky issues with plugins that make use of the postStart and postStop callbacks,
                            due
                            to
                            starting and stopping of FPPD
                        </li>
                    </ul>

                    <p>Things to note:
                    <ul>
                        <li><b>THIS PLUGIN & IS HIGHLY EXPERIMENTAL - while I've done absolute best to mitigate crashing
                                your
                                display. It may happen (fingers crossed).</b></li>
                        <li>You MUST add the VOTE_CHECK_API event to the start of the playlist, after any items that
                            should
                            play
                            only once (if you have them)
                        </li>
                        <li>All MP3's used in sequences must have valid ID3 tags for Artist and Name, otherwise Now
                            Playing
                            data
                            will not be submitted
                        </li>
                        <li>A spacer must be placed at the start of your scheduled playlist (after the VOTE_CHECK_API
                            event)
                            to
                            provide the plugin some time to do processing before the next item plays, this should be a
                            10
                            second
                            sequence with no effects (so your display is black)
                        </li>
                    </ul>

                    <p>Configuration:
                    <ul>
                        <li><b>1.</b> Enable Plugin
                        </li>
                        <li><b>2.</b> Enter API Key (Visit https://christmasdisplaysnear.me & Signup + Create your
                            display &
                            Device
                            entry (copy your Device Key from Step 3.)
                        </li>
                        <li><b>3.</b> Choose the playlist you wish to use for voting (this would normally be your
                            scheduled
                            playlist)
                        </li>
                        <li><b>4.</b> Choose the spacer sequence (required for Jukebox mode)</li>
                        <li><b>5.</b> <i>Optional:</i> Choose a playlist to use as a "spare pool", these are spare
                            sequences
                            you may
                            want run in the display
                        </li>
                        <li><b>6.</b> Tick "Sync Playlist if to publish the playlist to the Voting Website</li>
                        <li><b>7.</b> Click 'Save Config'</li>
                        <li><b>8.</b> Go to your scheduled playlist(s) & add the VOTE_CHECK_API event at the beginning
                            of the playlist (after any "Play first only once" items)
                        </li>
                    </ul>
                    <p>

                    <p>To report a bug, please file it against the <a
                                href="<? echo $gitURL ?>">FPP-VotingAPI-Integration</a> plugin
                        project on
                        Git: <? echo $gitURL ?> <br>
                        or Add to my <a href="https://trello.com/b/G5VbDFuQ/displays-near-me-public">Trello Board</a>
                    </p>

                    <form method="post"
                          action="http://<? echo $_SERVER['SERVER_NAME'] ?>/plugin.php?plugin=<? echo $pluginName; ?>&page=plugin_setup.php">
                        <hr>
                        <?
                        $restart = 0;
                        $reboot = 0;
                        echo "<b>ENABLE PLUGIN:</b> ";
                        PrintSettingCheckbox(" Plugin " . $pluginName, "ENABLED", $restart = 0, $reboot = 0, "ON", "OFF", $pluginName, $callbackName = "");
                        ?>
                        <br>
                        <br>
                        <?
                        echo "<b>Enter API / Device Key</b>: \n";
                        echo "<input type=\"text\" name=\"API_KEY\" size=\"64\" value=\"" . $API_KEY . "\"> \n";
                        echo "<br><small>(copy this from <a href='#'>My Dashboard > Devices</a>)</small>";
                        ?>
                        <hr>
                        <h3><em>Voting Options</em></h3>
                        <?
                        echo "<b>Fully Dynamic Playlist / Jukebox Mode?</b>: \n";
                        if (strtolower($FULLY_DYNAMIC_ONLY) == 'on') {
                            echo "<input type='radio' value=\"FULLY_DYNAMIC\" name=\"PLAYLIST_VOTE_MODE\" checked> \n";
                        } else {
                            echo "<input type='radio' value=\"FULLY_DYNAMIC\" name=\"PLAYLIST_VOTE_MODE\"> \n";
                        }
                        echo "<br><small>(choosing this option essentially allows visitors rearrange your playlist using votes, <br>the playlist will be rearranged automatically based sequence/item vote count from highest to lowest)</small>";
                        ?>
                        <br>
                        <br>
                        <?
                        echo "<b>Play only 'Most Voted' sequence?</b>: \n";
                        if (strtolower($HIGHEST_VOTED_ONLY) == 'on') {
                            echo "<input type='radio' value=\"HIGHEST_VOTED_ONLY\" name=\"PLAYLIST_VOTE_MODE\" checked> \n";
                        } else {
                            echo "<input type='radio' value=\"HIGHEST_VOTED_ONLY\" name=\"PLAYLIST_VOTE_MODE\"> \n";
                        }
                        echo "<br><small>(choosing this option will poll the voting server for the most voted item in your playlist and play it)</small>";
                        ?>
                        <hr>
                        <?
                        echo "<b>Max 'Most Voted' sequence repeats</b>: \n";
                        echo "<input type=\"text\" name=\"MAX_ITEM_REPEATS\" size=\"4\" value=\"" . $MAX_ITEM_REPEATS . "\"> \n";
                        echo "<br><small>(this is the MAX number of times you want a sequence to repeat in a row)</small>";
                        ?>
                        <br>
                        <br>
                        <?
                        echo "<b>Reset 'Most Voted' sequence's votes?</b>: \n";
                        if (strtolower($MOST_VOTED_RESET) == 'on') {
                            echo "<input type='checkbox' name=\"MOST_VOTED_RESET\" checked> \n";
                        } else {
                            echo "<input type='checkbox' name=\"MOST_VOTED_RESET\"> \n";
                        }
                        echo "<br><small>(you can opt to reset the reset the votes to 0 for the Popular/Voted sequence if it repeats too many times. <br> In order to give other voted items a chance at getting played)</small>";
                        ?>
                        <hr>
                        <h3><em>*Optional* Event Selection</em></h3>
                        <?
                        //print event selection dropdown
                        echo "<b>Select <em><b>START Event</b></em> to play at start of playlist:</b> \n";
                        echo "<select name=\"START_EVENT\">";

                        //Get list of playlists
                        $events = retrieveEvents();

                        //Default selection
                        echo "<option label='NONE' value=\"" . 'NONE' . "\">" . 'NONE' . "</option>";

                        foreach ($events as $event_filename => $event_value) {
                            $event_name_name = $event_value['name'];

                            //Check to see if the playlist is the selected playlist
                            if (strtolower($event_filename) == strtolower($START_EVENT)) {
                                //Print option as selected
                                echo "<option label='$event_filename' selected value=\"" . $event_filename . "\">" . $event_name_name . "</option>";
                            } else {
                                //Print every other option
                                echo "<option label='$event_filename' value=\"" . $event_filename . "\">" . $event_name_name . "</option>";
                            }
                        }
                        echo "</select>";
                        echo "<br><small>(this event will be put at the start of the playlist)</small>";
                        ?>
                        <br>
                        <?
                        //print event selection dropdown
                        echo "<b>Select <em><b>END Event</b></em> to play at end of playlist:</b> \n";
                        echo "<select name=\"END_EVENT\">";

                        //Default selection
                        echo "<option label='NONE' value=\"" . 'NONE' . "\">" . 'NONE' . "</option>";

                        foreach ($events as $event_filename => $event_value) {
                            $event_name_name = $event_value['name'];

                            //Check to see if the playlist is the selected playlist
                            if (strtolower($event_filename) == strtolower($END_EVENT)) {
                                //Print option as selected
                                echo "<option label='$event_filename' selected value=\"" . $event_filename . "\">" . $event_name_name . "</option>";
                            } else {
                                //Print every other option
                                echo "<option label='$event_filename' value=\"" . $event_filename . "\">" . $event_name_name . "</option>";
                            }
                        }
                        echo "</select>";
                        echo "<br><small>(this event will be put at the end of the playlist)</small>";
                        echo "<br><small>(most useful when using Jukebox Mode as we may never return return your schedule playlist, thus never playing any event you may have before the end of the schedule)</small>";

                        ?>
                        <br>
                        <br>
                        <hr>
                        <h3><em>Playlist Selection</em></h3>
                        <?
                        //print playlist selection dropdown
                        echo "<b>Select <em>Spacer</em> Sequence:</b> \n";
                        echo "<select name=\"SPACER_SEQUENCE\">";
                        //Get list of playlists
                        $sequence_list = retrieveSequences();

                        foreach ($sequence_list as $sequence_idx => $sequence_name) {
                            //Check to see if the playlist is the selected playlist
                            if (strtolower($sequence_idx) == strtolower($SPACER_SEQUENCE)) {
                                //Print option as selected
                                echo "<option label='$sequence_idx' selected value=\"" . $sequence_idx . "\">" . $sequence_idx . "</option>";
                            } else {
                                //Print every other option
                                echo "<option label='$sequence_idx' value=\"" . $sequence_idx . "\">" . $sequence_idx . "</option>";
                            }
                        }
                        echo "</select>";
                        echo "<br><small>(this sequence is used as a spacer to provide the plugin some time to do processing, this should be a 10 second sequence with no effects (so your display is black))</small>";
                        ?>
                        <br>
                        <br>
                        <?
                        //print playlist selection dropdown
                        echo "<b>Select <em><b>Main</b></em> Playlist to use for voting:</b> \n";
                        echo "<select name=\"MAIN_PLAYLIST\">";
                        //Get list of playlists
                        $playlist_list_main = retrievePlaylists();

                        foreach ($playlist_list_main as $playlist_name => $playlist_value) {
                            //Check to see if the playlist is the selected playlist
                            if (strtolower($playlist_name) == strtolower($MAIN_PLAYLIST)) {
                                //Print option as selected
                                echo "<option label='$playlist_name' selected value=\"" . $playlist_name . "\">" . $playlist_name . "</option>";
                            } else {
                                //Print every other option
                                echo "<option label='$playlist_name' value=\"" . $playlist_name . "\">" . $playlist_name . "</option>";
                            }
                        }
                        echo "</select>";
                        echo "<br><small>(this playlist is the playlist visitors will vote on)</small>";
                        ?>
                        <br>
                        <br>
                        <input id="RESET_MAIN_PL_NAMES" name="RESET_MAIN_PL_NAMES" type="submit" class="buttons"
                               value="Reload Main Playlist">
                        <br>
                        <small>(Click this button to reload entries from the selected Main playlist)</small>
                        <br>
                        <br>
                        <input id="SET_MAIN_PL_NAMES" name="SET_MAIN_PL_NAMES" type="submit" class="buttons"
                               value="Set Main Playlist Names">
                        <br>
                        <small>(Click this button to give playlist items names)</small>
                        <br>
                        <br>
                        <?
                        //if the set SET_MAIN_PL_NAMES button was clicked, it will trigger $SET_MAIN_PL_NAMES to true in the $_POST check
                        if ($SET_MAIN_PL_NAMES == true || $SET_MAIN_PL_NAMES_ADDED == true) {
                            //If the playlist names have already been added then print a message saying
                            if ($SET_MAIN_PL_NAMES_ADDED == true) {
                                echo "<b>You've previously set names for your sequences! attempting to show what was entered:</b><br>";
                            }

                            echo "<b>Enter some friendly names for your sequences (this is shown to your visitors):</b><br>";
                            //If names have previousl been given to the playlist items then try swap in the main playlist data and display existing values
                            if ($SET_MAIN_PL_NAMES_ADDED == true) {
                                $playlist_list_main_data = $MAIN_PLAYLIST_DATA;
                            } else {
                                //Get list of playlists
                                $playlist_list_main_data = retrievePlaylistContents($MAIN_PLAYLIST)[$MAIN_PLAYLIST];
                                $playlist_list_main_data = $playlist_list_main_data['data'];
                            }

                            //track each input field
                            $loop_count = 0;
                            foreach ($playlist_list_main_data as $playlist_item_id => $playlist_value) {
                                //Process the item returning an array with name and data
                                //change the data location - used in conjunction with $SET_MAIN_PL_NAMES_ADDED above
                                if (is_array($playlist_value) && array_key_exists('data', $playlist_value) && $SET_MAIN_PL_NAMES_ADDED == true) {
                                    $processed_playlist_item = $playlist_value;
                                } else {
                                    $processed_playlist_item = processPlaylistItemRow($playlist_value);
                                }
                                //Make sure the processed playlist item comes back with something
                                if (!empty($processed_playlist_item)) {
                                    //Print all the input fields
                                    echo "<input type='text' name='playlist_main_item_name_$loop_count' size=\"56\" value=\"" . $processed_playlist_item['name'] . "\">";
                                    echo "<input type='text' name='playlist_main_item_data_$loop_count' hidden value=\"" . $processed_playlist_item['data'] . "\">";
                                    echo "<br>";
                                    //incr count
                                    $loop_count++;
                                }
                            }
                            //number of input fields generated, this is incredibly important so we can get all user supplied sequence names
                            echo "<input type='text' hidden name=\"SET_MAIN_PL_NAMES_COUNT\" value='$loop_count'>";
                        }
                        ?>
                        <br>
                        <hr>
                        <?
                        echo "<b>Allow Spare Playlist Voting</b>:</b> \n";
                        if (strtolower($SPARE_VOTING) == 'on') {
                            echo "<input type='checkbox' name=\"SPARE_VOTING\" checked> \n";
                        } else {
                            echo "<input type='checkbox' name=\"SPARE_VOTING\"> \n";
                        }
                        echo "<br><small>(if you chose to allow spare playlist voting when your display via the website, THEN TICK THIS)</small>";
                        ?>
                        <br>
                        <br>
                        <?
                        //print playlist selection dropdown
                        echo "<b>Select <em><b>Spare</b></em> Playlist to use for spare sequence voting:</b> \n";
                        echo "<select name=\"SPARE_PLAYLIST\">";
                        //Get list of playlists
                        $playlist_list_spare = retrievePlaylists();

                        foreach ($playlist_list_spare as $playlist_name => $playlist_value) {
                            //Check to see if the playlist is the selected palylist
                            if (strtolower($playlist_name) == strtolower($SPARE_PLAYLIST)) {
                                //Print option as selected
                                echo "<option label='$playlist_name' selected value=\"" . $playlist_name . "\">" . $playlist_name . "</option>";
                            } else {
                                //Print every other option
                                echo "<option label='$playlist_name' value=\"" . $playlist_name . "\">" . $playlist_name . "</option>";
                            }
                        }
                        echo "</select>";
                        echo "<br><small>(this playlist should contain items that you may want in your display, but you want visitors to vote them in)</small>";
                        ?>
                        <br>
                        <br>
                        <input id="RESET_SPARE_PL_NAMES" name="RESET_SPARE_PL_NAMES" type="submit" class="buttons"
                               value="Reload Spare Playlist">
                        <br>
                        <small>(Click this button to reload entries from the selected Main playlist)</small>
                        <br>
                        <br>
                        <input id="SET_SPARE_PL_NAMES" name="SET_SPARE_PL_NAMES" type="submit" class="buttons"
                               value="Set Spare Playlist Names">
                        <br>
                        <small>(Click this button to give playlist items names)</small>
                        <br>
                        <hr>
                        <b>Sync Playlist?:</b>
                        <input type='checkbox' id='SYNC_PLAYLIST' name="SYNC_PLAYLIST"/>
                        <br>
                        <small>(check box to sync playlist to server when you click save!!)</small>
                        <hr>
                        <br>
                        <br>
                        <input id="submit_button" name="submit" type="submit" class="buttons" value="Save Config">
                        <br>
                        <br>
                        <?
                        if (file_exists($pluginUpdateFile)) {
                            //echo "updating plugin included";
                            include $pluginUpdateFile;
                        }
                        ?>
                    </form>
                </fieldset>
            </div>
        </div>
        <div id="tab-playlist-view">
            <div class="settings vote_plugin">
                <h4>Dynamic Playlist:</h4>
                <span>(playlist as seen by visitors)</span>
                <br>
                <span class="dynamic_lastUpdated"> <b>Last Updated: </b> <em><? echo date(getDateTimeFormatFull(), $DYNAMIC_PLAYLIST_LAST_UPDATE) ?></em></span>
                <table id="tblPlaylistOutput" class="playlistOutputTable">
                    <tr class="tblheader">
                        <td width="5%" align="left">Position</td>
                        <td width="40%" align="left">Name</td>
                        <td width="5%" align="left">Votes
                        <td width="10%" align="left">Last Updated</td>
                    </tr>
                    <?
                    //sudo rm /tmp/FPP.ControllerMonitor.log
                    //Controller List is already an array, lets loop over it and ping each host
                    //loop over each line and extract info
                    foreach ($DYNAMIC_PLAYLIST_DATA as $playlist_item_id => $playlist_item_data) {
                        //ID in the list
                        $pl_item_id = $playlist_item_id;
                        //playlist item id, this is the single identifier the API uses to locate this playlist entry
                        $pl_item_id = $playlist_item_data['id'];
                        //Playlist Item Name
                        $pl_item_name = $playlist_item_data['name'];
                        //Payload / Data, this is whatever was sent to the API when the initial Playlist sync/upload happened, can be whatever is useful to you
                        $pl_item_data = $playlist_item_data['data'];
                        //Position the playlist, this number is generally the length of the playlist but in reverse, if you have 20 items in your playlist, pos of item 1 is 20, 2 => 19
                        //the API always orders items respectivly based on votes, top items is most voted
                        $pl_item_position = $playlist_item_data['pos'];
                        //number of votes on that item
                        $pl_item_votes = $playlist_item_data['votes'];
                        //not really useful for anything, should be the source of the playlist item (main or spare) a API bug means this is useless at the moment (always reports main)
                        //ideally you could use it to see how many  'spare' items are in your playlist
                        $pl_item_source = $playlist_item_data['playlist'];

                        $pl_updated_at = $playlist_item_data['updated_at'];

                        ?>
                        <tr class="rowGpioDetails">
                            <td align="center"><? echo $pl_item_position ?> <br>
                                <small>
                                    [<? echo $pl_item_source ?>]
                                </small>
                            </td>
                            <td align="center"><b><? echo $pl_item_name ?></b>
                                <br>
                                <small>
                                    (<? echo $pl_item_id ?>)
                                    <br>
                                    <? echo $pl_item_data ?>
                                </small>
                            </td>
                            <td align="center">
                                <? echo $pl_item_votes ?>
                            </td>
                            <td class="">
                                <? echo $pl_updated_at ?>
                            </td>
                        </tr>
                        <?
                    }
                    ?>
                </table>
            </div>
        </div>
        <div id="tab-log-view">
            <div class="settings vote_plugin log-view-list">
                <span>Log output is reverse, with most current entries at the top</span>
                <?
                if (file_exists($logFile)) {
                    $log = tail($logFile, 500);
                    $log_arr = array_reverse(explode("\n", $log));

                    foreach ($log_arr as $lti => $ltd) {
                        $li_list_class = 'list-group-item-info';
                        $list_class = 'text-white';

                        if (stripos(($ltd), 'PLAY PLAYLIST') !== false || stripos(($ltd), 'FPP is idle') !== false || stripos(($ltd), 'Playlist Started') !== false) {
                            $list_class = 'text-white';
                            $li_list_class = 'list-group-item-success';
                        }

                        if (stripos(($ltd), 'POLLING FPP STATUS') !== false || stripos(($ltd), 'plugin.php') !== false) {
                            $list_class = 'text-white';
                            $li_list_class = 'list-group-item-primary';
                        }

                        //Identify the callback lines differently
                        if (strpos(($ltd), 'callbacks.php') !== false) {
                            $list_class = 'text-white';
                            $li_list_class = 'list-group-item-secondary';
                        }

                        //Errors
                        if (strpos(($ltd), 'WARNING') !== false) {
                            $list_class = 'text-invert';
                            $li_list_class = 'list-group-item-warning';
                        }
                        if (strpos(($ltd), 'ERROR') !== false) {
                            $list_class = 'text-white';
                            $li_list_class = 'list-group-item-danger';
                        }

                        //remove the plugin location from the output text
                        $ltd = str_ireplace("/home/fpp/media/plugins/FPP-VotingAPI-Integration/", "", $ltd);

                        //Remove warning and error prefixes
                        $ltd = str_replace("ERROR :: ", "", $ltd);
                        $ltd = str_replace("WARNING :: ", "", $ltd);

                        ?>
                        <li class="list-group-item <?php echo $li_list_class ?> ">
                            <span class="<?php echo $list_class ?>"><?php echo $ltd; ?></span>
                        </li>
                        <?php
                    }
                }
                ?>
            </div>
            <div>
                <span><b>Legend:</b></span>
                <br>
                <span class="text-white list-group-item-success error-log-legend"><em>Success</em></span>
                <br>
                <span class="text-white list-group-item-primary error-log-legend"><em>Info</em></span>
                <br>
                <span class="text-white list-group-item-secondary error-log-legend"><em>Callback Info</em></span>
                <br>
                <span class="text-invert list-group-item-warning error-log-legend"><em>Warnings</em></span><span>  - Something didn't work - non critical - for information purposes</span>
                <br>
                <span class="text-white list-group-item-danger error-log-legend"><em>Errors</em></span><span>  - Something didn't work - critical</span>
                <br>
            </div>
        </div>
        <div id="tab-advanced-settings">
            <div class="settings vote_plugin">
                <form method="post"
                      action="http://<? echo $_SERVER['SERVER_NAME'] ?>/plugin.php?plugin=<? echo $pluginName; ?>&page=plugin_setup.php">
                    <?
                    echo "<b>Select <em><b>API_SERVER</b></em>:</b> \n";
                    echo "<select name=\"API_SERVER\">";
                    //Get list of playlists
                    $api_servers = getApiServers();

                    $server_desc = "";

                    foreach ($api_servers as $api_server_key => $api_server_data) {
                        $api_server_id = $api_server_data['_id'];
                        $api_server_address = $api_server_data['server'];

                        //Print every other option
                        if (strtolower($api_server_address) == strtolower($API_SERVER)) {
                            //Print option as selected
                            echo "<option label='$api_server_id' selected value=\"" . $api_server_address . "\">" . $api_server_id . "</option>";
                        } else {
                            //Print every other option
                            echo "<option label='$api_server_id' value=\"" . $api_server_address . "\">" . $api_server_id . "</option>";
                        }

                        $server_desc .= "<span>$api_server_id -- $api_server_address</span><br>";
                    }
                    echo "</select>";
                    echo "<br>";
                    echo $server_desc;
                    ?>
                    <br>
                    <br>
                    <input id="SET_API_SERVER" name="SET_API_SERVER" type="submit" class="buttons"
                           value="Set API Server">
                    <br>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    /*
    Activate tabs
     */
    $(function () {
        $("#tabs").tabs();
    });
</script>
</html>