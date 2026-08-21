<?php

declare(strict_types=1);

namespace tgui\Controllers\TAC\TACDevices;

use tgui\Models\TACDevices;
use tgui\Models\APIPWPolicy;
use tgui\Controllers\Controller;
use Respect\Validation\Validator as v;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class TACDevicesCtrl extends Controller
{
////////////////////////////////////
////Device Ping///////////Start///////
	// public function getDevicePing(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	// {
	// 	//INITIAL CODE////START//
	// 	$data=array();
	// 	$data=$this->initialData([
	// 		'type' => 'get',
	// 		'object' => 'device',
	// 		'action' => 'ping'
	// 	]);
	// 	#check error#
	// 	if ($_SESSION['error']['status']){
	// 		$data['error']=$_SESSION['error'];
	// 		return $this->json($response, $data, 401);
	// 	}
	// 	//INITIAL CODE////END//
	// 	//CHECK ACCESS TO THAT FUNCTION//START//
	// 	if(!$this->checkAccess(2, true))
	// 	{
	// 		return $this->json($response, $data, 403);
	// 	}
	// 	//CHECK ACCESS TO THAT FUNCTION//END//
	//
	// 	$validation = $this->validator->validate($request, [
	// 		'ipaddr' => v::noWhitespace()->notEmpty()->ip()
	// 	]);
	//
	// 	if ($validation->failed()){
	// 		$data['error']['status']=true;
	// 		$data['error']['validation']=$validation->error_messages;
	// 		return $this->json($response, $data, 200);
	// 	}
	//
	// 	$data['pingResponses'] = intval(trim(shell_exec('ping '.$this->param($request, 'ipaddr').' -c4 | grep "64 bytes from" | wc -l')));
	//
	//
	// 	return $this->json($response, $data, 200);
	// }
////Device Ping///////////End///////
################################################

	public function itemValidation($req = [], $state = 'add'){
		$id = 0;
		$group = 0;
		if (is_object($req)){
			$id = ($state == 'edit') ? $req->getParsedBody()['id'] ?? null : 0;
			$group = $req->getParsedBody()['group'] ?? null;
		} else {
			if ( isset($req['group']) )
				$group = $req['group'];
		}

		$policy = APIPWPolicy::select()->first(1);
		return $this->validator->validate($req, [
			'name' => v::noWhitespace()->notEmpty()->theSameNameUsed( '\tgui\Models\TACDevices', $id )->theSameNameUsed( '\tgui\Models\TACDeviceGrps' ),
			'group' => v::when( v::oneOf( v::nullType(), v::equals('')), v::alwaysValid(), v::numericVal()->setName('Group ID')),
			'enable' => v::when( v::oneOf( v::nullType(), v::equals('') ) , v::alwaysValid(), v::noWhitespace()->notContainChars()->
				length($policy['tac_pw_length'], 64)->
				notEmpty()->
				passwdPolicyUppercase($policy['tac_pw_uppercase'])->
				passwdPolicyLowercase($policy['tac_pw_lowercase'])->
				passwdPolicySpecial($policy['tac_pw_special'])->
				passwdPolicyNumbers($policy['tac_pw_numbers'])->setName('Enable') ),
			'enable_flag' => v::when( v::nullType() , v::alwaysValid(), v::oneOf( v::equals('1'), v::equals('2'), v::equals('0') ) ),
			'key' => v::when( v::tacacsKeyAvailable($group), v::alwaysValid(),
			 	v::noWhitespace()->notContainChars()->
				length($policy['tac_pw_length'], 64)->
				notEmpty()->
				passwdPolicyUppercase($policy['tac_pw_uppercase'])->
				passwdPolicyLowercase($policy['tac_pw_lowercase'])->
				passwdPolicySpecial($policy['tac_pw_special'])->
				passwdPolicyNumbers($policy['tac_pw_numbers'])->setName('Tacacs Key') ),
			'address' => v::notEmpty()->numeric(),
		]);
	}

	#########	POST Add New Device	#########
	public function postDeviceAdd(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'device',
			'action' => 'add',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//
		//CHECK SHOULD I STOP THIS?//START//
		if( $this->shouldIStopThis() )
		{
			$data['error'] = $this->shouldIStopThis();
			return $this->json($response, $data, 400);
		}
		//CHECK SHOULD I STOP THIS?//END//
		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(2))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$allParams = $this->params($request);

		$validation = $this->itemValidation($request);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		if ( ! empty( $allParams['enable'] ) )
		{
			$allParams['enable'] = $this->encryption( $allParams['enable'], $allParams['enable_flag'], 1);
		}

		$device = TACDevices::create($allParams);

		$data['device']=$device;

		$data['changeConfiguration']=$this->changeConfigurationFlag(['unset' => 0]);

		$logEntry=array('action' => 'add', 'obj_name' => $device['name'], 'obj_id' => $device['id'], 'section' => 'tacacs devices', 'message' => 201);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		return $this->json($response, $data, 200);
	}
