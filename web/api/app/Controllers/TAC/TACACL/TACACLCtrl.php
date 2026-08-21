<?php

declare(strict_types=1);

namespace tgui\Controllers\TAC\TACACL;

use tgui\Models\TACACL;
use tgui\Models\TACUsers;
use tgui\Models\TACUserGrps;
use tgui\Controllers\Controller;
use Respect\Validation\Validator as v;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class TACACLCtrl extends Controller
{

	public function itemValidation($req = [], $state = 'add'){
		$id = 0;
		if (is_object($req)){
			$id = ($state == 'edit') ? $req->getParsedBody()['id'] ?? null : 0;
		}

		return $this->validator->validate($req, [
			'name' => v::noWhitespace()->notEmpty()->theSameNameUsed( 'tgui\Models\TACACL', $id ),
			'ace' => v::arrayVal()->length(1, null)->each(v::arrayVal()->allOf(
				v::key('order', v::oneOf(v::numericVal(), v::nullType(), v::equals('')))->setName('Order'),
				v::key('nac', v::oneOf(v::numericVal(), v::nullType(), v::equals('')))->setName('NAC'),
				v::key('nas', v::oneOf(v::numericVal(), v::nullType(), v::equals('')))->setName('NAS'),
				v::key('action', v::numericVal(), v::oneOf( v::equals(1), v::equals(0)) )->setName('Action')
			))
		]);
	}


################################################
	#########	POST Add New ACL	#########
	public function postACLAdd(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'acl',
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
		if(!$this->checkAccess(12))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->itemValidation($request);

		if ( $validation->failed() ){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$data['acl'] = TACACL::create( ['name' => $this->param($request, 'name')] );
		$tempId = $data['acl']->id;

		// $data['ace'] = array_map(function($x) use ($tempId){ $x['acl_id'] = $tempId; return $x; }, $this->param($request, 'ace'));

		$this->db->table('tac_acl_ace')->insert(array_map(function($x)use ($tempId) { $x['acl_id'] = $tempId; return $x; }, $this->param($request, 'ace')));

		$data['changeConfiguration']=$this->changeConfigurationFlag(['unset' => 0]);

		$logEntry=array('action' => 'add', 'obj_name' => $data['acl']->name, 'obj_id' => $data['acl']->id, 'section' => 'tacacs acl', 'message' => 207);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		return $this->json($response, $data, 200);
	}
########	Add New ACL	###############END###########
################################################
########	Edit ACL	###############START###########
	#########	GET Edit ACL	#########
	public function getACLEdit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'acl',
			'action' => 'edit',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(12))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$data['acl'] = TACACL::select()->where('id', $this->param($request, 'id'))->first();

		$data['ace'] = [];
		$aces = $this->db->table('tac_acl_ace as ace')->
			leftJoin('obj_addresses as addr_s', 'addr_s.id', '=', 'ace.nas')->
			leftJoin('obj_addresses as addr_c', 'addr_c.id', '=', 'ace.nac')->
			select(['nas as nas_id', 'nac as nac_id', 'addr_s.name as nas_name', 'addr_s.type as nas_type',
				'addr_s.address as nas_address', 'addr_c.name as nac_name', 'addr_c.type as nac_type',
				'addr_c.address as nac_address', 'action', 'order'
				])->
			orderBy('order', 'asc')->
			where('acl_id',$this->param($request, 'id'))->get();

		foreach ($aces as $ace) {
			$data['ace'][] = [
				'action' => $ace->action,
				'order' => $ace->order,
				'nas' => [[
					'id' => ($ace->nas_id) ? $ace->nas_id : 0,
					'text' => ($ace->nas_name) ? $ace->nas_name : 'any',
					'type' => $ace->nas_type,
					'address' => ($ace->nas_address) ? $ace->nas_address : 'any',
				]],
				'nac' => [[
					'id' => ($ace->nac_id) ? $ace->nac_id : 0,
					'text' => ($ace->nac_name) ? $ace->nac_name : 'any',
					'type' => $ace->nac_type,
					'address' => ($ace->nac_address) ? $ace->nac_address : 'any',
				]],
			];
		}

		$data['acl']->ace = $data['ace'];

		return $this->json($response, $data, 200);
	}

	#########	POST Edit ACL	#########
	public function postACLEdit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'acl',
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
		if(!$this->checkAccess(12))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $validation = $this->itemValidation($request, 'edit');

		if ( $validation->failed() ){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		TACACL::where('id', $this->param($request, 'id'))->update(['name' => $this->param($request, 'name')]);

		$tempId = $this->param($request, 'id');
		$this->db->table('tac_acl_ace')->where('acl_id',$tempId)->delete();
		$this->db->table('tac_acl_ace')->insert(array_map(function($x)use ($tempId) { $x['acl_id'] = $tempId; return $x; }, $this->param($request, 'ace')));

		$data['save'] = 1;

		$data['changeConfiguration']=$this->changeConfigurationFlag(['unset' => 0]);

		$logEntry=array('action' => 'edit', 'obj_name' => $this->param($request, 'name'), 'obj_id' => $this->param($request, 'id'), 'section' => 'tacacs acl', 'message' => 307);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		return $this->json($response, $data, 200);
	}
