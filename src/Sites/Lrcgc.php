<?php

//data2/lrc/\d*/\d*.lrc;

namespace Lyricphp\Sites;

use Symfony\Component\DomCrawler\Crawler;
use Lyricphp\Sites\lyricBase;
use Lyricphp\File;

class Lrcgc extends lyricBase {

    public $lyric;
    public $baseUrl;
    public $url;
    protected $client;
    protected $jsonData;

    public function __construct() {

        parent::__construct();
        $this->searchUrl = 'http://www.lrcgc.com/so/?q=';
        $this->baseUrl = "http://www.lrcgc.com";
    }

    public function scrape() {

        $url = $this->searchUrl . urlencode($this->song);

        $res = $this->client->Request($url);
        if (isset($res->data)) {
            $this->content = $res->data;
            $this->crawler->addContent($this->content);
            $this->parse();
        }
        return FALSE;
    }

    public function parse() {

        $crawler = $this->crawler->filterXPath('//div[@class="so_list"]/ul/li');
        $domNodeList = $crawler->getNode();
        $data = array();
        $nodeData = array();

        foreach ($domNodeList as $ele) {

            if (!$ele->hasChildNodes()) {
                continue;
            }

            foreach ($ele->childNodes as $subele) {
                if ($crawler->hasChildEle($subele) && $subele->tagName == "span") {
                    $childA = $subele->childNodes->item(1);

                    if ($childA instanceof \DOMElement && $childA->hasAttribute("href")) {
                        $nodeData['url'] = $childA->getAttribute("href");
                    }
                }

                if ($crawler->hasChildEle($subele) && $subele->tagName == "small") {
                    if ($subele->childNodes->length == 3) {

                        $nodeData['singer'] = $subele->lastChild->nodeValue;
                    }
                }
            }

            $data[] = $nodeData;
        }


        if ($crawler->count() < 0) {


            echo "No content found\n";

            return FALSE;
        }

        foreach ($data as $value) {

            if (strpos($value['singer'], $this->singer) !== FALSE || strpos($this->singer, $value['singer'])
                    !== FALSE) {
                $lrcpage = $this->client->Request($this->baseUrl . $value['url']);
                $this->setDownloadUrl($lrcpage);
            }
        }


        $first = reset($data);

        $lrcpage = $this->client->Request($this->baseUrl . $first['url']);
        $this->setDownloadUrl($lrcpage);
    }

    private function setDownloadUrl($lrcpage) {
        $crawler = $this->crawler->addContent($lrcpage->data)->filterXPath('//a[@id="J_downlrc"]');

        if ($crawler->count() > 0) {
            try {
                $lrcurl = $crawler->attr("href");
            } catch (\Exception $e) {
                echo $e->getMessage() . "\n";
            }
            $suffix_url = preg_replace('/\%2F/i', '/', urlencode($lrcurl));
            $this->downloadUrl = $this->baseUrl . "/" . $suffix_url;
        }
    }

    public function download($filename) {
        $this->scrape();

        if (!isset($this->downloadUrl)) {
            return FALSE;
        }
        if (!$urlRes = fopen($this->downloadUrl, 'rb')) {

            echo "failed to open $this->downloadUrl\n";
            return FALSE;
        }

        $lyric = '';

        while (!feof($urlRes)) {
            $lyric .= fread($urlRes, 1024 * 8);
        }

        $newLines = preg_split("/((\r?\n)|(\r\n?))/", $lyric);

        if (count($newLines) < 5) {
            $lyric = \Lyricphp\stringUtility::fixLongLine($lyric);
        }

        $this->save($lyric, $filename);
        return TRUE;
    }

}
