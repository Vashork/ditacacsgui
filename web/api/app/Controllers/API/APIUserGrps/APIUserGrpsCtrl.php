<?php

declare(strict_types=1);

namespace tgui\Controllers\API\APIUserGrps;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use tgui\Models\APIUserGrps;
use tgui\Controllers\Controller;
use Respect\Validation\Validator as v;

class APIUserGrpsCtrl extends Controller
{
	private function rightsToOneValue(array $array): int
	{
		$return=0;
		for ($i=0; $i < count($array); $i++)
		{
			$return+=pow(2, $array[$i]);
		}
		return $return;
	}
###############################################
	#########	POST Add New User Group	#########
	public function postUserGroupAdd(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'user group',
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
		if(!$this->checkAccess(8))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'name' => v::noWhitespace()->notEmpty()->theSameNameUsed( '\tgui\Models\APIUserGrps' ),
			'rights' => v::numericVal()->notEmpty()->min(1),//->arrayType(),//->adminRights(),
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$allParams = $this->params($request);

		$ldapGroups = $allParams['ldap_groups'];
		unset($allParams['ldap_groups']);

		// if ($allParams['default_flag']) APIUserGrps::where([['default_flag', '=', 1]])->update(['default_flag' => 0]);
		//
		// $allParams['rights'] = $this->rightsToOneValue($allParams['rights']);

		$data['group'] = APIUserGrps::create($allParams);

		$ldap_bind = [];
		foreach ($ldapGroups as $ldapGroup) {
			$ldap_bind[] = ['api_grp_id' => $data['group']->id, 'ldap_id' => $ldapGroup];
		}
		$this->db::table('ldap_bind')->insert($ldap_bind);

		$logEntry=array('action' => 'add', 'obj_name' => $data['group']->name, 'obj_id' => $data['group']->id, 'section' => 'api user groups', 'message' => 206);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		$data['backup_status'] = $this->APIBackupCtrl->apicfgSet();
		if ( $this->APIBackupCtrl->apicfgSet() )
		$data['backup'] = $this->APIBackupCtrl->makeBackup(['make' => 'apicfg']);

		return $this->json($response, $data, 200);
	}
########	Add New User Group	###############END###########
################################################
########	Edit User Group	###############START###########
	#########	GET Edit User Group	#########
	public function getUserGroupEdit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'user group',
			'action' => 'edit',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(8))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$data['group']=APIUserGrps::select()->
			where('id',$this->param($request, 'id'))->
			first();

		$data['group']['ldap_groups']=$this->db::table('ldap_bind')->
			leftJoin('ldap_groups as ld','ld.id','=','ldap_id')->
			select(['ld.cn as text', 'ld.id as id'])->where('api_grp_id',$this->param($request, 'id'))->get();

		return $this->json($response, $data, 200);
	}

	#########	POST Edit User Group	#########
	public function postUserGroupEdit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{

		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'user group',
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
		if(!$this->checkAccess(8))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'name' => v::noWhitespace()->notEmpty()->theSameNameUsed( '\tgui\Models\APIUserGrps', $this->param($request, 'id') ),
			'rights' => v::numericVal()->notEmpty()->min(1),
			'id' => v::numericVal()->notEmpty()->min(1),
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$allParams = $this->params($request);

		$ldapGroups = $allParams['ldap_groups'];
		unset($allParams['ldap_groups']);

		// if ($allParams['default_flag']) APIUserGrps::where([['default_flag', '=', 1]])->update(['default_flag' => 0]);
		// $id = $allParams['id'];
		// unset($allParams['id']);
		// $allParams['rights'] = $this->rightsToOneValue($allParams['rights']);
		$id = $allParams['id'];
		$data['save']=APIUserGrps::where('id',$id)->
			update($allParams);

		$ldap_bind = [];
		foreach ($ldapGroups as $ldapGroup) {
			$ldap_bind[] = ['api_grp_id' => $id, 'ldap_id' => $ldapGroup];
		}
		$this->db::table('ldap_bind')->where('api_grp_id', $id)->delete();
		$this->db::table('ldap_bind')->insert($ldap_bind);

		$data['save'] = 1;

		$name = $allParams['name'];

		$logEntry=array('action' => 'edit', 'obj_name' => $name, 'obj_id' => $id, 'section' => 'api user groups', 'message' => 306);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		$data['backup_status'] = $this->APIBackupCtrl->apicfgSet();
		if ( $this->APIBackupCtrl->apicfgSet() )
		$data['backup'] = $this->APIBackupCtrl->makeBackup(['make' => 'apicfg']);

		return $this->json($response, $data, 200);
	}
