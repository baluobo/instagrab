<?php

namespace Baluobo\Instagrab;

use Exception;
use DOMDocument;
use Requests;

class Grabber
{
    protected $url = '';

    protected $metaTags = [];

    protected $medium;

    protected $typename;

    protected $edges = [];

    protected $owner = [];

    /**
     * Grabber constructor.
     * @param $url
     * @throws Exception
     */
    public function __construct($url)
    {
        if (!$this->validateUrl($url)) {
            throw new Exception("Url is not valid.");
        }

        $this->url = $url;

        $response = $this->fetch($this->url);

        $this->parse($response->body);
    }

    /**
     * @param $url
     * @return \Requests_Response
     */
    public function fetch($url)
    {
        $response = Requests::get($url);

        return $response;
    }

    /**
     * @param $HTML
     */
    public function parse($HTML)
    {
        /**
         * meta tags
         */
        $dom = new DOMDocument();

        @$dom->loadHTML($HTML);

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if ($meta->getAttribute('property')) {
                $this->metaTags[$meta->getAttribute('property')] = $meta->getAttribute('content');
            }

            if ($meta->getAttribute('name') == 'medium') {
                $this->medium = $meta->getAttribute('content');
            }
        }

        /**
         * share data
         */
        preg_match("@<script type=\"text\/javascript\">window._sharedData = (.*);@", $HTML, $matches);

        if (isset($matches[1]) && $matches[1]) {
            $data = json_decode($matches[1], true);

            if (isset($data['entry_data']['PostPage'][0]['graphql']['shortcode_media'])) {
                $shortcode_media = $data['entry_data']['PostPage'][0]['graphql']['shortcode_media'];

                if (array_key_exists('__typename', $shortcode_media)) {
                    $this->typename = $shortcode_media['__typename'];
                }

                if (array_key_exists('edge_sidecar_to_children', $shortcode_media)) {
                    $this->edges = $shortcode_media['edge_sidecar_to_children']['edges'];
                }

                if (array_key_exists('owner', $shortcode_media)) {
                    $this->owner = $shortcode_media['owner'];
                }
            }
        }
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        if (array_key_exists('og:url', $this->metaTags)) {
            return $this->metaTags['og:url'];
        }
        return $this->url;
    }

    /**
     * @return array
     */
    public function getMetaTags()
    {
        return $this->metaTags;
    }

    /**
     * @return string
     */
    public function getMedium()
    {
        return $this->medium;
    }

    /**
     * @return string|null
     */
    public function getImage()
    {
        if (array_key_exists('og:image', $this->metaTags)) {
            return $this->metaTags['og:image'];
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getVideo()
    {
        if (array_key_exists('og:video', $this->metaTags)) {
            return $this->metaTags['og:video'];
        }
        return null;
    }

    /**
     * @return string
     */
    public function getTypename()
    {
        return $this->typename;
    }

    /**
     * @return array
     */
    public function getEdges()
    {
        return $this->edges;
    }

    /**
     * @return array
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param $url
     */
    public function download($url)
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.basename($url));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        readfile($url);
    }

    /**
     * @param $url
     * @return mixed
     */
    private function validateUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL);
    }
}
