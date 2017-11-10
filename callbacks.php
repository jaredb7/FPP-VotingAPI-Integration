#!/usr/bin/php
<?php
error_reporting(0);
//Include our required php files
include_once('/opt/fpp/www/common.php');
include_once("functions.inc.php");
include_once("commonFunctions.inc.php");

//Debug mode
//$DEBUG=true;

$skipJSsettings = 1;
//Plugin name
$pluginName = "FPP-VotingAPI-Integration";

//Logfile
$logFile = $settings['logDirectory'] . "/" . $pluginName . ".log";
//config file
$pluginConfigFile = $settings['configDirectory'] . "/plugin." . $pluginName;

//Callback register
$callbackRegisters = "sequence,media\n";
//Get php process id
$myPid = getmypid();

//var_dump($argv);

//Grab the fpp command from supplied args
//arg0 is the program
//arg1 is the first argument in the registration this will be --list
$FPPD_COMMAND = $argv[1];

//defaults
$ENABLED = '';

//Read the config, work out if plugin is enabled
if (file_exists($pluginConfigFile)) {
    $pluginSettings = parse_ini_file($pluginConfigFile);
    $ENABLED = $pluginSettings['ENABLED'];
}

//plugin not enabled, then just quit. so we won't get to the registration callback
if (strtolower($ENABLED) != "on" && $ENABLED != "1") {
    logEntry("WARNING :: Plugin Status: DISABLED Please enable in Plugin Setup to use & Restart FPPD Daemon");
    //quit script
    exit(0);
}

//echo "FPPD Command: ".$FPPD_COMMAND."<br/> \n";

//React to the FPPD list command to find out what callbacks we support
if ($FPPD_COMMAND == "--list") {
    echo $callbackRegisters;
    logEntry("FPPD List Registration request: responded:" . $callbackRegisters);
    exit(0);
}
//Act upon the FPPD callback with the --type command
if ($FPPD_COMMAND == "--type") {
    if ($DEBUG) {
        logEntry("DEBUG :: type callback requested");
    }
    //we got a register request message from the daemon
    $forkResult = fork($argv);
    if ($DEBUG) {
        logEntry("DEBUG :: Fork Result: " . $forkResult);
    }
    //exit here, work will be carried out via the forked process
    exit(0);
    //	processCallback($argv);
} else {

    logEntry("ERROR :: " . $argv[0] . " called with no parameters");
    exit(0);
}
?>