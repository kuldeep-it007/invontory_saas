<?php

namespace Rosetta\NAV\WS\PSI;

use Rosetta\NAV\WS\NavSoapClient;

class PSI_Service extends NavSoapClient
{

    /**
     * @var array $classmap The defined classes
     */
    private static $classmap = array (
      'PSI' => 'Rosetta\\NAV\\WS\\PSI\\PSI',
      'PSI_List' => 'Rosetta\\NAV\\WS\\PSI\\PSI_List',
      'PSI_Filter' => 'Rosetta\\NAV\\WS\\PSI\\PSI_Filter',
      'Read' => 'Rosetta\\NAV\\WS\\PSI\\Read',
      'Read_Result' => 'Rosetta\\NAV\\WS\\PSI\\Read_Result',
      'ReadByRecId' => 'Rosetta\\NAV\\WS\\PSI\\ReadByRecId',
      'ReadByRecId_Result' => 'Rosetta\\NAV\\WS\\PSI\\ReadByRecId_Result',
      'ReadMultiple' => 'Rosetta\\NAV\\WS\\PSI\\ReadMultiple',
      'ReadMultiple_Result' => 'Rosetta\\NAV\\WS\\PSI\\ReadMultiple_Result',
      'IsUpdated' => 'Rosetta\\NAV\\WS\\PSI\\IsUpdated',
      'IsUpdated_Result' => 'Rosetta\\NAV\\WS\\PSI\\IsUpdated_Result',
      'GetRecIdFromKey' => 'Rosetta\\NAV\\WS\\PSI\\GetRecIdFromKey',
      'GetRecIdFromKey_Result' => 'Rosetta\\NAV\\WS\\PSI\\GetRecIdFromKey_Result',
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
