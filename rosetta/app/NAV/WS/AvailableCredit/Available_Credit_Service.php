<?php

namespace Rosetta\NAV\WS\AvailableCredit;
use Rosetta\NAV\WS\NavSoapClient;

class Available_Credit_Service extends NavSoapClient
{

    /**
     * @var array $classmap The defined classes
     */
    private static $classmap = array (
      'Available_Credit' => 'Rosetta\\NAV\\WS\\AvailableCredit\\Available_Credit',
      'Available_Credit_List' => 'Rosetta\\NAV\\WS\\AvailableCredit\\Available_Credit_List',
      'Available_Credit_Filter' => 'Rosetta\\NAV\\WS\\AvailableCredit\\Available_Credit_Filter',
      'Read' => 'Rosetta\\NAV\\WS\\AvailableCredit\\Read',
      'Read_Result' => 'Rosetta\\NAV\\WS\\AvailableCredit\\Read_Result',
      'ReadByRecId' => 'Rosetta\\NAV\\WS\\AvailableCredit\\ReadByRecId',
      'ReadByRecId_Result' => 'Rosetta\\NAV\\WS\\AvailableCredit\\ReadByRecId_Result',
      'ReadMultiple' => 'Rosetta\\NAV\\WS\\AvailableCredit\\ReadMultiple',
      'ReadMultiple_Result' => 'Rosetta\\NAV\\WS\\AvailableCredit\\ReadMultiple_Result',
      'IsUpdated' => 'Rosetta\\NAV\\WS\\AvailableCredit\\IsUpdated',
      'IsUpdated_Result' => 'Rosetta\\NAV\\WS\\AvailableCredit\\IsUpdated_Result',
      'GetRecIdFromKey' => 'Rosetta\\NAV\\WS\\AvailableCredit\\GetRecIdFromKey',
      'GetRecIdFromKey_Result' => 'Rosetta\\NAV\\WS\\AvailableCredit\\GetRecIdFromKey_Result',
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
     * @param Read $parameters
     * @return Read_Result
     */
    public function Read(Read $parameters)
    {
      return $this->__soapCall('Read', array($parameters));
    }

    /**
     * @param ReadByRecId $parameters
     * @return ReadByRecId_Result
     */
    public function ReadByRecId(ReadByRecId $parameters)
    {
      return $this->__soapCall('ReadByRecId', array($parameters));
    }

    /**
     * @param ReadMultiple $parameters
     * @return ReadMultiple_Result
     */
    public function ReadMultiple(ReadMultiple $parameters)
    {
      return $this->__soapCall('ReadMultiple', array($parameters));
    }

    /**
     * @param IsUpdated $parameters
     * @return IsUpdated_Result
     */
    public function IsUpdated(IsUpdated $parameters)
    {
      return $this->__soapCall('IsUpdated', array($parameters));
    }

    /**
     * @param GetRecIdFromKey $parameters
     * @return GetRecIdFromKey_Result
     */
    public function GetRecIdFromKey(GetRecIdFromKey $parameters)
    {
      return $this->__soapCall('GetRecIdFromKey', array($parameters));
    }

}
