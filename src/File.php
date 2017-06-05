<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Lyricphp;

use Lyricphp\id3Handler;

class File extends id3Handler {

    public $dirName;
    public $Files;

    //$dir_name = iconv("utf-8", "gb2312", $dir_name);


    public function __construct($dirname, $suffix = "mp3") {

        $this->dirName = $dirname;
        $this->id3Handler = new id3Handler();


        if (!is_dir($dirname)) {

            die("directory ${dirname} doesn't exit");
        }

        $this->suffix = $suffix;
    }

    public function getFiles() {

        $this->Files = \glob($this->dirName . DIRECTORY_SEPARATOR."*.{$this->suffix}");
        
        return $this;
    }

    public function getFilesRecursive() {


        $directory = new \RecursiveDirectoryIterator($this->dirName);
        $iterator = new \RecursiveIteratorIterator($directory);
        $iterators = new \RegexIterator($iterator, '/.*\.' . $this->suffix . '$/', \RegexIterator::GET_MATCH);

        $files = array();
        foreach ($iterators as $info) {
            if ($info) {
                $this->Files[] = reset($info);
            }
        }
        return $this;
    }


    public function getID3Info() {

        $songs = array();
        foreach ($this->Files as $file) {
            $title = $this->id3Handler->parse($file)->getTitle();
            $artist = $this->id3Handler->getArtist();

            $data = array('file' => $file,'title' => $title,'singer' => $artist);
            $songs[] = $data ;
           
            //print "downloading lyric for ${file}" . "\n";

            //$filehandler->saveLyric($lyric->lrc->lyric, $file);
        }
        
        return $songs;
        
    }

}