########	Add New Device	###############END###########
################################################
########	Edit Device	###############START###########
	#########	GET Edit Device	#########
	public function getDeviceEdit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'device',
			'action' => 'edit',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(2))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$data['device']=TACDevices::select()->
			where('id',$this->param($request, 'id'))->
			first();
		$data['device']->address = $this->db->table('obj_addresses')->
			select(['name as text','id','type','address'])->where('id',$data['device']->address)->get();

		$data['device']->group = $this->db->table('tac_device_groups')->
			select(['name as text','id'])->where('id',$data['device']->group)->get();

		$data['device']->acl = $this->db->table('tac_acl')->
			select(['name as text','id'])->where('id',$data['device']->acl)->get();

		$data['device']->user_group = $this->db->table('tac_user_groups')->
			select(['name as text','id'])->where('id',$data['device']->user_group)->get();

		return $this->json($response, $data, 200);
	}

	#########	POST Edit Device	#########
	public function postDeviceEdit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'device',
			'action' => 'edit',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//
		//CHECK SHOULD I STOP THIS?//START//
		if( $this->shouldIStopThis() )
		{
			$data['error'] = $this->shouldIStopThis();
			return $this->json($response, $data, 400);
		}
		//CHECK SHOULD I STOP THIS?//END//
		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(2))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$allParams = $this->params($request);

		// $group = (empty($allParams['group'])) ? TACDevices::where([['id','=',$allParams['id']]])->first()->group : $allParams['group'];

		$validation = $this->itemValidation($request, 'edit');

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$allParams = $this->params($request);

		if ( ! empty( $allParams['enable'] ) )
		{
			$allParams['enable'] = $this->encryption( $allParams['enable'], $allParams['enable_flag'], 1);
		}

		$id = $allParams['id'];

		unset($allParams['id']);
		unset($allParams['enable_encrypt']);

		$data['save']=TACDevices::where('id',$id)->update($allParams);

		$data['changeConfiguration']=$this->changeConfigurationFlag(['unset' => 0]);

		$name = TACDevices::select('name')->where('id',$id)->first();

		$logEntry=array('action' => 'edit', 'obj_name' => $name['name'], 'obj_id' => $id, 'section' => 'tacacs devices', 'message' => 301);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		return $this->json($response, $data, 200);
	}
########	Edit Device	###############END###########
################################################
	#########	POST Delete Device	#########
	public function postDeviceDelete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'device',
			'action' => 'delete',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//
		//CHECK SHOULD I STOP THIS?//START//
		if( $this->shouldIStopThis() )
		{
			$data['error'] = $this->shouldIStopThis();
			return $this->json($response, $data, 400);
		}
		//CHECK SHOULD I STOP THIS?//END//
		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(2))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$data['result']=TACDevices::where('id', $this->param($request, 'id'))->delete();
		$data['id']=$this->param($request, 'id');

		$data['changeConfiguration']=$this->changeConfigurationFlag(['unset' => 0]);

		$logEntry=array('action' => 'delete', 'obj_name' => $this->param($request, 'name'), 'obj_id' => $this->param($request, 'id'), 'section' => 'tacacs devices', 'message' => 401);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		return $this->json($response, $data, 200);
	}
