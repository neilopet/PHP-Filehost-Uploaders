<?php

require_once 'AbstractCurly.class.php';

/**
 * ~Id: Filejungle.handler.php
 * @author  hedonist@privacyharbor.com
 * @package mirrormint
 * 
 * Usage:
 * ------------------------------------
*/
 
interface FilejungleInterface
{
    public function Upload( $callback ); // Upload file
}

final class Filejungle extends AbstractCurly implements FilejungleInterface
{
    private
        $upload_url,
        $cookie,
        $username,
        $password,
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
        parent::curly_init('http://filejungle.com/login.php');
        $postData = http_build_query(array(
            'loginUserName'     => $this->username,
            'loginUserPassword' => $this->password,
            'loginFormSubmit'   => ''
        ));
        $this->options[CURLOPT_HEADER] = TRUE;
        $this->options[CURLOPT_HTTPHEADER] = array
        (
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer: http://filejungle.com/',
            'Connection: keep-alive',
            'Content-Type: application/x-www-form-urlencoded', 
            'Content-Length: ' . strlen($postData)
        );
        $response = parent::curly_post($postData, function(){});
        $lines = explode("\n", $response);
        foreach($lines as $line)
        {
            if (preg_match('/Set-Cookie: (?<cname>[a-z0-9\_\-]+)=(?<cval>[a-z0-9\=\_\-\%\.]+)/i', $line, $matches))
            {
                if ($matches['cname'] == 'PHPSESSID')
                    $this->cookie = urldecode($matches['cval']);
            }
        }
    }
    
    public function Upload( $callback )
    {
        $this->Login();
        if (isset($this->cookie))
        {
            parent::curly_init('http://filejungle.com');
            $this->options[CURLOPT_HTTPHEADER] = array('Cookie: PHPSESSID=' . $this->cookie);
            $response = parent::curly_get(array(''=>''), function(){});
            if (preg_match("/uploadUrl = 'http:\/\/(?<upload_url>.*)';/", $response, $matches))
            {
                $this->upload_url = isset($matches['upload_url']) ? ('http://' . $matches['upload_url']) : NULL;
            }
            
            if (isset($this->upload_url))
            {
                $filesize = filesize($this->filePath);
                parent::curly_init($this->upload_url);
                $this->options[CURLOPT_HTTPHEADER] = array
                (
                    'X-File-Name: ' . basename($this->filePath), 
                    'X-File-Size: ' . $filesize,
                    'Content-Type: multipart/form-data',
                    'Origin: http://filejungle.com'
                );
                $json_obj = @json_decode(parent::curly_put(fopen($this->filePath, 'r'), $filesize, $callback));
                if (isset($json_obj))
                {
                    return 'http://www.filejungle.com/f/' . $json_obj->shortenCode;
                }
            }
        }
    }
}