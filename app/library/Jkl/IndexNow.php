<?php

/**
 * IndexNow (#220): tells Bing, and through it Yandex, Seznam, Naver and the rest of IndexNow's
 * engines, which of the site's addresses are new or changed, as they change, instead of waiting
 * for them to read the sitemaps again. Bing's index is what ChatGPT search and Copilot answer from.
 *
 * The protocol takes up to 10,000 addresses of one host per request, with a key the site serves
 * at /<key>.txt to show the request is its own. The key is no secret: anyone may read it there.
 */
class Jkl_IndexNow
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** The most addresses one request may carry */
    public const BATCH = 10000;

    private $_site;
    private $_host;
    private $_key;
    private $_post;

    /**
     * @param string        $site the site's origin, https://hhbd.pl
     * @param string        $key  8 to 128 letters, digits and dashes
     * @param callable|null $post called with the endpoint and the JSON body, returns the HTTP
     *                            status, 0 for none; an HTTP POST by default
     */
    public function __construct($site, $key, $post = null)
    {
        if (!self::isKey($key)) {
            throw new InvalidArgumentException('An IndexNow key is 8 to 128 letters, digits and dashes');
        }
        $this->_site = rtrim((string) $site, '/');
        $this->_host = (string) parse_url($this->_site, PHP_URL_HOST);
        $this->_key = (string) $key;
        $this->_post = null !== $post ? $post : array(self::class, 'post');
    }

    public static function isKey($key)
    {
        return 1 === preg_match('/^[A-Za-z0-9-]{8,128}$/', (string) $key);
    }

    public function key()
    {
        return $this->_key;
    }

    /** Where the site serves its key */
    public function keyLocation()
    {
        return $this->_site . '/' . $this->_key . '.txt';
    }

    /**
     * The requests' bodies for a set of paths or addresses: each made an address on the site,
     * those of another host left out, each once, at most BATCH to a request
     *
     * @return array[] host, key, keyLocation, urlList
     */
    public function requests(array $urls)
    {
        $list = array();
        foreach ($urls as $url) {
            $url = (string) $url;
            if ('' === $url) {
                continue;
            }
            if ('/' === $url[0]) {
                $url = $this->_site . $url;
            }
            if (parse_url($url, PHP_URL_HOST) === $this->_host) {
                $list[$url] = true;
            }
        }
        $requests = array();
        foreach (array_chunk(array_keys($list), self::BATCH) as $chunk) {
            $requests[] = array('host' => $this->_host, 'key' => $this->_key, 'keyLocation' => $this->keyLocation(), 'urlList' => $chunk);
        }
        return $requests;
    }

    /**
     * Sends them
     *
     * @return array one entry per request: the count of addresses and the HTTP status, 200 or
     *               202 when IndexNow took them
     */
    public function submit(array $urls)
    {
        $results = array();
        foreach ($this->requests($urls) as $request) {
            $status = (int) call_user_func($this->_post, self::ENDPOINT, json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $results[] = array('urls' => count($request['urlList']), 'status' => $status);
        }
        return $results;
    }

    /** A POST of JSON, with a short timeout: a slow IndexNow holds up an import or an admin */
    public static function post($endpoint, $json)
    {
        $context = stream_context_create(array('http' => array(
            'method'        => 'POST',
            'header'        => "Content-Type: application/json; charset=utf-8\r\n",
            'content'       => $json,
            'timeout'       => 10,
            'ignore_errors' => true,
        )));
        @file_get_contents($endpoint, false, $context);
        // The status line of the last response; none when the request never got one
        $headers = http_get_last_response_headers();
        return 1 === preg_match('#^HTTP/\S+\s+(\d{3})#', !empty($headers[0]) ? $headers[0] : '', $m) ? (int) $m[1] : 0;
    }
}
