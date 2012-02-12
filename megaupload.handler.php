<?php

require_once 'AbstractCurly.class.php';

/**
 * ~Id: Megaupload.handler.php
 * @author  hedonist@privacyharbor.com
 * @package mirrormint
 * 
 * Usage:
 * ------------------------------------
 * $mu = new Megaupload( 'testusername', 'testingpassword', '/path/to/file.rar' );
 * $ms->Upload(function($pct){ echo $pct,"%\r"; });
 */

interface MegauploadInterface
{
    public function Upload( $callback );       // Upload file
}

final class Megaupload extends AbstractCurly implements MegauploadInterface
{
    private
        $upload_identifier,
        $username,
        $password,
        $cookie,
        $filePath;
        
    function __construct( $username, $password, $path_to_file )
    {
        if (file_exists($path_to_file))
            $this->filePath = $path_to_file;
        $this->username = $username;
        $this->password = $password;
    }
    
    private function Login()
    {
        parent::curly_init('http://megaupload.com/?c=filemanager');
        
        $this->options[CURLOPT_HEADER] = TRUE; #arbitrary curlopt change
        
        $result = parent::curly_post(array(
            'login'    => '1',
            'username' => $this->username,
            'password' => $this->password
        ), function($pct){});
        $lines = explode("\n", $result);
        foreach ($lines as $line)
        {
            $line = trim($line);
            if (preg_match('/Set-Cookie: user=([a-zA-Z0-9]+);/', $line, $matches))
            {
                $this->cookie = $matches[1];
                return $this->cookie; #our cookie
            }
        }
        return FALSE;
    }
    
    /**
     * Megaupload::Upload()
     * 
     * @param callback $callback
     * @return string $download_url
     */
    final function Upload( $callback )
    {
        if (FALSE === $this->Login()) #must authenticate to retrieve cookie!
            die('Megaupload: error authenticating.');
        for($i = 0; $i < 32; $i++, $this->upload_identifier .= mt_rand(0, 9));
        parent::curly_init("http://www434.megaupload.com/upload_done.php?UPLOAD_IDENTIFIER={$this->upload_identifier}&user={$this->cookie}&s=" . filesize($this->filePath));
        $result = parent::curly_post(array(
                'Filename' => basename($this->filePath),
                'user'     => $this->cookie,
                'hotlink'  => '0',
                'Filedata' => "@{$this->filePath}",
                'Upload'   => 'Submit Query'
            ), 
            $callback
        );
        
        $lines = explode("\n", $result);
        foreach ($lines as $line)
        {
            $line = trim($line);
            if (preg_match("/parent.downloadurl = '(.*)'/", $line, $matches))
            {
                return $matches[1];
            }
        }
        return FALSE;
    }
}