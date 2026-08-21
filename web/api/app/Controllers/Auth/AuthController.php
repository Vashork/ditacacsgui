<?php

declare(strict_types=1);

namespace tgui\Controllers\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use tgui\Models\APIUsers;
use tgui\Models\APIPWPolicy;
use tgui\Controllers\Controller;
use Respect\Validation\Validator as v;
use Firebase\JWT\JWT;

class AuthController extends Controller
{
    ################################################
    ########	Sign IN	###############START###########
    #########	GET Sign IN	#########
    public function getSignIn(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->initialData([
            'type' => 'get',
            'object' => 'auth',
            'action' => 'signin',
        ]);

        $data['tacacs'] = ($this->db::getSchemaBuilder()->hasTable('mavis_local')) ? $this->MAVISLocal->change_passwd_gui() : 0;

        if (isset($_SESSION['error']['status']) && $_SESSION['error']['status']) {
            $data['error'] = $_SESSION['error'];
            return $this->json($response, $data, 401);
        }

        if (!isset($_SESSION['ldap']) && isset($_SESSION['uid'])) {
            $data['user'] = APIUsers::from('api_users as au')
                ->leftJoin('api_user_groups as aug', 'aug.id', '=', 'au.group')
                ->select(['au.*', 'aug.rights as rights'])
                ->where('au.id', $_SESSION['uid'])
                ->first();
        }

        return $this->json($response, $data, 200);
    }

    #########	POST Sign IN	#########
    public function postSignIn(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->initialData([
            'type' => 'post',
            'object' => 'auth',
            'action' => 'signin',
        ]);

        $data['authorised'] = false;

        $_SESSION['failedLoginCount'] = (empty($_SESSION['failedLoginCount'])) ? 1 : $_SESSION['failedLoginCount'] + 1;
        $lockTime = 300;
        $badLoginLimit = 5;

        if ($_SESSION['failedLoginCount'] > $badLoginLimit && empty($_SESSION['blockTime'])) {
            $_SESSION['error']['status'] = true;
            $_SESSION['error']['message'] = 'You was blocked for 5 minutes';
            $_SESSION['blockTime'] = time();
            $username = $this->param($request, 'username');
            $logEntry = ['username' => empty($username) ? '' : $username, 'uid' => 0, 'action' => 'Signin', 'section' => 'api auth', 'message' => 104];
            $this->APILoggingCtrl->makeLogEntry($logEntry);
            $data['error'] = $_SESSION['error'];
            return $this->json($response, $data, 401);
        }

        if (!empty($_SESSION['blockTime'])) {
            if ((time() - $_SESSION['blockTime']) > $lockTime) {
                unset($_SESSION['blockTime']);
                $_SESSION['failedLoginCount'] = 1;
            } else {
                $_SESSION['error']['status'] = true;
                $_SESSION['error']['message'] = 'You was blocked for 5 minutes';
                $data['error'] = $_SESSION['error'];
                return $this->json($response, $data, 401);
            }
        }

        if (!$this->db::schema()->hasTable('api_users')) {
            $this->APICheckerCtrl->myFirstTable();
        }

        $validation = $this->validator->validate($request, [
            'username' => v::notEmpty(),
            'password' => v::notEmpty()
        ]);

        if ($validation->failed()) {
            $data['error']['status'] = true;
            $data['error']['validation'] = $validation->error_messages;
            return $this->json($response, $data, 401);
        }

        $username = $this->param($request, 'username');
        $password = $this->param($request, 'password');

        $auth = $this->auth->attempt($username, $password);

        if (isset($_SESSION['error']['status']) && $_SESSION['error']['status']) {
            $data['error'] = $_SESSION['error'];
            $logEntry = ['username' => $username, 'uid' => 0, 'action' => 'Signin', 'section' => 'api auth', 'message' => 103];
            $this->APILoggingCtrl->makeLogEntry($logEntry);
            return $this->json($response, $data, 401);
        }

        if (!isset($_SESSION['ldap'])) {
            $data['user'] = APIUsers::from('api_users as au')
                ->leftJoin('api_user_groups as aug', 'aug.id', '=', 'au.group')
                ->select(['au.*', 'aug.rights as rights'])
                ->where('au.id', $_SESSION['uid'])
                ->first();
            $data['info']['user']['id'] = (isset($_SESSION['uid'])) ? $_SESSION['uid'] : 'empty';
            $data['info']['user']['username'] = (isset($_SESSION['uname'])) ? $_SESSION['uname'] : 'empty';
        } else {
            $data['user'] = $_SESSION['user'];
            $data['user']['rights'] = $this->db::table('api_user_groups')->select()->where('id', $_SESSION['groupId'])->first()->rights;
        }

        $logEntry = ['action' => 'Signin', 'section' => 'api auth', 'message' => 101];
        $this->APILoggingCtrl->makeLogEntry($logEntry);

        $data['authorised'] = $this->auth->check();
        $data['info']['user']['changePasswd'] = (isset($_SESSION['changePasswd'])) ? $_SESSION['changePasswd'] : 'empty';

        // Use JWT_SECRET from environment instead of DB_PASSWORD
        $jwtSecret = $_ENV['JWT_SECRET'] ?? '';
        $data['token'] = JWT::encode(
            ['id' => $data['user']->id, 'username' => $data['user']->username],
            $jwtSecret,
            "HS256"
        );

        return $this->json($response, $data, 200);
    }
    ########	Sign IN	###############END###########
    ################################################
    #########	POST CHANGE PASSWORD	#########
    public function postChangePassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->initialData([
            'type' => 'post',
            'object' => 'auth',
            'action' => 'change password',
        ]);

