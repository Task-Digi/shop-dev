<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Artisan;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Doing migration using url
     *
     * @author A
     * @return void|object
     */
    function migration($filter = 'config')
    {
        try {
            ini_set('max_execution_time', 300);
            if ($filter == 'config') {
                echo '<br>Initilize the config command process...';
                Artisan::call('config:clear');
                Artisan::call('cache:clear');
                Artisan::call('view:clear');
                Artisan::call('route:clear');
                Artisan::call('config:cache');
                echo '<br>Config commands ran and completed successfully...';
            } elseif ($filter == 'drop') {
                echo '<br>Initilize the application\'s table drop and migarate process...';
                Artisan::call('migrate:fresh', ['--force'=>true]);
                echo '<br>Table migration process completed successfully...';
            } elseif ($filter == 'migrate') {
                echo '<br>Initilize the application\'s table migration process...';
                Artisan::call('migrate', array('--path' => 'database/migrations', '--force'=>true));
                echo '<br>Table migration process completed successfully...';
            } elseif ($filter == 'seed') {
                echo '<br>Initilize the table\'s seeding process...';
                Artisan::call(
                    'db:seed',
                    [
                        '--force' => true
                    ]
                );
                echo '<br>Table seeding process completed successfully';

                echo '<br>All Process completed successfully...';
                return redirect()->route('login');
            }
        } catch (\Exception $e) {
            echo '<br>'.$e->getMessage();
//            Response::make($e->getMessage(), 500);
        }
    }
}
