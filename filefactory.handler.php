<?php

require_once 'AbstractCurly.class.php';

/** 
 * Filefactory
 * An object used to upload files to Filefactory.com
 * @author Neil Opet <neil.opet@gmail.com>
 */
 
interface FilefactoryInterface
{
    public function Upload( $callback ); // Upload file
}

final class Filefactory extends AbstractCurly implements FilefactoryInterface
{
    private
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
        parent::curly_init('http://www.filefactory.com/member/login.php');
        $queryStr = http_build_query(array(
            'email'    => $this->username,
            'password' => $this->password,
            'redirect' => '/'
        ));
        $this->options[CURLOPT_HTTPHEADER] = array
        (
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 
            'Cookie: FF_JoinPromo=true', 
            'Content-Type: application/x-www-form-urlencoded', 
            'Referer: http://www.filefactory.com/', 
            'Content-Length: ' . strlen($queryStr)
        );
        $this->options[CURLOPT_HEADER] = TRUE;
        $response = parent::curly_post($queryStr, function(){});
        $lines = explode("\n", $response);
        foreach($lines as $line)
        {
            if (preg_match('/Set-Cookie: (.*); expires/', $line, $matches))
            {
                $this->cookie = urldecode(strtr($matches[1], array('ff_membership=' => '')));
            }
        }
    }
    
    public function Upload( $callback )
    {
        $tmp_cookie = NULL;
        $this->Login();
        if (!isset($this->cookie))
            die('Filefactory session could not be extracted.');
        
        parent::curly_init('http://upload.filefactory.com/upload.php');
        $this->options[CURLOPT_HTTPHEADER] = array
        (
            'Host: upload.filefactory.com',
            'User-Agent: Shockwave Flash',
            'Content-Type: multipart/form-data', 
        );
        $download_link = parent::curly_post(array(
            'Filename'       => basename($this->filePath),
            'folderViewhash' => '0',
            'cookie'         => $this->cookie,
            'Filedata'       => "@{$this->filePath}",
            'Upload'         => 'Submit Query'            
        ), $callback); 
        
        return 'http://www.filefactory.com/file/' . $download_link . '/n/' . basename($this->filePath);
    }
}