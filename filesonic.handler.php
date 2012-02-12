<?php

require_once 'AbstractCurly.class.php';

/** 
 * Filesonic
 * An object used to upload files to Filesonic.com
 * @author Neil Opet <neil.opet@gmail.com>
 */
 
interface FilesonicInterface
{
    public function getUploadUrl( /* void */ ); // Authenticate & retrieve upload url
    public function Upload( $callback );       // Upload file to the url retrieved by getUploadUrl
}

final class Filesonic extends AbstractCurly implements FilesonicInterface
{
    private
        $filePath,
        $username,
        $password;
    
    const URI_API = 'http://api.filesonic.com/upload?method=%s';
    
    function __construct( $username, $password, $path_to_file )
    {
        // Check that file exists and is within permitted directory
        if (!file_exists($path_to_file))
            die('Invalid file specified.');
        // The username must be an e-mail
        if (!filter_var($username, FILTER_VALIDATE_EMAIL))
            die('Invalid e-mail provided.');    
        
        $this->filePath = $path_to_file;
        $this->username = $username;
        $this->password = md5(md5($password) . $username);
    }
    
    private function AuthenticateParams()
    {
        return '&' . http_build_query(array('u' => $this->username, 'p' => $this->password));
    }
    
    /**
     * Filesonic::getUploadUrl()
     * 
     * @return (string) $URL || (bool) FALSE
     */
    final function getUploadUrl()
    {    
        $result = json_decode(file_get_contents(sprintf(self::URI_API, 'getUploadUrl') . $this->AuthenticateParams()));
        return ( 
            (isset($result->FSApi_Upload->getUploadUrl->status) && $result->FSApi_Upload->getUploadUrl->status == 'success') 
                ? $result->FSApi_Upload->getUploadUrl->response->url 
                : FALSE
        );   
    }
    
    /**
     * Filesonic::Upload()
     * 
     * @param callback $callback
     * @return (string) $Download_URL || (bool) FALSE
     */
    final function Upload( $callback )
    {
        // Must authenticate and get Upload URI
        parent::curly_init( $this->getUploadUrl() );
        
        $response = json_decode(parent::curly_post(array(
            'files[]' => "@{$this->filePath}"
        ), $callback));
        
        return ((isset($response->FSApi_Upload->postFile->status) && $response->FSApi_Upload->postFile->status == 'success')
            ? $response->FSApi_Upload->postFile->response->files[0]->url
            : FALSE
        );
    }
}