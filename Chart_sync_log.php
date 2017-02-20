<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Chart_sync_log extends Model
{
    //
	//use SoftDeletes;
	protected $connection = 'mysql9';
	public $table = 'sync_log';
	public $timestamps = true;
	protected $guarded =['id'];

	

}

