<?php

require_once 'AbstractCurly.class.php';

/** 
 * Oron
 * An object used to upload files to Oron.com
 * @author Neil Opet <neil.opet@gmail.com>
 */
 
interface OronInterface
{
    public function Upload( $callback ); // Upload file
}

final class Oron extends AbstractCurly implements OronInterface
{
    private
        $sessionId,
        $filePath;
        
    function __construct( $session, $path_to_file )
    {
        if (file_exists($path_to_file))
            $this->filePath = $path_to_file;
        $this->sessionId = $session;
    }
    
    public function Upload( $callback )
    {
        $uploadID = number_format(microtime(TRUE), 0, '', '');
        file_get_contents('http://zeta.oron.com/status.html?file=' . $uploadID . '=' . basename($this->filePath));
        parent::curly_init('http://zeta.oron.com/upload/375/?X-Progress-ID=' . $uploadID);
        if (preg_match("/'fn' value='(.*)'><input/", parent::curly_post(array(
            'upload_type' => 'file',
            'srv_id'      => '375',
            'sess_id'     => $this->sessionId,
            'srv_tmp_url' => 'http://zeta.oron.com',
            'file_0'      => "@{$this->filePath};filename=" . basename($this->filePath),
            'ut'          => 'file',
            'tos'         => '1',
            'submit_btn'  => ' Upload!'
        ), $callback), $matches))
        {
            return 'http://oron.com/' . $matches[1];
        }
        return FALSE;
    }
}