<?php

/**
 * User: Jared Bowles
 * Date: 16/10/2017
 * Time: 7:46 PM
 */
class APIClient
{
    public static $USER_AGENT = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:13.0) Gecko/20100101 Firefox/13.0.1";
    public static $POST = "POST";
    public static $GET = "GET";
    public static $PUT = "PUT";
    public static $DELETE = "DELETE";

    /**
     * Some default options for curl
     */
    public static $DEFAULT_CURL_OPTS = array(
//        CURLOPT_SSLVERSION => 1,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_TIMEOUT => 5, // maximum number of seconds to allow cURL functions to execute
        CURLOPT_USERAGENT => 'FPP-VotingAPI-Integration Plugin v1.0',
        CURLOPT_HTTPHEADER => array("Content-Type: application/json; charset=utf-8", "Accept: application/json, text/javascript, */*; q=0.01"),
//        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_VERIFYPEER => false,
//        CURLOPT_SSL_CIPHER_LIST => 'TLSv1',
    );

    const HEADER_SEPARATOR = ';';
    const HTTP_GET = 'GET';
    const HTTP_POST = 'POST';

    /**
     * API Server endpoint
     * @var
     */
    private $_apiServer;

    /**
     * APIClient constructor.
     *
     * @param $api_server
     */
    function __construct($api_server)
    {
//        $this->_device_key = $_apiDeviceKey;
        $this->_apiServer = $api_server;

        //Setup the useragent
        $this->setup_user_agent();
    }

    /**
     * Setup a new user_agent string
     */
    public function setup_user_agent()
    {
        self::$USER_AGENT = "Mozilla/5.0 (FPPVoteApiPlugin/" . VOTING_API_PLUGIN_VERSION . "; PHPD/" . PHP_VERSION . ") (FPPVoteApiPlugin)";
        //	Mozilla/5.0 (Windows NT 6.1; WOW64; rv:35.0) Gecko/20100101 Firefox/35.0
        self::$DEFAULT_CURL_OPTS[CURLOPT_USERAGENT] = self::$USER_AGENT;
    }

    /**
     * Makes call to the specified path/API
     * @param string $resourcePath path to method endpoint
     * @param string $method method to call
     * @param array $queryParams parameters to be place in query URL
     * @param array $postData parameters to be placed in POST body
     * @param array $headerParams parameters to be place in request header
     * @throws Exception
     * @return string
     */
    public function callAPI($resourcePath, $method, $queryParams, $postData, $headerParams)
    {
        $headers = array();
        $request = array();

        # Allow API key from $headerParams to override default
        $added_api_key = False;
        if ($headerParams != null) {
            foreach ($headerParams as $key => $val) {
                $headers[] = "$key: $val";
                if ($key == 'api_key') {
                    $added_api_key = True;
                }
            }
        }
//        if (!$added_api_key) {
//            $headers[] = "api_key: " . $this->_device_key;
//        }

        if (is_object($postData) or is_array($postData)) {
            $postData = json_encode($postData);
        }

        //Final url is the base path + resource path(location|network|travel|version)
        $url = $this->_apiServer . $resourcePath;

        //Init curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_TIMEOUT, 1);
        // return the result on success, rather than just TRUE
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        //merge new headers
        if (!empty($headers)) {
            self::$DEFAULT_CURL_OPTS[CURLOPT_HTTPHEADER] = array_merge($headers, self::$DEFAULT_CURL_OPTS[CURLOPT_HTTPHEADER]);
        }

        //Set curl options
        foreach (self::$DEFAULT_CURL_OPTS as $opt => $opt_data) {
            curl_setopt($curl, $opt, $opt_data);
        }

        //Set HTTP Basic authentication
        //Your credentials goes here
//        curl_setopt($curl, CURLOPT_USERPWD, "" . ":" . "" );

        if (!empty($queryParams)) {
            //modified httpparms to build out the url with forward slashes
            $url = ($url . '/' . http_build_query($queryParams, null, "/"));
        }

        if ($method == self::$POST) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        } else if ($method == self::$PUT) {
            $json_data = json_encode($postData);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        } else if ($method == self::$DELETE) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        } else if ($method != self::$GET) {
            throw new Exception('Method ' . $method . ' is not recognized.');
        }
        curl_setopt($curl, CURLOPT_URL, $url);

        //Collect request info
        $request['headers'] = self::$DEFAULT_CURL_OPTS[CURLOPT_HTTPHEADER];
        $request['url'] = $url;
        $url_parse = parse_url($url);

        //Built response variables
        $response = null;
        $response_info = array('http_code' => 999);

        // Make the request
        $response = curl_exec($curl);
        $response_info = curl_getinfo($curl);

        //handle response
        if ($response_info['http_code'] == 0) {
            throw new Exception("TIMEOUT: API call to " . $url . " took more than 1s to return");
        } else if ($response_info['http_code'] == 200) {
            $data = json_decode($response, true);
        } else if ($response_info['http_code'] == 400) {
            throw new Exception("Bad Request: " . json_decode($response) . " " . $url . " : response code: " . $response_info['http_code']);
        } else if ($response_info['http_code'] == 401) {
            throw new Exception("Unauthorized API request to " . $url . " : Invalid Login Credentials");
        } else if ($response_info['http_code'] == 403) {
            throw new Exception("Quota exceeded for this method, or a security error prevented completion of your (successfully authorized) request : " . $url);
        } else if ($response_info['http_code'] == 404) {
            $data = null;
            throw new Exception("Not Found : " . $url);
        } else if ($response_info['http_code'] == 500) {
            throw new Exception("Internal server error, response code: " . $response_info['http_code']);
        } else {
            throw new Exception("Can't connect to the api: " . $url . " : response code: " . $response_info['http_code']);
        }

        return $data;
    }


    /**
     * Build a JSON POST object
     */
    public static function sanitizeForSerialization($postData)
    {
        foreach ($postData as $key => $value) {
            if (is_a($value, "DateTime")) {
                $postData->{$key} = $value->format(DateTime::ISO8601);
            }
        }
        return $postData;
    }

    /**
     * Take value and turn it into a string suitable for inclusion in
     * the path, by url-encoding.
     * @param string $value a string which will be part of the path
     * @return string the serialized object
     */
    public static function toPathValue($value)
    {
        return rawurlencode($value);
    }

    /**
     * Take value and turn it into a string suitable for inclusion in
     * the query, by imploding comma-separated if it's an object.
     * If it's a string, pass through unchanged. It will be url-encoded
     * later.
     * @param object $object an object to be serialized to a string
     * @return string the serialized object
     */
    public static function toQueryValue($object)
    {
        if (is_array($object)) {
            return implode(',', $object);
        } else {
            return $object;
        }
    }

    /**
     * Just pass through the header value for now. Placeholder in case we
     * find out we need to do something with header values.
     * @param string $value a string which will be part of the header
     * @return string the header string
     */
    public static function toHeaderValue($value)
    {
        return $value;
    }
}