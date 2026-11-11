<?php

namespace Rosetta\NAV\WS\ItemCard\Picture;

use Rosetta\NAV\WS\NavSoapClient;

class ItemPicture extends NavSoapClient
{

    /**
     * @var array $classmap The defined classes
     */
    private static $classmap = array (
      'SetItemPicture' => 'Rosetta\\NAV\\WS\\ItemCard\\Picture\\SetItemPicture',
      'SetItemPicture_Result' => 'Rosetta\\NAV\\WS\\ItemCard\\Picture\\SetItemPicture_Result',
    );

    /**
     * @param array $options A array of config values
     * @param string $wsdl The wsdl file to use
     */
    public function __construct(array $options = array(), $wsdl = null)
    {
      foreach (self::$classmap as $key => $value) {
        if (!isset($options['classmap'][$key])) {
          $options['classmap'][$key] = $value;
        }
      }
      $options = array_merge(array (
      'connection_timeout' => 60,
    ), $options);
      parent::__construct($wsdl, $options);
    }

    /**
     * @param SetItemPicture $parameters
     * @return SetItemPicture_Result
     */
    public function SetItemPicture(SetItemPicture $parameters)
    {
      return $this->__soapCall('SetItemPicture', array($parameters));
    }

}
