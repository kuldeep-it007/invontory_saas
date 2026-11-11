<?php

namespace Rosetta\NAV\WS\ItemCardIntransit;

class Item_Card_Intransit
{

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @var string $No
     */
    protected $No = null;

    /**
     * @var string $Description
     */
    protected $Description = null;

    /**
     * @var string $Description_2
     */
    protected $Description_2 = null;

    /**
     * @var string $Quality
     */
    protected $Quality = null;

    /**
     * @var string $Base_Unit_of_Measure
     */
    protected $Base_Unit_of_Measure = null;

    /**
     * @var boolean $Assembly_BOM
     */
    protected $Assembly_BOM = null;

    /**
     * @var float $Unit_Price
     */
    protected $Unit_Price = null;

    /**
     * @var string $Shelf_No
     */
    protected $Shelf_No = null;

    /**
     * @var string $Purch_Unit_of_Measure
     */
    protected $Purch_Unit_of_Measure = null;

    /**
     * @var boolean $Automatic_Ext_Texts
     */
    protected $Automatic_Ext_Texts = null;

    /**
     * @var string $Sales_Unit_of_Measure
     */
    protected $Sales_Unit_of_Measure = null;

    /**
     * @var boolean $Created_From_Nonstock_Item
     */
    protected $Created_From_Nonstock_Item = null;

    /**
     * @var string $Item_Category_Code
     */
    protected $Item_Category_Code = null;

    /**
     * @var string $Product_Group_Code
     */
    protected $Product_Group_Code = null;

    /**
     * @var string $Colour
     */
    protected $Colour = null;

    /**
     * @var string $Technical_Spec
     */
    protected $Technical_Spec = null;

    /**
     * @var string $Search_Description
     */
    protected $Search_Description = null;

    /**
     * @var float $Inventory
     */
    protected $Inventory = null;

    /**
     * @var float $SO_Qty_on_wearhouse
     */
    protected $SO_Qty_on_wearhouse = null;

    /**
     * @var float $Qty_on_Purch_Order
     */
    protected $Qty_on_Purch_Order = null;

    /**
     * @var float $Qty_on_Prod_Order
     */
    protected $Qty_on_Prod_Order = null;

    /**
     * @var float $Qty_on_Component_Lines
     */
    protected $Qty_on_Component_Lines = null;

    /**
     * @var float $Qty_on_Sales_Order
     */
    protected $Qty_on_Sales_Order = null;

    /**
     * @var float $Reserved_Qty_on_Sales_Orders
     */
    protected $Reserved_Qty_on_Sales_Orders = null;

    /**
     * @var float $Avg_Sale_in_Last_3_Months
     */
    protected $Avg_Sale_in_Last_3_Months = null;

    /**
     * @var float $Avg_Sale_in_Last_6_Months
     */
    protected $Avg_Sale_in_Last_6_Months = null;

    /**
     * @var float $Avg_Sale_in_Last_12_Months
     */
    protected $Avg_Sale_in_Last_12_Months = null;

    /**
     * @var float $Transit_Stock
     */
    protected $Transit_Stock = null;

    /**
     * @var float $Ready_at_Factory
     */
    protected $Ready_at_Factory = null;

    /**
     * @var boolean $Blocked
     */
    protected $Blocked = null;

    /**
     * @var date $Last_Date_Modified
     */
    protected $Last_Date_Modified = null;

    /**
     * @var StockoutWarningDefaultYes $StockoutWarningDefaultYes
     */
    protected $StockoutWarningDefaultYes = null;

    /**
     * @var PreventNegInventoryDefaultYes $PreventNegInventoryDefaultYes
     */
    protected $PreventNegInventoryDefaultYes = null;

    /**
     * @var string $Item_Tracking_Code
     */
    protected $Item_Tracking_Code = null;

    /**
     * @var string $Serial_Nos
     */
    protected $Serial_Nos = null;

    /**
     * @var string $Source_No
     */
    protected $Source_No = null;

    /**
     * @var string $Special_Equipment_Code
     */
    protected $Special_Equipment_Code = null;

    /**
     * @var string $Global_Dimension_1_Code
     */
    protected $Global_Dimension_1_Code = null;

    /**
     * @var string $Global_Dimension_2_Code
     */
    protected $Global_Dimension_2_Code = null;

    /**
     * @var float $MRP_Price
     */
    protected $MRP_Price = null;

    /**
     * @var boolean $MRP_Value
     */
    protected $MRP_Value = null;

    /**
     * @var Costing_Method $Costing_Method
     */
    protected $Costing_Method = null;

    /**
     * @var boolean $Cost_is_Adjusted
     */
    protected $Cost_is_Adjusted = null;

    /**
     * @var boolean $Cost_is_Posted_to_G_L
     */
    protected $Cost_is_Posted_to_G_L = null;

    /**
     * @var float $Standard_Cost
     */
    protected $Standard_Cost = null;

    /**
     * @var float $Unit_Cost
     */
    protected $Unit_Cost = null;

    /**
     * @var float $Overhead_Rate
     */
    protected $Overhead_Rate = null;

    /**
     * @var float $Indirect_Cost_Percent
     */
    protected $Indirect_Cost_Percent = null;

    /**
     * @var float $Last_Direct_Cost
     */
    protected $Last_Direct_Cost = null;

    /**
     * @var Price_Profit_Calculation $Price_Profit_Calculation
     */
    protected $Price_Profit_Calculation = null;

    /**
     * @var float $Profit_Percent
     */
    protected $Profit_Percent = null;

    /**
     * @var string $Gen_Prod_Posting_Group
     */
    protected $Gen_Prod_Posting_Group = null;

    /**
     * @var string $VAT_Prod_Posting_Group
     */
    protected $VAT_Prod_Posting_Group = null;

    /**
     * @var string $Excise_Prod_Posting_Group
     */
    protected $Excise_Prod_Posting_Group = null;

    /**
     * @var string $Tax_Group_Code
     */
    protected $Tax_Group_Code = null;

    /**
     * @var string $GST_Group_Code
     */
    protected $GST_Group_Code = null;

    /**
     * @var GST_Credit $GST_Credit
     */
    protected $GST_Credit = null;

    /**
     * @var float $VAT_If_applicable
     */
    protected $VAT_If_applicable = null;

    /**
     * @var string $HSN_SAC_Code
     */
    protected $HSN_SAC_Code = null;

    /**
     * @var boolean $Exempted
     */
    protected $Exempted = null;

    /**
     * @var string $Inventory_Posting_Group
     */
    protected $Inventory_Posting_Group = null;

    /**
     * @var string $Default_Deferral_Template_Code
     */
    protected $Default_Deferral_Template_Code = null;

    /**
     * @var float $Net_Invoiced_Qty
     */
    protected $Net_Invoiced_Qty = null;

    /**
     * @var boolean $Allow_Invoice_Disc
     */
    protected $Allow_Invoice_Disc = null;

    /**
     * @var string $Item_Disc_Group
     */
    protected $Item_Disc_Group = null;

    /**
     * @var string $Application_Wksh_User_ID
     */
    protected $Application_Wksh_User_ID = null;

    /**
     * @var Replenishment_System $Replenishment_System
     */
    protected $Replenishment_System = null;

    /**
     * @var string $Lead_Time_Calculation
     */
    protected $Lead_Time_Calculation = null;

    /**
     * @var string $Vendor_No
     */
    protected $Vendor_No = null;

    /**
     * @var string $Vendor_Item_No
     */
    protected $Vendor_Item_No = null;

    /**
     * @var Manufacturing_Policy $Manufacturing_Policy
     */
    protected $Manufacturing_Policy = null;

    /**
     * @var string $Routing_No
     */
    protected $Routing_No = null;

    /**
     * @var string $Production_BOM_No
     */
    protected $Production_BOM_No = null;

    /**
     * @var float $Rounding_Precision
     */
    protected $Rounding_Precision = null;

    /**
     * @var Flushing_Method $Flushing_Method
     */
    protected $Flushing_Method = null;

    /**
     * @var float $Scrap_Percent
     */
    protected $Scrap_Percent = null;

    /**
     * @var float $Lot_Size
     */
    protected $Lot_Size = null;

    /**
     * @var Assembly_Policy $Assembly_Policy
     */
    protected $Assembly_Policy = null;

    /**
     * @var Reordering_Policy $Reordering_Policy
     */
    protected $Reordering_Policy = null;

    /**
     * @var Reserve $Reserve
     */
    protected $Reserve = null;

    /**
     * @var Order_Tracking_Policy $Order_Tracking_Policy
     */
    protected $Order_Tracking_Policy = null;

    /**
     * @var boolean $Stockkeeping_Unit_Exists
     */
    protected $Stockkeeping_Unit_Exists = null;

    /**
     * @var string $Dampener_Period
     */
    protected $Dampener_Period = null;

    /**
     * @var float $Dampener_Quantity
     */
    protected $Dampener_Quantity = null;

    /**
     * @var boolean $Critical
     */
    protected $Critical = null;

    /**
     * @var string $Safety_Lead_Time
     */
    protected $Safety_Lead_Time = null;

    /**
     * @var float $Safety_Stock_Quantity
     */
    protected $Safety_Stock_Quantity = null;

    /**
     * @var boolean $Include_Inventory
     */
    protected $Include_Inventory = null;

    /**
     * @var string $Lot_Accumulation_Period
     */
    protected $Lot_Accumulation_Period = null;

    /**
     * @var string $Rescheduling_Period
     */
    protected $Rescheduling_Period = null;

    /**
     * @var float $Reorder_Point
     */
    protected $Reorder_Point = null;

    /**
     * @var float $Reorder_Quantity
     */
    protected $Reorder_Quantity = null;

    /**
     * @var float $Maximum_Inventory
     */
    protected $Maximum_Inventory = null;

    /**
     * @var float $Overflow_Level
     */
    protected $Overflow_Level = null;

    /**
     * @var string $Time_Bucket
     */
    protected $Time_Bucket = null;

    /**
     * @var float $Minimum_Order_Quantity
     */
    protected $Minimum_Order_Quantity = null;

    /**
     * @var float $Maximum_Order_Quantity
     */
    protected $Maximum_Order_Quantity = null;

    /**
     * @var float $Order_Multiple
     */
    protected $Order_Multiple = null;

    /**
     * @var string $GTIN
     */
    protected $GTIN = null;

    /**
     * @var string $Tariff_No
     */
    protected $Tariff_No = null;

    /**
     * @var string $Country_Region_of_Origin_Code
     */
    protected $Country_Region_of_Origin_Code = null;

    /**
     * @var float $Net_Weight
     */
    protected $Net_Weight = null;

    /**
     * @var float $Gross_Weight
     */
    protected $Gross_Weight = null;

    /**
     * @var string $Lot_Nos
     */
    protected $Lot_Nos = null;

    /**
     * @var string $Expiration_Calculation
     */
    protected $Expiration_Calculation = null;

    /**
     * @var string $Put_away_Template_Code
     */
    protected $Put_away_Template_Code = null;

    /**
     * @var string $Put_away_Unit_of_Measure_Code
     */
    protected $Put_away_Unit_of_Measure_Code = null;

    /**
     * @var string $Phys_Invt_Counting_Period_Code
     */
    protected $Phys_Invt_Counting_Period_Code = null;

    /**
     * @var date $Last_Phys_Invt_Date
     */
    protected $Last_Phys_Invt_Date = null;

    /**
     * @var date $Last_Counting_Period_Update
     */
    protected $Last_Counting_Period_Update = null;

    /**
     * @var date $Next_Counting_Start_Date
     */
    protected $Next_Counting_Start_Date = null;

    /**
     * @var date $Next_Counting_End_Date
     */
    protected $Next_Counting_End_Date = null;

    /**
     * @var string $Identifier_Code
     */
    protected $Identifier_Code = null;

    /**
     * @var boolean $Use_Cross_Docking
     */
    protected $Use_Cross_Docking = null;

    /**
     * @var float $Abatement_Percent
     */
    protected $Abatement_Percent = null;

    /**
     * @var boolean $Price_Inclusive_of_Tax
     */
    protected $Price_Inclusive_of_Tax = null;

    /**
     * @var string $PIT_Structure
     */
    protected $PIT_Structure = null;

    /**
     * @var boolean $Subcontracting
     */
    protected $Subcontracting = null;

    /**
     * @var string $Sub_Comp_Location
     */
    protected $Sub_Comp_Location = null;

