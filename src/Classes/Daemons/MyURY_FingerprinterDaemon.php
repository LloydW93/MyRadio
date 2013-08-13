<?php
/**
 * This file provides the MyURY_FingerprinterDaemon class for MyURY
 * @package MyURY_Daemon
 */

/**
 * The Fingerprinter Daemon will scan the digital files in the music library, and log information about potentially
 * incorrect metadata in the rec database.
 *
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 20130711
 * @package MyURY_Daemon
 */
class MyURY_FingerprinterDaemon extends MyURY_Daemon {
  /**
   * If this method returns true, the Daemon host should run this Daemon. If it returns false, it must not.
   * It is currently enabled for a full scan of the music library. Generally, it may often be disabled as it
   * generates a fare amount of load and actually making the changes is a manual process anyway.
   * @return boolean
   */
  public static function isEnabled() { return false; }
  
  /**
   * Process a batch of tracks that are currently not verified as correct, and sees if Last.FM has
   * metadata for it.
   * 
   * This function can be have the batch size changed by changing the first line
   * and can have less reliable proposals stored by modifying the rank comparison.
   * Change the levenshtein comparisons to tweak what amount of change is
   * automatically approved.
   * 
   * @todo While this logs Last.fm albums, it does not compare them.
   */
  public static function run() {
    //Get 5 unverified tracks. Tune the "limit" to change this
    $tracks = MyURY_Track::findByOptions(array('lastfmverified' => false, 'random' => true, 'digitised' => true,
        'nocorrectionproposed' => true, 'limit' => 5));
    
    foreach ($tracks as $track) {
      $info = MyURY_Track::identifyUploadedTrack($track->getPath());
      
      /**
       * We use two metrics to identify if the information is reliable
       * 1. Is the rank high (> 0.8)?
       * 2. Does is have a short levenshtein difference from the current value?
       */
      if (empty($info[0]) or $info[0]['rank'] < 0.8) {
        echo 'Fingerprint data for '.$track->getID().' unreliable (p='.(empty($info[0]) ? '0' : $info[0]['rank'])."). Skipping.\n";
        continue;
      }
      
      if ($info[0]['title'] !== $track->getTitle() && levenshtein($info[0]['title'], $track->getTitle()) <= 2) {
        echo "Minor title correction made - {$track->getTitle()} to {$info[0]['title']}\n";
        $track->setTitle($info[0]['title']);
      }
      
      if ($info[0]['artist'] !== $track->getArtist() && levenshtein($info[0]['artist'], $track->getArtist()) <= 2) {
        echo "Minor artist correction made - {$track->getArtist()} to {$info[0]['artist']}\n";
        $track->setArtist($info[0]['artist']);
      }
      
      if ($track->getTitle() == $info[0]['title']
              && $track->getArtist() == $info[0]['artist']) {
        echo "Track {$track->getID()} verified as correct.\n";
        
        $track->setLastfmVerified();
        continue;
      }
      
      $album = MyURY_Track::getAlbumDurationAndPositionFromLastfm($info[0]['title'], $info[0]['artist'])['album']->getTitle();
      
      if (levenshtein($info[0]['title'], $track->getTitle()) < 8
              or levenshtein($info[0]['artist'], $track->getArtist()) < 5
              or levenshtein($album, $track->getAlbum()->getTitle()) < 5) {
        MyURY_TrackCorrection::create($track, $info[0]['title'], $info[0]['artist'], $album, MyURY_TrackCorrection::LEVEL_RECOMMEND);
        echo "Correction recommended for {$track->getID()}.\n";
      } else {
        MyURY_TrackCorrection::create($track, $info[0]['title'], $info[0]['artist'], $album, MyURY_TrackCorrection::LEVEL_SUGGEST);
        echo "Correction suggested {$track->getID()}.\n";
      }
      
      //The Daemons slowly leaks memory if we don't clean up Track objects here - you can have a GB or so in a day
      $track->removeInstance();
    }
  }
}