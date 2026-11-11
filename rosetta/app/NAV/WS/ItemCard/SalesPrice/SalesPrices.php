<?php

namespace Rosetta\NAV\WS\ItemCard\SalesPrice;

class SalesPrices
{

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @var string $SalesCodeFilterCtrl
     */
    protected $SalesCodeFilterCtrl = null;

    /**
     * @var string $ItemNoFilterCtrl
     */
    protected $ItemNoFilterCtrl = null;

    /**
     * @var string $StartingDateFilter
     */
    protected $StartingDateFilter = null;

    /**
     * @var string $SalesCodeFilterCtrl2
     */
    protected $SalesCodeFilterCtrl2 = null;

    /**
     * @var Sales_Type $Sales_Type
     */
    protected $Sales_Type = null;

    /**
     * @var string $Sales_Code
     */
    protected $Sales_Code = null;

    /**
     * @var string $Item_No
     */
    protected $Item_No = null;

    /**
     * @var string $_x003C_Description_x003E_
     */
    protected $_x003C_Description_x003E_ = null;

    /**
     * @var string $Variant_Code
     */
    protected $Variant_Code = null;

    /**
     * @var string $Currency_Code
     */
    protected $Currency_Code = null;

    /**
     * @var string $Unit_of_Measure_Code
     */
    protected $Unit_of_Measure_Code = null;

    /**
     * @var float $Minimum_Quantity
     */
    protected $Minimum_Quantity = null;

    /**
     * @var Rate_Type $Rate_Type
     */
    protected $Rate_Type = null;

    /**
     * @var float $Unit_Price
     */
    protected $Unit_Price = null;

    /**
     * @var date $Starting_Date
     */
    protected $Starting_Date = null;

    /**
     * @var date $Ending_Date
     */
    protected $Ending_Date = null;

    /**
     * @var boolean $Price_Includes_VAT
     */
    protected $Price_Includes_VAT = null;

    /**
     * @var boolean $Price_Inclusive_of_Tax
     */
    protected $Price_Inclusive_of_Tax = null;

    /**
     * @var boolean $MRP
     */
    protected $MRP = null;

    /**
     * @var float $MRP_Price
     */
    protected $MRP_Price = null;

    /**
     * @var float $Abatement_Percent
     */
    protected $Abatement_Percent = null;

    /**
     * @var string $PIT_Structure
     */
    protected $PIT_Structure = null;

    /**
     * @var boolean $Allow_Line_Disc
     */
    protected $Allow_Line_Disc = null;

    /**
     * @var boolean $Allow_Invoice_Disc
     */
    protected $Allow_Invoice_Disc = null;

    /**
     * @var string $VAT_Bus_Posting_Gr_Price
     */
    protected $VAT_Bus_Posting_Gr_Price = null;

    
    public function __construct()
    {
    
    }

    /**
     * @return string
     */
    public function getKey()
    {
      return $this->Key;
    }

    /**
     * @param string $Key
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
      return $this;
    }

    /**
     * @return string
     */
    public function getSalesCodeFilterCtrl()
    {
      return $this->SalesCodeFilterCtrl;
    }

    /**
     * @param string $SalesCodeFilterCtrl
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setSalesCodeFilterCtrl($SalesCodeFilterCtrl)
    {
      $this->SalesCodeFilterCtrl = $SalesCodeFilterCtrl;
      return $this;
    }

    /**
     * @return string
     */
    public function getItemNoFilterCtrl()
    {
      return $this->ItemNoFilterCtrl;
    }

    /**
     * @param string $ItemNoFilterCtrl
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setItemNoFilterCtrl($ItemNoFilterCtrl)
    {
      $this->ItemNoFilterCtrl = $ItemNoFilterCtrl;
      return $this;
    }

    /**
     * @return string
     */
    public function getStartingDateFilter()
    {
      return $this->StartingDateFilter;
    }

    /**
     * @param string $StartingDateFilter
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setStartingDateFilter($StartingDateFilter)
    {
      $this->StartingDateFilter = $StartingDateFilter;
      return $this;
    }

    /**
     * @return string
     */
    public function getSalesCodeFilterCtrl2()
    {
      return $this->SalesCodeFilterCtrl2;
    }

    /**
     * @param string $SalesCodeFilterCtrl2
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setSalesCodeFilterCtrl2($SalesCodeFilterCtrl2)
    {
      $this->SalesCodeFilterCtrl2 = $SalesCodeFilterCtrl2;
      return $this;
    }

    /**
     * @return Sales_Type
     */
    public function getSales_Type()
    {
      return $this->Sales_Type;
    }