########	Edit ACL	###############END###########
################################################
########	Delete ACL	###############START###########
	#########	POST Delete ACL	#########
	public function postACLDelete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'acl',
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
		if(!$this->checkAccess(12))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$data['result']=TACACL::where('id',$this->param($request, 'id'))->delete();
		$data['id']=$this->param($request, 'id');
		$data['name']=$this->param($request, 'name');

		$data['changeConfiguration']=$this->changeConfigurationFlag(['unset' => 0]);

		$logEntry=array('action' => 'delete', 'obj_name' => $this->param($request, 'name'), 'obj_id' => $this->param($request, 'id'), 'section' => 'tacacs acl', 'message' => 407);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		$data['footprints_users']=TACUsers::where([['acl','=',$this->param($request, 'id')]])->update(['acl' => '0']);
		$data['footprints_groups']=TACUserGrps::where([['acl','=',$this->param($request, 'id')]])->update(['acl' => '0']);

		return $this->json($response, $data, 200);
	}
########	Delete ACL	###############END###########
################################################
#########	POST CSV	#########
	public function postACLCsv(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'acl',
			'action' => 'csv',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//
		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(12))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//
		$data['clear'] = shell_exec( TAC_ROOT_PATH . '/main.sh delete temp');
		$path = TAC_ROOT_PATH . '/temp/';
		$filename = 'tac_acl_'. $this->generateRandomString(8) .'.csv';

		$columns = $this->APICheckerCtrl->getTableTitles('tac_acl');

	  $f = fopen($path.$filename, 'w');
		$idList = $this->param($request, 'idList');
		$array = [];
		$array = ( empty($idList) ) ? TACACL::select($columns)->get()->toArray() : TACACL::select($columns)->whereIn('id', $idList)->get()->toArray();

		fputcsv($f, $columns /*, ',)'*/);
	  foreach ($array as $line) {
		fputcsv($f, $line /*, ',)'*/);
	  }

		$data['filename']=$filename;
		sleep(3);
		return $this->json($response, $data, 200);
	}
########	CSV	###############END###########
################################################
########	ACL Datatables ###############START###########
	#########	POST ACL Datatables	#########
	public function postACLDatatables(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'acl',
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
		if(!$this->checkAccess(12, true))
		{
			$data['data'] = [];
			$data['recordsTotal'] = 0;
			$data['recordsFiltered'] = 0;
			return $this->json($response, $data, 200);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$params=$this->params($request); //Get ALL parameters form Datatables

		//$columns = $this->APICheckerCtrl->getTableTitles('tac_acl'); //Array of all columnes that will used
		$columns = [];
		array_unshift( $columns, 'tac_acl.*' );
		array_push( $columns,
			$this->db::raw('(SELECT COUNT(*) FROM tac_device_groups WHERE acl = tac_acl.id) + '.
			'(SELECT COUNT(*) FROM tac_devices WHERE acl = tac_acl.id) + '.
			'(SELECT COUNT(*) FROM tac_user_groups WHERE acl = tac_acl.id) + '.
			'(SELECT COUNT(*) FROM tac_user_groups WHERE acl_match = tac_acl.id) + '.
			'(SELECT COUNT(*) FROM tac_services WHERE acl = tac_acl.id) + '.
			'(SELECT COUNT(*) FROM tac_users WHERE acl = tac_acl.id)  as ref')
		);
		$data['columns'] = $columns;
		$queries = (empty($params['searchTerm'])) ? [] : $params['searchTerm'];

		//Filter end
		$data['recordsTotal'] = TACACL::count();
		//Get temp data for Datatables with Fliter and some other parameters
		$tempData = TACACL::
		select($columns)->
		when( !empty($queries),
			function($query) use ($queries)
			{
				$query->where('name','LIKE', '%'.$queries.'%');
				return $query;
			});
		$data['recordsFiltered'] = $tempData->count();

		if (!empty($params['sortColumn']) and !empty($params['sortDirection']))
				$tempData = $tempData->orderBy($params['sortColumn'],$params['sortDirection']);

		$data['data'] = $tempData->get()->toArray();

		return $this->json($response, $data, 200);
	}

########	ACL Datatables	###############END###########
################################################
################################################
########	List ACL	###############START###########
	#########	GET List ACL#########
	public function getAclList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'acl',
			'action' => 'list',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//
		///IF GROUPID SET///
		if ($this->param($request, 'id') != null){
			$id = explode(',', $this->param($request, 'id'));

			$data['results'] = TACACL::select(['id','name AS text'])->whereIn('id', $id)->get();
			// if (  !count($data['results']) ) $data['results'] = null;
			return $this->json($response, $data, 200);
		}
		//////////////////////
		////LIST OF GROUPS////
		$query = TACACL::select(['id','name as text']);
		$data['total'] = $query->count();
		$search = $this->param($request, 'search');

		$query = $query->when( !empty($search), function($query) use ($search)
			{
				$query->where('name','LIKE', '%'.$search.'%');
			});

		$data['results']=$query->orderBy('name','asc')->get();

		return $this->json($response, $data, 200);
	}
