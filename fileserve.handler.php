<?php

require_once 'AbstractCurly.class.php';

/**
 * ~Id: fileserve.handler.php
 * @author  hedonist@privacyharbor.com
 * @package mirrormint
 * 
 * Usage:
 * ------------------------------------
*/
 
interface FileserveInterface
{
    public function Upload( $callback ); // Upload file
}

final class Fileserve extends AbstractCurly implements FileserveInterface
{
    private
        $userid,
        $filePath,
        $sessionId;
        
    function __construct( $userid, $path_to_file )
    {
        if (file_exists($path_to_file))
            $this->filePath = $path_to_file;
        //$this->url = "ftp://{$username}:{$password}@ftp.fileserve.com/" . basename($path_to_file);
        $this->userid = $userid;
    }
    
    /**
     * Fileserve::Upload()
     * 
     * @param callback $callback
     * @return string $download_url
     */
    public function Upload( $callback )
    {
        // Get session upload id.
        if (preg_match("/sessionId:'([a-z0-9\-]+)'/", file_get_contents('http://upload.fileserve.com/upload/15316968/5000/?callback=j&_=1326432820769'), $matches))
        {
            $this->sessionId = $matches[1];
        }
        
        parent::curly_init('http://upload.fileserve.com/upload/15316968/5000/' . $this->sessionId . '/');
        if (preg_match('/"shortenCode":"(.*)"/i', parent::curly_post(array(
            'file' => "@{$this->filePath};filename=" . basename($this->filePath)
        ),
        $callback), $matches))
        {
            return 'http://www.fileserve.com/file/' . $matches[1];
        }
        /* //obsolete
        return parent::curly_ftp(fopen($this->filePath, 'r'), filesize($this->filePath), function( $pct ){
            echo $pct, "%\r";
        });
        */
        return FALSE;
    }
}