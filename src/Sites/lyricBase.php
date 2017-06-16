<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Lyricphp\Sites;

use Goutte\Client;

//use GuzzleHttp\Client as GuzzleClient;

abstract class lyricBase {

    //the goutte object
    protected $goutteClient;
    //the guzzlehttp Client
    protected $Client;
    protected $singer = 'anonymous';
    protected $song;
    public $overwrite;

    public function __construct() {

        $this->goutteClient = new Client();
        $this->client = $this->goutteClient->getClient();
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
