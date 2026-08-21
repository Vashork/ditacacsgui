<?php

declare(strict_types=1);

namespace tgui\Controllers\APILogging;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use tgui\Models\APILogging;
use tgui\Models\APILoggingMissRules;
use tgui\Controllers\Controller;

use Respect\Validation\Validator as v;
use Symfony\Component\Yaml\Yaml;


class APILoggingCtrl extends Controller
{
	###################	MAKE LOG ENTRIES########START##
	public function makeLogEntry(array $attrArray)
	{
		$attrArrayStatic=array('username' => (empty($_SESSION['uname']))? '' : $_SESSION['uname'], 'uid' => (empty($_SESSION['uid']))? '' : $_SESSION['uid'], 'user_ip' => $_SERVER['REMOTE_ADDR']);

		if (isset($attrArray['message']))
		{
			require __DIR__ . '/messages.php';
			$attrArray['message']=$MESSAGES[$attrArray['message']];
		}

		$logEntry = APILogging::create( array_merge($attrArrayStatic, $attrArray) );

		return $logEntry;
	}
	###################	MAKE LOG ENTRIES########END##
	#######################################
	###################	POST LOGGING DATATABLES ########START##
	public function postLoggingDatatables(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'logging',
			'action' => 'datatables',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

		unset($data['error']);//BEACAUSE DATATABLES USES THAT VARIABLE//

		$params=$this->params($request); //Get ALL parameters form Datatables

		$columns = $this->APICheckerCtrl->getTableTitles('api_logging', 'logging'); //Array of all columnes that will used
		array_unshift( $columns, 'id' );
		array_push( $columns, 'created_at');
		$data['columns'] = $columns;
		$queries = (empty($params['searchTerm'])) ? [] : $params['searchTerm'];
		$size = $params['pageSize'];
		$start = $params['pageSize'] * ($params['page'] - 1);
		//Filter end
		$data['recordsTotal'] = APILogging::count();
		//Get temp data for Datatables with Fliter and some other parameters
		$tempData = APILogging::select($columns)->
		when( !empty($queries),
			function($query) use ($queries)
			{
				$query->where('username','LIKE', '%'.$queries.'%');
				$query->orWhere('user_ip','LIKE', '%'.$queries.'%');
				return $query;
			});
		$data['total'] = $tempData->count();

		$tempData->take($size)->offset($start);

		if (!empty($params['sortColumn']) and !empty($params['sortDirection']))
				$tempData = $tempData->orderBy($params['sortColumn'],$params['sortDirection']);

		$data['data'] = $tempData->get()->toArray();

		return $this->json($response, $data, 200);
	}
	###################	POST LOGGING DATATABLES ########END##
	public function postLoggingDelete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'logging',
			'action' => 'delete',
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
		$allParams = $this->params($request);
		$period = '';
		if (!preg_match('/^[0-9]\s(year[s]{0,1}|month[s]{0,1})', $allParams['period']))
		{
			$dateCount = new \DateTime;
			$dateCount->modify('-'.$allParams['period']);
			$period = $dateCount->format('Y-m-d H:i:s');
		}
		if ($allParams['period'] == 'all') $period = 'all';

		if (empty($period)){
			$data['error']['status']=true;
			return $this->json($response, $data, 200);
		}
		$data['period'] = $period;
		$data['result'] = ($period == 'all') ?  APILogging::delete() : APILogging::where('created_at','<=',$period)->delete();

