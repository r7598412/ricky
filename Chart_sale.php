<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Chart_sale extends Model
{
    //
	//use SoftDeletes;
	protected $connection = 'mysql9';
	public $table = 'sale';
	public $timestamps = false;

	protected $guarded =['id'];





	

}

