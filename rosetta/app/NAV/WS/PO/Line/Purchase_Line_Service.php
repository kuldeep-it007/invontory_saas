<?php

namespace Rosetta\NAV\WS\PO\Line;

use Rosetta\NAV\WS\NavSoapClient;

class Purchase_Line_Service extends NavSoapClient
{

    /**
     * @var array $classmap The defined classes
     */
    private static $classmap = array (
      'Purchase_Line' => 'Rosetta\\NAV\\WS\\PO\\Line\\Purchase_Line',
      'Purchase_Line_List' => 'Rosetta\\NAV\\WS\\PO\\Line\\Purchase_Line_List',
      'Purchase_Line_Filter' => 'Rosetta\\NAV\\WS\\PO\\Line\\Purchase_Line_Filter',
      'ReadByRecId' => 'Rosetta\\NAV\\WS\\PO\\Line\\ReadByRecId',
      'ReadByRecId_Result' => 'Rosetta\\NAV\\WS\\PO\\Line\\ReadByRecId_Result',
      'ReadMultiple' => 'Rosetta\\NAV\\WS\\PO\\Line\\ReadMultiple',
      'ReadMultiple_Result' => 'Rosetta\\NAV\\WS\\PO\\Line\\ReadMultiple_Result',
      'IsUpdated' => 'Rosetta\\NAV\\WS\\PO\\Line\\IsUpdated',
      'IsUpdated_Result' => 'Rosetta\\NAV\\WS\\PO\\Line\\IsUpdated_Result',
      'GetRecIdFromKey' => 'Rosetta\\NAV\\WS\\PO\\Line\\GetRecIdFromKey',
      'GetRecIdFromKey_Result' => 'Rosetta\\NAV\\WS\\PO\\Line\\GetRecIdFromKey_Result',
      'Create' => 'Rosetta\\NAV\\WS\\PO\\Line\\Create',
      'Create_Result' => 'Rosetta\\NAV\\WS\\PO\\Line\\Create_Result',
      'CreateMultiple' => 'Rosetta\\NAV\\WS\\PO\\Line\\CreateMultiple',
      'CreateMultiple_Result' => 'Rosetta\\NAV\\WS\\PO\\Line\\CreateMultiple_Result',
      'Update' => 'Rosetta\\NAV\\WS\\PO\\Line\\Update',
      'Update_Result' => 'Rosetta\\NAV\\WS\\PO\\Line\\Update_Result',
      'UpdateMultiple' => 'Rosetta\\NAV\\WS\\PO\\Line\\UpdateMultiple',
      'UpdateMultiple_Result' => 'Rosetta\\NAV\\WS\\PO\\Line\\UpdateMultiple_Result',
      'Delete' => 'Rosetta\\NAV\\WS\\PO\\Line\\Delete',
      'Delete_Result' => 'Rosetta\\NAV\\WS\\PO\\Line\\Delete_Result',
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