		return $this->json($response, $data, 200);
	}

	public function postDelSpecial(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'logging',
			'action' => 'delete special',
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
			'username' => v::noWhitespace()->notEmpty(),
			'nac' => v::notEmpty()->oneOf( v::ip('*', FILTER_FLAG_IPV6), v::ip() ),
			//'nac_address' => v::notEmpty(),
			'date_from' => v::when( v::NullType(), v::alwaysValid(), v::date('Y-m-d H:i:s')->setName('Date From') ),
			'date_to' => v::when( v::NullType(), v::alwaysValid(), v::date('Y-m-d H:i:s')->setName('Date To') ),
			'tacacs_type' => v::oneOf( v::notEmpty(), v::numericVal() )->between(0, 1)->setName('TACACS Type'),
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$allParams = $this->params($request);

		$range = ( !empty($allParams['date_to']) AND !empty($allParams['date_from']) );

		if ( $range AND strtotime($allParams['date_from']) > strtotime($allParams['date_to'])){
			$data['error']['status']=true;
			$data['error']['validation']=['date_from' => ['Date From can\'t be more Date To']];
			return $this->json($response, $data, 200);
		}

		$table = ($allParams['tacacs_type'] == 1) ? 'tac_log_authorization' : 'tac_log_authentication';

		$data['result'] = 0;
		$query = $this->db->connection('logging')->table($table);

		$range = ( !empty($allParams['date_to']) AND !empty($allParams['date_from']) );

		if ($range)
			$query = $query->whereBetween('date', [ $allParams['date_from'], $allParams['date_to'] ]);

		if (!$range AND !empty($allParams['date_to']))
			$query = $query->whereBetween('date', [ date('Y-m-d H:i:s', 0), $allParams['date_to'] ]);

		if (!$range AND !empty($allParams['date_from']))
			$query = $query->whereBetween('date', [ $allParams['date_from'], date('Y-m-d H:i:s', time()) ]);

		$data['result'] = $query->where([['username',$allParams['username']],['nac',$allParams['nac']]])->delete();


		return $this->json($response, $data, 200);
	}

	public function postMissAdd(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'logging miss',
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
			'name' => v::notEmpty(),
			'username' => v::noWhitespace()->notEmpty(),
			//'nas_address' => v::notEmpty(),
			//'nac_address' => v::notEmpty(),
			'tacacs_type' => v::oneOf(v::notEmpty(), v::numericVal())->between(0, 2)->setName('TACACS Type'),
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$data['rule'] = APILoggingMissRules::create($this->params($request));

		$data['file'] = $this->createMissFile();

		return $this->json($response, $data, 200);
	}

	public function postMissDel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'logging miss',
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
		if(!$this->checkAccess(1))
		{
			return $this->json($response, $data, 403);
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$validation = $this->validator->validate($request, [
			'id' => v::notEmpty()->numeric(),
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

		$data['result'] = APILoggingMissRules::where('id',$this->param($request, 'id'))->delete();

		$data['file'] = $this->createMissFile();

		return $this->json($response, $data, 200);
	}

	public function postMissTable(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'logging miss',
			'action' => 'table',
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

		$params=$this->params($request); //Get ALL parameters form Datatables

		$columns = [];
		array_unshift( $columns, 'api_logging_miss_rules.*' );
		// array_push( $columns,
		// 	$this->db::raw('IFNULL(NULL, "Any") nac_address')
		// );
		array_push( $columns,
			$this->db::raw('IFNULL(addr.address, "Any") as nac')
		);
		array_push( $columns,
			$this->db::raw('CASE WHEN tacacs_type = 0 THEN "Authe"
WHEN tacacs_type = 1 THEN "Autho"
WHEN tacacs_type = 2 THEN "Acc"
ELSE "Unknown"
END AS tacacs_type')
		);
		$data['columns'] = $columns;
		$queries = (empty($params['searchTerm'])) ? [] : $params['searchTerm'];

		//Filter end
		$data['recordsTotal'] = APILoggingMissRules::count();
		//Get temp data for Datatables with Fliter and some other parameters
		$tempData = APILoggingMissRules::
		leftJoin('obj_addresses as addr', 'addr.id', '=', 'nac_address')->
		select($columns)->
		when( !empty($queries),
			function($query) use ($queries)
			{
				$query->where('name','LIKE', '%'.$queries.'%');
				$query->orWhere('username','LIKE', '%'.$queries.'%');
				return $query;
			});
		$data['recordsFiltered'] = $tempData->count();

		if (!empty($params['sortColumn']) and !empty($params['sortDirection']))
				$tempData = $tempData->orderBy($params['sortColumn'],$params['sortDirection']);

		$data['data'] = $tempData->get()->toArray();

		return $this->json($response, $data, 200);
	}

	public function createMissFile(){
		$configDir = '/opt/tgui_data/parser';
		if (!file_exists($configDir)) {
		    mkdir($configDir . $dirname, 0777);
		}
		$configFile = $configDir.'/missRules.yaml';
		$missRules = [ 'tacacs'=>[] ];

		$missRules['tacacs'] = [
			'authe' => APILoggingMissRules::
				leftJoin('obj_addresses as addr', 'addr.id', '=', 'nac_address')->
				select('username', 'addr.address as nac')->
				where('api_logging_miss_rules.type', 0)->
				where('tacacs_type', 0)->get()->toArray(),
			'autho' => APILoggingMissRules::
				leftJoin('obj_addresses as addr', 'addr.id', '=', 'nac_address')->
				select('username', 'addr.address as nac')->
				where('api_logging_miss_rules.type', 0)->
				where('tacacs_type', 1)->get()->toArray(),
			'acc' => APILoggingMissRules::
				leftJoin('obj_addresses as addr', 'addr.id', '=', 'nac_address')->
				select('username', 'addr.address as nac')->
				where('api_logging_miss_rules.type', 0)->
				where('tacacs_type', 2)->get()->toArray()
		];

		if (empty($missRules['tacacs']['authe']))
			unset($missRules['tacacs']['authe']);
		if (empty($missRules['tacacs']['autho']))
			unset($missRules['tacacs']['autho']);
		if (empty($missRules['tacacs']['acc']))
			unset($missRules['tacacs']['acc']);

		$yaml = Yaml::dump($missRules);
		return file_put_contents($configFile, $yaml);
	}
}
