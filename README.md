# FPP-VotingAPI-Integration
#!!WARNING - EXPERIMENTAL!!

THIS PLUGIN & IS HIGHLY EXPERIMENTAL - while I've done absolute best to mitigate crashing your display. It may happen (fingers crossed).
DO NOT run fully unattended.

You MUST add the VOTE_CHECK_API event to the start of the playlist, after any items that should play only once (if you have them)
All MP3's used in sequences must have valid ID3 tags for Artist and Name, otherwise Now Playing data will not be submitted
A spacer must be placed at the start of your scheduled playlist (after the VOTE_CHECK_API event) to provide the plugin some time to do processing before the next item plays, this should be a 10 second sequence with no effects (so your display is black)
Configuration:

If you experience show stoppage, disable this plugin and report the issue on either ACL or below.

To report a bug, please file it against the FPP-VotingAPI-Integrationplugin project on Git: https://github.com/jaredb7/FPP-VotingAPI-Integration.git 
or Add to my Trello Board

**A note on FPP & this implementation**

FPP in it's current form does not give me a way to to programmatically change playlists easily.
We can tell FPP to Play a playlist, but this can't be done if it's currently running a playlist.. if that makes sense?

Basically if your schedule is running (which it always will be).
 
When 'Vote Check' event runs I poll the API for votes then force stop the playlist ( fpp -d ), then I tell it to play another playlist (containing the most voted item, or entire dynamic playlist) in non-repeating mode.

After that has run (finished), FPPD will switch to your schedule. Whether this is a reliable feature (or even bug) I'm not sure, hence the experimental nature of this plugin.

**Description**

This plugin enables viewers to vote on playlist / sequence items in your display via a publicly accessible website @ ChristmasDisplaysNear.Me
Known Issues:

May Cause funky issues with plugins that make use of the postStart and postStop callbacks, due to starting and stopping of FPPD
Things to note:

_Configuration_

    1. Enable Plugin
    2. Enter API Key (Visit https://christmasdisplaysnear.me & Signup + Create your display & Device entry (copy your Device Key from Step 3.)
    3. Choose the playlist you wish to use for voting (this would normally be your scheduled playlist)
    4. Choose the spacer sequence (required for Jukebox mode)
    5. Optional: Choose a playlist to use as a "spare pool", these are spare sequences you may want run in the display
    6. Tick "Sync Playlist if to publish the playlist to the Voting Website
    7. Click 'Save Config'
    8. Go to your scheduled playlist(s) & add the VOTE_CHECK_API event at the beginning of the playlisy (after any "Play first only once" items)

**Settings Page**       
![Alt text](/images/settings_page.png?raw=true "Settings Page")

**Playlist View Page (View current playlist on voting server)**       
![Alt text](/images/playlist_view_page.png?raw=true "Playlist View Page")

**Log Viewer Page**       
![Alt text](/images/log_viewer_page.png?raw=true "Log Viewer Page")

**Advanced Settings Page**       
![Alt text](/images/advanced_settings_page.png?raw=true "Advanced Settings Page")