    /**
     * @var boolean $Scrap_Item
     */
    protected $Scrap_Item = null;

    /**
     * @var boolean $Capital_Item
     */
    protected $Capital_Item = null;

    /**
     * @var Excise_Accounting_Type $Excise_Accounting_Type
     */
    protected $Excise_Accounting_Type = null;

    /**
     * @var float $Assessable_Value
     */
    protected $Assessable_Value = null;

    /**
     * @var boolean $Fixed_Asset
     */
    protected $Fixed_Asset = null;

    /**
     * @var float $WAREHOUSE_Inventory
     */
    protected $WAREHOUSE_Inventory = null;

    /**
     * @var float $WAREHOUSE_SO_Qty_Adv
     */
    protected $WAREHOUSE_SO_Qty_Adv = null;

    /**
     * @var float $WAREHOUSE_SO_Qty
     */
    protected $WAREHOUSE_SO_Qty = null;

    /**
     * @var float $WAREHOUSE_PO_Qty
     */
    protected $WAREHOUSE_PO_Qty = null;

    /**
     * @var float $MUM_WARE_Inventory
     */
    protected $MUM_WARE_Inventory = null;

    /**
     * @var float $MUM_WARE_SO_Qty_Adv
     */
    protected $MUM_WARE_SO_Qty_Adv = null;

    /**
     * @var float $MUM_WARE_SO_Qty
     */
    protected $MUM_WARE_SO_Qty = null;

    /**
     * @var float $MUM_WARE_PO_Qty
     */
    protected $MUM_WARE_PO_Qty = null;

    /**
     * @var float $KRONO_MUM_Inventory
     */
    protected $KRONO_MUM_Inventory = null;

    /**
     * @var float $KRONO_MUM_SO_Qty_Adv
     */
    protected $KRONO_MUM_SO_Qty_Adv = null;

    /**
     * @var float $KRONO_MUM_SO_Qty
     */
    protected $KRONO_MUM_SO_Qty = null;

    /**
     * @var float $KRONO_MUM_PO_Qty
     */
    protected $KRONO_MUM_PO_Qty = null;

    /**
     * @var float $PUNE_DEP_2_Inventory
     */
    protected $PUNE_DEP_2_Inventory = null;

    /**
     * @var float $PUNE_DEP_2_SO_Qty_Adv
     */
    protected $PUNE_DEP_2_SO_Qty_Adv = null;

    /**
     * @var float $PUNE_DEP_2_SO_Qty
     */
    protected $PUNE_DEP_2_SO_Qty = null;

    /**
     * @var float $PUNE_DEP_2_PO_Qty
     */
    protected $PUNE_DEP_2_PO_Qty = null;

    /**
     * @var float $CS2_Inventory
     */
    protected $CS2_Inventory = null;

    /**
     * @var float $CS2_SO_Qty_Adv
     */
    protected $CS2_SO_Qty_Adv = null;

    /**
     * @var float $CS2_SO_Qty
     */
    protected $CS2_SO_Qty = null;

    /**
     * @var float $CS2_PO_Qty
     */
    protected $CS2_PO_Qty = null;

    /**
     * @var float $Inventory_In_Transit
     */
    protected $Inventory_In_Transit = null;

    /**
     * @var float $RGALLERY_Inventory
     */
    protected $RGALLERY_Inventory = null;

    /**
     * @var float $RGALLERY_SO_Qty_Adv
     */
    protected $RGALLERY_SO_Qty_Adv = null;

    /**
     * @var float $RGALLERY_SO_Qty
     */
    protected $RGALLERY_SO_Qty = null;

    /**
     * @var float $RGALLERY_PO_Qty
     */
    protected $RGALLERY_PO_Qty = null;

    /**
     * @var float $AMAZONBOM5_Inventory
     */
    protected $AMAZONBOM5_Inventory = null;

    /**
     * @var float $AMAZONBOM5_SO_Qty_Adv
     */
    protected $AMAZONBOM5_SO_Qty_Adv = null;

    /**
     * @var float $AMAZONBOM5_SO_Qty
     */
    protected $AMAZONBOM5_SO_Qty = null;

    /**
     * @var float $AMAZONBOM5_PO_Qty
     */
    protected $AMAZONBOM5_PO_Qty = null;

    /**
     * @var float $SBU3_Inventory
     */
    protected $SBU3_Inventory = null;

    /**
     * @var float $SBU3_SO_Qty
     */
    protected $SBU3_SO_Qty = null;

    /**
     * @var float $SBU3_SO_Qty_Adv
     */
    protected $SBU3_SO_Qty_Adv = null;

    /**
     * @var float $SBU3_PO_Qty
     */
    protected $SBU3_PO_Qty = null;

    /**
     * @var float $AMAZONBLR8_Inventory
     */
    protected $AMAZONBLR8_Inventory = null;

    /**
     * @var float $AMAZONBLR8_SO_Qty_Adv
     */
    protected $AMAZONBLR8_SO_Qty_Adv = null;

    /**
     * @var float $AMAZONBLR8_SO_Qty
     */
    protected $AMAZONBLR8_SO_Qty = null;

    /**
     * @var float $AMAZONBLR8_PO_Qty
     */
    protected $AMAZONBLR8_PO_Qty = null;

    /**
     * @var float $KRONO_BLR_Inventory
     */
    protected $KRONO_BLR_Inventory = null;

    /**
     * @var float $KRONO_BLR_SO_Qty_Adv
     */
    protected $KRONO_BLR_SO_Qty_Adv = null;

    /**
     * @var float $KRONO_BLR_SO_Qty
     */
    protected $KRONO_BLR_SO_Qty = null;

    /**
     * @var float $KRONO_BLR_PO_Qty
     */
    protected $KRONO_BLR_PO_Qty = null;

    /**
     * @var float $AMAZONBLR7_Inventory
     */
    protected $AMAZONBLR7_Inventory = null;

    /**
     * @var float $AMAZONBLR7_SO_Qty_Adv
     */
    protected $AMAZONBLR7_SO_Qty_Adv = null;

    /**
     * @var float $AMAZONBLR7_SO_Qty
     */
    protected $AMAZONBLR7_SO_Qty = null;

    /**
     * @var float $AMAZONBLR7_PO_Qty
     */
    protected $AMAZONBLR7_PO_Qty = null;

    /**
     * @var float $KRONO_PUNE_PO_Qty
     */
    protected $KRONO_PUNE_PO_Qty = null;

    /**
     * @var float $KRONO_PUNE_SO_Qty
     */
    protected $KRONO_PUNE_SO_Qty = null;

    /**
     * @var float $KRONO_PUNE_SO_Qty_Adv
     */
    protected $KRONO_PUNE_SO_Qty_Adv = null;

    /**
     * @var float $KRONO_PUNE_Inventory
     */
    protected $KRONO_PUNE_Inventory = null;

    /**
     * @var float $DISTRIBUTI_Inventory
     */
    protected $DISTRIBUTI_Inventory = null;

    /**
     * @var float $DISTRIBUTI_SO_Qty
     */
    protected $DISTRIBUTI_SO_Qty = null;

    /**
     * @var float $DISTRIBUTI_PO_Qty
     */
    protected $DISTRIBUTI_PO_Qty = null;

    /**
     * @var string $DISTRIBUTI_SO_Qty_Adv
     */
    protected $DISTRIBUTI_SO_Qty_Adv = null;

    /**
     * @var float $PRMDISTRI_Inventory
     */
    protected $PRMDISTRI_Inventory = null;

    /**
     * @var float $PRMDISTRI_SO_Qty
     */
    protected $PRMDISTRI_SO_Qty = null;

    /**
     * @var float $PRMDISTRI_PO_Qty
     */
    protected $PRMDISTRI_PO_Qty = null;

    /**
     * @var string $PRMDISTRI_SO_Qty_Adv
     */
    protected $PRMDISTRI_SO_Qty_Adv = null;

    /**
     * @var float $PROJECTS_Inventory
     */
    protected $PROJECTS_Inventory = null;

    /**
     * @var float $PROJECTS_SO_Qty
     */
    protected $PROJECTS_SO_Qty = null;

    /**
     * @var float $PROJECTS_PO_Qty
     */
    protected $PROJECTS_PO_Qty = null;

    /**
     * @var string $PROJECTS_SO_Qty_Adv
     */
    protected $PROJECTS_SO_Qty_Adv = null;

    /**
     * @var float $CASHNCARRY_Inventory
     */
    protected $CASHNCARRY_Inventory = null;

    /**
     * @var float $AMAZON02_Inventory
     */
    protected $AMAZON02_Inventory = null;

    /**
     * @var float $AMAZON02_SO_Qty
     */
    protected $AMAZON02_SO_Qty = null;

    /**
     * @var float $AMAZON02_PO_Qty
     */
    protected $AMAZON02_PO_Qty = null;

    /**
     * @var float $AMAZONBOM7_Inventory
     */
    protected $AMAZONBOM7_Inventory = null;

    /**
     * @var string $AMAZON02_SO_Qty_Adv
     */
    protected $AMAZON02_SO_Qty_Adv = null;

    /**
     * @var float $AMAZONBOM7_SO_Qty
     */
    protected $AMAZONBOM7_SO_Qty = null;

    /**
     * @var float $AMAZONBOM7_PO_Qty
     */
    protected $AMAZONBOM7_PO_Qty = null;

    /**
     * @var string $AMAZONBOM7_SO_Qty_Adv
     */
    protected $AMAZONBOM7_SO_Qty_Adv = null;

    /**
     * @var float $NOONDXB07_Inventory
     */
    protected $NOONDXB07_Inventory = null;

    /**
     * @var float $NOONDXB07_SO_Qty
     */
    protected $NOONDXB07_SO_Qty = null;

    /**
     * @var float $NOONDXB07_PO_Qty
     */
    protected $NOONDXB07_PO_Qty = null;

    /**
     * @var string $NOONDXB07_SO_Qty_Adv
     */
    protected $NOONDXB07_SO_Qty_Adv = null;

    /**
     * @var float $CASHNCARRY_SO_Qty
     */
    protected $CASHNCARRY_SO_Qty = null;

    /**
     * @var float $CASHNCARRY_PO_Qty
     */
    protected $CASHNCARRY_PO_Qty = null;

    /**
     * @var string $CASHNCARRY_SO_Qty_Adv
     */
    protected $CASHNCARRY_SO_Qty_Adv = null;

    /**
     * @var float $BANGALORE_SO_Qty
     */
    protected $BANGALORE_SO_Qty = null;

    /**
     * @var float $BANGALORE_Inventory
     */
    protected $BANGALORE_Inventory = null;

    /**
     * @var string $BANGALORE_SO_Qty_Adv
     */
    protected $BANGALORE_SO_Qty_Adv = null;

    /**
     * @var float $BANGALORE_PO_Qty
     */
    protected $BANGALORE_PO_Qty = null;

    /**
     * @var float $ECOMMERCE_Inventory
     */
    protected $ECOMMERCE_Inventory = null;

    /**
     * @var float $ECOMMERCE_SO_Qty
     */
    protected $ECOMMERCE_SO_Qty = null;

    /**
     * @var float $ECOMMERCE_PO_Qty
     */
    protected $ECOMMERCE_PO_Qty = null;

    /**
     * @var string $ECOMMERCE_SO_Qty_Adv
     */
    protected $ECOMMERCE_SO_Qty_Adv = null;

    /**
     * @var float $MUMBAI_PUN_Inventory
     */
    protected $MUMBAI_PUN_Inventory = null;

    /**
     * @var float $MUMBAI_PUN_SO_Qty
     */
    protected $MUMBAI_PUN_SO_Qty = null;

    /**
     * @var float $MUMBAI_PUN_PO_Qty
     */
    protected $MUMBAI_PUN_PO_Qty = null;

    /**
     * @var string $MUMBAI_PUN_SO_Qty_Adv
     */
    protected $MUMBAI_PUN_SO_Qty_Adv = null;

    /**
     * @var float $MUMBAI_SHO_Inventory
     */
    protected $MUMBAI_SHO_Inventory = null;

    /**
     * @var float $MUMBAI_SHO_SO_Qty
     */
    protected $MUMBAI_SHO_SO_Qty = null;

    /**
     * @var string $MUMBAI_SHO_SO_Qty_Adv
     */
    protected $MUMBAI_SHO_SO_Qty_Adv = null;