        if (isset($_SESSION['error']['status']) && $_SESSION['error']['status'] && $data['info']['user']['changePasswd'] != 1) {
            $data['error'] = $_SESSION['error'];
            return $this->json($response, $data, 401);
        }

        $password = APIUsers::where('id', $_SESSION['uid'])->first()->password;

        if ($this->db::schema()->hasTable('api_password_policy')) {
            $policy = APIPWPolicy::select()->first(1);
        } else {
            $policy = ['api_pw_length' => 8, 'api_pw_same' => true];
        }

        $changePasswd = $this->param($request, 'change_passwd');
        $changePasswdRepeat = $this->param($request, 'change_passwd_repeat');

        $validation = $this->validator->validate($request, [
            'change_passwd' => v::when(v::alwaysValid(), v::notContainChars()
                ->length($policy['api_pw_length'], 64)
                ->notEmpty()
                ->passwdPolicyUppercase($policy['api_pw_uppercase'])
                ->passwdPolicyLowercase($policy['api_pw_lowercase'])
                ->passwdPolicySpecial($policy['api_pw_special'])
                ->passwdPolicySame($policy['api_pw_same'], $password, 'api')
                ->passwdPolicyNumbers($policy['api_pw_numbers'])
                ->checkPassword($changePasswdRepeat)
                ->setName('Password')),
            'change_passwd_repeat' => v::checkPassword($changePasswd),
        ]);

        if ($validation->failed()) {
            $data['error']['status'] = true;
            $data['error']['validation'] = $validation->error_messages;
            return $this->json($response, $data, 200);
        }

        if (!isset($_SESSION['ldap'])) {
            $user = APIUsers::from('api_users as au')
                ->leftJoin('api_user_groups as aug', 'aug.id', '=', 'au.group')
                ->select(['au.*', 'aug.rights as rights'])
                ->where('au.id', $_SESSION['uid'])
                ->first();
        } else {
            return $this->json($response, $data, 200);
        }

        if ($user->changePasswd == 0) {
            $data['error']['status'] = true;
            $data['error']['message'] = 'Operation not permitted!';
            return $this->json($response, $data, 401);
        }

        $data['status'] = APIUsers::where('id', $_SESSION['uid'])
            ->update([
                'password' => password_hash($changePasswd, PASSWORD_DEFAULT),
                'changePasswd' => 0
            ]);
        $_SESSION['changePasswd'] = 0;
        $data['info']['user']['changePasswd'] = (isset($_SESSION['changePasswd'])) ? $_SESSION['changePasswd'] : 'empty';

        return $this->json($response, $data, 200);
    }
    ########	CHANGE PASSWORD	###############END###########
    ################################################
    ########	Sign OUT	###############START###########
    #########	GET Sign OUT	#########
    public function getSignOut(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->initialData([
            'type' => 'get',
            'object' => 'auth',
            'action' => 'signout',
        ]);

        if (isset($_SESSION['error']['status']) && $_SESSION['error']['status']) {
            $data['error'] = $_SESSION['error'];
            return $this->json($response, $data, 401);
        }

        $logEntry = ['action' => 'signout', 'section' => 'api auth', 'message' => 102];
        $this->APILoggingCtrl->makeLogEntry($logEntry);

        session_unset();
        session_destroy();
        $data['authorised'] = $this->auth->check();

        return $this->json($response, $data, 200);
    }

    #########	POST Sign OUT	#########
    public function postSignOut(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->initialData([
            'type' => 'post',
            'object' => 'auth',
            'action' => 'signout',
        ]);

        if (isset($_SESSION['error']['status']) && $_SESSION['error']['status']) {
            $data['error'] = $_SESSION['error'];
            return $this->json($response, $data, 401);
        }

        session_unset();
        session_destroy();
        $data['authorised'] = $this->auth->check();

        return $this->json($response, $data, 200);
    }
    ########	Sign OUT	###############END###########
    ################################################
}
