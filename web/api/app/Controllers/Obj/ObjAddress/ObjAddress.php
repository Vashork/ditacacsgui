<?php

declare(strict_types=1);

namespace tgui\Controllers\Obj\ObjAddress;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use tgui\Models\ObjAddress_;
use tgui\Controllers\Controller;
use Respect\Validation\Validator as v;

class ObjAddress extends Controller
{
################################################
	public function itemValidation($req = [], string $state = 'add'){
		$id = 0;
		$type = 0;
		if (is_object($req)){
			$id = ($state == 'edit') ? $req->getParsedBody()['id'] ?? null : 0;
			$type = $req->getParsedBody()['type'] ?? null;
		} else {
			$type = (isset($req['type'])) ? $req['type'] : 0;
		}
		return $this->validator->validate($req, [
			'name' => v::noWhitespace()->notEmpty()->theSameNameUsed( '\tgui\Models\ObjAddress_', $id ),
			'address' => v::notEmpty()->checkAddress($type)->setName('Address'),
			'type' => v::numericVal()->oneOf( v::equals(0), v::equals(1), v::equals(2)),
		]);
	}

	public function postAdd(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'obj address',
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
		if(!$this->checkAccess(14))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->itemValidation($request);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$allParams = $this->params($request);

		$data['address'] = ObjAddress_::create($allParams);


		return $this->json($response, $data, 200);
	}

	public function getEdit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'obj address',
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
		if(!$this->checkAccess(14))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'id' => v::numericVal()
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$data['address'] = ObjAddress_::select()->where('id', $this->param($request, 'id'))->first();

		return $this->json($response, $data, 200);
	}
