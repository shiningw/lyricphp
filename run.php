<?php

require_once __DIR__ . '/vendor/autoload.php';

use Lyricphp\Sites\Netease;
use Lyricphp\File;
//use Lyricphp\id3Handler;
use Lyricphp\Sites\Lrcgc;
use Lyricphp\stringUtility as utility;

$longopts = array("help", "source:", "artist:", "song:", "path:", "recursive","correct");
$options = getopt("s:p:S:a:RC", $longopts);
//print_r($options);
if (isset($options['p'])) {
    $path = $options['p'];
} elseif (isset($options['path'])) {
    $path = $options['path'];
}

if (!isset($path)) {

    if (isset($options['a'])) {
        $singer = $options['a'];
    } elseif (isset($options['artist'])) {
        $singer = $options['artist'];
    }

    if (isset($options['s'])) {
        $song = $options['s'];
    } elseif (isset($options['song'])) {
        $song = $options['song'];
    } else {

        showHelp();
        exit;
    }
}


if (isset($options['S'])) {
    $lrcsource = strtolower($options['S']);
} elseif (isset($options['source'])) {
    $lrcsource = strtolower($options['source']);
} else {
    $lrcsource = 'netease';
}

$netease = new Netease();
$lrcgc = new Lrcgc();

if (isset($path)) {
    
    if(isset($options['C']) || isset($options['correct'])) {
        $filehandler = new File($path,'lrc');
        (isset($options['R']) || isset($optons['recursive'])) ? $filehandler->getFilesRecursive() : $filehandler->getFiles();
        utility::correctLyric($filehandler->Files);
        exit;
    }
	//$path = 'F:\temp\music\测试';
    
    $filehandler = new File($path); //'/Volumes/DATA/temp/music/2100首无损单曲推荐/扩展篇/中文扩展a/'

    $songs = (isset($options['R']) || isset($optons['recursive'])) ? $filehandler->getFilesRecursive()->getID3Info() : $filehandler->getFiles()->getID3Info();
    batchProcess($songs);
	//print_r($songs);

    //$filehandler->getFiles();
    //utility::correctLyric($filehandler->Files);
    exit;
}

$netease->setSong($song)->download($song);

//exit;

function batchProcess($songs) {
    global $netease, $lrcgc;

    foreach ($songs as $song) {
        //sleep(1);
        $netease->setSong($song['title'])->setSinger($song['singer']);
        $file = substr($song['file'], 0, -4);

        if (!$netease->download($file)) {
            echo "switching to lrcgc.com for lyric \n";
            $lrcgc->setSong($song['title'])->setSinger($song['singer'])->download($file);
        }
    }
}

function showHelp() {

    print <<<EOF
    usage: run.php [<options>]

Get lyrics for all mp3 files stored in a directory or specified song from the command line
    
OPTIONS
  --path, -p   The path to search for.
  --help, -h       Display this help.
  --source, -S     The source from which to get lyric(eg netease,lrcgc).
  --song, -s       The song name to search
  --artist, -a     Filter result by singer
  --correct -C     correct lyric time
  --recursive -R   search a directory recursively\n
EOF;
}