########	Edit User Group	###############END###########
################################################
########	Delete User Group	###############START##########
	#########	POST Delete User Group	#########
	public function postUserGroupDelete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'user group',
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
		if(!$this->checkAccess(8))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$data['result']=APIUserGrps::where('id',$this->param($request, 'id'))->delete();
		$data['id']=$this->param($request, 'id');
		$data['name']=$this->param($request, 'name');

		$logEntry=array('action' => 'delete', 'obj_name' => $this->param($request, 'name'), 'obj_id' => $this->param($request, 'id'), 'section' => 'api user groups', 'message' => 306);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		$data['backup_status'] = $this->APIBackupCtrl->apicfgSet();
		if ( $this->APIBackupCtrl->apicfgSet() )
		$data['backup'] = $this->APIBackupCtrl->makeBackup(['make' => 'apicfg']);

		return $this->json($response, $data, 200);
	}
########	Delete User Group	###############END###########
################################################
########	User Group Datatables ###############START###########
	#########	POST User Group Datatables	#########
	public function postUserGroupDatatables(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'user group',
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
		if(!$this->checkAccess(8, true))
		{
			$data['data'] = [];
			$data['recordsTotal'] = 0;
			$data['recordsFiltered'] = 0;
			return $this->json($response, $data, 200);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$params=$this->params($request); //Get ALL parameters form Datatables

		$columns = $this->APICheckerCtrl->getTableTitles('api_user_groups'); //Array of all columnes that will used
		array_unshift( $columns, 'id' );
		array_push( $columns, 'created_at', 'updated_at' );
		$data['columns'] = $columns;
		$queries = (empty($params['searchTerm'])) ? [] : $params['searchTerm'];

		//Filter end
		$data['recordsTotal'] = APIUserGrps::count();
		//Get temp data for Datatables with Fliter and some other parameters
		$tempData = APIUserGrps::select($columns)->
		when( !empty($queries),
			function($query) use ($queries)
			{
				$query->where('username','LIKE', '%'.$queries.'%');
				return $query;
			});
		$data['recordsFiltered'] = $tempData->count();

		if (!empty($params['sortColumn']) and !empty($params['sortDirection']))
				$tempData = $tempData->orderBy($params['sortColumn'],$params['sortDirection']);

		$data['data'] = $tempData->get()->toArray();

		return $this->json($response, $data, 200);
	}

########	User Group Datatables	###############END###########
################################################
########	User Group Rights List ###############START###########
	private $rightsList=array(
		[ 'name' => 'DEMO (just view all)', 'value' => '0'],
		[ 'name' => 'Administrator', 'value' => '1'],
		[ 'name' => 'Add/Edit/Delete Tac Devices', 'value' => '2'],
		[ 'name' => 'Add/Edit/Delete Tac Device Groups', 'value' => '3'],
		[ 'name' => 'Add/Edit/Delete Tac Users', 'value' => '4'],
		[ 'name' => 'Add/Edit/Delete Tac User Groups', 'value' => '5'],
		[ 'name' => 'Edit/Apply/Test Tac Configuration', 'value' => '6'],
		// [ 'name' => 'Add/Edit/Delete API Users', 'value' => '7'],
		// [ 'name' => 'Add/Edit/Delete API User Groups', 'value' => '8'],
		[ 'name' => 'Add/Edit/Delete Object Addresses', 'value' => '14'],
		[ 'name' => 'Delete/Restore Backups', 'value' => '9'],
		[ 'name' => 'Upgrade API', 'value' => '10'],
		[ 'name' => 'MAVIS', 'value' => '11'],
		[ 'name' => 'Add/Edit/Delete Tac ACL', 'value' => '12'],
		[ 'name' => 'Add/Edit/Delete Tac Services', 'value' => '13'],
	);
	#########	POST User Group 	#########
	public function postUserGroupRightsList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'user group rights',
			'action' => 'list',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		$data['rights']=$this->rightsList;

		return $this->json($response, $data, 200);
	}

########	User Group Rights List	###############END###########
################################################
########	List User Group	###############START###########
	#########	GET List User	Group#########
	public function getUserGroupList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'user group',
			'action' => 'list',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(8, true))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		///IF GROUPID SET///
		if ($this->param($request, 'id') != null){
			$id = explode(',', $this->param($request, 'id'));

			$data['results'] = APIUserGrps::select(['id','name AS text'])->whereIn('id', $id)->get();
			// if (  !count($data['results']) ) $data['results'] = null;
			return $this->json($response, $data, 200);
		}
		//////////////////////
		////LIST OF GROUPS////
		$query = APIUserGrps::select(['id','name as text']);
		$data['total'] = $query->count();
		$search = $this->param($request, 'search');

		$query = $query->when( !empty($search), function($query) use ($search)
			{
				$query->where('name','LIKE', '%'.$search.'%');
			});

		$data['results']=$query->orderBy('name','asc')->get();

		return $this->json($response, $data, 200);
	}
########	List User Group	###############END###########
################################################
}//END OF CLASS//
