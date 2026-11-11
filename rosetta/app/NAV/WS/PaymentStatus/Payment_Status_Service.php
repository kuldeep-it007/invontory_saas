<?php

namespace Rosetta\NAV\WS\PaymentStatus;
use Rosetta\NAV\WS\NavSoapClient;

class Payment_Status_Service extends NavSoapClient
{

    /**
     * @var array $classmap The defined classes
     */
    private static $classmap = array (
      'Payment_Status' => 'Rosetta\\NAV\\WS\\PaymentStatus\\Payment_Status',
      'Payment_Status_List' => 'Rosetta\\NAV\\WS\\PaymentStatus\\Payment_Status_List',
      'Payment_Status_Filter' => 'Rosetta\\NAV\\WS\\PaymentStatus\\Payment_Status_Filter',
      'ReadByRecId' => 'Rosetta\\NAV\\WS\\PaymentStatus\\ReadByRecId',
      'ReadByRecId_Result' => 'Rosetta\\NAV\\WS\\PaymentStatus\\ReadByRecId_Result',
      'ReadMultiple' => 'Rosetta\\NAV\\WS\\PaymentStatus\\ReadMultiple',
      'ReadMultiple_Result' => 'Rosetta\\NAV\\WS\\PaymentStatus\\ReadMultiple_Result',
      'IsUpdated' => 'Rosetta\\NAV\\WS\\PaymentStatus\\IsUpdated',
      'IsUpdated_Result' => 'Rosetta\\NAV\\WS\\PaymentStatus\\IsUpdated_Result',
      'GetRecIdFromKey' => 'Rosetta\\NAV\\WS\\PaymentStatus\\GetRecIdFromKey',
      'GetRecIdFromKey_Result' => 'Rosetta\\NAV\\WS\\PaymentStatus\\GetRecIdFromKey_Result',
      'Update' => 'Rosetta\\NAV\\WS\\PaymentStatus\\Update',
      'Update_Result' => 'Rosetta\\NAV\\WS\\PaymentStatus\\Update_Result',
      'UpdateMultiple' => 'Rosetta\\NAV\\WS\\PaymentStatus\\UpdateMultiple',
      'UpdateMultiple_Result' => 'Rosetta\\NAV\\WS\\PaymentStatus\\UpdateMultiple_Result',
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
      'features' => 1,
    ), $options);
      if (!$wsdl) {
        $wsdl = 'Payment_Status.wsdl';
      }
      parent::__construct($wsdl, $options);
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

    /**
     * @param Update $parameters
     * @return Update_Result
     */
    public function Update(Update $parameters)
    {
      return $this->__soapCall('Update', array($parameters));
    }

    /**
     * @param UpdateMultiple $parameters
     * @return UpdateMultiple_Result
     */
    public function UpdateMultiple(UpdateMultiple $parameters)
    {
      return $this->__soapCall('UpdateMultiple', array($parameters));
    }

}