    /**
     * @param Sales_Type $Sales_Type
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
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
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setSales_Code($Sales_Code)
    {
      $this->Sales_Code = $Sales_Code;
      return $this;
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
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setItem_No($Item_No)
    {
      $this->Item_No = $Item_No;
      return $this;
    }

    /**
     * @return string
     */
    public function get_x003C_Description_x003E_()
    {
      return $this->_x003C_Description_x003E_;
    }

    /**
     * @param string $_x003C_Description_x003E_
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function set_x003C_Description_x003E_($_x003C_Description_x003E_)
    {
      $this->_x003C_Description_x003E_ = $_x003C_Description_x003E_;
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
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setVariant_Code($Variant_Code)
    {
      $this->Variant_Code = $Variant_Code;
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
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setCurrency_Code($Currency_Code)
    {
      $this->Currency_Code = $Currency_Code;
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
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
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
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setMinimum_Quantity($Minimum_Quantity)
    {
      $this->Minimum_Quantity = $Minimum_Quantity;
      return $this;
    }

    /**
     * @return Rate_Type
     */
    public function getRate_Type()
    {
      return $this->Rate_Type;
    }

    /**
     * @param Rate_Type $Rate_Type
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setRate_Type($Rate_Type)
    {
      $this->Rate_Type = $Rate_Type;
      return $this;
    }

    /**
     * @return float
     */
    public function getUnit_Price()
    {
      return $this->Unit_Price;
    }

    /**
     * @param float $Unit_Price
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setUnit_Price($Unit_Price)
    {
      $this->Unit_Price = $Unit_Price;
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
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setStarting_Date($Starting_Date)
    {
      $this->Starting_Date = $Starting_Date;
      return $this;
    }

    /**
     * @return date
     */
    public function getEnding_Date()
    {
      return $this->Ending_Date;
    }

    /**
     * @param date $Ending_Date
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setEnding_Date($Ending_Date)
    {
      $this->Ending_Date = $Ending_Date;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getPrice_Includes_VAT()
    {
      return $this->Price_Includes_VAT;
    }

    /**
     * @param boolean $Price_Includes_VAT
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setPrice_Includes_VAT($Price_Includes_VAT)
    {
      $this->Price_Includes_VAT = $Price_Includes_VAT;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getPrice_Inclusive_of_Tax()
    {
      return $this->Price_Inclusive_of_Tax;
    }

    /**
     * @param boolean $Price_Inclusive_of_Tax
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setPrice_Inclusive_of_Tax($Price_Inclusive_of_Tax)
    {
      $this->Price_Inclusive_of_Tax = $Price_Inclusive_of_Tax;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getMRP()
    {
      return $this->MRP;
    }

    /**
     * @param boolean $MRP
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setMRP($MRP)
    {
      $this->MRP = $MRP;
      return $this;
    }

    /**
     * @return float
     */
    public function getMRP_Price()
    {
      return $this->MRP_Price;
    }

    /**
     * @param float $MRP_Price
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setMRP_Price($MRP_Price)
    {
      $this->MRP_Price = $MRP_Price;
      return $this;
    }

    /**
     * @return float
     */
    public function getAbatement_Percent()
    {
      return $this->Abatement_Percent;
    }

    /**
     * @param float $Abatement_Percent
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setAbatement_Percent($Abatement_Percent)
    {
      $this->Abatement_Percent = $Abatement_Percent;
      return $this;
    }

    /**
     * @return string
     */
    public function getPIT_Structure()
    {
      return $this->PIT_Structure;
    }

    /**
     * @param string $PIT_Structure
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setPIT_Structure($PIT_Structure)
    {
      $this->PIT_Structure = $PIT_Structure;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getAllow_Line_Disc()
    {
      return $this->Allow_Line_Disc;
    }

    /**
     * @param boolean $Allow_Line_Disc
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setAllow_Line_Disc($Allow_Line_Disc)
    {
      $this->Allow_Line_Disc = $Allow_Line_Disc;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getAllow_Invoice_Disc()
    {
      return $this->Allow_Invoice_Disc;
    }

    /**
     * @param boolean $Allow_Invoice_Disc
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setAllow_Invoice_Disc($Allow_Invoice_Disc)
    {
      $this->Allow_Invoice_Disc = $Allow_Invoice_Disc;
      return $this;
    }

    /**
     * @return string
     */
    public function getVAT_Bus_Posting_Gr_Price()
    {
      return $this->VAT_Bus_Posting_Gr_Price;
    }

    /**
     * @param string $VAT_Bus_Posting_Gr_Price
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices
     */
    public function setVAT_Bus_Posting_Gr_Price($VAT_Bus_Posting_Gr_Price)
    {
      $this->VAT_Bus_Posting_Gr_Price = $VAT_Bus_Posting_Gr_Price;
      return $this;
    }

}
