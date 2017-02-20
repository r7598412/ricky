<?php namespace App;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

use Artesaos\Defender\Traits\HasDefenderTrait;
use Qnap\Qfms\QfmsService;

class User extends Model implements AuthenticatableContract, CanResetPasswordContract {

	use HasDefenderTrait;

	use Authenticatable, CanResetPassword;

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'users';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = ['username', 'password'];

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = ['password', 'remember_token'];

	protected $attributes = [
			'source' => 'ldap'
    ];

	public function user_meta()
	{
		// return $this->hasOne('\App\User_meta', 'username', 'username');
		return $this->hasOne('\App\User_meta', 'username', 'username');
	}

	public function scopeVip($query)
	{
		return $query->where('source', 'eloquent');
	}

	public function display_name()
	{
		if ($user_meta = $this->user_meta){
			return $user_meta->user_en_firstname . ' ' . $user_meta->user_en_lastname;
		} else {
			return $this->username;
		}
	}

	public function add_to_redmine(){
		$user = $this;
		if($user->user_meta)
			$user_meta = $user->user_meta->toArray();
		else{
			return false;
		}

		$redmine_user_data = [
				'login' => $user_meta['username'],
				'firstname' => $user_meta['user_en_firstname'],
				'lastname' => $user_meta['user_en_lastname'],
				'mail' => $user->qnap_email(),
				'auth_source_id' => 2,
			];

		$result = \Redmine::api('user')->create($redmine_user_data);
		$result = (Array) $result;

		if(isset($result['error'])){
			if(count($result['error'])==1)
			{
				\Log::error('User ' . $this->username . ' create redmine account error ' . $result['error']);
			}
			else
			{
				\Log::error('User ' . $this->username . ' create redmine account error ' . implode(',', $result['error']));
			}
			return false;
		}
		return $result;
	}


	public function qnap_email() {
		// $user_meta = $this->user_meta;
		// $email = $user_meta->username . '@qnap.com';
		// return $email;
		return $this->email;
	}

	public function redmine(){
		//return $this->hasOne('\App\RedmineUser', 'username', 'username');
		return $this->hasOne('\App\RedmineUser', 'email', 'email');
	}

	public function redmine_username(){
		return $this->hasOne('\App\RedmineUser', 'username', 'username');
		//return $this->hasOne('\App\RedmineUser', 'email', 'email');
	}

	public function mantis_handle(){
		//return $this->hasMany('\App\MantisData', 'handler_name', 'username');
		return $this->hasMany('\App\MantisData', 'email', 'email');
	}

	public function mantis_handle_time(){
		return $this->hasMany('\App\MantisData', 'email', 'email')->orderBy('date_lastupdate');
	}

	public function mantis_handle_open_time(){
		return $this->mantis_handle_time->filter(function($item){
			// return ((in_array($item->status, ['closed', 'confirmed']) === false) &&

			$show = true;
			if ($item->status == 'feedback')				
				$show = (strpos($item->reportemail, '@qnap.com') !== false);
			return ((in_array($item->status, ['closed', 'resolved', 'confirmed']) === false) &&
					$show &&
					(time_duration($item->date_lastupdate) >= (3 * 3600 * 24 + gitWeekdayShift())));
		});
	}
	
	public function mantis_handle_open_range($start,$end){
		return $this->mantis_handle_time->filter(function($item)use($start,$end){
			// return ((in_array($item->status, ['closed', 'confirmed']) === false) &&

			$show = true;
			if ($item->status == 'feedback')				
				$show = (strpos($item->reportemail, '@qnap.com') !== false);
			return ((in_array($item->status, ['closed', 'resolved', 'confirmed']) === false) &&
					$show &&
					(time_duration($item->date_lastupdate) >= ($start * 3600 * 24 + gitWeekdayShift()))
					&&
					(time_duration($item->date_lastupdate) < ($end * 3600 * 24 + gitWeekdayShift()))
					);
		});
	}

