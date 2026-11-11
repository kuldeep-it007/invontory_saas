<?php


 function autoload_976d1aeaf1722c0397b5a38f769f9249($class)
{
    $classes = array(
        'Rosetta\NAV\WS\Customer\Customer_Card_Service' => __DIR__ .'/Customer_Card_Service.php',
        'Rosetta\NAV\WS\Customer\Blocked' => __DIR__ .'/Blocked.php',
        'Rosetta\NAV\WS\Customer\Copy_Sell_to_Addr_to_Qte_From' => __DIR__ .'/Copy_Sell_to_Addr_to_Qte_From.php',
        'Rosetta\NAV\WS\Customer\Application_Method' => __DIR__ .'/Application_Method.php',
        'Rosetta\NAV\WS\Customer\Partner_Type' => __DIR__ .'/Partner_Type.php',
        'Rosetta\NAV\WS\Customer\Reserve' => __DIR__ .'/Reserve.php',
        'Rosetta\NAV\WS\Customer\Shipping_Advice' => __DIR__ .'/Shipping_Advice.php',
        'Rosetta\NAV\WS\Customer\GST_Customer_Type' => __DIR__ .'/GST_Customer_Type.php',
        'Rosetta\NAV\WS\Customer\GST_Registration_Type' => __DIR__ .'/GST_Registration_Type.php',
        'Rosetta\NAV\WS\Customer\P_A_N_Status' => __DIR__ .'/P_A_N_Status.php',
        'Rosetta\NAV\WS\Customer\Nature_of_Services' => __DIR__ .'/Nature_of_Services.php',
        'Rosetta\NAV\WS\Customer\Customer_Card' => __DIR__ .'/Customer_Card.php',
        'Rosetta\NAV\WS\Customer\Customer_Card_List' => __DIR__ .'/Customer_Card_List.php',
        'Rosetta\NAV\WS\Customer\Customer_Card_Fields' => __DIR__ .'/Customer_Card_Fields.php',
        'Rosetta\NAV\WS\Customer\Customer_Card_Filter' => __DIR__ .'/Customer_Card_Filter.php',
        'Rosetta\NAV\WS\Customer\Read' => __DIR__ .'/Read.php',
        'Rosetta\NAV\WS\Customer\Read_Result' => __DIR__ .'/Read_Result.php',
        'Rosetta\NAV\WS\Customer\ReadByRecId' => __DIR__ .'/ReadByRecId.php',
        'Rosetta\NAV\WS\Customer\ReadByRecId_Result' => __DIR__ .'/ReadByRecId_Result.php',
        'Rosetta\NAV\WS\Customer\ReadMultiple' => __DIR__ .'/ReadMultiple.php',
        'Rosetta\NAV\WS\Customer\ReadMultiple_Result' => __DIR__ .'/ReadMultiple_Result.php',
        'Rosetta\NAV\WS\Customer\IsUpdated' => __DIR__ .'/IsUpdated.php',
        'Rosetta\NAV\WS\Customer\IsUpdated_Result' => __DIR__ .'/IsUpdated_Result.php',
        'Rosetta\NAV\WS\Customer\GetRecIdFromKey' => __DIR__ .'/GetRecIdFromKey.php',
        'Rosetta\NAV\WS\Customer\GetRecIdFromKey_Result' => __DIR__ .'/GetRecIdFromKey_Result.php',
        'Rosetta\NAV\WS\Customer\Create' => __DIR__ .'/Create.php',
        'Rosetta\NAV\WS\Customer\Create_Result' => __DIR__ .'/Create_Result.php',
        'Rosetta\NAV\WS\Customer\CreateMultiple' => __DIR__ .'/CreateMultiple.php',
        'Rosetta\NAV\WS\Customer\CreateMultiple_Result' => __DIR__ .'/CreateMultiple_Result.php',
        'Rosetta\NAV\WS\Customer\Update' => __DIR__ .'/Update.php',
        'Rosetta\NAV\WS\Customer\Update_Result' => __DIR__ .'/Update_Result.php',
        'Rosetta\NAV\WS\Customer\UpdateMultiple' => __DIR__ .'/UpdateMultiple.php',
        'Rosetta\NAV\WS\Customer\UpdateMultiple_Result' => __DIR__ .'/UpdateMultiple_Result.php',
        'Rosetta\NAV\WS\Customer\Delete' => __DIR__ .'/Delete.php',
        'Rosetta\NAV\WS\Customer\Delete_Result' => __DIR__ .'/Delete_Result.php'
    );
    if (!empty($classes[$class])) {
        include $classes[$class];
    };
}

spl_autoload_register('autoload_976d1aeaf1722c0397b5a38f769f9249');

// Do nothing. The rest is just leftovers from the code generation.
{
}
