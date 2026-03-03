<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Role;

class UsersTableSeeder extends Seeder
{
    public function run(){
        
            $role_admin = Role::where('name', 'admin')->first();
    $role_user  = Role::where('name', 'user')->first();
    $admin = new User();
    $admin->name = 'Admin Name';
    $admin->email = 'admin@example.com';
    $admin->password = bcrypt('123456');
    $admin->save();
    $admin->roles()->attach($role_admin);
    $user = new User();
    $user->name = 'User Name';
    $user->email = 'user@example.com';
    $user->password = bcrypt('12345678');
    $user->save();
    $user->roles()->attach($role_user);
    }
}