    /**
     * @var float $MUMBAI_SHO_PO_Qty
     */
    protected $MUMBAI_SHO_PO_Qty = null;

    /**
     * @var boolean $IntransitBlocked
     */
    protected $IntransitBlocked = null;

    /**
     * @var float $HOMESTORE_Inventory
     */
    protected $HOMESTORE_Inventory = null;  
    
    /**
     * @var float $HOMESTORE_SO_QTY
     */
    protected $HOMESTORE_SO_QTY = null;

        /**
     * @var float $HOMESTORE_SO_QTY_Inv
     */
    protected $HOMESTORE_SO_QTY_Inv = null;

        /**
     * @var float $HOMESTORE_SO_QTY
     */
    protected $HOMESTORE_PO_QTY = null;
    /**
     * @var float $HOMESTORE_SO_Qty_Adv
     */
    protected $HOMESTORE_SO_Qty_Adv = null;    

        /**
     * @var float $BHIWANDI_Inventory
     */
    protected $BHIWANDI_Inventory = null;

    /**
     * @var float $BHIWANDI_SO_Qty
     */
    protected $BHIWANDI_SO_Qty = null;

        /**
     * @var float $BHIWANDI_PO_Qty
     */
    protected $BHIWANDI_PO_Qty = null;

        /**
     * @var float $BHIWANDI_SO_Qty_Adv
     */
    protected $BHIWANDI_SO_Qty_Adv = null;
    
