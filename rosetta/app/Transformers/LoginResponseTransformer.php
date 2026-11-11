<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\User;
use Spatie\Permission\Models\Permission;

class LoginResponseTransformer extends TransformerAbstract
{
	public function transform(User $user)
	{
		$data = [
			"id"         => $user->id,
			"name"       => $user->name,
			"email"      => $user->email,
			"created_at" => $user->created_at->format('U'),
			"updated_at" => $user->created_at->format('U'),
			"change_password" => (bool)$user->change_password ,
		];
		if($user->distributor)
		{
			$data['member_type'] = 'Distributor';
			$data['member_id'] = $user->distributor->id;
			$data['member_name'] = $user->distributor->name;
			$data['member_nav_id'] = $user->distributor->nav_id;
			$data['company_id'] = $user->distributor->company_id;
			$data['region_id'] = $user->distributor->company->region->id;
			$data['parent_super_stockist_id'] = $user->distributor->superStockist ? $user->distributor->superStockist->id : '';
			$data['parent_super_stockist_nav_id'] = $user->distributor->superStockist ? $user->distributor->superStockist->nav_id : '';
			$data['parent_super_stockist_name'] = $user->distributor->superStockist ? $user->distributor->superStockist->name : '';
			if($user->distributor->hasPermissionTo('deny_add_to_cart'))
				$data['allow_add_to_cart'] = false;
			else
				$data['allow_add_to_cart'] = true;
			if($user->distributor->hasPermissionTo('deny_block'))
				$data['allow_block'] = false;
			else
				$data['allow_block'] = true;
			if($user->distributor->hasPermissionTo('deny_advance_ordering'))
				$data['allow_advance_ordering'] = false;
			else
				$data['allow_advance_ordering'] = true;//#807
		}
		if($user->superStockist)
		{
			$data['member_type'] = 'superStockist';
			$data['member_id'] = $user->superStockist->id;
			$data['member_name'] = $user->superStockist->name;
			$data['member_nav_id'] = $user->superStockist->nav_id;
			$data['company_id'] = $user->superStockist->company_id;
			$data['region_id'] = $user->superStockist->company->region->id;
	/*		if($user->superStockist->hasPermissionTo('deny_add_to_cart'))
				$data['allow_add_to_cart'] = false;
			else
				$data['allow_add_to_cart'] = true;
			if($user->superStockist->hasPermissionTo('deny_block'))
				$data['allow_block'] = false;
			else
				$data['allow_block'] = true;
			if($user->superStockist->hasPermissionTo('deny_advance_ordering'))
				$data['allow_advance_ordering'] = false;
			else
				$data['allow_advance_ordering'] = true;//#807*/
		}
		if($user->dealer)
		{
			if($user->dealer->type == 'Retail Customer')
			{
				$data['member_type'] = 'Retail Customer';
				$data['member_id'] = $user->dealer->id;
				$data['member_name'] = $user->dealer->name;
				$data['member_nav_id'] = $user->dealer->nav_id;
				$data['company_id'] = $user->dealer->company_id;
				$data['region_id'] = $user->dealer->company->region->id;
				$data['parent_distributor_id'] = $user->dealer->distributor ? $user->dealer->distributor->id : '';
				$data['parent_distributor_nav_id'] = $user->dealer->distributor ? $user->dealer->distributor->nav_id : '';
				$data['parent_distributor_name'] = $user->dealer->distributor ? $user->dealer->distributor->name : '';

				$data['parent_super_stockist_id'] = (!empty($user->dealer->distributor->superStockist)) ? $user->dealer->distributor->superStockist->id : '';
				$data['parent_super_stockist_nav_id'] = (!empty($user->dealer->distributor->superStockist)) ? $user->dealer->distributor->superStockist->nav_id : '';
				$data['parent_super_stockist_name'] = (!empty($user->dealer->distributor->superStockist)) ? $user->dealer->distributor->superStockist->name : '';
			}
			if($user->dealer->type == 'Dealer' or $user->dealer->type == null or $user->dealer->type == '')
			{
				$data['member_type'] = 'Dealer';
				$data['member_id'] = $user->dealer->id;
				$data['member_name'] = $user->dealer->name;
				$data['member_nav_id'] = $user->dealer->nav_id;
				$data['company_id'] = $user->dealer->company_id;
				$data['region_id'] = $user->dealer->company->region->id;
				$data['parent_distributor_id'] = (!empty($user->dealer->distributor)) ? $user->dealer->distributor->id : '';
				$data['parent_distributor_nav_id'] = (!empty($user->dealer->distributor)) ? $user->dealer->distributor->nav_id : '';
				$data['parent_distributor_name'] = (!empty($user->dealer->distributor)) ? $user->dealer->distributor->name : '';

				
				$data['parent_super_stockist_id'] = (!empty($user->dealer->distributor->superStockist)) ? $user->dealer->distributor->superStockist->id : '';
				$data['parent_super_stockist_nav_id'] = (!empty($user->dealer->distributor->superStockist)) ? $user->dealer->distributor->superStockist->nav_id : '';
				$data['parent_super_stockist_name'] = (!empty($user->dealer->distributor->superStockist)) ? $user->dealer->distributor->superStockist->name : '';
			}
		}
		if($user->company)
		{
			$data['member_type'] = 'Company';
			$data['member_id'] = $user->company->id;
			$data['member_name'] = $user->company->name;
			$data['company_id'] = $user->company->id;
			$data['region_id'] = $user->company->region->id;
		}
		return $data;
	}
}