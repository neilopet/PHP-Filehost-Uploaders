<?php

/**
 * AbstractCurly
 * 
 * @package   mirrormint 
 * @author    hedonist@privacyharbor.com
 * @copyright 2012 mirrormint.com
 * @version   0.0.1 
 * @access    public
 */
 
abstract class AbstractCurly
{
    protected 
        $url,
        $options,
        $callback;
        
    /**
     * AbstractCurly::curly_init()
     * 
     * @param string $url
     * @return void
     */
    function curly_init( $url )
    {
        if (!filter_var($url, FILTER_VALIDATE_URL))
            die('URL not valid.');
        $this->url     = $url; 
        $this->options = array
        (
            CURLOPT_HEADER         => FALSE, 
            CURLOPT_RETURNTRANSFER => TRUE, 
            CURLOPT_NOPROGRESS     => FALSE,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1',
            CURLOPT_HTTPHEADER     => array('Expect:'),
            CURLOPT_FRESH_CONNECT  => TRUE
        );
    }
    
    /**
     * AbstractCurly::curly_exec()
     * 
     * @return string $Response
     */
    function curly_exec()
    {
        $ch       = curl_init();
        $callback = $this->callback;
        
        # every request will have this callback
        $curl = function($a, $b, $fileSize, $uploaded) use ($callback) {
            $pct = 0;
            if ($uploaded != 0)
            {
                $pct = floor(($uploaded / $fileSize) * 100);
                call_user_func($callback, $pct);
            }
        };
        $this->options[CURLOPT_PROGRESSFUNCTION] = $curl;
        curl_setopt_array($ch, $this->options);
        $ret = curl_exec($ch);
        //var_dump(curl_getinfo($ch));
        curl_close($ch);
        return $ret;
    }
    
    /**
     * AbstractCurly::curly_post()
     * 
     * @param mixed $postFields
     * @param callback $callback
     * @return string $Response
     */
    function curly_post( $postFields, $callback )
    {
        $this->callback = $callback;
        $this->options += array
        (
            CURLOPT_URL              => $this->url,
            CURLOPT_POST             => TRUE,
            CURLOPT_POSTFIELDS       => $postFields,
            CURLINFO_HEADER_OUT      => TRUE,
            
        );
        return $this->curly_exec();
    }
    
    /**
     * AbstractCurly::curly_put()
     * 
     * @param mixed $postFields
     * @param callback $callback
     * @return string $Response
     */
    function curly_put( $infile, $infilesize, $callback )
    {
        $this->callback = $callback;
        $this->options += array
        (
            CURLOPT_URL              => $this->url,
            CURLOPT_PUT              => TRUE,
            CURLOPT_INFILE           => $infile,
            CURLOPT_INFILESIZE       => $infilesize            
        );
        return $this->curly_exec();
    }
    
    /**
     * AbstractCurly::curly_get()
     * 
     * @param array $getParams
     * @param callback $callback
     * @return string $Response
     */
    function curly_get( array $getParams, $callback )
    {
        $this->callback = $callback;
        $this->options += array
        (
            CURLOPT_URL              => $this->url . '?' . http_build_query($getParams),
            CURLOPT_POST             => FALSE
        );
        return $this->curly_exec();
    }
    
    /**
     * AbstractCurly::curly_ftp()
     * 
     * @param string $infile
     * @param string $infilesize
     * @param callback $callback
     * @return
     */
    function curly_ftp( $infile, $infilesize, $callback )
    {
        $this->callback = $callback;
        $this->options += array
        (
            CURLOPT_URL              => $this->url,
            CURLOPT_UPLOAD           => TRUE,
            CURLOPT_INFILE           => $infile,
            CURLOPT_INFILESIZE       => $infilesize
        );
        return $this->curly_exec();
    }
}