<?php

require_once __DIR__ . '/vendor/autoload.php';

use Lyricphp\Sites\Netease;
use Lyricphp\File;
//use Lyricphp\id3Handler;
use Lyricphp\Sites\Lrcgc;
use Lyricphp\stringUtility as utility;

$longopts = array("help", "source:", "artist:", "song:", "path:", "recursive", "correct", "overwrite");
$options = getopt("s:p:S:a:RCO", $longopts);
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
    $lrcsource = ucfirst($options['S']);
} elseif (isset($options['source'])) {
    $lrcsource = ucfirst($options['source']);
} else {
    $lrcsource = 'Netease';
}
$recursive = (isset($options['R']) || isset($optons['recursive'])) ? TRUE : FALSE;
$overwrite = (isset($options['O']) || isset($options['overwrite'])) ? TRUE : FALSE;
$correctLyric = (isset($options['C']) || isset($options['correct'])) ? TRUE : FALSE;


$lyricClass = "\Lyricphp\Sites\\" . $lrcsource;
$lyricInstance = new $lyricClass ();

$lyricInstance->overwrite = $overwrite;


if (isset($path)) {

    if ($correctLyric) {
        $filehandler = new File($path, 'lrc');
        ($recursive) ? $filehandler->getFilesRecursive() : $filehandler->getFiles();
        foreach ($filehandler->Files as $file) {
            utility::correctLyric($file);
        }
        exit;
    }
    $filehandler = new File($path);

    $songs = ($recursive) ? $filehandler->getFilesRecursive()->getID3Info() : $filehandler->getFiles()->getID3Info();
    batchProcess($songs);

    echo count($songs) . " lyrics have been downloaded \n";
    exit;
}

$lyricInstance->setSong($song)->setSinger($singer)->download($song);

function batchProcess($songs) {
    global $lyricInstance, $overwrite;

    foreach ($songs as $song) {
        //sleep(1);
        $lyricInstance->setSong($song['title'])->setSinger($song['singer']);
        $file = substr($song['file'], 0, -4);
        
        if(!$overwrite && file_exists($file.".lrc")) {
            echo "lyric already existed \n";
            continue;
        }

        if (!$lyricInstance->download($file)) {
            if (in_array(get_class($lyricInstance), array('\Lyricphp\Sites\Qianqian', 'Qianqian'))) {
                $backupsite = new \Lyricphp\Sites\Netease ();
            } else {
                $backupsite = new \Lyricphp\Sites\Qianqian();
            }
            $backupsite->overwrite = $overwrite;
            echo "switching to " . get_class($backupsite) . "  for lyric \n";

            if (!$backupsite->setSong($song['title'])->setSinger($song['singer'])->download($file)) {

                echo "switching to lrcgc.com \n";
                $lrcgc = new \Lyricphp\Sites\Lrcgc();
                $lrcgc->overwrite = $overwrite;

                $lrcgc->setSong($song['title'])->setSinger($song['singer'])->download($file);
            }
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
  --recursive -R   search a directory recursively
  --overwrite -O   overwrite original lyric file\n
EOF;
}
