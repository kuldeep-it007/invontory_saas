<?php

namespace Rosetta\NAV\WS\PSSLines;

use Rosetta\NAV\WS\NavSoapClient;

class PSS_Service extends NavSoapClient
{

    /**
     * @var array $classmap The defined classes
     */
    private static $classmap = array (
      'PSS' => 'Rosetta\\NAV\\WS\\PSSLines\\PSS',
      'PSS_List' => 'Rosetta\\NAV\\WS\\PSSLines\\PSS_List',
      'PSS_Filter' => 'Rosetta\\NAV\\WS\\PSSLines\\PSS_Filter',
      'Read' => 'Rosetta\\NAV\\WS\\PSSLines\\Read',
      'Read_Result' => 'Rosetta\\NAV\\WS\\PSSLines\\Read_Result',
      'ReadByRecId' => 'Rosetta\\NAV\\WS\\PSSLines\\ReadByRecId',
      'ReadByRecId_Result' => 'Rosetta\\NAV\\WS\\PSSLines\\ReadByRecId_Result',
      'ReadMultiple' => 'Rosetta\\NAV\\WS\\PSSLines\\ReadMultiple',
      'ReadMultiple_Result' => 'Rosetta\\NAV\\WS\\PSSLines\\ReadMultiple_Result',
      'IsUpdated' => 'Rosetta\\NAV\\WS\\PSSLines\\IsUpdated',
      'IsUpdated_Result' => 'Rosetta\\NAV\\WS\\PSSLines\\IsUpdated_Result',
      'GetRecIdFromKey' => 'Rosetta\\NAV\\WS\\PSSLines\\GetRecIdFromKey',
      'GetRecIdFromKey_Result' => 'Rosetta\\NAV\\WS\\PSSLines\\GetRecIdFromKey_Result',
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
