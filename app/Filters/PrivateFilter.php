<?php namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use Config\Services;
use App\Models\UsersModel;
use App\Libraries\Authorization\BeforeValidException;
use App\Libraries\Authorization\SignatureInvalidException;
use App\Libraries\Authorization\JWT;
use UnexpectedValueException;

class PrivateFilter implements FilterInterface
{
    private $users;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->users = new UsersModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /* @param RequestInterface $request
     * @param null $arguments
     * @return Response
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (empty($request->getHeaderLine('Authorization'))) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    "code"   => 401,
                    'message' => [
                        "error" => lang("Message.message_9")
                    ]
                ]);
        }
        $sign = $this->decodeJWT(esc($request->getHeaderLine('Authorization')));
        if (!$sign) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    "code"   => 401,
                    'message' => [
                        "error" => lang("Message.message_10")
                    ]
                ]);
        }
        $user = $this->users
            ->where(["id" => $sign->user])
            ->select("id,email")
            ->first();
        if (!$user) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    "code"   => 401,
                    'message' => [
                        "error" => lang("Message.message_11")
                    ]
                ]);
        }
        $body['user'] = $user;
        $request->setBody($body);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do something here
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Decode JWT auth token
     * @param string $token
     * @return object|null
     */
    private function decodeJWT(string $token)
    {
        try {
            $decoded = JWT::decode($token, env('jwt.secret.key.access'), array('HS256'));
        } catch (SignatureInvalidException $ex) {
            $decoded = null;
        } catch (BeforeValidException $ef) {
            $decoded = null;
        } catch (UnexpectedValueException $e) {
            $decoded = null;
        }
        return $decoded;
    }
}