	public function mantis_handle_open(){
		return $this->mantis_handle->filter(function($item){
			// return ((in_array($item->status, ['closed', 'confirmed']) === false) &&

			$show = true;
			if ($item->status == 'feedback')				
				$show = (strpos($item->reportemail, '@qnap.com') !== false);
			return ((in_array($item->status, ['closed', 'resolved', 'confirmed']) === false) &&
					$show &&
					(time_duration($item->date_lastupdate) >= (3 * 3600 * 24 + gitWeekdayShift())));
		});
	}

	public function mantis_handle_open_or(){
		return $this->mantis_handle->filter(function($item){
			return (in_array($item->status, ['closed', 'confirmed']) === false) ;
		});
	}

	public function mantis_handle_open_quit(){
		return $this->mantis_handle_time->filter(function($item){
			$show = true;
			// if ($item->status == 'feedback')				
			// 	$show = (strpos($item->reportemail, '@qnap.com') !== false);
			return ((in_array($item->status, ['closed', 'resolved', 'confirmed']) === false) &&
					$show );
		});
	}

	public function bugzilla_user(){
		//return $this->hasOne('\App\BugzillaUser', 'username', 'username');
		return $this->hasOne('\App\BugzillaUser', 'email', 'email');
	}

	public function bugzilla_assigned_to(){
		$bugzilla_user = $this->bugzilla_user;
		if( $bugzilla_user ){
			return $bugzilla_user->assigned_to_bugs->filter(function($bug){
				return in_array($bug->status, ['NEW', 'ASSIGNED', 'REOPENED']) 
				//&&(time_duration($bug->bug_when) >= (3 * 3600 * 24 + gitWeekdayShift()))
				;
			});
		}
		else{
			return \App\BugzillaBug::find([]);
		}
	}
	
	public function bugzilla_assigned_to_range($start,$end){
		
		$bugzilla_user = $this->bugzilla_user;
		if( $bugzilla_user ){
			return $bugzilla_user->assigned_to_bugs->filter(function($bug) use($start,$end){
				return in_array($bug->status, ['NEW', 'ASSIGNED', 'REOPENED']) &&
						(time_duration($bug->bug_when) >= ($start * 3600 * 24 + gitWeekdayShift()))&&
						(time_duration($bug->bug_when) <= ($end * 3600 * 24 + gitWeekdayShift()))&&
						(strtotime($bug->eta) <= time()&&
						strtotime($bug->response) <= time()
						);
			});
			
		}
		else{
			return \App\BugzillaBug::find([]);
		}
	}
	
	public function bugzilla_assigned_to_processing(){
		
		$bugzilla_user = $this->bugzilla_user;
		if( $bugzilla_user ){
			return $bugzilla_user->assigned_to_bugs->filter(function($bug){
				return in_array($bug->status, ['NEW', 'ASSIGNED', 'REOPENED']) &&
						(strtotime($bug->eta) >= time()||
						strtotime($bug->response) >= time()
						);
			});
			
		}
		else{
			return \App\BugzillaBug::find([]);
		}
	}

	public function bugzilla_assigned_to_time(){
		$bugzilla_user = $this->bugzilla_user;
		if( $bugzilla_user ){
			return $bugzilla_user->assigned_to_bugs_time->filter(function($bug){
				return in_array($bug->status, ['NEW', 'ASSIGNED', 'REOPENED']) 
				//&&(time_duration($bug->bug_when) >= (3 * 3600 * 24 + gitWeekdayShift()))
				;
			});
		}
		else{
			return \App\BugzillaBug::find([]);
		}
	}

	public function bugzilla_assigned_to_or(){
		$bugzilla_user = $this->bugzilla_user;
		if( $bugzilla_user ){
			return $bugzilla_user->assigned_to_bugs->filter(function($bug){
				return in_array($bug->status, ['NEW', 'ASSIGNED', 'REOPENED']);
			});
		}
		else{
			return \App\BugzillaBug::find([]);
		}
	}

	public function bugzilla_report_time(){
		$bugzilla_user = $this->bugzilla_user;
		if( $bugzilla_user ){
			return $bugzilla_user->report_bugs_time->filter(function($bug){
				return in_array($bug->status, ['NeedMoreInfo','RESOLVED']) 
				//&&(time_duration($bug->bug_when) >= (3 * 3600 * 24 + gitWeekdayShift()))
						;
			});
		}
		else{
			return \App\BugzillaBug::find([]);
		}
	}
	
