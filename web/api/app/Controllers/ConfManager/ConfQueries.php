<?php

declare(strict_types=1);

namespace tgui\Controllers\ConfManager;

use tgui\Models\Conf_Queries;
use tgui\Models\Conf_Devices;
use tgui\Models\Conf_Models;
use tgui\Controllers\Controller;
use Respect\Validation\Validator as v;

use Symfony\Component\Yaml\Yaml;

use tgui\Services\CMDRun\CMDRun as CMDRun;

use tgui\Controllers\ConfManager\ConfManagerHelper as Helper;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ConfQueries extends Controller
{
################################################
	public function postAdd(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'ConfQueries',
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
		if(!$this->checkAccess(1))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'name' => v::noWhitespace()->notEmpty()->alnum()->theSameNameUsed( '\tgui\Models\Conf_Queries' ),
			'model' => v::numericVal()->noWhitespace()->notEmpty(),
			'devices' => v::notEmpty()->arrayType()->each( v::numericVal() ),
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$allParams = $this->params($request);

		if ( empty($allParams['credential']) ) unset($allParams['credential']);

		$devices = $allParams['devices'];
		unset($allParams['devices']);

		$query = Conf_Queries::create($allParams);

		$devices_bind = [];
		foreach ($devices as $device) {
			$devices_bind[] = ['query_id' => $query->id, 'device_id' => $device];
		}
		$this->db::table('confM_bind_query_devices')->insert($devices_bind);

		if ( !$query->disabled ){
			$this->ConfManager->createConfig();
		}

		$data['query'] = 1;

		return $this->json($response, $data, 200);
	}

	public function getEdit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'ConfQueries',
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
		if(!$this->checkAccess(1))
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
		//$data['test333'] = $this->ConfManager->createConfig();

		$data['query'] = Conf_Queries::leftJoin('confM_models as cm', 'cm.id', '=', 'confM_queries.model')->
		leftJoin('confM_credentials as cc', 'cc.id', '=', 'confM_queries.credential')->
		select(['confM_queries.*','cm.name as model_name', 'cc.name as credo_name'])->where('confM_queries.id', $this->param($request, 'id'))->first();

		$data['query']['f_group'] = (empty($data['query']['f_group'])) ?
			[] : [[ 'id' => $data['query']['f_group'], 'text' => $data['query']['f_group']]];
		$data['query']['model'] = (empty($data['query']['model_name'])) ?
			[] : [[ 'id' => $data['query']['model'], 'text' => $data['query']['model_name']]];
		unset($data['query']['model_name']);
		$data['query']['credential'] = (empty($data['query']['credo_name'])) ?
			[] : [[ 'id' => $data['query']['credential'], 'text' => $data['query']['credo_name']]];
		unset($data['query']['credo_name']);

		$data['query']['devices'] = $this->db::table('confM_bind_query_devices')->
		leftJoin('confM_devices as cd', 'cd.id', '=', 'device_id')->
		select(["device_id as id", 'cd.name as text'])->where('query_id', $this->param($request, 'id'))->get();

		return $this->json($response, $data, 200);
	}
	////////////////////////////////////////////////////////////////
	public function postEdit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'ConfQueries',
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
		if(!$this->checkAccess(1))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

    $validation = $this->validator->validate($request, [
			'name' => v::noWhitespace()->notEmpty()->alnum()->theSameNameUsed( '\tgui\Models\Conf_Queries', $this->param($request, 'id') )->setName('Name'),
			'model' => v::numericVal()->noWhitespace()->notEmpty()->setName('Model'),
			'devices' => v::notEmpty()->arrayType()->each( v::numericVal()->setName('Devices') )->setName('Devices'),
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$allParams = $this->params($request);

		if ( isset($allParams['credential']) AND $allParams['credential'] == '') $allParams['credential'] = null;

		$devices = isset($allParams['devices']) ? $allParams['devices'] : false ;
		unset( $allParams['devices'] );
		$old_group = '_';
		if ( isset($allParams['f_group']) ){
			$old_group = Conf_Queries::select('f_group')->where( 'id', $this->param($request, 'id') )->first()->f_group;
		}

		$cmd = Conf_Queries::where( 'id', $this->param($request, 'id') )->update($allParams);

		if ($devices){
			$devices_bind = [];
			$this->db::table('confM_bind_query_devices')->where('query_id', $this->param($request, 'id'))->delete();
			foreach ($devices as $device) {
				$devices_bind[] = ['query_id' => $this->param($request, 'id'), 'device_id' => $device];
			}
			$this->db::table('confM_bind_query_devices')->insert($devices_bind);
		}

		if ( $old_group != '_'){
			$data['old_path'] = (empty($old_group)) ? '' : $old_group.'/';
			$data['new_path'] = (empty($allParams['f_group'])) ? '' : $allParams['f_group'].'/';
			$allQueries = $this->db::table('confM_queries as q')->
				leftJoin('confM_bind_query_devices as qu_de', 'qu_de.query_id', '=', 'q.id')->
				leftJoin('confM_devices as d', 'd.id', '=', 'device_id')->
				select([
					'q.id as q_id',
					'qu_de.device_id as d_id',
					'd.name as d_name'
				])->where('q.id',$this->param($request, 'id'))->get();
			foreach ($allQueries as $query_file) {
				$old_path = $data['old_path'].$query_file->d_name.'__'.$query_file->d_id.'_'.$query_file->q_id;
				$new_path = $data['new_path'].$query_file->d_name.'__'.$query_file->d_id.'_'.$query_file->q_id;
				$data['show_cmd'] = CMDRun::init()->
					setCmd('/opt/tacacsgui/plugins/ConfigManager/cm_git.sh')->
					setAttr(['--mv-from='.$old_path,'--mv-to='.$new_path])->
					get();
			}
			Helper::forceCommit();
		}

		$this->ConfManager->createConfig();

		$data['save'] = 1;

		return $this->json($response, $data, 200);
	}
	///////////////////////////////////////////////////////////
	public function postDel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'ConfQueries',
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
		if(!$this->checkAccess(1))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'name' => v::noWhitespace()->notEmpty(),//->theSameNameUsed( '\tgui\Models\Conf_Queries' ),
			'id' => v::numericVal()
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$data['result'] = Conf_Queries::where( 'id', $this->param($request, 'id') )->delete();

		$this->ConfManager->createConfig();

		return $this->json($response, $data, 200);
	}

  public function postDatatables(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data=array();
    $data=$this->initialData([
      'type' => 'post',
      'object' => 'ConfQueries',
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
    if(!$this->checkAccess(4, true))
    {
      $data['data'] = [];
      $data['recordsTotal'] = 0;
      $data['recordsFiltered'] = 0;
      return $this->json($response, $data, 200);
    }
    //CHECK ACCESS TO THAT FUNCTION//END//

    $params = $this->params($request); //Get ALL parameters form Datatables

    $columns = $this->APICheckerCtrl->getTableTitles('confM_queries'); //Array of all columnes that will used
    array_unshift( $columns, 'id' );
    array_push( $columns, 'created_at', 'updated_at',
		'models.name as model', 'cre.name as creden_name', $this->db::raw('count(*) as devices') );
		$columns[array_search('name', $columns)] = 'confM_queries.name AS name';
		$columns[array_search('id', $columns)] = 'confM_queries.id AS id';
		$columns[array_search('created_at', $columns)] = 'confM_queries.created_at AS created_at';
		$columns[array_search('updated_at', $columns)] = 'confM_queries.updated_at AS updated_at';

		$data['columns'] = $columns;
		$queries = (empty($params['searchTerm'])) ? [] : $params['searchTerm'];

		//Filter end
		$data['recordsTotal'] = Conf_Queries::count();
		//Get temp data for Datatables with Fliter and some other parameters
		$tempData = Conf_Queries::leftJoin('confM_models as models', 'models.id', '=', 'confM_queries.model')->
			leftJoin('confM_credentials as cre', 'cre.id', '=', 'confM_queries.credential')->
			leftJoin('confM_bind_query_devices as qd', 'qd.query_id', '=', 'confM_queries.id')->
			groupBy('confM_queries.id')->
			select($columns);
		// when( !empty($queries),
		// 	function($query) use ($queries)
		// 	{
		// 		$query->where('username','LIKE', '%'.$queries.'%');
		// 		return $query;
		// 	});
		$data['recordsFiltered'] = $tempData->count();

		if (!empty($params['sortColumn']) and !empty($params['sortDirection']))
				$tempData = $tempData->orderBy($params['sortColumn'],$params['sortDirection']);

		$data['data'] = $tempData->get()->toArray();

		return $this->json($response, $data, 200);
    // $data['columns'] = $columns;
    // $queries = [];
    // $data['filter'] = [];
    // $data['filter']['error'] = false;
    // $data['filter']['message'] = '';
    // //Filter start
    // $searchString = ( empty($params['search']['value']) ) ? '' : $params['search']['value'];
    // $temp = $this->queriesMaker($columns, $searchString);
    // $queries = $temp['queries'];
    // $data['filter'] = $temp['filter'];
		//
    // $data['queries'] = $queries;
    // $data['columns'] = $columns;
    // //Filter end
    // $data['recordsTotal'] = Conf_Queries::count();
    // //Get temp data for Datatables with Fliter and some other parameters
    // $tempData = Conf_Queries::leftJoin('confM_models as models', 'models.id', '=', 'confM_queries.model')->
		// 	leftJoin('confM_credentials as cre', 'cre.id', '=', 'confM_queries.credential')->
		// 	leftJoin('confM_bind_query_devices as qd', 'qd.query_id', '=', 'confM_queries.id')->
		// 	groupBy('confM_queries.id')->
		// 	select($columns)->
    //   when( !empty($queries),
    //     function($query) use ($queries)
    //     {
    //       foreach ($queries as $condition => $attr) {
    //         switch ($condition) {
    //           case '!==':
    //             foreach ($attr as $column => $value) {
    //               $query->whereNotIn($column, $value);
    //             }
    //             break;
    //           case '==':
    //             foreach ($attr as $column => $value) {
    //               $query->whereIn($column, $value);
    //             }
    //             break;
    //           case '!=':
    //             foreach ($attr as $column => $valueArr) {
    //               for ($i=0; $i < count($valueArr); $i++) {
    //                 if ($i == 0) $query->where($column,'NOT LIKE', '%'.$valueArr[$i].'%');
    //                 $query->where($column,'NOT LIKE', '%'.$valueArr[$i].'%');
    //               }
    //             }
    //             break;
    //           case '=':
    //             foreach ($attr as $column => $valueArr) {
    //               for ($i=0; $i < count($valueArr); $i++) {
    //                 if ($i == 0) $query->where($column,'LIKE', '%'.$valueArr[$i].'%');
    //                 $query->where($column,'LIKE', '%'.$valueArr[$i].'%');
    //               }
    //             }
    //             break;
    //           default:
    //             //return $query;
    //             break;
    //         }
    //       }
    //       return $query;
    //     });
    //     $data['recordsFiltered'] = $tempData->count();
		//
		// 		// $data['test_23'] = $tempData->
   	// 		// orderBy($params['columns'][$params['order'][0]['column']]['data'],$params['order'][0]['dir'])->
   	// 		// take($params['length'])->
   	// 		// offset($params['start'])->toSql();
		//
   	// 		$tempData = $tempData->
   	// 		orderBy($params['columns'][$params['order'][0]['column']]['data'],$params['order'][0]['dir'])->
   	// 		take($params['length'])->
   	// 		offset($params['start'])->
   	// 		get()->toArray();
   	// 	//Creating correct array of answer to Datatables
   	// 	$data['data']=array();
   	// 	foreach($tempData as $query){
   	// 		$buttons='<button class="btn btn-warning btn-xs btn-flat" onclick="cm_queries.get(\''.$query['id'].'\',\''.$query['name'].'\')">Edit</button> '.
		// 		'<button class="btn btn-info btn-xs btn-flat" disabled ><i class="fa fa-refresh"></i></button> '.
		// 		'<button class="btn btn-danger btn-xs btn-flat" onclick="cm_queries.del(\''.$query['id'].'\',\''.$query['name'].'\')">Del</button>';
   	// 		$query['buttons'] = $buttons;
   	// 		array_push($data['data'],$query);
   	// 	}
   	// 	//Some additional parameters for Datatables
   	// 	$data['draw']=intval( $params['draw'] );
		//
   	// 	return $this->json($response, $data, 200);
  }

	public function postPreview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'ConfQueries',
			'action' => 'preview',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(1))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'model' => v::numericVal()->noWhitespace()->notEmpty(),
			'device' => v::numericVal()->noWhitespace()->notEmpty(),
			'debug' => v::boolVal()
		]);

		$testCm = $data['testResponse'] = Helper::init()->cmGeneralCheck();

		if ( $validation->failed() OR !$testCm ){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			$data['error']['validation']['cm'] = !$testCm;
			return $this->json($response, $data, 200);
		}
		$creden = ( $this->param($request, 'credential') ) ? $this->param($request, 'credential') : 0;
		$model = Conf_Models::select()->where('id', $this->param($request, 'model'))->first();
		$device = $this->db::table('confM_devices as d')->
			leftJoin('confM_credentials as cd', 'cd.id', '=', 'd.credential')->
			leftJoin('obj_addresses as addr', 'addr.id', '=', 'd.address')->
			leftJoin('confM_credentials as cq', 'cq.id', '=', $this->db::raw($creden) )->
			select([
				'd.name as name',
				'd.prompt as prompt',
				$this->db::raw("substring_index(addr.address,'/',1) as ip"),
				//"substring_index(`addr.address`,'/',1) as ip",
				'd.protocol as protocol',
				'd.port as port',
				'cd.username as d_username',
				'cd.password as d_password',
				'cq.username as q_username',
				'cq.password as q_password',
				])->
			where('d.id', $this->param($request, 'device'))->first();
		$expectations = $this->db::table('confM_bind_model_expect')->select(['send','expect','write'])->where('model_id', $this->param($request, 'model'))->get()->toArray();

		if ( !$model OR !$device OR !$expectations) {
			$data['error']['status']=true;
			$data['error']['validation']['other'] = true;
			return $this->json($response, $data, 200);
		}

		$prompt_m = array_filter( array_map('trim', explode(',', $model->prompt) ), function($value) { return $value !== ''; } );
		$prompt_d = array_filter( array_map('trim', explode(',', $device->prompt) ), function($value) { return $value !== ''; } );

		$pattern = [ 'queries' =>
			[
				[
					'name' => $device->name,
					'ip' => $device->ip,
					'protocol' => $device->protocol,
					'port' => $device->port,
					'credential' => [
						'type' => 'text',
						'username' => ( ( !empty($device->d_username) OR !empty($device->d_password) ) ? $device->d_username : $device->q_username ),
						'password' => ( ( !empty($device->d_username) OR !empty($device->d_password) ) ? $device->d_password : $device->q_password ),
					],
					'group' => '',
					'prompt' => array_merge($prompt_d, $prompt_m),
					'omitLines' => array_filter( array_map('trim', explode(',', $this->param($request, 'omitLines')) ), function($value) { return $value !== ''; } ),
					'timeout' => 4,
					'expectations' => json_decode( json_encode($expectations), true )
				]
			]
		];

		$yaml = Yaml::dump( $pattern, 4 );
		file_put_contents( TAC_ROOT_PATH . '/temp/' . $device->name . '.yaml', $yaml);
		$debug = ( !!@$this->param($request, 'debug') ) ? ' -d' : '';
		try {
      $data['preview'] = CMDRun::init()->setCmd( MAINSCRIPT )->setAttr(['run', 'cmd', '/opt/tacacsgui/plugins/ConfigManager/cm.py', '-tq', TAC_ROOT_PATH . '/temp/' . $device->name . '.yaml' . $debug, '-m', '___', '-an'])->get();
    } catch (\Exception $e) {
      $data['preview'] = $e->getMessage();
      //$data['preview_err_'] = false;
    }
		#$data['test123123'] = shell_exec("/opt/tacacsgui/main.sh  'run' 'cmd' '/opt/tacacsgui/plugins/ConfigManager/cm.py' '-tq' '/opt/tacacsgui/temp/router_12.yaml' -d");

		return $this->json($response, $data, 200);
	}

}//END OF CLASS//