########	Delete Device	###############END###########
################################################
#########	POST CSV Device	#########
// public function postDeviceCsv(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
// {
// 	//INITIAL CODE////START//
// 	$data=array();
// 	$data=$this->initialData([
// 		'type' => 'post',
// 		'object' => 'device',
// 		'action' => 'csv',
// 	]);
// 	#check error#
// 	if ($_SESSION['error']['status']){
// 		$data['error']=$_SESSION['error'];
// 		return $this->json($response, $data, 401);
// 	}
// 	//INITIAL CODE////END//
// 	//CHECK ACCESS TO THAT FUNCTION//START//
// 	if(!$this->checkAccess(2))
// 	{
// 		return $this->json($response, $data, 403);
// 	}
// 	//CHECK ACCESS TO THAT FUNCTION//END//
// 	//$data['clear'] = shell_exec( TAC_ROOT_PATH . '/main.sh delete temp');
// 	shell_exec( TAC_ROOT_PATH . '/main.sh delete temp');
// 	$path = TAC_ROOT_PATH . '/temp/';
// 	$filename = 'tac_devices_'. $this->generateRandomString(8) .'.csv';
//
// 	$columns = $this->APICheckerCtrl->getTableTitles('tac_devices');
//
//   $f = fopen($path.$filename, 'w');
// 	$idList = $this->param($request, 'idList');
// 	$array = [];
// 	$array = ( empty($idList) ) ? TACDevices::select($columns)->get()->toArray() : TACDevices::select($columns)->whereIn('id', $idList)->get()->toArray();
//
// 	fputcsv($f, $columns /*, ',)'*/);
//   foreach ($array as $line) {
// 		fputcsv($f, $line /*, ',)'*/);
//   }
//
// 	//$data['filename']=$path.$filename;
// 	header("X-Sendfile: $path.$filename");
// 	header("Content-type: application/octet-stream");
// 	header('Content-Disposition: attachment; filename="'.$filename.'"');
// 	exit(0);
// 	//return $this->json($response, $data, 200);
// }
########	CSV Device	###############END###########
########	#########################
########	Device Datatables ###############START###########
	#########	POST Device Datatables	#########
	public function postDeviceDatatables(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'device',
			'action' => 'datatables',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		unset($data['error']);//BEACAUSE DATATABLES USES THAT VARIABLE//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(2, true))
		{
			$data['data'] = [];
			$data['recordsTotal'] = 0;
			$data['recordsFiltered'] = 0;
			return $this->json($response, $data, 200);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$params=$this->params($request); //Get ALL parameters form Datatables

		//$data['columns'] = $this->APICheckerCtrl->getTableTitles('tac_devices');

		$columns = $this->APICheckerCtrl->getTableTitles('tac_devices'); //Array of all columnes that will used
		array_unshift( $columns, 'tac_devices.id as id' );
		$columns[array_search('address', $columns)] = 'tac_devices.address AS address_id';
		$columns[array_search('name', $columns)] = 'tac_devices.name AS name';
		$columns[array_search('type', $columns)] = 'tac_devices.type AS type';
		$columns[array_search('enable', $columns)] = 'tac_devices.enable AS enable';
		$columns[array_search('enable_flag', $columns)] = 'tac_devices.enable_flag AS enable_flag';
		$columns[array_search('acl', $columns)] = 'tac_devices.acl AS acl';
		$columns[array_search('user_group', $columns)] = 'tac_devices.user_group AS user_group';
		$columns[array_search('connection_timeout', $columns)] = 'tac_devices.connection_timeout AS connection_timeout';
		$columns[array_search('banner_welcome', $columns)] = 'tac_devices.banner_welcome AS banner_welcome';
		$columns[array_search('banner_failed', $columns)] = 'tac_devices.banner_failed AS banner_failed';
		$columns[array_search('banner_motd', $columns)] = 'tac_devices.banner_motd AS banner_motd';
		$columns[array_search('manual', $columns)] = 'tac_devices.acl AS manual';
		$columns[array_search('key', $columns)] = 'tac_devices.key AS key';
		array_push( $columns,
		'tac_devices.created_at as created_at',
		'tac_devices.updated_at as updated_at',
		'address.address as address',
		'address.name as address_name',
		'groups.name as group_name'
		);
		$data['columns'] = $columns;
		$queries = (empty($params['searchTerm'])) ? [] : $params['searchTerm'];

		$data['recordsTotal'] = TACDevices::count();
		//Get temp data for Datatables with Fliter and some other parameters
		$tempData = TACDevices::leftJoin('obj_addresses as address', 'address.id', '=', 'tac_devices.address')->
			leftJoin('tac_acl as acl', 'acl.id', '=', 'tac_devices.acl')->
			leftJoin('tac_device_groups as groups', 'groups.id', '=', 'tac_devices.group')->
			select($columns)->
			when( !empty($queries),
				function($query) use ($queries)
				{
					$query->where('tac_devices.name','LIKE', '%'.$queries.'%');
					$query->orWhere('address.address','LIKE', '%'.$queries.'%');
					return $query;
				});
			$data['recordsFiltered'] = $tempData->count();

			if (!empty($params['sortColumn']) and !empty($params['sortDirection']))
					$tempData = $tempData->orderBy($params['sortColumn'],$params['sortDirection']);

			$data['data'] = $tempData->get()->toArray();


		return $this->json($response, $data, 200);
	}

########	Device Datatables	###############END###########
###############################################
########	List Device	###############START###########
	#########	GET List Device#########
	public function getList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'device list',
			'action' => 'list',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(3, true))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		///IF GROUPID SET///
		if ($this->param($request, 'id') != null){
			$id = explode(',', $this->param($request, 'id'));

			$data['results'] = TACDevices::select(['id','name AS text'])->whereIn('id', $id)->get();
			// if (  !count($data['results']) ) $data['results'] = null;
			return $this->json($response, $data, 200);
		}
		//////////////////////
		////LIST OF GROUPS////
		$query = TACDevices::select(['id','name as text']);
		$data['total'] = $query->count();
		$search = $this->param($request, 'search');

		$query = $query->when( !empty($search), function($query) use ($search)
			{
				$query->where('name','LIKE', '%'.$search.'%');
			});

		$data['results']=$query->get();

		return $this->json($response, $data, 200);
	}
########	List Device Group	###############END###########
################################################

}//END OF CLASS//
