<?php

/*
  show-youtube-vids-by-views.php
  Usage: cat <html file> | php show-youtube-vids-by-views.php
  Usage: php show-youtube-vids-by-views.php <html file>

  A script to take a Youtube channel's html, extract the URLs, titles, and
  views, then represent them in decreasing order of those views.

  This script expects you to have already downloaded the html of the Youtube
  videos page in which you're interested. While this can be achieved using
  View Source | Save As, not all videos are shown.

  Youtube hide the larger part of a channel's content behind javascript calls
  that do not fire until you move down the page. A number of extensions offer
  to deal with this: I've found success with Gildas' 'SingleFile' for Chromium,
  available at
  https://chrome.google.com/webstore/detail/singlefile/mpiodijhokgodhhofbcjdecpffjipkle
  To use the above suggestion:
  1. Install the browser extension
  2. Visit the "Videos" page you wish ordered. Example:
  https://www.youtube.com/c/StephanLivera/videos
  3. Click the SingleFile icon in the address bar
  4. You'll be invited to Reload the page - click Reload
  5. Scroll to the bottom of the page. This forces all the hidden videos to be
  retrieved
  6. Click SingleFile once more. After a few seconds the html will finish being
  downloaded to your default Downloads directory where it can be used as with
  the Usage specified above.
*/

//
// Phase 1: set up
//
mb_internal_encoding("UTF-8");
$msg = '';
$result = 0;

// Fetch and check arguments
if ($argc > 2) {
  $msg = "Expected no more than one argument\n";
  $result = 1;
  goto completed;

} else if ($argc === 2) {
  // Given a filename to access
  $filename = $argv[1];

} else {
  // Expecting STDIN.
  $filename = null;
}

// Obtain the data on STDIN
if (posix_isatty(STDIN)){
  if ($filename === null) {
    $msg = "Expected a filename but wasn't given one\n";
    $result = 1;
    goto completed;
  }
  $html = file_get_contents($filename);

} else {
  // Otherwise look at parameters
  $html = trim(stream_get_contents(STDIN));
}

//
// Phase 2: retrieve pertinent details
//
$res_arr = array();
$view_marker = ' views" title="';
$view_marker_alt = " views' title='";
$video_marker_s = '<a id=video';
$video_marker_e = '">';
$href_marker = '" href="';
$video_idx_s = $video_idx_e = 0;
do {
  $found = false;
  // Look for the next video mentioned

  // Pick up just the data for this one video
  $video_idx_s = strpos($html, $video_marker_s, $video_idx_e);
  if ($video_idx_s !== false) {
    $found = true;
    $video_idx_e = strpos($html, $video_marker_e, $video_idx_s);
    $video_html = substr($html, $video_idx_s, $video_idx_e - $video_idx_s + 1);

    // Having found it, extract the views
    $a = $b = $title_idx_s = strpos($video_html, $view_marker);
    if ($a === false) {
      // If the title contains double quotes, Youtube html uses singles
      $a = $b = $title_idx_s = strpos($video_html, $view_marker_alt);
    }
    while ($a !== 0 && substr($video_html, --$a, 1) != ' ');
    $views = substr($video_html, $a + 1, $b - $a - 1);

    // If the views is in short form, get it into normal form.
    // This matters if we act on the rounded numbers rather than the accurate
    // numbers.
    if (($d = strpos($views, 'K')) !== false) {
      // Thousands
      $dec = substr($views, 0, $d);
      if (strpos($dec, '.') !== false) {
	$dec = str_replace('.', '', $dec);
	$dec .= '00';
      } else {
	$dec .= '000';
      }
      $views = $dec;

    } else if (($d = strpos($views, 'M')) !== false) {
      // Millions
      $dec = substr($views, 0, $d);
      if (strpos($dec, '.') !== false) {
	$dec = str_replace('.', '', $dec);
	$dec .= '00000';
      } else {
	$dec .= '000000';
      }
      $views = $dec;
    }

    // Remove commas if present
    $views = str_replace(',', '', $views);

    // Extract the title
    $title_idx_s += strlen($view_marker);
    $title_idx_e = $title_idx_s;
    while($video_html[$title_idx_e++] !== '"');
    $title_idx_e--;
    $title = substr($video_html, $title_idx_s, $title_idx_e - $title_idx_s);

    // Extract the URL
    $href_idx_s = strpos($video_html, $href_marker);
    $href_idx_s += strlen($href_marker);
    $href_idx_e = $href_idx_s;
    while($video_html[$href_idx_e++] !== '"');
    $href_idx_e--;
    $href = substr($video_html, $href_idx_s, $href_idx_e - $href_idx_s);

    // Store what we found for next phase, and repeat
    $res_arr[$views][] = array($href, $title);
  }

  // Prepare to find the next video
  $video_idx_s = $video_idx_e;
} while ($found === true);

//
// Phase 3: reverse sort by views
//
krsort($res_arr, SORT_NATURAL);

//
//Phase 4: Tell the user what we know
//
foreach($res_arr as $views => $vals) {
  foreach($vals as list($url, $title)) {
    $msg .= "$views  $url  $title\n";
  }
}

completed:
if ($msg !== '')
  echo($msg);
exit($result);
