<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Lyricphp\Sites;

use Lyricphp\Client;
use Lyricphp\Crawler;

//use GuzzleHttp\Client as GuzzleClient;

abstract class lyricBase {

    protected $client;
    protected $singer = 'anonymous';
    protected $song;
    public $overwrite;
    protected $crawler;

    public function __construct() {

        $this->client = new Client();
        $this->crawler = new Crawler();
       
    }

    public function setSong($name) {

        $this->song = $name;
        return $this;
    }

    public function setSinger($name) {

        $this->singer = $name;
        return $this;
    }

    public function save($lyric, $file) {
       $filename = $file.".lrc";
        if (file_exists($filename) && !$this->overwrite) {
            echo "lyric already existed \n";
            return TRUE;
        }
         $this->downloadMsg();

        file_put_contents($filename, $lyric);
    }

    protected function downloadMsg() {
        echo "downloading lyric for {$this->song} by {$this->singer} \n";
    }

}
