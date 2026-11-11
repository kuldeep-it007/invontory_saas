<?php
namespace Rosetta\NAV\WS;

use DOMDocument;

use Illuminate\Support\Facades\Log;

class NavSoapClient extends \SoapClient
{
    public function __construct($wsdl,array $options = array())
    {
        $options['cache_wsdl'] = WSDL_CACHE_NONE;
        $options['trace'] = config('nav.debug');
        parent::__construct(app_path()."/NAV/wsdl/". $wsdl . ".wsdl", $options);
    }
	function __doRequest($request, $location, $action, $version, $one_way = NULL) 
  	{
    //	$location = $this->location;#756
	    $headers = [
      		'Method: POST',
      		'Connection: Keep-Alive',
      		'User-Agent: PHP-SOAP-CURL',
      		'Content-Type: application/xml; charset=utf-8',
      		'SOAPAction: "'.$action.'"',
    		'Expect:',    
    	];
    	$this->__last_request_headers = $headers;
    	$ch = curl_init($location);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    	curl_setopt($ch, CURLOPT_POST, true );
    	curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    	curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    	curl_setopt($ch, CURLOPT_USERPWD, config('nav.login').':'.config('nav.password'));
    	$response = curl_exec($ch);
        if(config('nav.debug'))
        {
			Log::channel('nav')->info("url : " . $location);
            //$log->addInfo("url : " . $location);
            $dom = new DOMDocument;
            $dom->preserveWhiteSpace = FALSE;
            $dom->loadXML($request);
            $dom->formatOutput = TRUE;
			Log::channel('nav')->info("Request :\n " . $dom->saveXML());
            $dom->loadXML($response);
			Log::channel('nav')->info("Response : \n" . $dom->saveXML());
			Log::channel('nav')->info("---------------------------------------------------------------------------");
        }
    	return $response;
  	}
}