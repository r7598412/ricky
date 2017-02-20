<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Chart_temptable extends Model
{
    //
	//use SoftDeletes;
	protected $connection = 'mysql9';
	public $table = 'tempTable_final';
	public $timestamps = false;



	 public function package(){
		 return $this->hasOne('\App\Chart_package', 'package_id', 'package_id');
	 }
	
	// public function model(){
	// 	 return $this->hasOne('\App\Chart_model', 'subcategory_id', 'subcategory_id')->where('facility_id',$this->facility_id);
	//  }
	
	 public function countrya(){
		 return $this->hasOne('\App\Chart_country', 'qnap_country', 'country');
	 }



	

}