	public function bugzilla_report_time_range($start,$end){
		$bugzilla_user = $this->bugzilla_user;
		if( $bugzilla_user ){
			return $bugzilla_user->report_bugs_time->filter(function($bug)use($start,$end){
				return in_array($bug->status, ['NeedMoreInfo','RESOLVED']) &&
						(time_duration($bug->bug_when) >= ($start * 3600 * 24 + gitWeekdayShift()))&&
						(time_duration($bug->bug_when) <= ($end * 3600 * 24 + gitWeekdayShift()))&&
						(strtotime($bug->eta) <= time()&&
						strtotime($bug->response) <= time()
						);
			});
		}
		else{
			return \App\BugzillaBug::find([]);
		}
	}
	
	public function bugzilla_report_time_processing(){
		$bugzilla_user = $this->bugzilla_user;
		if( $bugzilla_user ){
			return $bugzilla_user->report_bugs_time->filter(function($bug){
				return in_array($bug->status, ['NeedMoreInfo','RESOLVED']) &&
						//((time_duration($bug->eta) >= (1 * 3600 * 24 + gitWeekdayShift()))||
						(strtotime($bug->eta) >= time()||
						strtotime($bug->response) >= time()
						);
						
			});
		}
		else{
			return \App\BugzillaBug::find([]);
		}
	}

	public function bugzilla_assigned_to_quit(){
		$bugzilla_user = $this->bugzilla_user;
		if( $bugzilla_user ){
			return $bugzilla_user->assigned_to_bugs_time->filter(function($bug){
				return (in_array($bug->status, ['CLOSED', 'RESOLVED', 'VERIFIED']) === false);
			});
		}
		else{
			return \App\BugzillaBug::find([]);
		}
	}
	
	public function bugzilla_new_bugs(){
		$bugzilla_user = $this->bugzilla_user;
		if( $bugzilla_user ){
			$yesterday = date("Y-m-d", strtotime('-1 day'));
			$bugs_new = \App\BugzillaBug::where('username',$this->username)->where('creation_ts',$yesterday)->get();
			$bugs_reopen = \App\BugzillaBug::where('username',$this->username)->where('status','REOPENED')->get();
			$bugs_reopen = $bugs_reopen->filter(function($item)use($yesterday){
				$reopen = \App\models\api\Bugs_activity::where('bug_id',$item->id)
					->where('bug_when','LIKE','%'.$yesterday.'%')
					->where('fieldid',8)
					->where('added','REOPENED')
					->get();
				if($reopen->count()>0)
					return true;
			});
			$ids = array_unique(array_merge($bugs_new->pluck('id')->toArray(),$bugs_reopen->pluck('id')->toArray()));
			return \App\BugzillaBug::whereIn('username',$ids)->get();
		}
		else{
			return \App\BugzillaBug::find([]);
		}
	}

	public function qfms_stuntman_list(){
		$qfms = new QfmsService;
		return $qfms->set_user($this->id)->retrieve_stuntman_list();
	}

	public function qfms_unread_count(){
		$qfms = new QfmsService;
		return $qfms->set_user($this->id)->retrieve_unread_count();
	}

	public function qfms_valid(){
		$qfms = new QfmsService;
		return $qfms->set_user($this->id)->has_token();
	}

	public function sync_redmine_group(){
		$redmine_groups = $this->user_meta->department->redmine_group();
		if( empty($redmine_groups) ){
			return 'empty';
		}
		else{

			$redmine_user_id = $this->redmine->id;

			foreach($redmine_groups as $redmine_group_id){
				\Redmine::api('group')->addUser($redmine_group_id, $redmine_user_id);
			}
		}
		return '';
	}

	public function kayako(){
		return $this->hasOne('\App\KayakoSwstaff', 'email', 'email');
	}

	public function kayakoStafflist(){
		return $this->hasOne('\App\KayakoStafflist', 'staff_email', 'email');
	}
	
