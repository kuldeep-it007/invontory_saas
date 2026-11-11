<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddSpatieRolesPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $superadmin_role = Role::create(['name' => 'superadmin']);
        $companyAdmin_role = Role::create(['name' => 'companyAdmin']);
        $companySalesRep_role = Role::create(['name' => 'companySalesRep']);
        $dealerAdmin_role = Role::create(['name' => 'dealerAdmin']);
        $dealerSalesRep_role = Role::create(['name' => 'dealerSalesRep']);
        $distributorAdmin_role = Role::create(['name' => 'distributorAdmin']);
        $distributorSalesRep_role = Role::create(['name' => 'distributorSalesRep']);

        $p1 = Permission::create(['name' => 'regions']);
        $p2 = Permission::create(['name' => 'companies']);
    //    $p3 = Permission::create(['name' => 'customers']);
        $p4 = Permission::create(['name' => 'tax_type_lookup']);
        $p5 = Permission::create(['name' => 'createDealers']);
        $p6 = Permission::create(['name' => 'updateDealers']);
        $p7 = Permission::create(['name' => 'deleteDealers']);
        $p8 = Permission::create(['name' => 'viewDealers']);
        $p9 = Permission::create(['name' => 'createDistributors']);
        $p10 = Permission::create(['name' => 'updateDistributors']);
        $p11 = Permission::create(['name' => 'deleteDistributors']);
        $p12 = Permission::create(['name' => 'viewDistributors']);
        $p13 = Permission::create(['name' => 'approveDealers']);
        $p14 = Permission::create(['name' => 'rejectDealers']);
        $p15 = Permission::create(['name' => 'viewProducts']);
        $p16 = Permission::create(['name' => 'orderProducts']);
        $p17 = Permission::create(['name' => 'viewDiscounts']);
        $p18 = Permission::create(['name' => 'manageDiscounts']);
        $p19 = Permission::create(['name' => 'manageBlockedItems']);
        $p20 = Permission::create(['name' => 'manageProducts']);
        $p21 = Permission::create(['name' => 'viewCategories']);
        $p22 = Permission::create(['name' => 'createCategories']);
        $p23 = Permission::create(['name' => 'viewCollections']);
        $p24 = Permission::create(['name' => 'createCollections']);
        $p25 = Permission::create(['name' => 'viewColours']);
        $p26 = Permission::create(['name' => 'createColours']);
        $p27 = Permission::create(['name' => 'viewUser']);
        $p28 = Permission::create(['name' => 'updateUser']);
        $p29 = Permission::create(['name' => 'createUser']);
        $p30 = Permission::create(['name' => 'deleteUser']);
        $p31 = Permission::create(['name' => 'manageProfile']);
      //  $p32 = Permission::create(['name' => 'viewBackOrders']);
      //  $p33 = Permission::create(['name' => 'createBackOrders']);
     //   $p34 = Permission::create(['name' => 'updateBackOrders']);
       // $p35 = Permission::create(['name' => 'deleteBackOrderLines']);
     //   $p36 = Permission::create(['name' => 'deleteBackOrderLineItems']);
       // $p37 = Permission::create(['name' => 'deleteBackOrder']);
        $p38 = Permission::create(['name' => 'viewPurchaseOrders']);
        $p39 = Permission::create(['name' => 'updatePurchaseLines']);
        $p40 = Permission::create(['name' => 'deletePurchaseLines']);
        $p41 = Permission::create(['name' => 'viewAdvanceOrders']);
        $p42 = Permission::create(['name' => 'createAdvanceOrders']);
        $p43 = Permission::create(['name' => 'updateAdvanceOrders']);
        $p44 = Permission::create(['name' => 'updateAdvanceLines']);
        $p45 = Permission::create(['name' => 'deleteAdvanceLines']);
        $p46 = Permission::create(['name' => 'deleteAdvanceItems']);
        $p47 = Permission::create(['name' => 'createRetailCustomers']);
        $p48 = Permission::create(['name' => 'updateRetailCustomers']);
        $p49 = Permission::create(['name' => 'deleteRetailCustomers']);
        $p50 = Permission::create(['name' => 'viewRetailCustomers']);
        $p51 = Permission::create(['name' => 'sendNotification']);
        $p52 = Permission::create(['name' => 'viewCompanyStock']);
        $p53 = Permission::create(['name' => 'viewAlternateStock']);
      //  $p54 = Permission::create(['name' => 'deleteAdvanceItems']);
        $p55 = Permission::create(['name' => 'updateAdvanceItems']);
        $p56 = Permission::create(['name' => 'viewAdvanceMessages']);
        $p57 = Permission::create(['name' => 'sendAdvanceMessages']);
        $p58 = Permission::create(['name' => 'viewActiveProductAttributes']);
        $p59 = Permission::create(['name' => 'addActiveProductAttributes']);
        $p60 = Permission::create(['name' => 'updateActiveProductAttributes']);
        $p61 = Permission::create(['name' => 'deleteActiveProductAttributes']);
        $p62 = Permission::create(['name' => 'viewCustomerDiscounts']);
        $p63 = Permission::create(['name' => 'manageCustomerDiscounts']);
        $p64 = Permission::create(['name' => 'viewSalesOrders']);
        $p65 = Permission::create(['name' => 'unBlockMyStock']);
        $p66 = Permission::create(['name' => 'addStock']);
        $p67 = Permission::create(['name' => 'manageReturns']);
        $p68 = Permission::create(['name' => 'viewStockPoints']);
        $p69 = Permission::create(['name' => 'addStockPoint']);
        $p70 = Permission::create(['name' => 'viewStockPointCustomers']);
        $p71 = Permission::create(['name' => 'deleteStockPointCustomers']);
        $p72 = Permission::create(['name' => 'addStockPointCustomers']);
        $p73 = Permission::create(['name' => 'viewDudBlockedItems']);
        $p74 = Permission::create(['name' => 'manageCoDistributorDiscounts']);
        $p75 = Permission::create(['name' => 'manageCoDistributors']);
        $p76 = Permission::create(['name' => 'manageCoDistributorOrders']);
        $p77 = Permission::create(['name' => 'addCoDistributorDiscounts']);
        $p78 = Permission::create(['name' => 'deleteCoDistributorDiscounts']);
        $p79 = Permission::create(['name' => 'viewCLRates']);
        $p80 = Permission::create(['name' => 'addCLRates']);
        $p81 = Permission::create(['name' => 'deleteCLRates']);
        $p82 = Permission::create(['name' => 'viewProductPrices']);
        $p83 = Permission::create(['name' => 'addProductPrices']);
        $p84 = Permission::create(['name' => 'editProductPrices']);
        $p85 = Permission::create(['name' => 'impersonate']);
        $p86 = Permission::create(['name' => 'updateStock']);
        $p87 = Permission::create(['name' => 'deleteStock']);
        $p88 = Permission::create(['name' => 'viewTaxes']);
        $p89 = Permission::create(['name' => 'addTaxes']);
        $p90 = Permission::create(['name' => 'updateTaxes']);
        $p91 = Permission::create(['name' => 'deleteTaxes']);
        $p92 = Permission::create(['name' => 'viewOnlineDiscounts']);
        $p93 = Permission::create(['name' => 'deleteOnlineDiscount']);
        $p94 = Permission::create(['name' => 'addOnlineDiscount']);
        $p95 = Permission::create(['name' => 'manageTransactions']);
        $p96 = Permission::create(['name' => 'viewInvoices']);
        $p97 = Permission::create(['name' => 'printInvoices']);

      //  $superadmin_role->givePermissionTo(Permission::all());
        $superadmin_role->givePermissionTo([$p1, $p2, $p4, $p5, $p6, $p7, $p8, $p9, $p10, $p11, $p12, $p13, $p14, $p15, $p17, $p18, $p19, $p20, $p21, $p22, $p23, $p24, $p25, $p26, $p27, $p28, $p29, $p30, $p31, $p38, $p39, $p40, $p41, $p43, $p44, $p45, $p46, $p47, $p48, $p49, $p50, $p51, $p55, $p56, $p57, $p58, $p59, $p60, $p61, $p66, $p68, $p69, $p70, $p71, $p72, $p74, $p75, $p77, $p78, $p79, $p80, $p81, $p82, $p83, $p84, $p85, $p86, $p87, $p88, $p89, $p90, $p91, $p92, $p93, $p94, $p95, $p96, $p97]);
        $companyAdmin_role->givePermissionTo([$p5, $p6, $p7, $p8, $p9, $p10, $p11, $p12, $p13, $p14, $p15, $p19, $p21, $p23, $p25, $p27, $p28, $p29, $p30, $p31, $p38, $p39, $p40, $p41, $p43, $p44, $p45, $p46, $p47, $p48, $p49, $p50, $p51, $p52, $p55, $p56, $p57, $p58, $p59, $p60, $p61, $p66, $p68, $p69, $p70, $p71, $p72, $p74, $p75, $p77, $p78, $p79, $p80, $p81, $p82, $p83, $p84, $p85, $p86, $p87, $p88, $p89, $p90, $p91, $p92, $p93, $p94, $p95, $p96, $p97]);
        $companySalesRep_role->givePermissionTo([$p15, $p21, $p23, $p25, $p52, $p68, $p69, $p70, $p71, $p72]);  
        $distributorAdmin_role->givePermissionTo([$p5, $p6, $p7, $p8, $p12, $p16, $p27, $p28, $p29, $p30, $p31, $p38, $p41, $p42, $p46, $p53, $p56, $p57, $p62, $p63, $p64, $p65, $p66, $p67, $p73, $p74, $p75, $p76, $p79, $p80, $p81]);
        $distributorSalesRep_role->givePermissionTo([$p76, $p79]);
        $dealerAdmin_role->givePermissionTo([$p8, $p16, $p27, $p28, $p29, $p30, $p31, $p38, $p41, $p42, $p46, $p53, $p56, $p57]);
        $dealerSalesRep_role->givePermissionTo([]);
        
       //superadmin - $p16, $p42, $p52, $p53, $p62, $p63, $p64, $p65, $p67, $p73, $p76
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
