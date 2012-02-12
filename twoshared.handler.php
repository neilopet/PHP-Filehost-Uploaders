<?php

require_once 'AbstractCurly.class.php';

/**
 * ~Id: Twoshared.handler.php
 * @author  hedonist@privacyharbor.com
 * @package mirrormint
 * 
 * Usage:
 * ------------------------------------
*/
 
interface TwosharedInterface
{
    public function Upload( $callback ); // Upload file
}

final class Twoshared extends AbstractCurly implements TwosharedInterface
{
    private
        $sessionId,
        $filePath;
        
    function __construct( $path_to_file )
    {
        if (file_exists($path_to_file))
            $this->filePath = $path_to_file;
    }
    
    public function Upload( $callback )
    {
        $this->sessionId = substr(strtr(base64_encode(md5(microtime(TRUE))), array('+'=>'','/'=>'','='=>'')), 0, 16);
        // initiate session
        file_get_contents('http://2shared.com/uploadComplete.jsp?sId=' . $this->sessionId);
        
        parent::curly_init('http://dc187.2shared.com/main/upload2.jsp?sId=' . $this->sessionId);
        if (preg_match('/Your upload has successfully completed!/', parent::curly_post(array(
            'fff'    => "@{$this->filePath};filename=" . basename($this->filePath),
            'mainDC' => '282'
        ), $callback)))
        {
            /*
        <textarea cols="20" rows="2" name="downloadLink" id="downloadLink" readonly="readonly" onclick="this.focus();this.select()" style="width:468px">http://www.2shared.com/document/H9ueae5_/test.html</textarea><br />
            */
            if (preg_match('/>http:\/\/www.2shared.com\/(.*)<\/textarea>/i', file_get_contents('http://2shared.com/uploadComplete.jsp?sId=' . $this->sessionId), $matches))
            {
                return 'http://www.2shared.com/' . $matches[1];
            }
        }
        return FALSE;
    }
}