	public function sku_empty_count(){
		$codes = \App\Nas_codes::where('owner','LIKE','%'.$this->username.'%')->where('is_filter',1)->get();
		if(empty($codes->toArray())){
			return 0;
		}
		$table = strtolower($codes[0]->table_used);
		$type = $codes[0]->type;
		$data =[];
		if(strtolower($table)=='nas_sw'){
			foreach($codes as $code){
				$temp = [];
				if(strtolower($code->table_used)=='nas_sw'){
					$models = \App\Nas_nas_sw::where(function($q)use($code){
							$q->whereNull($code->field);
							$q->orWhere($code->field,"");
						})->get();
						//dd($models);
					if(count($models)==0)
						continue;
					foreach($models as $model){
						$temp[$model->NAS_models_id] = $model->nas_models->toArray();	
					}
					$data[] =[
						'id' => $code->id,
						'field' => $code->title,
						'sku' => $temp
					];
				}
			}
		}else{
			// if(strtolower($table)=='models'){
				// $models = \App\Nas_models::where(function($q)use($codes){
							// foreach($codes as $code){
								// if(strtolower($code->table_used)=='models'){
								// $q->orWhereNull($code->field);
								// $q->orWhere($code->field,"");
								// }
							// }
						// })->get();
					// if(count($models)==0)
						// continue;
					// foreach($models as $model){
						// $data[$model->id] =[
							// 'model_name' => $model->model_name,
							// 'sku_name' => $model->sku_name,
							// 'series_name' => $model->nas_series->series_name
						// ];						
					// }
			// }
			foreach($codes as $code){
				$temp = [];
				if(strtolower($code->table_used)=='models'){
					$models = \App\Nas_models::where(function($q)use($code){
							$q->whereNull($code->field);
							$q->orWhere($code->field,"");
						})->get();
					if(count($models)==0)
						continue;
					foreach($models as $model){
						$data[$model->id] =[
							'model_name' => $model->model_name,
							'sku_name' => $model->sku_name,
							'series_name' => $model->nas_series->series_name
						];						
					}
				}elseif(strtolower($code->table_used)=='nas_hw'){
					$models = \App\Nas_nas_hw::where(function($q)use($code){
							$q->whereNull($code->field);
							$q->orWhere($code->field,"");
						})->get();
					if(count($models)==0)
						continue;
					foreach($models as $model){
						$data[$model->NAS_models_id] =[
							'model_name' => $model->nas_models->model_name,
							'sku_name' => $model->nas_models->sku_name,
							'series_name' => $model->nas_models->nas_series->series_name
						];	
					}
				}elseif(strtolower($code->table_used)=='nas_app'){
					$models = \App\Nas_nas_app::where(function($q)use($code){
							$q->whereNull($code->field);
							$q->orWhere($code->field,"");
						})->get();
					if(count($models)==0)
						continue;
					foreach($models as $model){
						$data[$model->NAS_models_id] =[
							'model_name' => $model->nas_models->model_name,
							'sku_name' => $model->nas_models->sku_name,
							'series_name' => $model->nas_models->nas_series->series_name
						];	
					}
				}
			}
		}
		return count($data);
	}
	
