<?php
namespace Rosetta\Transformers\User;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Setting;
use Rosetta\Models\User;

class NoticeTransformer extends TransformerAbstract
{
	public function transform(User $user)
	{
		$notice = Setting::where('type','=','notice')->where('key','=','notice')->first();
		if(!$notice)
		{
			return [];
		}
		$tenant_notice = json_decode($notice->value);
		return ['message' => $tenant_notice->message];
	}
}