////////////////////////////////////////////////////////////
	public function postEdit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'obj address',
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
		if(!$this->checkAccess(14))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $validation = $this->itemValidation($request, 'edit');

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$allParams = $this->params($request);

		$data['save']=ObjAddress_::where('id', $allParams['id'])->update($allParams);

		return $this->json($response, $data, 200);
	}
	///////////////////////////////////////////////////////////
	public function postDel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'obj address',
			'action' => 'del',
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
		if(!$this->checkAccess(14))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'id' => v::numericVal()->notEmpty()
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		// if ( $this->db::table('confM_bind_query_devices')->where( 'device_id', $req->getParam('id') )->count() ){
		// 	$data['result'] = 0;
		// } else
		$data['result'] = ObjAddress_::where( 'id', $this->param($request, 'id') )->delete();

		return $this->json($response, $data, 200);
	}

  public function postDatatables(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data=array();
    $data=$this->initialData([
      'type' => 'post',
      'object' => 'confDevices',
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
    if(!$this->checkAccess(14, true))
    {
      $data['data'] = [];
      $data['recordsTotal'] = 0;
      $data['recordsFiltered'] = 0;
      return $this->json($response, $data, 200);
    }
    //CHECK ACCESS TO THAT FUNCTION//END//

    $params = $this->params($request); //Get ALL parameters form Datatables

		//$columns = $this->APICheckerCtrl->getTableTitles('obj_addresses'); //Array of all columnes that will used
		$columns = [];
		array_unshift( $columns, 'obj_addresses.*' );
		array_push( $columns,
			$this->db::raw('(SELECT COUNT(*) FROM tac_devices WHERE address = obj_addresses.id) + '.
			'(SELECT COUNT(*) FROM confM_devices WHERE address = obj_addresses.id) + '.
			'(select count(distinct tae.acl_id) from tgui.tac_acl_ace tae where tae.nac = obj_addresses.id or tae.nas = obj_addresses.id ) as ref')
		);
		$data['columns'] = $columns;
		$queries = (empty($params['searchTerm'])) ? [] : $params['searchTerm'];

		$data['recordsTotal'] = ObjAddress_::count();
		//Get temp data for Datatables with Fliter and some other parameters
		$tempData = ObjAddress_::
			// leftJoin('tac_acl as acl', 'acl.id', '=', 'tac_devices.acl')->
			select($columns)->
			when( !empty($queries),
				function($query) use ($queries)
				{
					$query->where('obj_addresses.name','LIKE', '%'.$queries.'%');
					$query->orWhere('obj_addresses.address','LIKE', '%'.$queries.'%');
					return $query;
				});
			$data['recordsFiltered'] = $tempData->count();

			if (!empty($params['sortColumn']) and !empty($params['sortDirection']))
					$tempData = $tempData->orderBy($params['sortColumn'],$params['sortDirection']);
			$data['sql'] = $tempData->toSql();
			$data['data'] = $tempData->
			get()->toArray();

   	return $this->json($response, $data, 200);
  }

	public function getList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'obj',
			'action' => 'address list',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(14, true))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		///IF GROUPID SET///
		if ($this->param($request, 'id') != null){
			$result = ( is_array($id) ) ? ObjAddress_::select(['id','name AS text','type','address'])->whereIn('id', $this->param($request, 'id'))
			:
			ObjAddress_::select(['id','name AS text','type','address'])->where('id', $this->param($request, 'id'));
			$data['results'] = $result->orderBy('name')->get();
			// if (  !count($data['results']) ) $data['results'] = null;
			return $this->json($response, $data, 200);
		}
		//////////////////////
		////LIST OF GROUPS////
		$query = ObjAddress_::select(['id','name AS text','type','address'])->orderBy('name');
		$data['total'] = $query->count();
		$search = $this->param($request, 'search');

		$query = $query->when( !empty($search), function($query) use ($search)
			{
				$query->where('name','LIKE', '%'.$search.'%');
			});

		$data['results']=$query->orderBy('name')->get()->toArray();

		$extra = json_decode($this->param($request, 'extra'));

		if ( $extra AND !empty($extra->any) )
			array_unshift( $data['results'], ['text' => 'any', 'id' => 0, 'address' => 'any']);

		return $this->json($response, $data, 200);
	}

	public function getRef(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'obj',
			'action' => 'address ref',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(14, true))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$data['obj'] = ObjAddress_::select(['id','name as text'])->where('id',$this->param($request, 'id'))->first();
		$data['mainlist'] = [
			[ 'name' => 'TACACS Devices', 'list' => [] ],
			[ 'name' => 'TACACS ACLs', 'list' => [] ],
			[ 'name' => 'ConfManger Devices', 'list' => [] ],
		];

		$data['mainlist'][0]['list'] = $this->db->table('tac_devices as td')->
		select(['td.name as text', 'td.id as id'])->
		where('address',$this->param($request, 'id'))->get();

		$data['mainlist'][1]['list'] = $this->db->table('tac_acl as ta')->
		leftJoin('tac_acl_ace as tae', 'tae.acl_id','=','ta.id')->
		select(['ta.name as text', 'ta.id as id'])->
		groupBy('ta.id')->
		where('tae.nas',$this->param($request, 'id'))->orWhere('tae.nac',$this->param($request, 'id'))->get();

		$data['mainlist'][2]['list'] = $this->db->table('confM_devices as cd')->
		select(['cd.name as text', 'cd.id as id'])->
		where('address',$this->param($request, 'id'))->get();

		return $this->json($response, $data, 200);
	}

	public function selectType($type = 0){
		if (is_int($type))
			return $type;
		if ($type == 'ipv6')
			return 2;

		return 0;
	}

	public function getAddressId($address, $name = ''){
		$id = 0;
		$messages = [];
		$type = 0;
		switch (true) {
			case (v::CheckAddress(0)->validate($address)):
				break;
			case (v::CheckAddress(1)->validate($address)):
				$type = 1;
				break;
			default:
				if ( ctype_digit( (string) $address ) ){
					$temp = ObjAddress_::select('name')->where('id', $address)->first();
					if ($temp)
						return [$address, ['Address found: '. $temp->name]];
					else
						return [0, ['Address with id '.$address.' NOT found']];
				}
				else
					return [0, ['Incorrect Address '.$address]];
		}

		$temp = ObjAddress_::select(['id','name'])->where('address', $address)->first();
		if ($temp)
			return [$temp->id, ['Address found: '. $temp->name]];

		if (empty($name))
			$name = $address;

		$newAddr = ObjAddress_::create([
			'name' => $name,
			'address' => $address,
			'type' => $type,
		]);

		return [$newAddr->id, ['New Address was added: '. $newAddr->name]];
	}

}//END OF CLASS//
