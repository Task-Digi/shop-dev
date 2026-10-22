<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Role;

class User extends Authenticatable
{
  use Notifiable;

  public function roles()
  {
    return $this->belongsToMany(Role::class);
  }

  protected $table = 'users';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'name',
    'email',
    'password',
    'is_admin',
    'is_active',
  ];


  /**
   * The attributes that should be hidden for arrays.
   *
   * @var array
   */
  protected $hidden = [
    'password',
    'remember_token',
  ];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = [
    'email_verified_at' => 'datetime',
    'password' => 'hashed',
    'is_admin' => 'boolean',
    'is_active' => 'boolean',
  ];

  public function services()
  {
    return $this->belongsToMany(Service::class)->withTimestamps();
  }

  public function canAccessService(string $service): bool
  {
    if (!$this->is_active) {
      return false;
    }

    return $this->is_admin || $this->services()->where('slug', $service)->exists();
  }

  public function authorizeRoles($roles)

  {
    if ($this->hasAnyRole($roles)) {
      return true;
    }
    abort(401, 'This action is unauthorized.');
  }
  public function hasAnyRole($roles)
  {
    if (is_array($roles)) {
      foreach ($roles as $role) {
        if ($this->hasRole($role)) {
          return true;
        }
      }
    } else {
      if ($this->hasRole($roles)) {
        return true;
      }
    }
    return false;
  }
  public function hasRole($role)
  {
    if ($this->roles()->where('name', $role)->first()) {
      return true;
    }
    return false;
  }
}
