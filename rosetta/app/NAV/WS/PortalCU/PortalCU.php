<?php

namespace Rosetta\NAV\WS\PortalCU;

use Rosetta\NAV\WS\NavSoapClient;

class PortalCU extends NavSoapClient
{

    /**
     * @var array $classmap The defined classes
     */
    private static $classmap = array (
      'GenerateOC' => 'Rosetta\\NAV\\WS\\PortalCU\\GenerateOC',
      'GenerateOC_Result' => 'Rosetta\\NAV\\WS\\PortalCU\\GenerateOC_Result',
      'GenerateSalesInv' => 'Rosetta\\NAV\\WS\\PortalCU\\GenerateSalesInv',
      'GenerateSalesInv_Result' => 'Rosetta\\NAV\\WS\\PortalCU\\GenerateSalesInv_Result',
      'InsertTrackingSpecification' => 'Rosetta\\NAV\\WS\\PortalCU\\InsertTrackingSpecification',
      'InsertTrackingSpecification_Result' => 'Rosetta\\NAV\\WS\\PortalCU\\InsertTrackingSpecification_Result',
      'InsertReservation' => 'Rosetta\\NAV\\WS\\PortalCU\\InsertReservation',
      'InsertReservation_Result' => 'Rosetta\\NAV\\WS\\PortalCU\\InsertReservation_Result',
      'GeneratePO' => 'Rosetta\\NAV\\WS\\PortalCU\\GeneratePO',
      'GeneratePO_Result' => 'Rosetta\\NAV\\WS\\PortalCU\\GeneratePO_Result',
      'InsertTrackingSpecificationPur' => 'Rosetta\\NAV\\WS\\PortalCU\\InsertTrackingSpecificationPur',
      'InsertTrackingSpecificationPur_Result' => 'Rosetta\\NAV\\WS\\PortalCU\\InsertTrackingSpecificationPur_Result',
      'EditSO' => 'Rosetta\\NAV\\WS\\PortalCU\\EditSO',
      'EditSO_Result' => 'Rosetta\\NAV\\WS\\PortalCU\\EditSO_Result',
      'DeleteSOLine' => 'Rosetta\\NAV\\WS\\PortalCU\\DeleteSOLine',
      'DeleteSOLine_Result' => 'Rosetta\\NAV\\WS\\PortalCU\\DeleteSOLine_Result',
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
     * @param GenerateOC $parameters
     * @return GenerateOC_Result
     */
    public function GenerateOC(GenerateOC $parameters)
    {
      return $this->__soapCall('GenerateOC', array($parameters));
    }

    /**
     * @param GenerateSalesInv $parameters
     * @return GenerateSalesInv_Result
     */
    public function GenerateSalesInv(GenerateSalesInv $parameters)
    {
      return $this->__soapCall('GenerateSalesInv', array($parameters));
    }

    /**
     * @param InsertTrackingSpecification $parameters
     * @return InsertTrackingSpecification_Result
     */
    public function InsertTrackingSpecification(InsertTrackingSpecification $parameters)
    {
      return $this->__soapCall('InsertTrackingSpecification', array($parameters));
    }

    /**
     * @param InsertReservation $parameters
     * @return InsertReservation_Result
     */
    public function InsertReservation(InsertReservation $parameters)
    {
      return $this->__soapCall('InsertReservation', array($parameters));
    }

    /**
     * @param GeneratePO $parameters
     * @return GeneratePO_Result
     */
    public function GeneratePO(GeneratePO $parameters)
    {
      return $this->__soapCall('GeneratePO', array($parameters));
    }

    /**
     * @param InsertTrackingSpecificationPur $parameters
     * @return InsertTrackingSpecificationPur_Result
     */
    public function InsertTrackingSpecificationPur(InsertTrackingSpecificationPur $parameters)
    {
      return $this->__soapCall('InsertTrackingSpecificationPur', array($parameters));
    }

    /**
     * @param EditSO $parameters
     * @return EditSO_Result
     */
    public function EditSO(EditSO $parameters)
    {
      return $this->__soapCall('EditSO', array($parameters));
    }


    /**
     * @param DeleteSOLine $parameters
     * @return DeleteSOLine_Result
     */
    public function DeleteSOLine(DeleteSOLine $parameters)
    {
      return $this->__soapCall('DeleteSOLine', array($parameters));
    }
}
