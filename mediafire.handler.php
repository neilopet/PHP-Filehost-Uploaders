<?php

require_once 'AbstractCurly.class.php';

/**
 * ~Id: Mediafire.handler.php
 * @author  hedonist@privacyharbor.com
 * @package mirrormint
 * 
 * Usage:
 * ------------------------------------
*/
 
interface MediafireInterface
{
    public function Upload( $callback ); // Upload file
}

final class Mediafire extends AbstractCurly implements MediafireInterface
{
    private
        $cookies = array(),
        $ukey,
        $skey,
        $session,
        //$session_token,
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
        //$this->ukey = 'vc4ckoa537ali12n18clccse6kjmb3ln';
        //$this->skey = 'u1d8jxh456p';
        $charset = strtr(strtolower(base64_encode(md5(microtime(TRUE)))), array('='=>'','+'=>'','/'=>''));
        $this->ukey = substr($charset, 0, 32);
        $this->skey = substr(str_shuffle($charset), 0, 11);
        $this->cookies = array(
            'ukey=' . $this->ukey, 
            'skey=' . $this->skey
        );
        
        // Loop this in-case the ukey and skey are invalid.
        // Should only require 2 requests max.
        for ($i = 1; $i <= 2; $i++)
        {
            parent::curly_init('http://www.mediafire.com/dynamic/login.php?popup=1');
            
            $this->options[CURLOPT_HEADER] = TRUE;
            $this->options[CURLOPT_HTTPHEADER] = array('Cookie: ' . implode(';', $this->cookies));
            
            $response = parent::curly_post(http_build_query(array(
                'login_email'    => $this->username,
                'login_pass'     => $this->password,
                'login_remember' => 'on',
                'submit_login'   => 'Login+to+MediaFire'
            )), function(){});
            
            $lines = explode("\n", $response);
            foreach ($lines as $line)
            {
                $line = trim($line);
                if (preg_match('/Set-Cookie: ([a-z0-9]+)=([a-z0-9]+);/i', $line, $matches))
                {
                    
                    $cookie = $matches[1] . '=' . $matches[2];
                    $this->cookies[] = $cookie;
                    
                    switch ($matches[1])
                    {
                        case 'ukey':
                            $this->ukey = $matches[2];
                            break;
                        case 'skey':
                            $this->skey = $matches[2];
                            break;
                        case 'session':
                            $this->session = $matches[2];
                            break;
                        case 'user':
                            $this->user = $matches[2];
                            break;
                    }
                }
                
                if (isset($this->session, $this->user, $this->ukey, $this->skey))
                    return TRUE;
            }
        }
        
        // Get session_token - OBSOLETE
        /*
        parent::curly_init('http://www.mediafire.com/');
        $this->options[CURLOPT_HTTPHEADER] = array('Cookie: ' . implode(';', array(
            'skey=' . $this->skey,
            'ukey=' . $this->ukey,
            'user=' . $this->user,
            'session=' . $this->session . ';'
        )));
        $response =  parent::curly_get(array(), function(){});
        if (preg_match('/window\.tH\.YQ\("([a-z0-9]+)",/', $response, $matches))
        {
            $this->session_token = $matches[1];
        }
        */
    }
    
    /**
     * Mediafire::Upload()
     * 
     * @param callback $callback
     * @return string $download_link
     */
    public function Upload( $callback )
    {
        $this->Login();        
        if (!isset($this->ukey, $this->skey, $this->session))
        {
            die('Invalid mediafire session.');
        }
        
        // Upload and fetch tracker key
        parent::curly_init('http://www.mediafire.com/douploadtoapi/?type=basic&ukey=' . $this->ukey . '&user=' . $this->user . '&uploadkey=myfiles&filenum=0&uploader=0&MFULConfig=wxbp16ok9pzhorbna5rxf8bey6n8qxjx');
        $response = parent::curly_post(array(
            'Filedata' => "@{$this->filePath};filename=" . basename($this->filePath)
        ),
        $callback);
        
        if (preg_match('/<key>(.*)<\/key>/', $response, $matches))
        {
            while(TRUE)
            {
                parent::curly_init('http://www.mediafire.com/basicapi/pollupload.php');                
                if (preg_match('/<quickkey>(.*)<\/quickkey>/', parent::curly_get(array('key' => $matches[1]), function(){}), $matches2))
                {
                    if ($matches2[1] != '')
                        return 'http://www.mediafire.com/download.php?' . $matches2[1];
                }
            }
        }
        
        return FALSE;
    }
}