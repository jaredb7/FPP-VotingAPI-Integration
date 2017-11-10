<?php
include_once 'ApiClient.php';

/**
 * User: Jared Bowles
 * Date: 16/10/2017
 * Time: 9:35 PM
 */
class DeviceDataApi
{

    /**
     * @var $apiClient APIClient
     */
    private $apiClient;

    /*
     * API key
     */
    private $_api_key;

    /**
     * DeviceDataApi constructor.
     *
     * @param $api_server
     * @param $api_key
     */
    function __construct($api_server, $api_key)
    {
        $this->_api_key = $api_key;
        $this->apiClient = new APIClient($api_server);
    }

    /**
     * Retrieves the full playlist as it stands on the API server
     *
     * @return null|string
     */
    public function getDynamicPlaylist()
    {
        //parse inputs
        $resourcePath = "device/" . $this->_api_key . "/data/playlist";
        $resourcePath = str_replace("{format}", "json", $resourcePath);
        $method = "GET";
        $queryParams = array();
        $headerParams = array();

        //make the API Call
        if (!isset($body)) {
            $body = null;
        }

        $response = $this->apiClient->callAPI($resourcePath, $method, $queryParams, $body, $headerParams);

        if (!$response) {
            return null;
        }

        $responseObject = $response;

        return $responseObject;
    }

    /**
     * Resets votes for the supplied playlist item id, playlist item id should be one returned from either most-voted or playlist endpoint
     * and uniquely identifies the playlist item on a specified device
     *
     * @param $playlist_item_id
     * @return null|string
     */
    public function resetPlaylistItemVotes($playlist_item_id)
    {
        //parse inputs
        $resourcePath = "device/" . $this->_api_key . "/data/playlist/" . $playlist_item_id . "/reset-votes";
        $resourcePath = str_replace("{format}", "json", $resourcePath);
        $method = "POST";
        $queryParams = array();
        $headerParams = array();

        //make the API Call
        if (!isset($body)) {
            $body = null;
        }

        $response = $this->apiClient->callAPI($resourcePath, $method, $queryParams, $body, $headerParams);

        if (!$response) {
            return null;
        }

        $responseObject = $response;

        return $responseObject;
    }

    /**
     * Retrieves the most voted item from the api
     *
     * @param bool $auto_reset
     * @return null|string
     */
    public function getMostVoted($auto_reset = false)
    {
        //parse inputs
        $resourcePath = "device/" . $this->_api_key . "/data/playlist/most-voted";
        $resourcePath = str_replace("{format}", "json", $resourcePath);
        $method = "GET";
        $queryParams = array();
        $headerParams = array();

        //make the API Call
        if (!isset($body)) {
            $body = null;
        }

        $response = $this->apiClient->callAPI($resourcePath, $method, $queryParams, $body, $headerParams);

        if (!$response) {
            return null;
        }

        $responseObject = $response;

        return $responseObject;
    }

    /**
     * Submit now playing data to the api
     *
     * @param $now_playing_data
     * @return null|string
     */
    public function nowPlaying($now_playing_data)
    {
//    "now_playing": {
//    "name": "Now Playing Sequence",
//    "duration": "duration in seconds",
//    "position": "Optional|Unused: position in playlist, 0 base numbering, eg. if first item in playlist then position 0, 2nd = position 1",
//    "start_time": "Optional|Unused: Time item started playing, format should be H:i:s eg. 20:13:34"
//    }

        //parse inputs
        $resourcePath = "device/" . $this->_api_key . "/data/playlist/now-playing";
        $resourcePath = str_replace("{format}", "json", $resourcePath);
        $method = "POST";
        $queryParams = array();
        $headerParams = array();

        $canUpload = false;
        //if now duration, then 0 it out,
        if (!isset($now_playing_data['duration'])) {
            $now_playing_data['duration'] = 0;
        }

        //we must have a name though
        if (isset($now_playing_data['name'])) {
            //we can upload
            $canUpload = true;
        }

        //build up the body content
        $body['now_playing'] = $now_playing_data;

        //make the API Call
        if (!isset($body)) {
            $body = null;
        }

        if ($canUpload) {
            $response = $this->apiClient->callAPI($resourcePath, $method, $queryParams, $body, $headerParams);
        }

        if (!$response) {
            return null;
        }

        $responseObject = $response;

        return $responseObject;
    }

    /**
     * Playlist Upload
     *
     * @param $playlistOptions
     * @param $playlistData
     * @param string $playlistType
     * @return null
     */
    public function upload($playlistOptions, $playlistData, $playlistType = "main")
    {
        //parse inputs
        $resourcePath = "device/" . $this->_api_key . "/data/playlist";
        $resourcePath = str_replace("{format}", "json", $resourcePath);
        $method = "POST";
        $queryParams = array();
        $headerParams = array();

        //build up the body content
        $body['options'] = $playlistOptions;
        if ($playlistType == "main") {
            $body['main'] = $playlistData;
        } elseif ($playlistType == "spare") {
            $body['spare'] = $playlistData;
        }

        //make the API Call
        if (!isset($body)) {
            $body = null;
        }

        $response = $this->apiClient->callAPI($resourcePath, $method, $queryParams, $body, $headerParams);

        if (!$response) {
            return null;
        }

        $responseObject = $response;

        return $responseObject;
    }
}