<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Rosetta\Models\Permission;

class PermissionGroup extends Model
{
    protected $table = "permission_groups";
    protected $dateFormat = 'U';
    public function permissions()
    {
    	return $this->hasMany(Permission::class);
    }
}
