<?php

namespace Rosetta\NAV\WS\PendingPurchaseOrders;
use Rosetta\NAV\WS\NavSoapClient;

class Pending_Purchase_Orders_Service extends NavSoapClient
{

    /**
     * @var array $classmap The defined classes
     */
    private static $classmap = array (
      'Pending_Purchase_Orders' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\Pending_Purchase_Orders',
      'Pending_Purchase_Orders_List' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\Pending_Purchase_Orders_List',
      'Pending_Purchase_Orders_Filter' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\Pending_Purchase_Orders_Filter',
      'Read' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\Read',
      'Read_Result' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\Read_Result',
      'ReadByRecId' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\ReadByRecId',
      'ReadByRecId_Result' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\ReadByRecId_Result',
      'ReadMultiple' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\ReadMultiple',
      'ReadMultiple_Result' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\ReadMultiple_Result',
      'IsUpdated' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\IsUpdated',
      'IsUpdated_Result' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\IsUpdated_Result',
      'GetRecIdFromKey' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\GetRecIdFromKey',
      'GetRecIdFromKey_Result' => 'Rosetta\\NAV\\WS\\PendingPurchaseOrders\\GetRecIdFromKey_Result',
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
        $wsdl = 'pending_purchase_orders.wsdl';
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

}
