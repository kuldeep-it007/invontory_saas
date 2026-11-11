<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Notification;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Traits\Author;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements Auditable, JWTSubject
{
    use HasRoles;
    use \OwenIt\Auditing\Auditable;
    use Notifiable; 
    use Author;
    //use EntrustUserTrait { restore as private restoreA; }
    use SoftDeletes { restore as private restoreB; }

    public function restore()
    {
        $this->restoreA();
        $this->restoreB();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'remember_token', 'distributor_id', 'dealer_id', 'super_stockist_id', 'company_id', 'communication_email', 'created_at', 'updated_at', 'deleted_at', 'active', 'change_password', 'phone', 'is_moved_from_pd', 'is_moved_from_md'
    ];

    protected $dateFormat = 'U';
    protected $dates = ['deleted_at'];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
    public function distributor()
    {  
        return $this->belongsTo(Distributor::class);
    }
    public function superStockist()
    {  
        return $this->belongsTo(SuperStockist::class);
    }
    public function dealer()
    {  
        return $this->belongsTo(Dealer::class);
    }
    public function company()
    {  
        return $this->belongsTo(Company::class);
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return [];
    }
}
