<?php

namespace Lyricphp\Sites;

use Goutte\Client;
use Lyricphp\stringUtility as utility;

class Netease extends lyricBase {

    public $lyric;
    public $baseUrl;
    public $url;
    protected $client;
    protected $jsonData;

    public function __construct($files = null) {

        if (isset($files)) {
            $this->files = $files;
        }

        parent::__construct();
        $this->searchUrl = 'http://music.163.com/api/search/get/web';
        $this->lyricUrl = "http://music.163.com/api/song/lyric?lv=1&kv=1&tv=-1&id=";
    }

    public function setHeaders($name, $value) {

        $this->headers[$name] = $value;
    }

    public function getDefaultHeaders() {
        return array(
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip,deflate,sdch',
            'Accept-Language' => 'zh-CN,zh;q=0.8,gl;q=0.6,zh-TW;q=0.4',
            'Connection' => 'keep-alive',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Host' => 'music.163.com',
            'Referer' => 'http://music.163.com/search/',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36',
        );
    }

    public function searchSong($name) {

        foreach ($this->getDefaultHeaders() as $key => $value) {

            $this->setHeaders($key, $value);
        }

        $params = array(
            'form_params' => array(
                's' => $name,
                'type' => 1,
                'offset' => 0,
                'limit' => 10
            ),
            'headers' => $this->headers,
        );

        try{
            $res = $this->client->request('POST', $this->searchUrl, $params);
        }catch (Exception $e) {
            echo $e->getMessage."\n";
            return FALSE;
        }
        $contents =  json_decode($res->getBody()->getContents());
        //$this->jsonData = json_decode($contents);
        if(isset($contents->result)) {
             $this->songData = $contents->result->songs;
        }
        return $this->songData;
    }

    public function searchLyric($id) {
        $lyricurl = $this->lyricUrl . $id;
        try {
            $res = $this->client->request('GET', $lyricurl);
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
        $contents = $res->getBody()->getContents();
        $this->lyricData = json_decode($contents);

        return $this->lyricData;
    }

    public function getSongId($singer = null) {
         if(!isset($singer)) {
              return $this->songData[0]->id;
         }
        foreach ($this->songData as $song) {
            $artist = reset($song->artists);
            //print_r($artist);
            if (strpos($artist->name, $singer) !== FALSE || strpos($singer, $artist->name) !== FALSE) {

                return $song->id;
            } else {
                //get the first one if no one match
                if (isset($this->songData[0]->id)) {
                    return $this->songData[0]->id;
                }
            }
        }
    }

    public function download($savepath) {
       
        if(file_exists($savepath.".lrc")) {
            //echo "lyric already existed \n";
            return TRUE;
        }

        $this->searchSong($this->song);
        $songid = $this->getSongId($this->singer);
        if (empty($songid)) {
            echo "no lyric found for " . $this->song . "\n";
            return FALSE;
        }
        $lyric = $this->searchLyric($songid);
        //$filehandler->saveLyric($lyric->lrc->lyric, $file);
        //echo $lyric->lrc->lyric;
        
        if(!isset($lyric->lrc)) {
             echo "no lyric found for " . $this->song . "\n";
            return FALSE;
        }

        echo "downloading lyric for $this->song \n";
        
         // $lyricContent = utility::correctLyricString($lyric->lrc->lyric);
          
          
        
        $this->save($lyric->lrc->lyric, $savepath);

        return TRUE;
    }

    
}
 