<?php

require_once 'AbstractCurly.class.php';

/**
 * ~Id: Uploading.handler.php
 * @author  hedonist@privacyharbor.com
 * @package mirrormint
 * 
 * Notes:
 * All active sessions expire on successful upload.
 * 
 * We can possibly remove the Upload URL request if we
 * can decipher the upload url.  It looks like a Base64
 * that uses the SID cookie.  I couldn't reverse the algorithm
 * in such little time. 
 * 
 * Uploading.com uses duplicate "file" fields in the
 * post header for an upload.  The first "file" field
 * contains the unique file ID returned by the files/generate/
 * HTTP request.  The second "file" field is the contents of 
 * the uploaded file.
 * 
 * Flow:
 * START: > Login 
 *        > Get upload URL from Javascript on index 
 *        > Generate new file id and download link
 *        > Post upload form
 * 
 * Usage:
 * ------------------------------------
    $uploading = new Uploading('username', 'password', 'C:\\path\\to\\file.whatever');
    echo $uploading->Upload(function($pct) {
        echo $pct, "%\r"; 
    });
*/
 
interface UploadingInterface
{
    public function Upload( $callback ); // Upload file
}

final class Uploading extends AbstractCurly implements UploadingInterface
{
    private
        $SID,
        $upload_url,
        $cookies,
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
        $postData = http_build_query(array(
            'email'    => $this->username,
            'password' => $this->password, 
            'remember' => 'on'
        ));
        parent::curly_init('http://uploading.com/general/login_form/?JsHttpRequest=' . time() . '-xml');
        $this->options[CURLOPT_HEADER] = TRUE;
        $this->options[CURLOPT_HTTPHEADER] = array('Content-Type: application/octet-stream; charset=UTF-8', 'Content-Length: ' . strlen($postData));
        $response = parent::curly_post($postData, function(){});
        $lines = explode("\n", $response);
        $cookies = array();
        foreach($lines as $line)
        {
            if (preg_match('/Set-Cookie: (?<cname>[a-z0-9\_\-]+)=(?<cval>[a-z0-9\=\_\-\%\.]+)/i', $line, $matches))
            {
                $this->cookies[] = $matches['cname'] . '=' . $matches['cval'];
                if ($matches['cname'] == 'SID')
                    $this->SID = urldecode($matches['cval']);
            }
        }
        return TRUE;
    }
    
    public function Upload( $callback )
    {
        if ($this->Login())
        {
            if (!isset($this->SID))
                die('Uploading.com - No session.');
                
            // get upload url
            parent::curly_init('http://uploading.com/');
            $this->options[CURLOPT_HTTPHEADER] = array('Cookie: ' . implode('; ', $this->cookies)); # must be set each time
            $response = parent::curly_get(array(''=>''), function(){});
            
            if (preg_match("/upload_url: 'http:\/\/(?<upload_url>.*)',/", $response, $matches))
            {
                $this->upload_url = isset($matches['upload_url']) ? ('http://' . $matches['upload_url']) : NULL;
            }
            
            if (!isset($this->upload_url))
                die('No upload URL returned.');
            
            // create file placeholder
            parent::curly_init('http://uploading.com/files/generate/?JsHttpRequest');
            $this->options[CURLOPT_HTTPHEADER] = array('Cookie: ' . implode('; ', $this->cookies)); # must be set each time
            $json_obj = json_decode(parent::curly_post(array(
                'name' => basename($this->filePath),
                'size' => filesize($this->filePath)
            ), function(){}));
            
            // this needs the post header custom created because of duplicate "file" fields
            parent::curly_init($this->upload_url);
            
            // additional headers required for upload
            $boundary = substr(strtr(base64_encode(md5(microtime(TRUE) . 'asdfasdf')), array('+'=>'','/'=>'','='=>'')), 0, 30);
            $this->options[CURLOPT_HTTPHEADER] = array
            (
                'Accept: text/*', 
                'Content-Type: multipart/form-data; boundary=----------' . $boundary, 
                'User-Agent: Shockwave Flash', 
                'Connection: Keep-Alive', 
                'Cache-Control: no-cache'
            );
            
            $headers = array
            (
                '------------' . $boundary,
                'Content-Disposition: form-data; name="Filename"',
                '',
                basename($this->filePath),
                '------------' . $boundary,
                'Content-Disposition: form-data; name="folder_id"',
                '',
                '0',
                '------------' . $boundary,
                'Content-Disposition: form-data; name="SID"',
                '',
                $this->SID,
                '------------' . $boundary,
                'Content-Disposition: form-data; name="file"',
                '',
                $json_obj->file_id,
                '------------' . $boundary,
                'Content-Disposition: form-data; name="file"; filename="' . basename($this->filePath) . '"',
                'Content-Type: application/octet-stream',
                '',
                file_get_contents($this->filePath),
                '------------' . $boundary,
                'Content-Disposition: form-data; name="Upload"',
                '',
                'Submit Query',
                '------------' . $boundary . '--',
            );
            
            return (substr(parent::curly_post(implode("\r\n", $headers), $callback), 0, 3) == 'new' ? $json_obj->link : FALSE);
        }
    }
}