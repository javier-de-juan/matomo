<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Login;

use Exception;
use Piwik\AuthResult;
use Piwik\Db;
use Piwik\Plugins\UsersManager\Model;
use Piwik\Session;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;

class Auth implements \Piwik\Auth
{
    protected $login;
    protected $token_auth;
    protected $md5Password;

    /**
     * Authentication module's name, e.g., "Login"
     *
     * @return string
     */
    public function getName()
    {
        return 'Login';
    }

    /**
     * Authenticates user
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        if (!empty($this->md5Password)) { // favor authenticating by password
            $this->token_auth = UsersManagerAPI::getInstance()->getTokenAuth($this->login, $this->getTokenAuthSecret());
        }

        if (is_null($this->login)) {
            return $this->authenticateWithToken($this->token_auth);
        } elseif (!empty($this->login)) {
            return $this->authenticateWithTokenOrHashToken($this->token_auth, $this->login);
        }

        return new AuthResult(AuthResult::FAILURE, $this->login, $this->token_auth);
    }

    private function authenticateWithToken($token)
    {
        $model = new Model();
        $user  = $model->getUserByTokenAuth($token);

        if (empty($user['login'])) {
            return new AuthResult(AuthResult::FAILURE, null, $token);
        }

        $code = $user['superuser_access'] ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;

        return new AuthResult($code, $user['login'], $token);
    }

    private function authenticateWithTokenOrHashToken($token, $login)
    {
        $model = new Model();
        $user  = $model->getUser($login);

        if (!empty($user['token_auth'])
            // authenticate either with the token or the "hash token"
            && ((SessionInitializer::getHashTokenAuth($login, $user['token_auth']) === $token)
                || $user['token_auth'] === $token)
        ) {
            $this->setTokenAuth($user['token_auth']);
            $code = !empty($user['superuser_access']) ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;

            return new AuthResult($code, $login, $user['token_auth']);
        }

        return new AuthResult(AuthResult::FAILURE, $login, $token);
    }

    /**
     * Returns the login of the user being authenticated.
     *
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * Accessor to set login name
     *
     * @param string $login user login
     */
    public function setLogin($login)
    {
        $this->login = $login;
    }

    /**
     * Returns the secret used to calculate a user's token auth.
     *
     * @return string
     */
    public function getTokenAuthSecret()
    {
        return $this->md5Password;
    }

    /**
     * Accessor to set authentication token
     *
     * @param string $token_auth authentication token
     */
    public function setTokenAuth($token_auth)
    {
        $this->token_auth = $token_auth;
    }

    /**
     * Sets the password to authenticate with.
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        if (empty($password)) {
            $this->md5Password = null;
        } else {
            $this->md5Password = md5($password);
        }
    }

    /**
     * Sets the password hash to use when authentication.
     *
     * @param string $passwordHash The password hash.
     * @throws Exception if $passwordHash does not have 32 characters in it.
     */
    public function setPasswordHash($passwordHash)
    {
        if ($passwordHash === null) {
            $this->md5Password = null;
            return;
        }

        if (strlen($passwordHash) != 32) {
            throw new Exception("Invalid hash: incorrect length " . strlen($passwordHash));
        }

        $this->md5Password = $passwordHash;
    }
}
