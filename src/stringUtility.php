<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Lyricphp;

class stringUtility {

    public static function toCRLF($files) {

        foreach ($files as $file) {

            $fh = fopen($file, 'rb');
            $str = '';
            while ($line = fgets($fh, 4096)) {

                if (strpos($line, "\r\n") !== FALSE) {
                    $str .= $line;
                } else {
                    $str .= str_replace("\n", "\r\n", $line);
                }
            }
            fclose($fh);

            $wfh = fopen($file, 'wb');
            //echo $str;
            fwrite($wfh, $str);
            fclose($wfh);
        }
    }

    public static function convert($file) {
        $fh = fopen($file, 'rb');
        $str = fread($fh, filesize($file));
        fclose($fh);
        mb_detect_order(array('UTF-8', 'ISO-8859-1'));
        $re_str = mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
        $wfh = fopen($file . "-encoded", 'wb');
        fwrite($wfh, $re_str);
        fclose($wfh);
    }

    public static function checkEncoding($string, $string_encoding) {
        $fs = $string_encoding == 'UTF-8' ? 'UTF-32' : $string_encoding;

        $ts = $string_encoding == 'UTF-32' ? 'UTF-8' : $string_encoding;

        return $string === mb_convert_encoding(mb_convert_encoding($string, $fs, $ts), $ts, $fs);
    }

    /*
     * remove useless zero in timeline
     */

    public static function correctLyric($files) {

        foreach ($files as $file) {

            $fh = fopen($file, 'r+');
            //$wfh = fopen('a'.$file,'wb');
            $r_line = '';
            while ($line = fgets($fh, 4096)) {
                // print "$file " . ftell($fh) . "\n";
                //print $line."\n";
                preg_match('/^\[((?P<time>[^\]]+))\]((?P<lyric>.*))/', $line, $matches);


                if (isset($matches['time']) && strlen($matches['time']) >= 9) {
                    $tmp = substr($matches['time'], 0, -1);
                    $line = '[' . $tmp . ']' . $matches['lyric'] . "\n";
                    //print mb_detect_encoding($line);
                } else {
                    // echo "skip this line for $file \n";
                    // print mb_detect_encoding($line);
                }
                $r_line .= $line;
                //mb_detect_order(array('UTF-8','ISO-8859-1'));
                //$re_line = mb_convert_encoding($line, "UTF-8","ISO-8859-1");
                //fwrite($fh, $re_line);
            }
            //break;
            //print $file."\n";
            fclose($fh);
            //iconv(mb_detect_encoding($r_line,mb_detect_order(), true), "UTF-8", $r_line);

            file_put_contents($file, $r_line);
            //fclose($wfh);
        }


        return $matches;
    }

    public static function download($url, $filename) {

        if (!isset($url)) {
            return FALSE;
        }
        if (!$urlRes = fopen($url, 'rb')) {

            echo "failed to open $this->downloadUrl\n";
            return FALSE;
        }

        if (!$wfh = fopen($filename, 'wb')) {
            echo "failed open ${filename} for writing \n";
            return FALSE;
        }

        while (!feof($urlRes)) {
            //echo (fgets($file))."12222";
            fwrite($wfh, fread($urlRes, 1024 * 8), 1024 * 8);
        }

        echo ftell($wfh)." Bytes have been downloaded \n";

        fclose($urlRes);
        fclose($wfh);
        return TRUE;
    }

}
