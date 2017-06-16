<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

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
        // print $url;
        $this->crawler = $this->goutteClient->request('GET', $url);
//print_r($this->crawler->getBody()->getContents());
        $this->parse();
    }

    public function parse() {

        $crawler = $this->crawler->filterXPath('//div[@class="so_list"]//li');

        if ($crawler->count() > 0) {


            $lrcpageurl = $crawler->each(function (Crawler $node, $i) {
                try {
                    $artist = $node->children()->eq($node->children()->count() - 2)->text();
                } catch (\InvalidArgumentException $ex) {
                    echo $ex->getMessage();
                }

                try {
                    $lrcurl = $node->children()->filter("a")->attr("href");
                } catch (\InvalidArgumentException $ex) {
                    echo $ex->getMessage();
                }

                if (!isset($lrcurl)) {
                    return FALSE;
                }

                return array('artist' => $artist, 'lrcurl' => $lrcurl);
            });
        } else {

            echo "No content found\n";

            return FALSE;
        }

        //print_r($lrcpageurl);

        foreach ($lrcpageurl as $value) {

            if (strpos($value['artist'], $this->singer) !== FALSE || strpos($this->singer, $value['artist'])
                    !== FALSE) {
                $this->crawler = $this->goutteClient->request('GET', $this->baseUrl . $value['lrcurl']);

                //file_put_contents('dddd.lrc', $content);
            } else {

                $this->crawler = $this->goutteClient->request('GET', $this->baseUrl . $lrcpageurl[0]['lrcurl']);
            }
            $subcrawler = $this->crawler->filterXPath('//a[@id="J_downlrc"]');
            if ($subcrawler->count() > 0) {
                $lrcurl = $subcrawler->attr("href");
                $suffix_url = preg_replace('/\%2F/i', '/', urlencode($lrcurl));
                $this->downloadUrl = $this->baseUrl . "/" . $suffix_url;
            }
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
            //echo (fgets($file))."12222";
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
