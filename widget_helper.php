<?php

if ( !function_exists('widget_table')){
function widget_table(&$view, $model_name, $columns = [])
{
	$table = [];
	if($model_name instanceof \Illuminate\Database\Eloquent\Collection){
		$table = $model_name->toArray();
	}
	else{
		$collection = \App::Make($model_name)
			->get($columns);
		$table = $collection->toArray();
	}

	$thead = array_keys($table[0]);
	foreach( $thead as $i => $th ){
		$thead[$i] = Lang::get('table.' . $th);
		if( config('app.locale') == 'en' ){
			$thead[$i] = strtoupper(Lang::get('table.' . $th));
		}
	}

	if( isset($table[0]) ){
		$view->withTable([
			'thead' => $thead,
			'tbody' => $table
		]);
	}
	return ;
}
}
if ( !function_exists('widget_tree_table')){
function widget_tree_table(&$view, $datas, $columns = [])
{
	$thead = array_keys($datas[1]);
	foreach( $thead as $i => $th ){
		if( !in_array($th, $columns ) ){
			unset($thead[$i]);
			continue;
		}
		$thead[$i] = Lang::get('table.' . $th);
		if( config('app.locale') == 'en' ){
			$thead[$i] = strtoupper(Lang::get('table.' . $th));
		}
	}

	$outdata = [];
	foreach($datas as $index => $data){
		$outdatas[$index]['output'] = array_only($data, $columns);
		$outdatas[$index]['other'] = array_except($data, $columns);
	}

	if( isset($datas[1]) ){
		$view->withTable([
			'thead' => $thead,
			'tbody' => $outdatas
		]);
	}
	return ;
}
}
