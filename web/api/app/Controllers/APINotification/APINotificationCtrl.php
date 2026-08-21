<?php
declare(strict_types=1);
namespace tgui\Controllers\APINotification;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use tgui\Controllers\Controller;
use tgui\Models\APINotification;
use tgui\Models\PostLog;
use Respect\Validation\Validator as v;

use tgui\Services\CMDRun\CMDRun as CMDRun;

class APINotificationCtrl extends Controller
{
  public function getSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'notification',
			'action' => 'settings',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

    //CHECK ACCESS TO THAT FUNCTION//START//
    if(!$this->checkAccess(1, true))
    {
      return $this->json($response, $data, 403);
    }
    //CHECK ACCESS TO THAT FUNCTION//END//

    $data['settings'] = APINotification::select()->first();

    if ( empty($data['settings']->bad_authentication_email_list) ) $data['settings']->bad_authentication_email_list='';
    if ( empty($data['settings']->bad_authorization_email_list) ) $data['settings']->bad_authorization_email_list='';
    
		return $this->json($response, $data, 200);
	}
  public function postSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'notification',
			'action' => 'settings',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $this->json($response, $data, 401);
		}
		//INITIAL CODE////END//

    //CHECK ACCESS TO THAT FUNCTION//START//
    if( ! $this->checkAccess(1) )
    {
      return $this->json($response, $data, 403);
    }
    //CHECK ACCESS TO THAT FUNCTION//END//

    $allParams = $this->params($request);

    $validation = $this->validator->validate($request, [
      'bad_authentication_enable' => v::when( v::nullType() , v::alwaysValid(), v::numericVal()->min(0)->max(1)),
      'bad_authorization_enable' => v::when( v::nullType() , v::alwaysValid(), v::numericVal()->min(0)->max(1)),
      'bad_authentication_count' => v::when( v::nullType() , v::alwaysValid(), v::numericVal()->min(0)->max(60)->setName('Bad Authentication Count')),
      'bad_authorization_count' => v::when( v::nullType() , v::alwaysValid(), v::numericVal()->min(0)->max(60)->setName('Bad Authorization Count')),
      'bad_authentication_interval' => v::when( v::nullType() , v::alwaysValid(), v::numericVal()->min(0)->max(30)->setName('Bad Authentication Interval')),
      'bad_authorization_interval' => v::when( v::nullType() , v::alwaysValid(), v::numericVal()->min(0)->max(30)->setName('Bad Authorization Interval')),
      'bad_authentication_email_list' => v::when( v::nullType() , v::alwaysValid(), v::arrayType()->each(v::oneOf(v::email(), v::equals(''))->setName('Email List'))->setName('Email List')),
      'bad_authorization_email_list' => v::when( v::nullType() , v::alwaysValid(), v::arrayType()->each(v::oneOf(v::email(), v::equals(''))->setName('Email List'))->setName('Email List')),
    ]);

    if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $this->json($response, $data, 200);
		}

    if ( isset($allParams['bad_authentication_email_list']) ) $allParams['bad_authentication_email_list'] = implode('; ',$allParams['bad_authentication_email_list']);
    if ( isset($allParams['bad_authorization_email_list']) ) $allParams['bad_authorization_email_list'] = implode('; ',$allParams['bad_authorization_email_list']);

    $data['save'] = APINotification::where([['id','=',1]])->update($allParams);

    if ( $data['save'] ) {
      try {
        $data['del_settings'] = CMDRun::init()->setSudo()->setCmd(MAINSCRIPT)->setAttr(['delete', 'temp-file', 'notification-settings'])->get();
      } catch (\Exception $e) {
        $data['del_settings'] = $e->getMessage();
      }
    }

		return $this->json($response, $data, 200);
	}
  public function postDatatables(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'notification',
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

		$columns = $this->APICheckerCtrl->getTableTitles('post_log', 'logging'); //Array of all columnes that will used
		array_unshift( $columns, 'id' );

    $data['columns'] = $columns;
		$queries = (empty($params['searchTerm'])) ? [] : $params['searchTerm'];
		$size = $params['pageSize'];
		$start = $params['pageSize'] * ($params['page'] - 1);
		//Filter end
		$data['recordsTotal'] = PostLog::count();
		//Get temp data for Datatables with Fliter and some other parameters
		$tempData = PostLog::select($columns);
		// when( !empty($queries),
		// 	function($query) use ($queries)
		// 	{
		// 		$query->where('nas','LIKE', '%'.$queries.'%');
		// 		$query->orWhere('nac','LIKE', '%'.$queries.'%');
		// 		return $query;
		// 	})->
    $data['total'] = $tempData->count();

		$tempData = $tempData->take($size)->offset($start);

		if (!empty($params['sortColumn']) and !empty($params['sortDirection']))
				$tempData = $tempData->orderBy($params['sortColumn'],$params['sortDirection']);

		$data['data'] = $tempData->get()->toArray();

		return $this->json($response, $data, 200);
	}
  public function postBufferDatatables(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    //INITIAL CODE////START//
    $data=array();
    $data=$this->initialData([
      'type' => 'post',
      'object' => 'notification',
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

    $columns = $this->APICheckerCtrl->getTableTitles('post_buffer', 'logging'); //Array of all columnes that will used
    array_unshift( $columns, 'id' );

    $data['columns'] = $columns;
		$queries = (empty($params['searchTerm'])) ? [] : $params['searchTerm'];

		//Filter end
		$data['recordsTotal'] = $this->db::connection('logging')->table('post_buffer')->count();
		//Get temp data for Datatables with Fliter and some other parameters
		$tempData = $this->db::connection('logging')->table('post_buffer')->select($columns);

		$data['recordsFiltered'] = $tempData->count();

		if (!empty($params['sortColumn']) and !empty($params['sortDirection']))
				$tempData = $tempData->orderBy($params['sortColumn'],$params['sortDirection']);

		$data['data'] = $tempData->get()->toArray();

		return $this->json($response, $data, 200);
  }
}
