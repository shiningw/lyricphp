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

        file_put_contents($file . ".lrc", $lyric);
        return;
        if ($fh = fopen($lyric. ".lrc", 'wb')) {
            fwrite($fh, $lyric);
        } else {

            throw new Exception("Can not open ${file} \n");
        }

        fclose($fh);
    }

}
