<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Prospect;

class ProspectTransformer extends TransformerAbstract
{
	public function transform(Prospect $prospect)
	{
		return [
			"id"         => $prospect->id,
			"name"       => $prospect->name,
			"business_name"      => $prospect->business_name,
			"type"      => $prospect->type,
			"email"      => $prospect->email,
			"phone"      => $prospect->phone,
			"password"      => $prospect->password,
			"receive_updates"     => (bool)$prospect->receive_updates,
			"gst_no"      => $prospect->gst_no,
			"pan"      => $prospect->pan,
			"street"      => $prospect->street,
			"floor"      => $prospect->floor_unit,
			"city"      => $prospect->city,
			"state"      => $prospect->state,
			"post_code"      => $prospect->post_code,
			"country_code"      => $prospect->country_code
		];
	}
}//#785