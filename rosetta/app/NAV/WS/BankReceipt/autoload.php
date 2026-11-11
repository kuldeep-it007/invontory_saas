<?php


 function autoload_ab5ba9400d9c0055f78b5cf3b49fb66a($class)
{
    $classes = array(
        'Rosetta\NAV\WS\BankReceipt\Bankreceipt_Service' => __DIR__ .'/Bankreceipt_Service.php',
        'Rosetta\NAV\WS\BankReceipt\Document_Type' => __DIR__ .'/Document_Type.php',
        'Rosetta\NAV\WS\BankReceipt\Payment_Type' => __DIR__ .'/Payment_Type.php',
        'Rosetta\NAV\WS\BankReceipt\Service_Tax_Type' => __DIR__ .'/Service_Tax_Type.php',
        'Rosetta\NAV\WS\BankReceipt\Party_Type' => __DIR__ .'/Party_Type.php',
        'Rosetta\NAV\WS\BankReceipt\Account_Type' => __DIR__ .'/Account_Type.php',
        'Rosetta\NAV\WS\BankReceipt\Gen_Posting_Type' => __DIR__ .'/Gen_Posting_Type.php',
        'Rosetta\NAV\WS\BankReceipt\Bal_Account_Type' => __DIR__ .'/Bal_Account_Type.php',
        'Rosetta\NAV\WS\BankReceipt\Bal_Gen_Posting_Type' => __DIR__ .'/Bal_Gen_Posting_Type.php',
        'Rosetta\NAV\WS\BankReceipt\Nature_of_Supply' => __DIR__ .'/Nature_of_Supply.php',
        'Rosetta\NAV\WS\BankReceipt\GST_Group_Type' => __DIR__ .'/GST_Group_Type.php',
        'Rosetta\NAV\WS\BankReceipt\GST_Place_of_Supply' => __DIR__ .'/GST_Place_of_Supply.php',
        'Rosetta\NAV\WS\BankReceipt\GST_Customer_Type' => __DIR__ .'/GST_Customer_Type.php',
        'Rosetta\NAV\WS\BankReceipt\GST_Vendor_Type' => __DIR__ .'/GST_Vendor_Type.php',
        'Rosetta\NAV\WS\BankReceipt\Applies_to_Doc_Type' => __DIR__ .'/Applies_to_Doc_Type.php',
        'Rosetta\NAV\WS\BankReceipt\Service_Tax_Rounding_Type' => __DIR__ .'/Service_Tax_Rounding_Type.php',
        'Rosetta\NAV\WS\BankReceipt\bankreceipt' => __DIR__ .'/bankreceipt.php',
        'Rosetta\NAV\WS\BankReceipt\bankreceipt_List' => __DIR__ .'/bankreceipt_List.php',
        'Rosetta\NAV\WS\BankReceipt\bankreceipt_Fields' => __DIR__ .'/bankreceipt_Fields.php',
        'Rosetta\NAV\WS\BankReceipt\bankreceipt_Filter' => __DIR__ .'/bankreceipt_Filter.php',
        'Rosetta\NAV\WS\BankReceipt\ReadByRecId' => __DIR__ .'/ReadByRecId.php',
        'Rosetta\NAV\WS\BankReceipt\ReadByRecId_Result' => __DIR__ .'/ReadByRecId_Result.php',
        'Rosetta\NAV\WS\BankReceipt\ReadMultiple' => __DIR__ .'/ReadMultiple.php',
        'Rosetta\NAV\WS\BankReceipt\ReadMultiple_Result' => __DIR__ .'/ReadMultiple_Result.php',
        'Rosetta\NAV\WS\BankReceipt\IsUpdated' => __DIR__ .'/IsUpdated.php',
        'Rosetta\NAV\WS\BankReceipt\IsUpdated_Result' => __DIR__ .'/IsUpdated_Result.php',
        'Rosetta\NAV\WS\BankReceipt\GetRecIdFromKey' => __DIR__ .'/GetRecIdFromKey.php',
        'Rosetta\NAV\WS\BankReceipt\GetRecIdFromKey_Result' => __DIR__ .'/GetRecIdFromKey_Result.php',
        'Rosetta\NAV\WS\BankReceipt\Create' => __DIR__ .'/Create.php',
        'Rosetta\NAV\WS\BankReceipt\Create_Result' => __DIR__ .'/Create_Result.php',
        'Rosetta\NAV\WS\BankReceipt\CreateMultiple' => __DIR__ .'/CreateMultiple.php',
        'Rosetta\NAV\WS\BankReceipt\CreateMultiple_Result' => __DIR__ .'/CreateMultiple_Result.php',
        'Rosetta\NAV\WS\BankReceipt\Update' => __DIR__ .'/Update.php',
        'Rosetta\NAV\WS\BankReceipt\Update_Result' => __DIR__ .'/Update_Result.php',
        'Rosetta\NAV\WS\BankReceipt\UpdateMultiple' => __DIR__ .'/UpdateMultiple.php',
        'Rosetta\NAV\WS\BankReceipt\UpdateMultiple_Result' => __DIR__ .'/UpdateMultiple_Result.php',
        'Rosetta\NAV\WS\BankReceipt\Delete' => __DIR__ .'/Delete.php',
        'Rosetta\NAV\WS\BankReceipt\Delete_Result' => __DIR__ .'/Delete_Result.php'
    );
    if (!empty($classes[$class])) {
        include $classes[$class];
    };
}

spl_autoload_register('autoload_ab5ba9400d9c0055f78b5cf3b49fb66a');

// Do nothing. The rest is just leftovers from the code generation.
{
}
