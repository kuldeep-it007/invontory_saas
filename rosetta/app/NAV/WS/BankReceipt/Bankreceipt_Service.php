<?php

namespace Rosetta\NAV\WS\BankReceipt;

use Rosetta\NAV\WS\NavSoapClient;

class Bankreceipt_Service extends NavSoapClient
{

    /**
     * @var array $classmap The defined classes
     */
    private static $classmap = array (
      'bankreceipt' => 'Rosetta\\NAV\\WS\\BankReceipt\\bankreceipt',
      'bankreceipt_List' => 'Rosetta\\NAV\\WS\\BankReceipt\\bankreceipt_List',
      'bankreceipt_Filter' => 'Rosetta\\NAV\\WS\\BankReceipt\\bankreceipt_Filter',
      'ReadByRecId' => 'Rosetta\\NAV\\WS\\BankReceipt\\ReadByRecId',
      'ReadByRecId_Result' => 'Rosetta\\NAV\\WS\\BankReceipt\\ReadByRecId_Result',
      'ReadMultiple' => 'Rosetta\\NAV\\WS\\BankReceipt\\ReadMultiple',
      'ReadMultiple_Result' => 'Rosetta\\NAV\\WS\\BankReceipt\\ReadMultiple_Result',
      'IsUpdated' => 'Rosetta\\NAV\\WS\\BankReceipt\\IsUpdated',
      'IsUpdated_Result' => 'Rosetta\\NAV\\WS\\BankReceipt\\IsUpdated_Result',
      'GetRecIdFromKey' => 'Rosetta\\NAV\\WS\\BankReceipt\\GetRecIdFromKey',
      'GetRecIdFromKey_Result' => 'Rosetta\\NAV\\WS\\BankReceipt\\GetRecIdFromKey_Result',
      'Create' => 'Rosetta\\NAV\\WS\\BankReceipt\\Create',
      'Create_Result' => 'Rosetta\\NAV\\WS\\BankReceipt\\Create_Result',
      'CreateMultiple' => 'Rosetta\\NAV\\WS\\BankReceipt\\CreateMultiple',
      'CreateMultiple_Result' => 'Rosetta\\NAV\\WS\\BankReceipt\\CreateMultiple_Result',
      'Update' => 'Rosetta\\NAV\\WS\\BankReceipt\\Update',
      'Update_Result' => 'Rosetta\\NAV\\WS\\BankReceipt\\Update_Result',
      'UpdateMultiple' => 'Rosetta\\NAV\\WS\\BankReceipt\\UpdateMultiple',
      'UpdateMultiple_Result' => 'Rosetta\\NAV\\WS\\BankReceipt\\UpdateMultiple_Result',
      'Delete' => 'Rosetta\\NAV\\WS\\BankReceipt\\Delete',
      'Delete_Result' => 'Rosetta\\NAV\\WS\\BankReceipt\\Delete_Result',
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
     * @param Create $parameters
     * @return Create_Result
     */
    public function Create(Create $parameters)
    {
      return $this->__soapCall('Create', array($parameters));
    }

    /**
     * @param CreateMultiple $parameters
     * @return CreateMultiple_Result
     */
    public function CreateMultiple(CreateMultiple $parameters)
    {
      return $this->__soapCall('CreateMultiple', array($parameters));
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

    /**
     * @param Delete $parameters
     * @return Delete_Result
     */
    public function Delete(Delete $parameters)
    {
      return $this->__soapCall('Delete', array($parameters));
    }

}
