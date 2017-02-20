<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class User_qdata_relation extends Model {

	public $timestamps = false;
	protected $table = 'user_qdata_relation';
	protected $fillable = ['user_id', 'qdata_id', 'relation'];

}
