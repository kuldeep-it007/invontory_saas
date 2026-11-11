<?php

namespace Rosetta\NAV\WS\ItemCard\SalesPrice;

class Read
{

    /**
     * @var string $Item_No
     */
    protected $Item_No = null;

    /**
     * @var string $Sales_Type
     */
    protected $Sales_Type = null;

    /**
     * @var string $Sales_Code
     */
    protected $Sales_Code = null;

    /**
     * @var date $Starting_Date
     */
    protected $Starting_Date = null;

    /**
     * @var string $Currency_Code
     */
    protected $Currency_Code = null;

    /**
     * @var string $Variant_Code
     */
    protected $Variant_Code = null;

    /**
     * @var string $Unit_of_Measure_Code
     */
    protected $Unit_of_Measure_Code = null;

    /**
     * @var float $Minimum_Quantity
     */
    protected $Minimum_Quantity = null;

    /**
     * @var string $Rate_Type
     */
    protected $Rate_Type = null;

    /**
     * @param string $Item_No
     * @param string $Sales_Type
     * @param string $Sales_Code
     * @param date $Starting_Date
     * @param string $Currency_Code
     * @param string $Variant_Code
     * @param string $Unit_of_Measure_Code
     * @param float $Minimum_Quantity
     * @param string $Rate_Type
     */
    public function __construct($Item_No, $Sales_Type, $Sales_Code, $Starting_Date, $Currency_Code, $Variant_Code, $Unit_of_Measure_Code, $Minimum_Quantity, $Rate_Type)
    {
      $this->Item_No = $Item_No;
      $this->Sales_Type = $Sales_Type;
      $this->Sales_Code = $Sales_Code;
      $this->Starting_Date = $Starting_Date;
      $this->Currency_Code = $Currency_Code;
      $this->Variant_Code = $Variant_Code;
      $this->Unit_of_Measure_Code = $Unit_of_Measure_Code;
      $this->Minimum_Quantity = $Minimum_Quantity;
      $this->Rate_Type = $Rate_Type;
    }

    /**
     * @return string
     */
    public function getItem_No()
    {
      return $this->Item_No;
    }

    /**
     * @param string $Item_No
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\Read
     */
    public function setItem_No($Item_No)
    {
      $this->Item_No = $Item_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getSales_Type()
    {
      return $this->Sales_Type;
    }

    /**
     * @param string $Sales_Type
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\Read
     */
    public function setSales_Type($Sales_Type)
    {
      $this->Sales_Type = $Sales_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getSales_Code()
    {
      return $this->Sales_Code;
    }

    /**
     * @param string $Sales_Code
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\Read
     */
    public function setSales_Code($Sales_Code)
    {
      $this->Sales_Code = $Sales_Code;
      return $this;
    }

    /**
     * @return date
     */
    public function getStarting_Date()
    {
      return $this->Starting_Date;
    }

    /**
     * @param date $Starting_Date
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\Read
     */
    public function setStarting_Date($Starting_Date)
    {
      $this->Starting_Date = $Starting_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getCurrency_Code()
    {
      return $this->Currency_Code;
    }

    /**
     * @param string $Currency_Code
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\Read
     */
    public function setCurrency_Code($Currency_Code)
    {
      $this->Currency_Code = $Currency_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getVariant_Code()
    {
      return $this->Variant_Code;
    }

    /**
     * @param string $Variant_Code
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\Read
     */
    public function setVariant_Code($Variant_Code)
    {
      $this->Variant_Code = $Variant_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getUnit_of_Measure_Code()
    {
      return $this->Unit_of_Measure_Code;
    }

    /**
     * @param string $Unit_of_Measure_Code
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\Read
     */
    public function setUnit_of_Measure_Code($Unit_of_Measure_Code)
    {
      $this->Unit_of_Measure_Code = $Unit_of_Measure_Code;
      return $this;
    }

    /**
     * @return float
     */
    public function getMinimum_Quantity()
    {
      return $this->Minimum_Quantity;
    }

    /**
     * @param float $Minimum_Quantity
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\Read
     */
    public function setMinimum_Quantity($Minimum_Quantity)
    {
      $this->Minimum_Quantity = $Minimum_Quantity;
      return $this;
    }

    /**
     * @return string
     */
    public function getRate_Type()
    {
      return $this->Rate_Type;
    }

    /**
     * @param string $Rate_Type
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\Read
     */
    public function setRate_Type($Rate_Type)
    {
      $this->Rate_Type = $Rate_Type;
      return $this;
    }

}
