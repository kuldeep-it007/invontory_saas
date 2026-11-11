<?php

namespace Rosetta\NAV\WS\ItemCardIntransit;
use Rosetta\NAV\WS\NavSoapClient;

class Item_Card_Intransit_Service extends NavSoapClient
{

    /**
     * @var array $classmap The defined classes
     */
    private static $classmap = array (
      'Item_Card_Intransit' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\Item_Card_Intransit',
      'Item_Card_Intransit_List' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\Item_Card_Intransit_List',
      'Item_Card_Intransit_Filter' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\Item_Card_Intransit_Filter',
      'Read' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\Read',
      'Read_Result' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\Read_Result',
      'ReadByRecId' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\ReadByRecId',
      'ReadByRecId_Result' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\ReadByRecId_Result',
      'ReadMultiple' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\ReadMultiple',
      'ReadMultiple_Result' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\ReadMultiple_Result',
      'IsUpdated' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\IsUpdated',
      'IsUpdated_Result' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\IsUpdated_Result',
      'GetRecIdFromKey' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\GetRecIdFromKey',
      'GetRecIdFromKey_Result' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\GetRecIdFromKey_Result',
      'Create' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\Create',
      'Create_Result' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\Create_Result',
      'CreateMultiple' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\CreateMultiple',
      'CreateMultiple_Result' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\CreateMultiple_Result',
      'Update' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\Update',
      'Update_Result' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\Update_Result',
      'UpdateMultiple' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\UpdateMultiple',
      'UpdateMultiple_Result' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\UpdateMultiple_Result',
      'Delete' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\Delete',
      'Delete_Result' => 'Rosetta\\NAV\\WS\\ItemCardIntransit\\Delete_Result',
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
        $wsdl = 'Item_Card_Intransit.wsdl';
      }
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
