<?php

namespace Rosetta\NAV\WS\VATProductPostingGroup;
use Rosetta\NAV\WS\NavSoapClient;
class VAT_Product_Posting_Groups_Service extends NavSoapClient
{

    /**
     * @var array $classmap The defined classes
     */
    private static $classmap = array (
      'VAT_Product_Posting_Groups' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\VAT_Product_Posting_Groups',
      'VAT_Product_Posting_Groups_List' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\VAT_Product_Posting_Groups_List',
      'VAT_Product_Posting_Groups_Filter' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\VAT_Product_Posting_Groups_Filter',
      'Read' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\Read',
      'Read_Result' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\Read_Result',
      'ReadByRecId' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\ReadByRecId',
      'ReadByRecId_Result' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\ReadByRecId_Result',
      'ReadMultiple' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\ReadMultiple',
      'ReadMultiple_Result' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\ReadMultiple_Result',
      'IsUpdated' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\IsUpdated',
      'IsUpdated_Result' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\IsUpdated_Result',
      'GetRecIdFromKey' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\GetRecIdFromKey',
      'GetRecIdFromKey_Result' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\GetRecIdFromKey_Result',
      'Create' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\Create',
      'Create_Result' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\Create_Result',
      'CreateMultiple' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\CreateMultiple',
      'CreateMultiple_Result' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\CreateMultiple_Result',
      'Update' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\Update',
      'Update_Result' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\Update_Result',
      'UpdateMultiple' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\UpdateMultiple',
      'UpdateMultiple_Result' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\UpdateMultiple_Result',
      'Delete' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\Delete',
      'Delete_Result' => 'Rosetta\\NAV\\WS\\VATProductPostingGroup\\Delete_Result',
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
