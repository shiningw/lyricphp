<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Lyricphp;

use ID3Parser\ID3Parser;

class id3Handler {

    protected $id3;
    public $id3v2;

    public function __construct($filename = null) {
        $this->id3 = new ID3Parser();
       if(isset($filename)) {
          $this->fileName = $filename;
          $this->readAllTags();
       }
    }
    public function parse($file) {
        $this->fileName = $file;
        $this->readAllTags();
        return $this;
    }
    
    protected function readAllTags() {

        $this->tags = $this->id3->analyze($this->fileName);

        if (isset($this->tags['id3v2'])) {

            if (isset($this->tags['id3v2']['comments']['picture'])) {
                unset($this->tags['id3v2']['comments']['picture']);
            }
            if (isset($this->tags['id3v2']['APIC'])) {
                unset($this->tags['id3v2']['APIC']);
            }
            
            $this->id3v2 = $this->tags['id3v2'];
            $this->comments = $this->id3v2['comments'];
        } else {
            $this->id3v1 = $this->tags['id3v1'];
            $this->comments = $this->id3v1['comments'];
        }
    }

    public function getID3v2() {


        return $this->id3v2;
    }

    public function getID3v1() {


        return $this->id3v1;
    }

    public function getTitle() {

        if (isset($this->comments['title']) && is_array($this->comments['title'])) {

            $title = reset($this->comments['title']);
            return $title;
        }
    }

    public function getArtist() {
        if (isset($this->comments['artist']) && is_array($this->comments['artist'])) {

            $artist = reset($this->comments['artist']);
            return $artist;
        }else {
             return 'anonymous';
        }
    }

    public function getComments() {
       
        return $this->comments;
    }

}