########	List ACL	###############END###########
################################################
########	Reference List ACL	###############START###########
	#########	GET List ACL#########
	public function getAclRef(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'acl',
			'action' => 'ref',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		$data['obj'] = TACACL::select(['id','name as text'])->where('id',$this->param($request, 'id'))->first();
		$data['mainlist'] = [
			[ 'name' => 'TACACS Users', 'list' => [] ],
			[ 'name' => 'TACACS User Groups', 'list' => [] ],
			[ 'name' => 'TACACS Devices', 'list' => [] ],
			[ 'name' => 'TACACS Device Groups', 'list' => [] ],
			[ 'name' => 'TACACS User Groups Ref', 'list' => [] ],
			[ 'name' => 'TACACS Services Ref', 'list' => [] ],
		];

		$data['mainlist'][0]['list'] = $this->db->table('tac_users as tu')->
		select(['tu.username as text', 'tu.id as id'])->
		where('acl',$this->param($request, 'id'))->get();

		$data['mainlist'][1]['list'] = $this->db->table('tac_user_groups as tug')->
		select(['tug.name as text', 'tug.id as id'])->
		where('acl',$this->param($request, 'id'))->get();

		$data['mainlist'][2]['list'] = $this->db->table('tac_devices as td')->
		select(['td.name as text', 'td.id as id'])->
		where('acl',$this->param($request, 'id'))->get();

		$data['mainlist'][3]['list'] = $this->db->table('tac_device_groups as tdg')->
		select(['tdg.name as text', 'tdg.id as id'])->
		where('acl',$this->param($request, 'id'))->get();

		$data['mainlist'][4]['list'] = $this->db->table('tac_user_groups as tug')->
		select(['tug.name as text', 'tug.id as id'])->
		where('acl_match',$this->param($request, 'id'))->get();

		$data['mainlist'][5]['list'] = $this->db->table('tac_services as ts')->
		select(['ts.name as text', 'ts.id as id'])->
		where('acl',$this->param($request, 'id'))->get();

		return $this->json($response, $data, 200);
	}
########	Reference List ACL	###############END###########
################################################

	public function getAclId($acl){
		$id = 0;
		$messages = [];
		$type = 0;

		if ( ctype_digit( (string) $acl ) ){
			$temp = TACACL::select('name')->where('id', $acl)->first();
			if ($temp)
				return [$acl, ['ACL found: '. $temp->name]];
			else
				return [0, ['ACL with id '.$acl.' NOT found']];
		}

		$temp = TACACL::select('id')->where('name', $acl)->first();
		if (!$temp)
			return [0, ['ACL with name '.$acl.' NOT found']];

		return [$temp->id, ['ACL name '.$acl.' found']];

	}

	public function getAclAction($action = 0){
		if ( ctype_digit( (string) $action ) ){
			if ( (string) $action == '1' )
				return 1;
			else
				return 0;
		}

		if ( (string) $action == 'permit' )
			return 1;

		return 0;
	}

	public function prepareAcl($acl){
		$newAcl = [];
		$checkLength = 0;
		$name = '';
		$messages = [];

		for ($i=0; $i < count($acl); $i++) {
			if (empty($checkLength))
				$checkLength = count(array_keys($acl[$i]));
			if ($checkLength != count(array_keys($acl[$i])))
				return [[], ['Incorrect file fields list!']];

			$name = $acl[$i]['name'];
			if( empty($newAcl[$name]) )
				$newAcl[ $name ] = [ 'name' => $name, 'ace' => [] ];

			if (empty($acl[$i]['order']))
				$acl[$i]['order'] = (count($newAcl[$name]['ace']) + 1);

			$acl[$i]['action'] = $this->getAclAction( $acl[$i]['action'] );

			if (!empty($acl[$i]['nas'])){
				list($acl[$i]['nas'], $messages) = $this->ObjAddress->getAddressId( $acl[$i]['nas'] );
				if (empty($acl[$i]['nas']))
					return [[], $messages];
			}
			if (!empty($acl[$i]['nac'])){
				list($acl[$i]['nac'], $messages) = $this->ObjAddress->getAddressId( $acl[$i]['nac'] );
				if (empty($acl[$i]['nac']))
					return [[], $messages];
			}

			$newAcl[ $name ]['ace'][] = [
				'order' => $acl[$i]['order'],
				'action' => $acl[$i]['action'],
				'nas' => empty($acl[$i]['nas']) ? null : $acl[$i]['nas'] ,
				'nac' => empty($acl[$i]['nac']) ? null : $acl[$i]['nac'] ,
			];

		}

		return array_values($newAcl);
	}

}//END OF CLASS//