	public function sku_empty(){
		$codes = \App\Nas_codes::where('owner','LIKE','%'.$this->username.'%')->where('is_filter',1)->get();
		if(empty($codes->toArray())){
			return [];
		}
		$table = strtolower($codes[0]->table_used);
		$type = $codes[0]->type;
		$data =[];
		if(strtolower($table)=='nas_sw'){
			foreach($codes as $code){
				$temp = [];
				if(strtolower($code->table_used)=='nas_sw'){
					$models = \App\Nas_nas_sw::where(function($q)use($code){
							$q->whereNull($code->field);
							$q->orWhere($code->field,"");
						})->get();
						//dd($models);
					if(count($models)==0)
						continue;
					foreach($models as $model){
						$temp[$model->NAS_models_id] = $model->nas_models->toArray();	
					}
					$data[] =[
						'id' => $code->id,
						'field' => $code->title,
						'sku' => $temp
					];
				}
			}
		}else{
			// if(strtolower($table)=='models'){
				// $models = \App\Nas_models::where(function($q)use($codes){
							// foreach($codes as $code){
								// if(strtolower($code->table_used)=='models'){
								// $q->orWhereNull($code->field);
								// $q->orWhere($code->field,"");
								// }
							// }
						// })->get();
					// if(count($models)==0)
						// continue;
					// foreach($models as $model){
						// $data[$model->id] =[
							// 'model_name' => $model->model_name,
							// 'sku_name' => $model->sku_name,
							// 'series_name' => $model->nas_series->series_name
						// ];						
					// }
			// }
			foreach($codes as $code){
				$temp = [];
				if(strtolower($code->table_used)=='models'){
					$models = \App\Nas_models::where(function($q)use($code){
							$q->whereNull($code->field);
							$q->orWhere($code->field,"");
						})->get();
					if(count($models)==0)
						continue;
					foreach($models as $model){
						$data[$model->id] =[
							'model_name' => $model->model_name,
							'sku_name' => $model->sku_name,
							'series_name' => $model->nas_series->series_name
						];						
					}
				}elseif(strtolower($code->table_used)=='nas_hw'){
					$models = \App\Nas_nas_hw::where(function($q)use($code){
							$q->whereNull($code->field);
							$q->orWhere($code->field,"");
						})->get();
					if(count($models)==0)
						continue;
					foreach($models as $model){
						$data[$model->NAS_models_id] =[
							'model_name' => $model->nas_models->model_name,
							'sku_name' => $model->nas_models->sku_name,
							'series_name' => $model->nas_models->nas_series->series_name
						];	
					}
				}elseif(strtolower($code->table_used)=='nas_app'){
					$models = \App\Nas_nas_app::where(function($q)use($code){
							$q->whereNull($code->field);
							$q->orWhere($code->field,"");
						})->get();
					if(count($models)==0)
						continue;
					foreach($models as $model){
						$data[$model->NAS_models_id] =[
							'model_name' => $model->nas_models->model_name,
							'sku_name' => $model->nas_models->sku_name,
							'series_name' => $model->nas_models->nas_series->series_name
						];	
					}
				}
			}
		}
		return $data;
	}
	
	public function bugzilla_mantis(){
		return \App\BugzillaBug::whereIn("status",['NEW','ASSIGNED','REOPENED','NeedMoreInfo','RESOLVED'])
									->where("username",$this->username)
									->where(function($q){
										$q->orWhere("bugfrom",'mantis');
										$q->orWhere("keywords",'mantis');
									})->get();
	}
	
	public function redmine_quick(){
		if(!$this->redmine){
			return 0;
		}else{
			return \App\RedmineIssuesigns::where("user_id",$this->redmine->id)->count();
		}
	}
	
	public function owner($type){
		if($type=='Model'){
			$type = 'Project';
		}
		$codes = \App\Nas_codes::where('type',$type)
		->where('owner','LIKE','%'.$this->username.'%')
		->where('is_filter',1)
		->get();
		if(empty($codes->toArray())){
			return false;
		}else{
			return true;
		}
	}
	
	public function role($type){
		//echo $type;
		$role_id = \App\Roles::where('field',$type)->where('is_user','1')->first();
		//var_dump($role_id);
		if(empty($role_id)){
			return 0;
			//return Response()->json("Please check the key");
		}
		//echo $this->email;
		$role_user = \App\Role_user::where('roles_id', $role_id->id)->where('username',$this->username)->first();
		//var_dump($role_user);
		//echo '123';
		if(empty($role_user)){
			$role_user = 0;
		}
		else{
			//echo '123';
			$role_user = $role_user->role;
		}
		//echo '456';
		if(isset($this->user_meta->department_id))
		{
			$role_dep = \App\Role_department::where('department_id', $this->user_meta->department_id)
											->first();
		}
		//dd($role_dep);
		if(empty($role_dep)){
			$role_dep[$type] = 0;
		}else{
			$role_dep = $role_dep->toArray();
			if(!array_key_exists($type,$role_dep)){
				$role_dep[$type] = 0;
			}
		}
		// echo $role_dep[$type];
		$role = max($role_dep[$type],$role_user);
		
		return (int) $role;
	}
	
