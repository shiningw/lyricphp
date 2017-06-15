<?php

namespace Lyricphp\Sites;

class Tiantian extends lyricBase {

    protected $doc;
    protected $lyricCode = NULL, $lyricId = NULL;

    public function __construct() {
        $this->doc = new \DOMDocument();
        $this->lyricUrl = "http://lrccnc.ttplayer.com/dll/lyricsvr.dll?sh?";
    }

    protected function SingleDecToHex($dec) {

        $remainder = $dec % 16;
        if ($remainder < 10) {
            return $remainder;
        }
        $arr = array("A", "B", "C", "D", "E", "F");
        return $arr[$remainder - 10];
    }

    protected function toHexString($str) {
        if (!$str) {
            return false;
        }

        $result = "";
        for ($i = 0; $i < strlen($str); $i++) {
            $ord = ord($str[$i]);
            $result .= $this->SingleDecToHex(($ord - $ord % 16) / 16);
            $result .= $this->SingleDecToHex($ord % 16);
        }
        return $result;
    }

    public function codeString($str) {

        $codeString = str_replace(array(" ", "'"), "", strtolower($str));
        return $this->toHexString(mb_convert_encoding($codeString, 'UTF-16LE', 'UTF-8'));
    }

    protected function conv($num) {
        $tp = bcmod($num, 4294967296);

        if (bccomp($num, 0) >= 0 && bccomp($tp, 2147483648) > 0)
            $tp = bcadd($tp, -4294967296);
        if (bccomp($num, 0) < 0 && bccomp($tp, 2147483648) < 0)
            $tp = bcadd($tp, 4294967296);

        return $tp;
    }

    public function getLyricCode($Id, $artist, $title) {
        $Id = (int) $Id;
        $utf8Str = $this->toHexString($artist . $title);

        $length = strlen($utf8Str) / 2;
        for ($i = 0; $i <= $length - 1; $i++) {
            // eval('$song[' . $i . '] = 0x' . substr($utf8Str, $i * 2, 2) . ';');
            $song[$i] = intval('0x' . substr($utf8Str, $i * 2, 2), 16);
        }
        $tmp2 = 0;
        $tmp3 = 0;

        $tmp1 = ($Id & 0x0000FF00) >> 8; //右移8位后为0x0000015F 
//tmp1 0x0000005F 
        if (($Id & 0x00FF0000) == 0) {
            $tmp3 = 0x000000FF & ~$tmp1; //CL 0x000000E7 
        } else {
            $tmp3 = 0x000000FF & (($Id & 0x00FF0000) >> 16); //右移16位后为0x00000001 
        }
        $tmp3 = $tmp3 | ((0x000000FF & $Id) << 8); //tmp3 0x00001801 
        $tmp3 = $tmp3 << 8; //tmp3 0x00180100 
        $tmp3 = $tmp3 | (0x000000FF & $tmp1); //tmp3 0x0018015F 
        $tmp3 = $tmp3 << 8; //tmp3 0x18015F00 
        if (($Id & 0xFF000000) == 0) {
            $tmp3 = $tmp3 | (0x000000FF & (~$Id)); //tmp3 0x18015FE7 
        } else {
            $tmp3 = $tmp3 | (0x000000FF & ($Id >> 24)); //右移24位后为0x00000000 
        }

        $i = $length - 1;
        while ($i >= 0) {
            $char = $song[$i];
            if ($char >= 0x80)
                $char = $char - 0x100;

            $tmp1 = ($char + $tmp2) & 0x00000000FFFFFFFF;
            $tmp2 = ($tmp2 << ($i % 2 + 4)) & 0x00000000FFFFFFFF;
            $tmp2 = ($tmp1 + $tmp2) & 0x00000000FFFFFFFF;
            $i -= 1;
        }

        $i = 0;
        $tmp1 = 0;
        while ($i <= $length - 1) {
            $char = $song[$i];
            if ($char >= 128)
                $char = $char - 256;
            $tmp7 = ($char + $tmp1) & 0x00000000FFFFFFFF;
            $tmp1 = ($tmp1 << ($i % 2 + 3)) & 0x00000000FFFFFFFF;
            $tmp1 = ($tmp1 + $tmp7) & 0x00000000FFFFFFFF;

            $i += 1;
        }

        $t = $this->conv($tmp2 ^ $tmp3);
        $t = $this->conv(($t + ($tmp1 | $Id)));
        $t = $this->conv(bcmul($t, ($tmp1 | $tmp3)));
        $t = $this->conv(bcmul($t, ($tmp2 ^ $Id)));

        if (bccomp($t, 2147483648) > 0)
            $t = bcadd($t, - 4294967296);
        return $t;
    }

    protected function parse() {
        $hexedsinger = $this->codeString($this->singer);
        $hexedtitle = $this->codeString($this->song);
        $lyricUrl = sprintf("http://lrccnc.ttplayer.com/dll/lyricsvr.dll?sh?Artist=%s&Title=%s&Flags=0", $hexedsinger, $hexedtitle);

        if (!$this->doc->load($lyricUrl)) {

            return FALSE;
        }
        //echo $this->doc->saveXML();
        $lrcNodeList = $this->doc->getElementsByTagName("lrc");
        if ($lrcNodeList->length == 0) {
            return FALSE;
        }
              
        foreach ($lrcNodeList as $lrcNode) {
           
            $artist = $lrcNode->getAttribute("artist");
            if (strpos($artist, $this->singer) !== FALSE || strpos($this->singer, $artist)
                    !== FALSE) {

                $this->setLyricParams($lrcNode,$artist);
                //print $artist . '--' . $this->singer . "\n";

                return;
            }
        }
     

        $firstNode = $lrcNodeList->item(0);

        $this->setLyricParams($firstNode);
    }

    public function setLyricParams(\DOMElement $domelement,$artist = null) {

        $id = $domelement->getAttribute("id");
        $title = $domelement->getAttribute("title");
        //$artist = $domelement->getAtribute("artist");
        $code = $this->getLyricCode($id, $artist, $title);
        $this->lyricId = $id;
        $this->lyricCode = $code;
    }

    public function download($file) {

        $this->parse();

        //print $this->lyricCode;
        //return TRUE;
        $lrcstr = file_get_contents("http://lrccnc.ttplayer.com/dll/lyricsvr.dll?dl?Id=" . $this->lyricId . "&Code=" . $this->lyricCode);

        if (empty($lrcstr)) {
            return FALSE;
        }
        $this->downloadMsg();

        $this->save($lrcstr, $file);
        return TRUE;
    }

}
