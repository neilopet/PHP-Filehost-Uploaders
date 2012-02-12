<?php

require_once 'AbstractCurly.class.php';

/** 
 * Depositfiles
 * An object used to upload files to Depositfiles.com
 * @author Neil Opet <neil.opet@gmail.com>
 */
 
interface DepositfilesInterface
{
    public function Upload( $callback ); // Upload file
}

final class Depositfiles extends AbstractCurly implements DepositfilesInterface
{
    private
        $upload_url,
        $upload_id,
        $session,
        $filePath;
        
    function __construct( $session, $path_to_file )
    {
        if (file_exists($path_to_file))
            $this->filePath = $path_to_file;
        $this->session = $session;
        $this->upload_id = time();
        $this->GenID(); 
    }
    
    private function GenID()
    {
        $chars = '1234567890qwertyuiopasdfghjklzxcvbnm';
        for ($i = 1; $i <= 32; $i++)
        {
            $this->upload_id .= $chars[mt_rand(0, 35)];
        }
    }
    
    public function Upload( $callback )
    {
        // get upload stuff
        //<form target="uploadframe" id="upload_form"
        parent::curly_init('http://depositfiles.com/');
        $this->options[CURLOPT_HTTPHEADER] = array
        (
            'Cookie: autologin=' . $this->session
        );
        $response = parent::curly_get(array(''=>''), function(){});
        if (preg_match('/<form target="uploadframe" id="upload_form" method="post" enctype="multipart\/form-data" action="(?<upload_url>.*)\/\?X-Progress-ID=(.*)" onsubmit="return check_form\(\)">/', $response, $matches))
        {
            $this->upload_url = isset($matches['upload_url']) ? ($matches['upload_url'] . '/?X-Progress-ID=' . $this->upload_id) : NULL;
        }
        
        if (!isset($this->upload_url, $this->upload_id))
            die('No upload URL or ID returned.');
            
        parent::curly_init($this->upload_url);
        $this->options[CURLOPT_HTTPHEADER] = array
        (
            'Cookie: autologin=' . $this->session,
            'Connection: keep-alive',
            'Referer: http://depositfiles.com/'
        );
        if (preg_match("/parent.ud_download_url = '(.*)';/", parent::curly_post(array(
            'MAX_FILE_SIZE'     => '2097152000',
            'UPLOAD_IDENTIFIER' => $this->upload_id,
            'go'                => '1',
            'files'             => "@{$this->filePath};filename=" . basename($this->filePath),
            'agree'             => '1',
            'padding'           => '                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        '
        ), $callback), $matches))
        {
            return $matches[1];
        }
        
    }
}