	public function sw_owner($id){
		$codes = \App\Nas_codes::where('id',$id)
		->where('owner','LIKE','%'.$this->username.'%')
		->where('is_filter',1)
		->get();
		if(empty($codes->toArray())){
			return false;
		}else{
			return true;
		}
	}
	
	public function translate(){
		$codes = \App\Nas_codes
				::whereIn('field_type',['M','S'])
				->where('owner','LIKE','%'.$this->username.'%')
				->where('is_filter',1)
				->get();
		$detail_id = [];
		foreach($codes as $code){
			foreach($code->nas_code_detail as $detail){
				if($detail->tran_status >= 1 && $detail->tran_status <= 19)
				array_push($detail_id,$detail->id);	
			}
		}
		$data = \App\Nas_code_details::whereIn('id',$detail_id)->get();
		return $data;
	}
	
	public function approved(){
		//echo '1';
		$role_P = $this->role('Model');
		$role_H = $this->role('Model');
		$role_S = $this->role('Model');
		$role_A = $this->role('APP');
		//echo '1-1';
		
		/*$approver_codes = \App\Nas_codes::where('approver','LIKE','%'.$this->username.'%')->get();
		$all_codes = \App\Nas_codes::where('is_filter',1)->get();
		$modelss = \App\Nas_models
				::leftJoin('nas_series', 'models.NAS_series_id', '=', 'nas_series.id')
				->leftJoin('nas_hw', 'models.id','=','nas_hw.NAS_models_id')
				->leftJoin('nas_sw','models.id' ,'=','nas_sw.NAS_models_id')
				->leftJoin('nas_app','models.id' ,'=','nas_app.NAS_models_id')
				->where(function($q)use($all_codes){
					foreach ($all_codes as $code){
						$q->where($code->field,'!=','');
						$q->whereNotNull($code->field);
					}
				})
				->get();
		//var_dump($modelss);
		if(empty($modelss)){
			return [];
		}
		$model_array = [];
		//echo '2-1';
		foreach($modelss as $model){
			$code_trans = \App\Nas_codes::with('nas_code_detail')
					->whereIn('id',json_decode($model->is_web))
					->where('is_filter',1)
					->whereIn('field_type',['M','S'])
					->get();
			$temp = $model->toArray();
			$tran_count = 0;
			//already translate
			foreach($code_trans as $code){
				//$temp[$code->field]==
				//dd($code);
				$code_detail = $code->nas_code_detail->filter(function($c){
					return $c->tran_status < 20;
				})->count();
				$tran_count = $tran_count + $code_detail;
				if($tran_count > 0) break;
			}
			//already approved
			$code_approved = array_intersect(json_decode($model->is_web),$approver_codes->pluck('id')->toArray());
			$approved_count = \App\Nas_model_approved
						::where('model_id',$model->NAS_models_id)
						->where('approved_id',$model->approved_id)
						->whereIn('codes_id',$code_approved)
						->get();
			if($tran_count == 0 && count($code_approved)!=count($approved_count))
				array_push($model_array,$model->NAS_models_id);
		}
		//echo '2';
		$models = \App\Nas_models
						::whereIn('id',$model_array)
						->leftJoin('nas_series', 'models.NAS_series_id', '=', 'nas_series.id')
						->leftJoin('nas_hw', 'models.id','=','nas_hw.NAS_models_id')
						->leftJoin('nas_sw','models.id' ,'=','nas_sw.NAS_models_id')
						->leftJoin('nas_app','models.id' ,'=','nas_app.NAS_models_id')
						->where(function($q)use($role_P,$role_H,$role_S,$role_A){
							if($role_P>=8){$q->orWhere('models.Approved','0');}
							if($role_H>=8){$q->orWhere('nas_hw.Approved','0');}
							if($role_S>=8){$q->orWhere('nas_sw.Approved','0');}
							if($role_A>=8){$q->orWhere('nas_app.Approved','0');}
						})
						->where(function($q)use($approver_codes){
							foreach ($approver_codes as $code){
								$q->orWhere('is_web','LIKE','%"'.$code->id.'"%');
							}
						})
						->where(function($q){
							$q->whereNotNull('is_web');
							$q->orWhere('is_web','!=','');
						})
						//->whereIn('models.status',['Launched','EOL'])
						//->where('models.status','EOL')
						//->select('models.id','internal_model_name','model_name')
						->select('models.id as id',"model_name","sku_name","product_segment","NAS_series_id")
						->orderby("id")
						->get();
						//echo '3';*/
		$approver_codes = \App\Nas_codes::where('approver','LIKE','%'.$this->username.'%')->get();
		$all_codes = \App\Nas_codes::where('is_filter',1)->where('type','!=','SW')->get();
		$modelss = \App\Nas_models
				::leftJoin('nas_series', 'models.NAS_series_id', '=', 'nas_series.id')
				->leftJoin('nas_hw', 'models.id','=','nas_hw.NAS_models_id')
				->leftJoin('nas_sw','models.id' ,'=','nas_sw.NAS_models_id')
				->leftJoin('nas_app','models.id' ,'=','nas_app.NAS_models_id')
				->where(function($q)use($all_codes){
					foreach ($all_codes as $code){
						$q->where($code->field,'!=','');
						$q->whereNotNull($code->field);
					}
				})
				->get();
		$model_array = [];
		foreach($modelss as $model){
			$code_trans = \App\Nas_codes::with('nas_code_detail')
					->whereIn('id',json_decode($model->is_web))
					->where('is_filter',1)
					->whereIn('field_type',['M','S'])
					->get();
			$temp = $model->toArray();
			$tran_count = 0;
			//already translate
			foreach($code_trans as $code){
				$code_detail = $code->nas_code_detail->filter(function($c){
					return $c->tran_status < 20;
				})->count();
				$tran_count = $tran_count + $code_detail;
				if($tran_count > 0){
					break;
				} 
			}
			//already approved
			//$code_approved = array_intersect(json_decode($model->is_web),$approver_codes->pluck('id')->toArray());
			//dd($approver_codes->pluck('id')->toArray());
			// $approved_count = \App\Nas_model_approved
						// ::where('model_id',$model->NAS_models_id)
						// ->where('approved_id',$model->approved_id)
						// ->whereIn('codes_id',$code_approved)
						// ->get();
			//if($tran_count == 0 && count($code_approved)!=count($approved_count))
			if($tran_count == 0)
				array_push($model_array,$model->NAS_models_id);
		}
		//dd($model_array);
		//dd($approver_codes->pluck('id')->toArray());
		$models = \App\Nas_models
						::whereIn('models.id',$model_array)
						->leftJoin('nas_series', 'models.NAS_series_id', '=', 'nas_series.id')
						->leftJoin('nas_hw', 'models.id','=','nas_hw.NAS_models_id')
						->leftJoin('nas_sw','models.id' ,'=','nas_sw.NAS_models_id')
						->leftJoin('nas_app','models.id' ,'=','nas_app.NAS_models_id')
						->where(function($q)use($role_P,$role_H,$role_S,$role_A){
							if($role_P>=8){$q->orWhere('models.Approved','0');}
							if($role_H>=8){$q->orWhere('nas_hw.Approved','0');}
							if($role_S>=8){$q->orWhere('nas_sw.Approved','0');}
							//if($role_A>=8){$q->orWhere('nas_app.Approved','0');}
						})
						->where(function($q)use($approver_codes){
							foreach ($approver_codes as $code){
								$q->orWhere('is_web','LIKE','%"'.$code->id.'"%');
							}
						})
						->where(function($q){
							$q->whereNotNull('is_web');
							$q->orWhere('is_web','!=','');
						})
						//->whereIn('models.status',['Launched','EOL'])
						//->where('models.status','EOL')
						//->select('models.id','internal_model_name','model_name')
						->select('models.id as id',"model_name","sku_name","product_segment","NAS_series_id")
						->orderby("id")
						->get(); 
		return $models;
	}
	
	public function nas_model_count(){
		//return $this->hasOne('\App\RedmineUser', 'username', 'username');
		return $this->hasOne('\App\Nas_model_count', 'email', 'email');
	}
}
