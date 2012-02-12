<?php

require_once 'AbstractCurly.class.php';

/** 
 * Filepost
 * An object used to upload files to Filepost.com
 * @author Neil Opet <neil.opet@gmail.com>
 */
 
interface FilepostInterface
{
    public function Upload( $callback ); // Upload file
}

final class Filepost extends AbstractCurly implements FilepostInterface
{
    private
        $cookies,
        $SID,
        $upload_url,
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
        parent::curly_init('http://filepost.com/general/login_form/?JsHttpRequest=' . time() . '-xml');
        $this->options[CURLOPT_HEADER] = TRUE;
        $response = parent::curly_post(http_build_query(array(
            'email'                    => $this->username,
            'password'                 => $this->password,
            'recaptcha_response_field' => ''
        )), function(){});
        $lines = explode("\n", $response);
        foreach($lines as $line)
        {
            if (preg_match('/Set-Cookie: (?<cname>[a-z0-9\_\-]+)=(?<cval>[a-z0-9\=\_\-\%\.]+)/i', $line, $matches))
            {
                $this->cookies[] = $matches['cname'] . '=' . $matches['cval'];
                if ($matches['cname'] == 'SID')
                    $this->SID = urldecode($matches['cval']);
            }
        }
    }
    
    public function Upload( $callback )
    {
        $this->Login();
        if (isset($this->SID))
        {
            // get the upload SID and url
            parent::curly_init('http://filepost.com/');
            $this->options[CURLOPT_HTTPHEADER] = array('Cookie: ' . implode('; ', $this->cookies));
            $response = parent::curly_get(array(''=>''), function(){});
                    
            if (preg_match("/SID: '(?<sid>[a-f0-9]{32})'/", $response, $matches))
            {
                $this->SID = isset($matches['sid']) ? $matches['sid'] : NULL;
            }
            
            if (preg_match("/upload_url: 'http:\/\/(?<upload_url>.*)',/", $response, $matches))
            {
                $this->upload_url = isset($matches['upload_url']) ? ('http://' . $matches['upload_url']) : NULL;
            }
            
            if (!isset($this->upload_url, $this->SID))
                die('No active SID or URL returned.');
            
            parent::curly_init($this->upload_url);
            $this->options[CURLOPT_HTTPHEADER] = array('Accept: text/*', 'User-Agent: Shockwave Flash', 'Connection: Keep-Alive', 'Cache-Control: no-cache');
            $response = parent::curly_post(array(
                'Filename' => basename($this->filePath),
                'SID'      => $this->SID, // not the static cookie,
                'file'     => "@{$this->filePath};filename=" . basename($this->filePath),
                'Upload'   => 'Submit Query'
            ), $callback);
            
            if (preg_match('/"answer":"(?<tmp_link>.*)"/', $response, $matches))
            {
                $contents = file_get_contents('http://filepost.com/files/done/' . $matches['tmp_link']);
                if (preg_match('/id="down_link" class="inp_text" value="http:\/\/(?<dlink>.*)"\/>/', $contents, $matches2))
                {
                    echo 'http://' . $matches2['dlink'];
                }
            }
        }
    }
}