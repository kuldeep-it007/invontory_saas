<?php
namespace Rosetta\Services\OnlineDiscounts;

use Rosetta\Models\User;
use Rosetta\Models\Company;
use Rosetta\Models\OnlineDiscount;
use Rosetta\Models\Product\Product;

Interface OnlineDiscountsServiceInterface
{
    public function getAllForCompany(Company $company, Array $filters = []);

    /**
     * Undocumented function
     *
     * @param User $user
     * @param Company $company
     * @param integer $discountID
     * @throws ModelNotFoundException
     * @return void
     */
    public function delete(User $user, Company $company,int $discountID);

    /**
     * Create Online Discount
     *
     * @param User $user
     * @param Company $company
     * @param Array $params
     * @return OnlineDiscount
     */
    public function create(User $user, Company $company,Array $params);

    /**
     * Update Online Discount
     *
     * @param User $user
     * @param Company $company
     * @param int $discountID
     * @param Array $params
     * @return OnlineDiscount
     */
    public function update(User $user, Company $company,int $discountID,Array $params);

    /**
     * Return online discount for a product
     *
     * @param Company $company
     * @param Product $product
     * @return float
     */
    public function getDiscount(Company $company,Product $product) : float;
}