        /**
     * @var float $locationSpecificStock
     */
    protected $locationSpecificStock = [];    
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
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
      return $this;
    }

    /**
     * @return string
     */
    public function getNo()
    {
      return $this->No;
    }

    /**
     * @param string $No
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setNo($No)
    {
      $this->No = $No;
      return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
      return $this->Description;
    }

    /**
     * @param string $Description
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
      return $this;
    }

    /**
     * @return string
     */
    public function getDescription_2()
    {
      return $this->Description_2;
    }

    /**
     * @param string $Description_2
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setDescription_2($Description_2)
    {
      $this->Description_2 = $Description_2;
      return $this;
    }

    /**
     * @return string
     */
    public function getQuality()
    {
      return $this->Quality;
    }

    /**
     * @param string $Quality
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setQuality($Quality)
    {
      $this->Quality = $Quality;
      return $this;
    }

    /**
     * @return string
     */
    public function getBase_Unit_of_Measure()
    {
      return $this->Base_Unit_of_Measure;
    }

    /**
     * @param string $Base_Unit_of_Measure
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setBase_Unit_of_Measure($Base_Unit_of_Measure)
    {
      $this->Base_Unit_of_Measure = $Base_Unit_of_Measure;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getAssembly_BOM()
    {
      return $this->Assembly_BOM;
    }

    /**
     * @param boolean $Assembly_BOM
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAssembly_BOM($Assembly_BOM)
    {
      $this->Assembly_BOM = $Assembly_BOM;
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
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setUnit_Price($Unit_Price)
    {
      $this->Unit_Price = $Unit_Price;
      return $this;
    }

    /**
     * @return string
     */
    public function getShelf_No()
    {
      return $this->Shelf_No;
    }

    /**
     * @param string $Shelf_No
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setShelf_No($Shelf_No)
    {
      $this->Shelf_No = $Shelf_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getPurch_Unit_of_Measure()
    {
      return $this->Purch_Unit_of_Measure;
    }

    /**
     * @param string $Purch_Unit_of_Measure
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPurch_Unit_of_Measure($Purch_Unit_of_Measure)
    {
      $this->Purch_Unit_of_Measure = $Purch_Unit_of_Measure;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getAutomatic_Ext_Texts()
    {
      return $this->Automatic_Ext_Texts;
    }

    /**
     * @param boolean $Automatic_Ext_Texts
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAutomatic_Ext_Texts($Automatic_Ext_Texts)
    {
      $this->Automatic_Ext_Texts = $Automatic_Ext_Texts;
      return $this;
    }

    /**
     * @return string
     */
    public function getSales_Unit_of_Measure()
    {
      return $this->Sales_Unit_of_Measure;
    }

    /**
     * @param string $Sales_Unit_of_Measure
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSales_Unit_of_Measure($Sales_Unit_of_Measure)
    {
      $this->Sales_Unit_of_Measure = $Sales_Unit_of_Measure;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getCreated_From_Nonstock_Item()
    {
      return $this->Created_From_Nonstock_Item;
    }

    /**
     * @param boolean $Created_From_Nonstock_Item
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCreated_From_Nonstock_Item($Created_From_Nonstock_Item)
    {
      $this->Created_From_Nonstock_Item = $Created_From_Nonstock_Item;
      return $this;
    }

    /**
     * @return string
     */
    public function getItem_Category_Code()
    {
      return $this->Item_Category_Code;
    }

    /**
     * @param string $Item_Category_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setItem_Category_Code($Item_Category_Code)
    {
      $this->Item_Category_Code = $Item_Category_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getProduct_Group_Code()
    {
      return $this->Product_Group_Code;
    }

    /**
     * @param string $Product_Group_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setProduct_Group_Code($Product_Group_Code)
    {
      $this->Product_Group_Code = $Product_Group_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getColour()
    {
      return $this->Colour;
    }

    /**
     * @param string $Colour
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setColour($Colour)
    {
      $this->Colour = $Colour;
      return $this;
    }

    /**
     * @return string
     */
    public function getTechnical_Spec()
    {
      return $this->Technical_Spec;
    }

    /**
     * @param string $Technical_Spec
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setTechnical_Spec($Technical_Spec)
    {
      $this->Technical_Spec = $Technical_Spec;
      return $this;
    }

    /**
     * @return string
     */
    public function getSearch_Description()
    {
      return $this->Search_Description;
    }

    /**
     * @param string $Search_Description
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSearch_Description($Search_Description)
    {
      $this->Search_Description = $Search_Description;
      return $this;
    }

    /**
     * @return float
     */
    public function getInventory()
    {
      return $this->Inventory;
    }

    /**
     * @param float $Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setInventory($Inventory)
    {
      $this->Inventory = $Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getSO_Qty_on_wearhouse()
    {
      return $this->SO_Qty_on_wearhouse;
    }

    /**
     * @param float $SO_Qty_on_wearhouse
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSO_Qty_on_wearhouse($SO_Qty_on_wearhouse)
    {
      $this->SO_Qty_on_wearhouse = $SO_Qty_on_wearhouse;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_on_Purch_Order()
    {
      return $this->Qty_on_Purch_Order;
    }

    /**
     * @param float $Qty_on_Purch_Order
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setQty_on_Purch_Order($Qty_on_Purch_Order)
    {
      $this->Qty_on_Purch_Order = $Qty_on_Purch_Order;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_on_Prod_Order()
    {
      return $this->Qty_on_Prod_Order;
    }

    /**
     * @param float $Qty_on_Prod_Order
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setQty_on_Prod_Order($Qty_on_Prod_Order)
    {
      $this->Qty_on_Prod_Order = $Qty_on_Prod_Order;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_on_Component_Lines()
    {
      return $this->Qty_on_Component_Lines;
    }

    /**
     * @param float $Qty_on_Component_Lines
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setQty_on_Component_Lines($Qty_on_Component_Lines)
    {
      $this->Qty_on_Component_Lines = $Qty_on_Component_Lines;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_on_Sales_Order()
    {
      return $this->Qty_on_Sales_Order;
    }

    /**
     * @param float $Qty_on_Sales_Order
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setQty_on_Sales_Order($Qty_on_Sales_Order)
    {
      $this->Qty_on_Sales_Order = $Qty_on_Sales_Order;
      return $this;
    }

    /**
     * @return float
     */
    public function getReserved_Qty_on_Sales_Orders()
    {
      return $this->Reserved_Qty_on_Sales_Orders;
    }

    /**
     * @param float $Reserved_Qty_on_Sales_Orders
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setReserved_Qty_on_Sales_Orders($Reserved_Qty_on_Sales_Orders)
    {
      $this->Reserved_Qty_on_Sales_Orders = $Reserved_Qty_on_Sales_Orders;
      return $this;
    }

    /**
     * @return float
     */
    public function getAvg_Sale_in_Last_3_Months()
    {
      return $this->Avg_Sale_in_Last_3_Months;
    }

    /**
     * @param float $Avg_Sale_in_Last_3_Months
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAvg_Sale_in_Last_3_Months($Avg_Sale_in_Last_3_Months)
    {
      $this->Avg_Sale_in_Last_3_Months = $Avg_Sale_in_Last_3_Months;
      return $this;
    }

    /**
     * @return float
     */
    public function getAvg_Sale_in_Last_6_Months()
    {
      return $this->Avg_Sale_in_Last_6_Months;
    }

    /**
     * @param float $Avg_Sale_in_Last_6_Months
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAvg_Sale_in_Last_6_Months($Avg_Sale_in_Last_6_Months)
    {
      $this->Avg_Sale_in_Last_6_Months = $Avg_Sale_in_Last_6_Months;
      return $this;
    }

    /**
     * @return float
     */
    public function getAvg_Sale_in_Last_12_Months()
    {
      return $this->Avg_Sale_in_Last_12_Months;
    }

    /**
     * @param float $Avg_Sale_in_Last_12_Months
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAvg_Sale_in_Last_12_Months($Avg_Sale_in_Last_12_Months)
    {
      $this->Avg_Sale_in_Last_12_Months = $Avg_Sale_in_Last_12_Months;
      return $this;
    }

    /**
     * @return float
     */
    public function getTransit_Stock()
    {
      return $this->Transit_Stock;
    }

    /**
     * @param float $Transit_Stock
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setTransit_Stock($Transit_Stock)
    {
      $this->Transit_Stock = $Transit_Stock;
      return $this;
    }

    /**
     * @return float
     */
    public function getReady_at_Factory()
    {
      return $this->Ready_at_Factory;
    }

    /**
     * @param float $Ready_at_Factory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setReady_at_Factory($Ready_at_Factory)
    {
      $this->Ready_at_Factory = $Ready_at_Factory;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getBlocked()
    {
      return $this->Blocked;
    }

    /**
     * @param boolean $Blocked
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setBlocked($Blocked)
    {
      $this->Blocked = $Blocked;
      return $this;
    }

    /**
     * @return date
     */
    public function getLast_Date_Modified()
    {
      return $this->Last_Date_Modified;
    }

    /**
     * @param date $Last_Date_Modified
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setLast_Date_Modified($Last_Date_Modified)
    {
      $this->Last_Date_Modified = $Last_Date_Modified;
      return $this;
    }

    /**
     * @return StockoutWarningDefaultYes
     */
    public function getStockoutWarningDefaultYes()
    {
      return $this->StockoutWarningDefaultYes;
    }

    /**
     * @param StockoutWarningDefaultYes $StockoutWarningDefaultYes
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setStockoutWarningDefaultYes($StockoutWarningDefaultYes)
    {
      $this->StockoutWarningDefaultYes = $StockoutWarningDefaultYes;
      return $this;
    }

    /**
     * @return PreventNegInventoryDefaultYes
     */
    public function getPreventNegInventoryDefaultYes()
    {
      return $this->PreventNegInventoryDefaultYes;
    }

    /**
     * @param PreventNegInventoryDefaultYes $PreventNegInventoryDefaultYes
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPreventNegInventoryDefaultYes($PreventNegInventoryDefaultYes)
    {
      $this->PreventNegInventoryDefaultYes = $PreventNegInventoryDefaultYes;
      return $this;
    }

    /**
     * @return string
     */
    public function getItem_Tracking_Code()
    {
      return $this->Item_Tracking_Code;
    }

    /**
     * @param string $Item_Tracking_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setItem_Tracking_Code($Item_Tracking_Code)
    {
      $this->Item_Tracking_Code = $Item_Tracking_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getSerial_Nos()
    {
      return $this->Serial_Nos;
    }

    /**
     * @param string $Serial_Nos
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSerial_Nos($Serial_Nos)
    {
      $this->Serial_Nos = $Serial_Nos;
      return $this;
    }

    /**
     * @return string
     */
    public function getSource_No()
    {
      return $this->Source_No;
    }

    /**
     * @param string $Source_No
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSource_No($Source_No)
    {
      $this->Source_No = $Source_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getSpecial_Equipment_Code()
    {
      return $this->Special_Equipment_Code;
    }

    /**
     * @param string $Special_Equipment_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSpecial_Equipment_Code($Special_Equipment_Code)
    {
      $this->Special_Equipment_Code = $Special_Equipment_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getGlobal_Dimension_1_Code()
    {
      return $this->Global_Dimension_1_Code;
    }

    /**
     * @param string $Global_Dimension_1_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setGlobal_Dimension_1_Code($Global_Dimension_1_Code)
    {
      $this->Global_Dimension_1_Code = $Global_Dimension_1_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getGlobal_Dimension_2_Code()
    {
      return $this->Global_Dimension_2_Code;
    }

    /**
     * @param string $Global_Dimension_2_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setGlobal_Dimension_2_Code($Global_Dimension_2_Code)
    {
      $this->Global_Dimension_2_Code = $Global_Dimension_2_Code;
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
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMRP_Price($MRP_Price)
    {
      $this->MRP_Price = $MRP_Price;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getMRP_Value()
    {
      return $this->MRP_Value;
    }

    /**
     * @param boolean $MRP_Value
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMRP_Value($MRP_Value)
    {
      $this->MRP_Value = $MRP_Value;
      return $this;
    }

    /**
     * @return Costing_Method
     */
    public function getCosting_Method()
    {
      return $this->Costing_Method;
    }

    /**
     * @param Costing_Method $Costing_Method
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCosting_Method($Costing_Method)
    {
      $this->Costing_Method = $Costing_Method;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getCost_is_Adjusted()
    {
      return $this->Cost_is_Adjusted;
    }

    /**
     * @param boolean $Cost_is_Adjusted
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCost_is_Adjusted($Cost_is_Adjusted)
    {
      $this->Cost_is_Adjusted = $Cost_is_Adjusted;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getCost_is_Posted_to_G_L()
    {
      return $this->Cost_is_Posted_to_G_L;
    }

    /**
     * @param boolean $Cost_is_Posted_to_G_L
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCost_is_Posted_to_G_L($Cost_is_Posted_to_G_L)
    {
      $this->Cost_is_Posted_to_G_L = $Cost_is_Posted_to_G_L;
      return $this;
    }

    /**
     * @return float
     */
    public function getStandard_Cost()
    {
      return $this->Standard_Cost;
    }

    /**
     * @param float $Standard_Cost
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setStandard_Cost($Standard_Cost)
    {
      $this->Standard_Cost = $Standard_Cost;
      return $this;
    }

    /**
     * @return float
     */
    public function getUnit_Cost()
    {
      return $this->Unit_Cost;
    }

    /**
     * @param float $Unit_Cost
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setUnit_Cost($Unit_Cost)
    {
      $this->Unit_Cost = $Unit_Cost;
      return $this;
    }

    /**
     * @return float
     */
    public function getOverhead_Rate()
    {
      return $this->Overhead_Rate;
    }

    /**
     * @param float $Overhead_Rate
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setOverhead_Rate($Overhead_Rate)
    {
      $this->Overhead_Rate = $Overhead_Rate;
      return $this;
    }

    /**
     * @return float
     */
    public function getIndirect_Cost_Percent()
    {
      return $this->Indirect_Cost_Percent;
    }

    /**
     * @param float $Indirect_Cost_Percent
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setIndirect_Cost_Percent($Indirect_Cost_Percent)
    {
      $this->Indirect_Cost_Percent = $Indirect_Cost_Percent;
      return $this;
    }

    /**
     * @return float
     */
    public function getLast_Direct_Cost()
    {
      return $this->Last_Direct_Cost;
    }

    /**
     * @param float $Last_Direct_Cost
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setLast_Direct_Cost($Last_Direct_Cost)
    {
      $this->Last_Direct_Cost = $Last_Direct_Cost;
      return $this;
    }

    /**
     * @return Price_Profit_Calculation
     */
    public function getPrice_Profit_Calculation()
    {
      return $this->Price_Profit_Calculation;
    }

    /**
     * @param Price_Profit_Calculation $Price_Profit_Calculation
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPrice_Profit_Calculation($Price_Profit_Calculation)
    {
      $this->Price_Profit_Calculation = $Price_Profit_Calculation;
      return $this;
    }

    /**
     * @return float
     */
    public function getProfit_Percent()
    {
      return $this->Profit_Percent;
    }

    /**
     * @param float $Profit_Percent
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setProfit_Percent($Profit_Percent)
    {
      $this->Profit_Percent = $Profit_Percent;
      return $this;
    }

    /**
     * @return string
     */
    public function getGen_Prod_Posting_Group()
    {
      return $this->Gen_Prod_Posting_Group;
    }

    /**
     * @param string $Gen_Prod_Posting_Group
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setGen_Prod_Posting_Group($Gen_Prod_Posting_Group)
    {
      $this->Gen_Prod_Posting_Group = $Gen_Prod_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getVAT_Prod_Posting_Group()
    {
      return $this->VAT_Prod_Posting_Group;
    }

    /**
     * @param string $VAT_Prod_Posting_Group
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setVAT_Prod_Posting_Group($VAT_Prod_Posting_Group)
    {
      $this->VAT_Prod_Posting_Group = $VAT_Prod_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getExcise_Prod_Posting_Group()
    {
      return $this->Excise_Prod_Posting_Group;
    }

    /**
     * @param string $Excise_Prod_Posting_Group
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setExcise_Prod_Posting_Group($Excise_Prod_Posting_Group)
    {
      $this->Excise_Prod_Posting_Group = $Excise_Prod_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getTax_Group_Code()
    {
      return $this->Tax_Group_Code;
    }

    /**
     * @param string $Tax_Group_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setTax_Group_Code($Tax_Group_Code)
    {
      $this->Tax_Group_Code = $Tax_Group_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getGST_Group_Code()
    {
      return $this->GST_Group_Code;
    }

    /**
     * @param string $GST_Group_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setGST_Group_Code($GST_Group_Code)
    {
      $this->GST_Group_Code = $GST_Group_Code;
      return $this;
    }

    /**
     * @return GST_Credit
     */
    public function getGST_Credit()
    {
      return $this->GST_Credit;
    }

    /**
     * @param GST_Credit $GST_Credit
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setGST_Credit($GST_Credit)
    {
      $this->GST_Credit = $GST_Credit;
      return $this;
    }

    /**
     * @return float
     */
    public function getVAT_If_applicable()
    {
      return $this->VAT_If_applicable;
    }

    /**
     * @param float $VAT_If_applicable
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setVAT_If_applicable($VAT_If_applicable)
    {
      $this->VAT_If_applicable = $VAT_If_applicable;
      return $this;
    }

    /**
     * @return string
     */
    public function getHSN_SAC_Code()
    {
      return $this->HSN_SAC_Code;
    }

    /**
     * @param string $HSN_SAC_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setHSN_SAC_Code($HSN_SAC_Code)
    {
      $this->HSN_SAC_Code = $HSN_SAC_Code;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getExempted()
    {
      return $this->Exempted;
    }

    /**
     * @param boolean $Exempted
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setExempted($Exempted)
    {
      $this->Exempted = $Exempted;
      return $this;
    }

    /**
     * @return string
     */
    public function getInventory_Posting_Group()
    {
      return $this->Inventory_Posting_Group;
    }

    /**
     * @param string $Inventory_Posting_Group
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setInventory_Posting_Group($Inventory_Posting_Group)
    {
      $this->Inventory_Posting_Group = $Inventory_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getDefault_Deferral_Template_Code()
    {
      return $this->Default_Deferral_Template_Code;
    }

    /**
     * @param string $Default_Deferral_Template_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setDefault_Deferral_Template_Code($Default_Deferral_Template_Code)
    {
      $this->Default_Deferral_Template_Code = $Default_Deferral_Template_Code;
      return $this;
    }

    /**
     * @return float
     */
    public function getNet_Invoiced_Qty()
    {
      return $this->Net_Invoiced_Qty;
    }

    /**
     * @param float $Net_Invoiced_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setNet_Invoiced_Qty($Net_Invoiced_Qty)
    {
      $this->Net_Invoiced_Qty = $Net_Invoiced_Qty;
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
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAllow_Invoice_Disc($Allow_Invoice_Disc)
    {
      $this->Allow_Invoice_Disc = $Allow_Invoice_Disc;
      return $this;
    }

    /**
     * @return string
     */
    public function getItem_Disc_Group()
    {
      return $this->Item_Disc_Group;
    }

    /**
     * @param string $Item_Disc_Group
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setItem_Disc_Group($Item_Disc_Group)
    {
      $this->Item_Disc_Group = $Item_Disc_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getApplication_Wksh_User_ID()
    {
      return $this->Application_Wksh_User_ID;
    }

    /**
     * @param string $Application_Wksh_User_ID
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setApplication_Wksh_User_ID($Application_Wksh_User_ID)
    {
      $this->Application_Wksh_User_ID = $Application_Wksh_User_ID;
      return $this;
    }

    /**
     * @return Replenishment_System
     */
    public function getReplenishment_System()
    {
      return $this->Replenishment_System;
    }

    /**
     * @param Replenishment_System $Replenishment_System
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setReplenishment_System($Replenishment_System)
    {
      $this->Replenishment_System = $Replenishment_System;
      return $this;
    }

    /**
     * @return string
     */
    public function getLead_Time_Calculation()
    {
      return $this->Lead_Time_Calculation;
    }

    /**
     * @param string $Lead_Time_Calculation
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setLead_Time_Calculation($Lead_Time_Calculation)
    {
      $this->Lead_Time_Calculation = $Lead_Time_Calculation;
      return $this;
    }

    /**
     * @return string
     */
    public function getVendor_No()
    {
      return $this->Vendor_No;
    }

    /**
     * @param string $Vendor_No
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setVendor_No($Vendor_No)
    {
      $this->Vendor_No = $Vendor_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getVendor_Item_No()
    {
      return $this->Vendor_Item_No;
    }

    /**
     * @param string $Vendor_Item_No
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setVendor_Item_No($Vendor_Item_No)
    {
      $this->Vendor_Item_No = $Vendor_Item_No;
      return $this;
    }

    /**
     * @return Manufacturing_Policy
     */
    public function getManufacturing_Policy()
    {
      return $this->Manufacturing_Policy;
    }

    /**
     * @param Manufacturing_Policy $Manufacturing_Policy
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setManufacturing_Policy($Manufacturing_Policy)
    {
      $this->Manufacturing_Policy = $Manufacturing_Policy;
      return $this;
    }

    /**
     * @return string
     */
    public function getRouting_No()
    {
      return $this->Routing_No;
    }

    /**
     * @param string $Routing_No
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setRouting_No($Routing_No)
    {
      $this->Routing_No = $Routing_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getProduction_BOM_No()
    {
      return $this->Production_BOM_No;
    }

    /**
     * @param string $Production_BOM_No
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setProduction_BOM_No($Production_BOM_No)
    {
      $this->Production_BOM_No = $Production_BOM_No;
      return $this;
    }

    /**
     * @return float
     */
    public function getRounding_Precision()
    {
      return $this->Rounding_Precision;
    }

    /**
     * @param float $Rounding_Precision
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setRounding_Precision($Rounding_Precision)
    {
      $this->Rounding_Precision = $Rounding_Precision;
      return $this;
    }

    /**
     * @return Flushing_Method
     */
    public function getFlushing_Method()
    {
      return $this->Flushing_Method;
    }

    /**
     * @param Flushing_Method $Flushing_Method
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setFlushing_Method($Flushing_Method)
    {
      $this->Flushing_Method = $Flushing_Method;
      return $this;
    }

    /**
     * @return float
     */
    public function getScrap_Percent()
    {
      return $this->Scrap_Percent;
    }

    /**
     * @param float $Scrap_Percent
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setScrap_Percent($Scrap_Percent)
    {
      $this->Scrap_Percent = $Scrap_Percent;
      return $this;
    }

    /**
     * @return float
     */
    public function getLot_Size()
    {
      return $this->Lot_Size;
    }

    /**
     * @param float $Lot_Size
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setLot_Size($Lot_Size)
    {
      $this->Lot_Size = $Lot_Size;
      return $this;
    }

    /**
     * @return Assembly_Policy
     */
    public function getAssembly_Policy()
    {
      return $this->Assembly_Policy;
    }

    /**
     * @param Assembly_Policy $Assembly_Policy
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAssembly_Policy($Assembly_Policy)
    {
      $this->Assembly_Policy = $Assembly_Policy;
      return $this;
    }

    /**
     * @return Reordering_Policy
     */
    public function getReordering_Policy()
    {
      return $this->Reordering_Policy;
    }

    /**
     * @param Reordering_Policy $Reordering_Policy
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setReordering_Policy($Reordering_Policy)
    {
      $this->Reordering_Policy = $Reordering_Policy;
      return $this;
    }

    /**
     * @return Reserve
     */
    public function getReserve()
    {
      return $this->Reserve;
    }

    /**
     * @param Reserve $Reserve
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setReserve($Reserve)
    {
      $this->Reserve = $Reserve;
      return $this;
    }

    /**
     * @return Order_Tracking_Policy
     */
    public function getOrder_Tracking_Policy()
    {
      return $this->Order_Tracking_Policy;
    }

    /**
     * @param Order_Tracking_Policy $Order_Tracking_Policy
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setOrder_Tracking_Policy($Order_Tracking_Policy)
    {
      $this->Order_Tracking_Policy = $Order_Tracking_Policy;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getStockkeeping_Unit_Exists()
    {
      return $this->Stockkeeping_Unit_Exists;
    }

    /**
     * @param boolean $Stockkeeping_Unit_Exists
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setStockkeeping_Unit_Exists($Stockkeeping_Unit_Exists)
    {
      $this->Stockkeeping_Unit_Exists = $Stockkeeping_Unit_Exists;
      return $this;
    }

    /**
     * @return string
     */
    public function getDampener_Period()
    {
      return $this->Dampener_Period;
    }

    /**
     * @param string $Dampener_Period
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setDampener_Period($Dampener_Period)
    {
      $this->Dampener_Period = $Dampener_Period;
      return $this;
    }

    /**
     * @return float
     */
    public function getDampener_Quantity()
    {
      return $this->Dampener_Quantity;
    }

    /**
     * @param float $Dampener_Quantity
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setDampener_Quantity($Dampener_Quantity)
    {
      $this->Dampener_Quantity = $Dampener_Quantity;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getCritical()
    {
      return $this->Critical;
    }

    /**
     * @param boolean $Critical
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCritical($Critical)
    {
      $this->Critical = $Critical;
      return $this;
    }

    /**
     * @return string
     */
    public function getSafety_Lead_Time()
    {
      return $this->Safety_Lead_Time;
    }

    /**
     * @param string $Safety_Lead_Time
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSafety_Lead_Time($Safety_Lead_Time)
    {
      $this->Safety_Lead_Time = $Safety_Lead_Time;
      return $this;
    }

    /**
     * @return float
     */
    public function getSafety_Stock_Quantity()
    {
      return $this->Safety_Stock_Quantity;
    }

    /**
     * @param float $Safety_Stock_Quantity
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSafety_Stock_Quantity($Safety_Stock_Quantity)
    {
      $this->Safety_Stock_Quantity = $Safety_Stock_Quantity;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getInclude_Inventory()
    {
      return $this->Include_Inventory;
    }

    /**
     * @param boolean $Include_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setInclude_Inventory($Include_Inventory)
    {
      $this->Include_Inventory = $Include_Inventory;
      return $this;
    }

    /**
     * @return string
     */
    public function getLot_Accumulation_Period()
    {
      return $this->Lot_Accumulation_Period;
    }

    /**
     * @param string $Lot_Accumulation_Period
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setLot_Accumulation_Period($Lot_Accumulation_Period)
    {
      $this->Lot_Accumulation_Period = $Lot_Accumulation_Period;
      return $this;
    }

    /**
     * @return string
     */
    public function getRescheduling_Period()
    {
      return $this->Rescheduling_Period;
    }

    /**
     * @param string $Rescheduling_Period
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setRescheduling_Period($Rescheduling_Period)
    {
      $this->Rescheduling_Period = $Rescheduling_Period;
      return $this;
    }

    /**
     * @return float
     */
    public function getReorder_Point()
    {
      return $this->Reorder_Point;
    }

    /**
     * @param float $Reorder_Point
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setReorder_Point($Reorder_Point)
    {
      $this->Reorder_Point = $Reorder_Point;
      return $this;
    }

    /**
     * @return float
     */
    public function getReorder_Quantity()
    {
      return $this->Reorder_Quantity;
    }

    /**
     * @param float $Reorder_Quantity
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setReorder_Quantity($Reorder_Quantity)
    {
      $this->Reorder_Quantity = $Reorder_Quantity;
      return $this;
    }

    /**
     * @return float
     */
    public function getMaximum_Inventory()
    {
      return $this->Maximum_Inventory;
    }

    /**
     * @param float $Maximum_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMaximum_Inventory($Maximum_Inventory)
    {
      $this->Maximum_Inventory = $Maximum_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getOverflow_Level()
    {
      return $this->Overflow_Level;
    }

    /**
     * @param float $Overflow_Level
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setOverflow_Level($Overflow_Level)
    {
      $this->Overflow_Level = $Overflow_Level;
      return $this;
    }

    /**
     * @return string
     */
    public function getTime_Bucket()
    {
      return $this->Time_Bucket;
    }

    /**
     * @param string $Time_Bucket
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setTime_Bucket($Time_Bucket)
    {
      $this->Time_Bucket = $Time_Bucket;
      return $this;
    }

    /**
     * @return float
     */
    public function getMinimum_Order_Quantity()
    {
      return $this->Minimum_Order_Quantity;
    }

    /**
     * @param float $Minimum_Order_Quantity
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMinimum_Order_Quantity($Minimum_Order_Quantity)
    {
      $this->Minimum_Order_Quantity = $Minimum_Order_Quantity;
      return $this;
    }

    /**
     * @return float
     */
    public function getMaximum_Order_Quantity()
    {
      return $this->Maximum_Order_Quantity;
    }

    /**
     * @param float $Maximum_Order_Quantity
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMaximum_Order_Quantity($Maximum_Order_Quantity)
    {
      $this->Maximum_Order_Quantity = $Maximum_Order_Quantity;
      return $this;
    }

    /**
     * @return float
     */
    public function getOrder_Multiple()
    {
      return $this->Order_Multiple;
    }

    /**
     * @param float $Order_Multiple
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setOrder_Multiple($Order_Multiple)
    {
      $this->Order_Multiple = $Order_Multiple;
      return $this;
    }

    /**
     * @return string
     */
    public function getGTIN()
    {
      return $this->GTIN;
    }

    /**
     * @param string $GTIN
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setGTIN($GTIN)
    {
      $this->GTIN = $GTIN;
      return $this;
    }

    /**
     * @return string
     */
    public function getTariff_No()
    {
      return $this->Tariff_No;
    }

    /**
     * @param string $Tariff_No
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setTariff_No($Tariff_No)
    {
      $this->Tariff_No = $Tariff_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getCountry_Region_of_Origin_Code()
    {
      return $this->Country_Region_of_Origin_Code;
    }

    /**
     * @param string $Country_Region_of_Origin_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCountry_Region_of_Origin_Code($Country_Region_of_Origin_Code)
    {
      $this->Country_Region_of_Origin_Code = $Country_Region_of_Origin_Code;
      return $this;
    }

    /**
     * @return float
     */
    public function getNet_Weight()
    {
      return $this->Net_Weight;
    }

    /**
     * @param float $Net_Weight
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setNet_Weight($Net_Weight)
    {
      $this->Net_Weight = $Net_Weight;
      return $this;
    }

    /**
     * @return float
     */
    public function getGross_Weight()
    {
      return $this->Gross_Weight;
    }

    /**
     * @param float $Gross_Weight
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setGross_Weight($Gross_Weight)
    {
      $this->Gross_Weight = $Gross_Weight;
      return $this;
    }

    /**
     * @return string
     */
    public function getLot_Nos()
    {
      return $this->Lot_Nos;
    }

    /**
     * @param string $Lot_Nos
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setLot_Nos($Lot_Nos)
    {
      $this->Lot_Nos = $Lot_Nos;
      return $this;
    }

    /**
     * @return string
     */
    public function getExpiration_Calculation()
    {
      return $this->Expiration_Calculation;
    }

    /**
     * @param string $Expiration_Calculation
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setExpiration_Calculation($Expiration_Calculation)
    {
      $this->Expiration_Calculation = $Expiration_Calculation;
      return $this;
    }

    /**
     * @return string
     */
    public function getPut_away_Template_Code()
    {
      return $this->Put_away_Template_Code;
    }

    /**
     * @param string $Put_away_Template_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPut_away_Template_Code($Put_away_Template_Code)
    {
      $this->Put_away_Template_Code = $Put_away_Template_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getPut_away_Unit_of_Measure_Code()
    {
      return $this->Put_away_Unit_of_Measure_Code;
    }

    /**
     * @param string $Put_away_Unit_of_Measure_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPut_away_Unit_of_Measure_Code($Put_away_Unit_of_Measure_Code)
    {
      $this->Put_away_Unit_of_Measure_Code = $Put_away_Unit_of_Measure_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getPhys_Invt_Counting_Period_Code()
    {
      return $this->Phys_Invt_Counting_Period_Code;
    }

    /**
     * @param string $Phys_Invt_Counting_Period_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPhys_Invt_Counting_Period_Code($Phys_Invt_Counting_Period_Code)
    {
      $this->Phys_Invt_Counting_Period_Code = $Phys_Invt_Counting_Period_Code;
      return $this;
    }

    /**
     * @return date
     */
    public function getLast_Phys_Invt_Date()
    {
      return $this->Last_Phys_Invt_Date;
    }

    /**
     * @param date $Last_Phys_Invt_Date
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setLast_Phys_Invt_Date($Last_Phys_Invt_Date)
    {
      $this->Last_Phys_Invt_Date = $Last_Phys_Invt_Date;
      return $this;
    }

    /**
     * @return date
     */
    public function getLast_Counting_Period_Update()
    {
      return $this->Last_Counting_Period_Update;
    }

    /**
     * @param date $Last_Counting_Period_Update
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setLast_Counting_Period_Update($Last_Counting_Period_Update)
    {
      $this->Last_Counting_Period_Update = $Last_Counting_Period_Update;
      return $this;
    }

    /**
     * @return date
     */
    public function getNext_Counting_Start_Date()
    {
      return $this->Next_Counting_Start_Date;
    }

    /**
     * @param date $Next_Counting_Start_Date
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setNext_Counting_Start_Date($Next_Counting_Start_Date)
    {
      $this->Next_Counting_Start_Date = $Next_Counting_Start_Date;
      return $this;
    }

    /**
     * @return date
     */
    public function getNext_Counting_End_Date()
    {
      return $this->Next_Counting_End_Date;
    }

    /**
     * @param date $Next_Counting_End_Date
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setNext_Counting_End_Date($Next_Counting_End_Date)
    {
      $this->Next_Counting_End_Date = $Next_Counting_End_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getIdentifier_Code()
    {
      return $this->Identifier_Code;
    }

    /**
     * @param string $Identifier_Code
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setIdentifier_Code($Identifier_Code)
    {
      $this->Identifier_Code = $Identifier_Code;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getUse_Cross_Docking()
    {
      return $this->Use_Cross_Docking;
    }

    /**
     * @param boolean $Use_Cross_Docking
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setUse_Cross_Docking($Use_Cross_Docking)
    {
      $this->Use_Cross_Docking = $Use_Cross_Docking;
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
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAbatement_Percent($Abatement_Percent)
    {
      $this->Abatement_Percent = $Abatement_Percent;
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
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPrice_Inclusive_of_Tax($Price_Inclusive_of_Tax)
    {
      $this->Price_Inclusive_of_Tax = $Price_Inclusive_of_Tax;
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
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPIT_Structure($PIT_Structure)
    {
      $this->PIT_Structure = $PIT_Structure;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getSubcontracting()
    {
      return $this->Subcontracting;
    }

    /**
     * @param boolean $Subcontracting
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSubcontracting($Subcontracting)
    {
      $this->Subcontracting = $Subcontracting;
      return $this;
    }

    /**
     * @return string
     */
    public function getSub_Comp_Location()
    {
      return $this->Sub_Comp_Location;
    }

    /**
     * @param string $Sub_Comp_Location
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSub_Comp_Location($Sub_Comp_Location)
    {
      $this->Sub_Comp_Location = $Sub_Comp_Location;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getScrap_Item()
    {
      return $this->Scrap_Item;
    }

    /**
     * @param boolean $Scrap_Item
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setScrap_Item($Scrap_Item)
    {
      $this->Scrap_Item = $Scrap_Item;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getCapital_Item()
    {
      return $this->Capital_Item;
    }

    /**
     * @param boolean $Capital_Item
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCapital_Item($Capital_Item)
    {
      $this->Capital_Item = $Capital_Item;
      return $this;
    }

    /**
     * @return Excise_Accounting_Type
     */
    public function getExcise_Accounting_Type()
    {
      return $this->Excise_Accounting_Type;
    }

    /**
     * @param Excise_Accounting_Type $Excise_Accounting_Type
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setExcise_Accounting_Type($Excise_Accounting_Type)
    {
      $this->Excise_Accounting_Type = $Excise_Accounting_Type;
      return $this;
    }

    /**
     * @return float
     */
    public function getAssessable_Value()
    {
      return $this->Assessable_Value;
    }

    /**
     * @param float $Assessable_Value
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAssessable_Value($Assessable_Value)
    {
      $this->Assessable_Value = $Assessable_Value;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getFixed_Asset()
    {
      return $this->Fixed_Asset;
    }

    /**
     * @param boolean $Fixed_Asset
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setFixed_Asset($Fixed_Asset)
    {
      $this->Fixed_Asset = $Fixed_Asset;
      return $this;
    }

    /**
     * @return float
     */
    public function getWAREHOUSE_Inventory()
    {
      return $this->WAREHOUSE_Inventory;
    }

    /**
     * @param float $WAREHOUSE_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setWAREHOUSE_Inventory($WAREHOUSE_Inventory)
    {
      $this->WAREHOUSE_Inventory = $WAREHOUSE_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getWAREHOUSE_SO_Qty_Adv()
    {
      return $this->WAREHOUSE_SO_Qty_Adv;
    }

    /**
     * @param float $WAREHOUSE_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setWAREHOUSE_SO_Qty_Adv($WAREHOUSE_SO_Qty_Adv)
    {
      $this->WAREHOUSE_SO_Qty_Adv = $WAREHOUSE_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getWAREHOUSE_SO_Qty()
    {
      return $this->WAREHOUSE_SO_Qty;
    }

    /**
     * @param float $WAREHOUSE_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setWAREHOUSE_SO_Qty($WAREHOUSE_SO_Qty)
    {
      $this->WAREHOUSE_SO_Qty = $WAREHOUSE_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getWAREHOUSE_PO_Qty()
    {
      return $this->WAREHOUSE_PO_Qty;
    }

    /**
     * @param float $WAREHOUSE_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setWAREHOUSE_PO_Qty($WAREHOUSE_PO_Qty)
    {
      $this->WAREHOUSE_PO_Qty = $WAREHOUSE_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getMUM_WARE_Inventory()
    {
      return $this->MUM_WARE_Inventory;
    }

    /**
     * @param float $MUM_WARE_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUM_WARE_Inventory($MUM_WARE_Inventory)
    {
      $this->MUM_WARE_Inventory = $MUM_WARE_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getMUM_WARE_SO_Qty_Adv()
    {
      return $this->MUM_WARE_SO_Qty_Adv;
    }

    /**
     * @param float $MUM_WARE_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUM_WARE_SO_Qty_Adv($MUM_WARE_SO_Qty_Adv)
    {
      $this->MUM_WARE_SO_Qty_Adv = $MUM_WARE_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getMUM_WARE_SO_Qty()
    {
      return $this->MUM_WARE_SO_Qty;
    }

    /**
     * @param float $MUM_WARE_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUM_WARE_SO_Qty($MUM_WARE_SO_Qty)
    {
      $this->MUM_WARE_SO_Qty = $MUM_WARE_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getMUM_WARE_PO_Qty()
    {
      return $this->MUM_WARE_PO_Qty;
    }

    /**
     * @param float $MUM_WARE_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUM_WARE_PO_Qty($MUM_WARE_PO_Qty)
    {
      $this->MUM_WARE_PO_Qty = $MUM_WARE_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_MUM_Inventory()
    {
      return $this->KRONO_MUM_Inventory;
    }

    /**
     * @param float $KRONO_MUM_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_MUM_Inventory($KRONO_MUM_Inventory)
    {
      $this->KRONO_MUM_Inventory = $KRONO_MUM_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_MUM_SO_Qty_Adv()
    {
      return $this->KRONO_MUM_SO_Qty_Adv;
    }

    /**
     * @param float $KRONO_MUM_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_MUM_SO_Qty_Adv($KRONO_MUM_SO_Qty_Adv)
    {
      $this->KRONO_MUM_SO_Qty_Adv = $KRONO_MUM_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_MUM_SO_Qty()
    {
      return $this->KRONO_MUM_SO_Qty;
    }

    /**
     * @param float $KRONO_MUM_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_MUM_SO_Qty($KRONO_MUM_SO_Qty)
    {
      $this->KRONO_MUM_SO_Qty = $KRONO_MUM_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_MUM_PO_Qty()
    {
      return $this->KRONO_MUM_PO_Qty;
    }

    /**
     * @param float $KRONO_MUM_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_MUM_PO_Qty($KRONO_MUM_PO_Qty)
    {
      $this->KRONO_MUM_PO_Qty = $KRONO_MUM_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getPUNE_DEP_2_Inventory()
    {
      return $this->PUNE_DEP_2_Inventory;
    }

    /**
     * @param float $PUNE_DEP_2_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPUNE_DEP_2_Inventory($PUNE_DEP_2_Inventory)
    {
      $this->PUNE_DEP_2_Inventory = $PUNE_DEP_2_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getPUNE_DEP_2_SO_Qty_Adv()
    {
      return $this->PUNE_DEP_2_SO_Qty_Adv;
    }

    /**
     * @param float $PUNE_DEP_2_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPUNE_DEP_2_SO_Qty_Adv($PUNE_DEP_2_SO_Qty_Adv)
    {
      $this->PUNE_DEP_2_SO_Qty_Adv = $PUNE_DEP_2_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getPUNE_DEP_2_SO_Qty()
    {
      return $this->PUNE_DEP_2_SO_Qty;
    }

    /**
     * @param float $PUNE_DEP_2_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPUNE_DEP_2_SO_Qty($PUNE_DEP_2_SO_Qty)
    {
      $this->PUNE_DEP_2_SO_Qty = $PUNE_DEP_2_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getPUNE_DEP_2_PO_Qty()
    {
      return $this->PUNE_DEP_2_PO_Qty;
    }

    /**
     * @param float $PUNE_DEP_2_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPUNE_DEP_2_PO_Qty($PUNE_DEP_2_PO_Qty)
    {
      $this->PUNE_DEP_2_PO_Qty = $PUNE_DEP_2_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getCS2_Inventory()
    {
      return $this->CS2_Inventory;
    }

    /**
     * @param float $CS2_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCS2_Inventory($CS2_Inventory)
    {
      $this->CS2_Inventory = $CS2_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getCS2_SO_Qty_Adv()
    {
      return $this->CS2_SO_Qty_Adv;
    }

    /**
     * @param float $CS2_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCS2_SO_Qty_Adv($CS2_SO_Qty_Adv)
    {
      $this->CS2_SO_Qty_Adv = $CS2_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getCS2_SO_Qty()
    {
      return $this->CS2_SO_Qty;
    }

    /**
     * @param float $CS2_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCS2_SO_Qty($CS2_SO_Qty)
    {
      $this->CS2_SO_Qty = $CS2_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getCS2_PO_Qty()
    {
      return $this->CS2_PO_Qty;
    }

    /**
     * @param float $CS2_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCS2_PO_Qty($CS2_PO_Qty)
    {
      $this->CS2_PO_Qty = $CS2_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getInventory_In_Transit()
    {
      return $this->Inventory_In_Transit;
    }

    /**
     * @param float $Inventory_In_Transit
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setInventory_In_Transit($Inventory_In_Transit)
    {
      $this->Inventory_In_Transit = $Inventory_In_Transit;
      return $this;
    }

    /**
     * @return float
     */
    public function getRGALLERY_Inventory()
    {
      return $this->RGALLERY_Inventory;
    }

    /**
     * @param float $RGALLERY_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setRGALLERY_Inventory($RGALLERY_Inventory)
    {
      $this->RGALLERY_Inventory = $RGALLERY_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getRGALLERY_SO_Qty_Adv()
    {
      return $this->RGALLERY_SO_Qty_Adv;
    }

    /**
     * @param float $RGALLERY_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setRGALLERY_SO_Qty_Adv($RGALLERY_SO_Qty_Adv)
    {
      $this->RGALLERY_SO_Qty_Adv = $RGALLERY_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getRGALLERY_SO_Qty()
    {
      return $this->RGALLERY_SO_Qty;
    }

    /**
     * @param float $RGALLERY_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setRGALLERY_SO_Qty($RGALLERY_SO_Qty)
    {
      $this->RGALLERY_SO_Qty = $RGALLERY_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getRGALLERY_PO_Qty()
    {
      return $this->RGALLERY_PO_Qty;
    }

    /**
     * @param float $RGALLERY_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setRGALLERY_PO_Qty($RGALLERY_PO_Qty)
    {
      $this->RGALLERY_PO_Qty = $RGALLERY_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBOM5_Inventory()
    {
      return $this->AMAZONBOM5_Inventory;
    }

    /**
     * @param float $AMAZONBOM5_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBOM5_Inventory($AMAZONBOM5_Inventory)
    {
      $this->AMAZONBOM5_Inventory = $AMAZONBOM5_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBOM5_SO_Qty_Adv()
    {
      return $this->AMAZONBOM5_SO_Qty_Adv;
    }

    /**
     * @param float $AMAZONBOM5_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBOM5_SO_Qty_Adv($AMAZONBOM5_SO_Qty_Adv)
    {
      $this->AMAZONBOM5_SO_Qty_Adv = $AMAZONBOM5_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBOM5_SO_Qty()
    {
      return $this->AMAZONBOM5_SO_Qty;
    }

    /**
     * @param float $AMAZONBOM5_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBOM5_SO_Qty($AMAZONBOM5_SO_Qty)
    {
      $this->AMAZONBOM5_SO_Qty = $AMAZONBOM5_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBOM5_PO_Qty()
    {
      return $this->AMAZONBOM5_PO_Qty;
    }

    /**
     * @param float $AMAZONBOM5_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBOM5_PO_Qty($AMAZONBOM5_PO_Qty)
    {
      $this->AMAZONBOM5_PO_Qty = $AMAZONBOM5_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getSBU3_Inventory()
    {
      return $this->SBU3_Inventory;
    }

    /**
     * @param float $SBU3_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSBU3_Inventory($SBU3_Inventory)
    {
      $this->SBU3_Inventory = $SBU3_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getSBU3_SO_Qty()
    {
      return $this->SBU3_SO_Qty;
    }

    /**
     * @param float $SBU3_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSBU3_SO_Qty($SBU3_SO_Qty)
    {
      $this->SBU3_SO_Qty = $SBU3_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getSBU3_SO_Qty_Adv()
    {
      return $this->SBU3_SO_Qty_Adv;
    }

    /**
     * @param float $SBU3_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSBU3_SO_Qty_Adv($SBU3_SO_Qty_Adv)
    {
      $this->SBU3_SO_Qty_Adv = $SBU3_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getSBU3_PO_Qty()
    {
      return $this->SBU3_PO_Qty;
    }

    /**
     * @param float $SBU3_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setSBU3_PO_Qty($SBU3_PO_Qty)
    {
      $this->SBU3_PO_Qty = $SBU3_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBLR8_Inventory()
    {
      return $this->AMAZONBLR8_Inventory;
    }

    /**
     * @param float $AMAZONBLR8_Invenntory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBLR8_Inventory($AMAZONBLR8_Inventory)
    {
      $this->AMAZONBLR8_Inventory = $AMAZONBLR8_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBLR8_SO_Qty_Adv()
    {
      return $this->AMAZONBLR8_SO_Qty_Adv;
    }

    /**
     * @param float $AMAZONBLR8_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBLR8_SO_Qty_Adv($AMAZONBLR8_SO_Qty_Adv)
    {
      $this->AMAZONBLR8_SO_Qty_Adv = $AMAZONBLR8_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBLR8_SO_Qty()
    {
      return $this->AMAZONBLR8_SO_Qty;
    }

    /**
     * @param float $AMAZONBLR8_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBLR8_SO_Qty($AMAZONBLR8_SO_Qty)
    {
      $this->AMAZONBLR8_SO_Qty = $AMAZONBLR8_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBLR8_PO_Qty()
    {
      return $this->AMAZONBLR8_PO_Qty;
    }

    /**
     * @param float $AMAZONBLR8_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBLR8_PO_Qty($AMAZONBLR8_PO_Qty)
    {
      $this->AMAZONBLR8_PO_Qty = $AMAZONBLR8_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_BLR_Inventory()
    {
      return $this->KRONO_BLR_Inventory;
    }

    /**
     * @param float $KRONO_BLR_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_BLR_Inventory($KRONO_BLR_Inventory)
    {
      $this->KRONO_BLR_Inventory = $KRONO_BLR_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_BLR_SO_Qty_Adv()
    {
      return $this->KRONO_BLR_SO_Qty_Adv;
    }

    /**
     * @param float $KRONO_BLR_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_BLR_SO_Qty_Adv($KRONO_BLR_SO_Qty_Adv)
    {
      $this->KRONO_BLR_SO_Qty_Adv = $KRONO_BLR_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_BLR_SO_Qty()
    {
      return $this->KRONO_BLR_SO_Qty;
    }

    /**
     * @param float $KRONO_BLR_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_BLR_SO_Qty($KRONO_BLR_SO_Qty)
    {
      $this->KRONO_BLR_SO_Qty = $KRONO_BLR_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_BLR_PO_Qty()
    {
      return $this->KRONO_BLR_PO_Qty;
    }

    /**
     * @param float $KRONO_BLR_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_BLR_PO_Qty($KRONO_BLR_PO_Qty)
    {
      $this->KRONO_BLR_PO_Qty = $KRONO_BLR_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBLR7_Inventory()
    {
      return $this->AMAZONBLR7_Inventory;
    }

    /**
     * @param float $AMAZONBLR7_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBLR7_Inventory($AMAZONBLR7_Inventory)
    {
      $this->AMAZONBLR7_Inventory = $AMAZONBLR7_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBLR7_SO_Qty_Adv()
    {
      return $this->AMAZONBLR7_SO_Qty_Adv;
    }

    /**
     * @param float $AMAZONBLR7_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBLR7_SO_Qty_Adv($AMAZONBLR7_SO_Qty_Adv)
    {
      $this->AMAZONBLR7_SO_Qty_Adv = $AMAZONBLR7_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBLR7_SO_Qty()
    {
      return $this->AMAZONBLR7_SO_Qty;
    }

    /**
     * @param float $AMAZONBLR7_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBLR7_SO_Qty($AMAZONBLR7_SO_Qty)
    {
      $this->AMAZONBLR7_SO_Qty = $AMAZONBLR7_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBLR7_PO_Qty()
    {
      return $this->AMAZONBLR7_PO_Qty;
    }

    /**
     * @param float $AMAZONBLR7_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBLR7_PO_Qty($AMAZONBLR7_PO_Qty)
    {
      $this->AMAZONBLR7_PO_Qty = $AMAZONBLR7_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_PUNE_PO_Qty()
    {
      return $this->KRONO_PUNE_PO_Qty;
    }

    /**
     * @param float $KRONO_PUNE_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_PUNE_PO_Qty($KRONO_PUNE_PO_Qty)
    {
      $this->KRONO_PUNE_PO_Qty = $KRONO_PUNE_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_PUNE_SO_Qty()
    {
      return $this->KRONO_PUNE_SO_Qty;
    }

    /**
     * @param float $KRONO_PUNE_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_PUNE_SO_Qty($KRONO_PUNE_SO_Qty)
    {
      $this->KRONO_PUNE_SO_Qty = $KRONO_PUNE_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_PUNE_SO_Qty_Adv()
    {
      return $this->KRONO_PUNE_SO_Qty_Adv;
    }

    /**
     * @param float $KRONO_PUNE_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_PUNE_SO_Qty_Adv($KRONO_PUNE_SO_Qty_Adv)
    {
      $this->KRONO_PUNE_SO_Qty_Adv = $KRONO_PUNE_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getKRONO_PUNE_Inventory()
    {
      return $this->KRONO_PUNE_Inventory;
    }

    /**
     * @param float $KRONO_PUNE_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setKRONO_PUNE_Inventory($KRONO_PUNE_Inventory)
    {
      $this->KRONO_PUNE_Inventory = $KRONO_PUNE_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getDISTRIBUTI_Inventory()
    {
      return $this->DISTRIBUTI_Inventory;
    }

    /**
     * @param float $DISTRIBUTI_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setDISTRIBUTI_Inventory($DISTRIBUTI_Inventory)
    {
      $this->DISTRIBUTI_Inventory = $DISTRIBUTI_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getDISTRIBUTI_SO_Qty()
    {
      return $this->DISTRIBUTI_SO_Qty;
    }

    /**
     * @param float $DISTRIBUTI_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setDISTRIBUTI_SO_Qty($DISTRIBUTI_SO_Qty)
    {
      $this->DISTRIBUTI_SO_Qty = $DISTRIBUTI_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getDISTRIBUTI_PO_Qty()
    {
      return $this->DISTRIBUTI_PO_Qty;
    }

    /**
     * @param float $DISTRIBUTI_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setDISTRIBUTI_PO_Qty($DISTRIBUTI_PO_Qty)
    {
      $this->DISTRIBUTI_PO_Qty = $DISTRIBUTI_PO_Qty;
      return $this;
    }

    /**
     * @return string
     */
    public function getDISTRIBUTI_SO_Qty_Adv()
    {
      return $this->DISTRIBUTI_SO_Qty_Adv;
    }

    /**
     * @param string $DISTRIBUTI_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setDISTRIBUTI_SO_Qty_Adv($DISTRIBUTI_SO_Qty_Adv)
    {
      $this->DISTRIBUTI_SO_Qty_Adv = $DISTRIBUTI_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getPRMDISTRI_Inventory()
    {
      return $this->PRMDISTRI_Inventory;
    }

    /**
     * @param float $PRMDISTRI_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPRMDISTRI_Inventory($PRMDISTRI_Inventory)
    {
      $this->PRMDISTRI_Inventory = $PRMDISTRI_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getPRMDISTRI_SO_Qty()
    {
      return $this->PRMDISTRI_SO_Qty;
    }

    /**
     * @param float $PRMDISTRI_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPRMDISTRI_SO_Qty($PRMDISTRI_SO_Qty)
    {
      $this->PRMDISTRI_SO_Qty = $PRMDISTRI_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getPRMDISTRI_PO_Qty()
    {
      return $this->PRMDISTRI_PO_Qty;
    }

    /**
     * @param float $PRMDISTRI_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPRMDISTRI_PO_Qty($PRMDISTRI_PO_Qty)
    {
      $this->PRMDISTRI_PO_Qty = $PRMDISTRI_PO_Qty;
      return $this;
    }

    /**
     * @return string
     */
    public function getPRMDISTRI_SO_Qty_Adv()
    {
      return $this->PRMDISTRI_SO_Qty_Adv;
    }

    /**
     * @param string $PRMDISTRI_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPRMDISTRI_SO_Qty_Adv($PRMDISTRI_SO_Qty_Adv)
    {
      $this->PRMDISTRI_SO_Qty_Adv = $PRMDISTRI_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getPROJECTS_Inventory()
    {
      return $this->PROJECTS_Inventory;
    }

    /**
     * @param float $PROJECTS_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPROJECTS_Inventory($PROJECTS_Inventory)
    {
      $this->PROJECTS_Inventory = $PROJECTS_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getPROJECTS_SO_Qty()
    {
      return $this->PROJECTS_SO_Qty;
    }

    /**
     * @param float $PROJECTS_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPROJECTS_SO_Qty($PROJECTS_SO_Qty)
    {
      $this->PROJECTS_SO_Qty = $PROJECTS_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getPROJECTS_PO_Qty()
    {
      return $this->PROJECTS_PO_Qty;
    }

    /**
     * @param float $PROJECTS_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPROJECTS_PO_Qty($PROJECTS_PO_Qty)
    {
      $this->PROJECTS_PO_Qty = $PROJECTS_PO_Qty;
      return $this;
    }

    /**
     * @return string
     */
    public function getPROJECTS_SO_Qty_Adv()
    {
      return $this->PROJECTS_SO_Qty_Adv;
    }

    /**
     * @param string $PROJECTS_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setPROJECTS_SO_Qty_Adv($PROJECTS_SO_Qty_Adv)
    {
      $this->PROJECTS_SO_Qty_Adv = $PROJECTS_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getCASHNCARRY_Inventory()
    {
      return $this->CASHNCARRY_Inventory;
    }

    /**
     * @param float $CASHNCARRY_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCASHNCARRY_Inventory($CASHNCARRY_Inventory)
    {
      $this->CASHNCARRY_Inventory = $CASHNCARRY_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZON02_Inventory()
    {
      return $this->AMAZON02_Inventory;
    }

    /**
     * @param float $AMAZON02_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZON02_Inventory($AMAZON02_Inventory)
    {
      $this->AMAZON02_Inventory = $AMAZON02_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZON02_SO_Qty()
    {
      return $this->AMAZON02_SO_Qty;
    }

    /**
     * @param float $AMAZON02_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZON02_SO_Qty($AMAZON02_SO_Qty)
    {
      $this->AMAZON02_SO_Qty = $AMAZON02_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZON02_PO_Qty()
    {
      return $this->AMAZON02_PO_Qty;
    }

    /**
     * @param float $AMAZON02_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZON02_PO_Qty($AMAZON02_PO_Qty)
    {
      $this->AMAZON02_PO_Qty = $AMAZON02_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBOM7_Inventory()
    {
      return $this->AMAZONBOM7_Inventory;
    }

    /**
     * @param float $AMAZONBOM7_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBOM7_Inventory($AMAZONBOM7_Inventory)
    {
      $this->AMAZONBOM7_Inventory = $AMAZONBOM7_Inventory;
      return $this;
    }

    /**
     * @return string
     */
    public function getAMAZON02_SO_Qty_Adv()
    {
      return $this->AMAZON02_SO_Qty_Adv;
    }

    /**
     * @param string $AMAZON02_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZON02_SO_Qty_Adv($AMAZON02_SO_Qty_Adv)
    {
      $this->AMAZON02_SO_Qty_Adv = $AMAZON02_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBOM7_SO_Qty()
    {
      return $this->AMAZONBOM7_SO_Qty;
    }

    /**
     * @param float $AMAZONBOM7_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBOM7_SO_Qty($AMAZONBOM7_SO_Qty)
    {
      $this->AMAZONBOM7_SO_Qty = $AMAZONBOM7_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getAMAZONBOM7_PO_Qty()
    {
      return $this->AMAZONBOM7_PO_Qty;
    }

    /**
     * @param float $AMAZONBOM7_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBOM7_PO_Qty($AMAZONBOM7_PO_Qty)
    {
      $this->AMAZONBOM7_PO_Qty = $AMAZONBOM7_PO_Qty;
      return $this;
    }

    /**
     * @return string
     */
    public function getAMAZONBOM7_SO_Qty_Adv()
    {
      return $this->AMAZONBOM7_SO_Qty_Adv;
    }

    /**
     * @param string $AMAZONBOM7_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setAMAZONBOM7_SO_Qty_Adv($AMAZONBOM7_SO_Qty_Adv)
    {
      $this->AMAZONBOM7_SO_Qty_Adv = $AMAZONBOM7_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getNOONDXB07_Inventory()
    {
      return $this->NOONDXB07_Inventory;
    }

    /**
     * @param float $NOONDXB07_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setNOONDXB07_Inventory($NOONDXB07_Inventory)
    {
      $this->NOONDXB07_Inventory = $NOONDXB07_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getNOONDXB07_SO_Qty()
    {
      return $this->NOONDXB07_SO_Qty;
    }

    /**
     * @param float $NOONDXB07_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setNOONDXB07_SO_Qty($NOONDXB07_SO_Qty)
    {
      $this->NOONDXB07_SO_Qty = $NOONDXB07_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getNOONDXB07_PO_Qty()
    {
      return $this->NOONDXB07_PO_Qty;
    }

    /**
     * @param float $NOONDXB07_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setNOONDXB07_PO_Qty($NOONDXB07_PO_Qty)
    {
      $this->NOONDXB07_PO_Qty = $NOONDXB07_PO_Qty;
      return $this;
    }

    /**
     * @return string
     */
    public function getNOONDXB07_SO_Qty_Adv()
    {
      return $this->NOONDXB07_SO_Qty_Adv;
    }

    /**
     * @param string $NOONDXB07_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setNOONDXB07_SO_Qty_Adv($NOONDXB07_SO_Qty_Adv)
    {
      $this->NOONDXB07_SO_Qty_Adv = $NOONDXB07_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getCASHNCARRY_SO_Qty()
    {
      return $this->CASHNCARRY_SO_Qty;
    }

    /**
     * @param float $CASHNCARRY_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCASHNCARRY_SO_Qty($CASHNCARRY_SO_Qty)
    {
      $this->CASHNCARRY_SO_Qty = $CASHNCARRY_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getCASHNCARRY_PO_Qty()
    {
      return $this->CASHNCARRY_PO_Qty;
    }

    /**
     * @param float $CASHNCARRY_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCASHNCARRY_PO_Qty($CASHNCARRY_PO_Qty)
    {
      $this->CASHNCARRY_PO_Qty = $CASHNCARRY_PO_Qty;
      return $this;
    }

    /**
     * @return string
     */
    public function getCASHNCARRY_SO_Qty_Adv()
    {
      return $this->CASHNCARRY_SO_Qty_Adv;
    }

    /**
     * @param string $CASHNCARRY_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setCASHNCARRY_SO_Qty_Adv($CASHNCARRY_SO_Qty_Adv)
    {
      $this->CASHNCARRY_SO_Qty_Adv = $CASHNCARRY_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getBANGALORE_SO_Qty()
    {
      return $this->BANGALORE_SO_Qty;
    }

    /**
     * @param float $BANGALORE_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setBANGALORE_SO_Qty($BANGALORE_SO_Qty)
    {
      $this->BANGALORE_SO_Qty = $BANGALORE_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getBANGALORE_Inventory()
    {
      return $this->BANGALORE_Inventory;
    }

    /**
     * @param float $BANGALORE_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setBANGALORE_Inventory($BANGALORE_Inventory)
    {
      $this->BANGALORE_Inventory = $BANGALORE_Inventory;
      return $this;
    }

    /**
     * @return string
     */
    public function getBANGALORE_SO_Qty_Adv()
    {
      return $this->BANGALORE_SO_Qty_Adv;
    }

    /**
     * @param string $BANGALORE_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setBANGALORE_SO_Qty_Adv($BANGALORE_SO_Qty_Adv)
    {
      $this->BANGALORE_SO_Qty_Adv = $BANGALORE_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getBANGALORE_PO_Qty()
    {
      return $this->BANGALORE_PO_Qty;
    }

    /**
     * @param float $BANGALORE_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setBANGALORE_PO_Qty($BANGALORE_PO_Qty)
    {
      $this->BANGALORE_PO_Qty = $BANGALORE_PO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getECOMMERCE_Inventory()
    {
      return $this->ECOMMERCE_Inventory;
    }

    /**
     * @param float $ECOMMERCE_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setECOMMERCE_Inventory($ECOMMERCE_Inventory)
    {
      $this->ECOMMERCE_Inventory = $ECOMMERCE_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getECOMMERCE_SO_Qty()
    {
      return $this->ECOMMERCE_SO_Qty;
    }

    /**
     * @param float $ECOMMERCE_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setECOMMERCE_SO_Qty($ECOMMERCE_SO_Qty)
    {
      $this->ECOMMERCE_SO_Qty = $ECOMMERCE_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getECOMMERCE_PO_Qty()
    {
      return $this->ECOMMERCE_PO_Qty;
    }

    /**
     * @param float $ECOMMERCE_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setECOMMERCE_PO_Qty($ECOMMERCE_PO_Qty)
    {
      $this->ECOMMERCE_PO_Qty = $ECOMMERCE_PO_Qty;
      return $this;
    }

    /**
     * @return string
     */
    public function getECOMMERCE_SO_Qty_Adv()
    {
      return $this->ECOMMERCE_SO_Qty_Adv;
    }

    /**
     * @param string $ECOMMERCE_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setECOMMERCE_SO_Qty_Adv($ECOMMERCE_SO_Qty_Adv)
    {
      $this->ECOMMERCE_SO_Qty_Adv = $ECOMMERCE_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getMUMBAI_PUN_Inventory()
    {
      return $this->MUMBAI_PUN_Inventory;
    }

    /**
     * @param float $MUMBAI_PUN_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUMBAI_PUN_Inventory($MUMBAI_PUN_Inventory)
    {
      $this->MUMBAI_PUN_Inventory = $MUMBAI_PUN_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getMUMBAI_PUN_SO_Qty()
    {
      return $this->MUMBAI_PUN_SO_Qty;
    }

    /**
     * @param float $MUMBAI_PUN_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUMBAI_PUN_SO_Qty($MUMBAI_PUN_SO_Qty)
    {
      $this->MUMBAI_PUN_SO_Qty = $MUMBAI_PUN_SO_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getMUMBAI_PUN_PO_Qty()
    {
      return $this->MUMBAI_PUN_PO_Qty;
    }

    /**
     * @param float $MUMBAI_PUN_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUMBAI_PUN_PO_Qty($MUMBAI_PUN_PO_Qty)
    {
      $this->MUMBAI_PUN_PO_Qty = $MUMBAI_PUN_PO_Qty;
      return $this;
    }

    /**
     * @return string
     */
    public function getMUMBAI_PUN_SO_Qty_Adv()
    {
      return $this->MUMBAI_PUN_SO_Qty_Adv;
    }

    /**
     * @param string $MUMBAI_PUN_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUMBAI_PUN_SO_Qty_Adv($MUMBAI_PUN_SO_Qty_Adv)
    {
      $this->MUMBAI_PUN_SO_Qty_Adv = $MUMBAI_PUN_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getMUMBAI_SHO_Inventory()
    {
      return $this->MUMBAI_SHO_Inventory;
    }

    /**
     * @param float $MUMBAI_SHO_Inventory
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUMBAI_SHO_Inventory($MUMBAI_SHO_Inventory)
    {
      $this->MUMBAI_SHO_Inventory = $MUMBAI_SHO_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getMUMBAI_SHO_SO_Qty()
    {
      return $this->MUMBAI_SHO_SO_Qty;
    }

    /**
     * @param float $MUMBAI_SHO_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUMBAI_SHO_SO_Qty($MUMBAI_SHO_SO_Qty)
    {
      $this->MUMBAI_SHO_SO_Qty = $MUMBAI_SHO_SO_Qty;
      return $this;
    }

    /**
     * @return string
     */
    public function getMUMBAI_SHO_SO_Qty_Adv()
    {
      return $this->MUMBAI_SHO_SO_Qty_Adv;
    }

    /**
     * @param string $MUMBAI_SHO_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUMBAI_SHO_SO_Qty_Adv($MUMBAI_SHO_SO_Qty_Adv)
    {
      $this->MUMBAI_SHO_SO_Qty_Adv = $MUMBAI_SHO_SO_Qty_Adv;
      return $this;
    }

    /**
     * @return float
     */
    public function getMUMBAI_SHO_PO_Qty()
    {
      return $this->MUMBAI_SHO_PO_Qty;
    }

    /**
     * @param float $MUMBAI_SHO_PO_Qty
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setMUMBAI_SHO_PO_Qty($MUMBAI_SHO_PO_Qty)
    {
      $this->MUMBAI_SHO_PO_Qty = $MUMBAI_SHO_PO_Qty;
      return $this;
    }
    public function setLocationSpecificStock($source,$stock,$blocked,$intransit=0,$intransit_blocked=0)
    {
        $this->locationSpecificStock[] = (Object)[
            "id" => $source->id,
            "type" => (new \ReflectionClass($source))->getShortName(),
            "name" => $source->name,
            "display_name" => $source->display_name,
            "inventory" => $stock,
            "blocked" => $blocked,
            "intransit" => $intransit,
            "intransit_blocked"  => $intransit_blocked,
        ];
    }

        /**
     * @return float
     */
    public function getHOMESTORE_Inventory()
    {
      return $this->HOMESTORE_Inventory;
    }

    /**
     * @param float $HOMESTORE_Inventory
     * @return \Rosetta\NAV\WS\ItemCard\Item_Card
     */
    public function setHOMESTORE_Inventory($HOMESTORE_Inventory)
    {
      $this->HOMESTORE_Inventory = $HOMESTORE_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getHOMESTORE_SO_QTY()
    {
      return $this->HOMESTORE_SO_QTY;
    }

    /**
     * @param float $HOMESTORE_SO_QTY
     * @return \Rosetta\NAV\WS\ItemCard\Item_Card
     */
    public function setHOMESTORE_SO_QTY($HOMESTORE_SO_QTY)
    {
      $this->HOMESTORE_SO_QTY = $HOMESTORE_SO_QTY;
      return $this;
    }

     /**
     * @return float
     */
    public function getHOMESTORE_SO_QTY_Inv()
    {
      return $this->HOMESTORE_SO_QTY_Inv;
    }

    /**
     * @param float $HOMESTORE_SO_QTY_Inv
     * @return \Rosetta\NAV\WS\ItemCard\Item_Card
     */
    public function setHOMESTORE_SO_QTY_Inv($HOMESTORE_SO_QTY_Inv)
    {
      $this->HOMESTORE_SO_QTY_Inv = $HOMESTORE_SO_QTY_Inv;
      return $this;
    }

    /**
     * @return float
     */
    public function getHOMESTORE_PO_QTY()
    {
      return $this->HOMESTORE_PO_QTY;
    }

    /**
     * @param float $HOMESTORE_SO_QTY
     * @return \Rosetta\NAV\WS\ItemCard\Item_Card
     */
    public function setHOMESTORE_PO_QTY($HOMESTORE_PO_QTY)
    {
      $this->HOMESTORE_PO_QTY = $HOMESTORE_PO_QTY;
      return $this;
    }
    

    /**
     * @return float
     */
    public function getHOMESTORE_SO_Qty_Adv()
    {
      return $this->HOMESTORE_SO_Qty_Adv;
    }

    /**
     * @param float $HOMESTORE_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setHOMESTORE_SO_Qty_Adv($HOMESTORE_SO_Qty_Adv)
    {
      $this->HOMESTORE_SO_Qty_Adv = $HOMESTORE_SO_Qty_Adv;
      return $this;
    }    

    /**
     * @return float
     */
    public function getBHIWANDI_Inventory()
    {
      return $this->BHIWANDI_Inventory;
    }

    /**
     * @param float $BHIWANDI_Inventory
     * @return \Rosetta\NAV\WS\ItemCard\Item_Card
     */
    public function setBHIWANDI_Inventory($BHIWANDI_Inventory)
    {
      $this->BHIWANDI_Inventory = $BHIWANDI_Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getBHIWANDI_SO_Qty()
    {
      return $this->BHIWANDI_SO_Qty;
    }

    /**
     * @param float $BHIWANDI_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCard\Item_Card
     */
    public function setBHIWANDI_SO_Qty($BHIWANDI_SO_Qty)
    {
      $this->BHIWANDI_SO_Qty = $BHIWANDI_SO_Qty;
      return $this;
    } 

    public function getBHIWANDI_PO_Qty()
    {
      return $this->BHIWANDI_PO_Qty;
    }

    /**
     * @param float $BHIWANDI_SO_Qty
     * @return \Rosetta\NAV\WS\ItemCard\Item_Card
     */
    public function setBHIWANDI_PO_Qty($BHIWANDI_PO_Qty)
    {
      $this->BHIWANDI_PO_Qty = $BHIWANDI_PO_Qty;
      return $this;
    } 


    /**
     * @return float
     */
    public function getBHIWANDI_SO_Qty_Adv()
    {
      return $this->BHIWANDI_SO_Qty_Adv;
    }

    /**
     * @param float $BHIWANDI_SO_Qty_Adv
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setBHIWANDI_SO_Qty_Adv($BHIWANDI_SO_Qty_Adv)
    {
      $this->BHIWANDI_SO_Qty_Adv = $BHIWANDI_SO_Qty_Adv;
      return $this;
    }      

    public function getLocationSpecificStock()
    {
        return $this->locationSpecificStock;
    }

    /**
     * @return boolean
     */
    public function getIntransitBlocked()
    {
      return $this->IntransitBlocked;
    }

    /**
     * @param boolean $IntransitBlocked
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit
     */
    public function setIntransitBlocked($IntransitBlocked)
    {
      $this->IntransitBlocked = $IntransitBlocked;
      return $this;
    }
}
