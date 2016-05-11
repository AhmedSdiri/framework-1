<?php
/**
 * Giuard - A simple Authentication Guard.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */


namespace Auth;

use Core\Config;
use Helpers\Session;
use Helpers\Cookie;
use Helpers\Encrypter;
use Helpers\Password;
use Auth\Model;

use \stdClass;


class Guard
{
    /**
     * The currently authenticated User information.
     *
     * @var \stdClass
     */
    protected $user = null;

    /**
     * @var \Auth\Model
     */
    protected $model = null;

    /**
     * Indicates if the logout method has been called.
     *
     * @var bool
     */
    protected $loggedOut = false;

    /**
     * Indicates if a token user retrieval has been attempted.
     *
     * @var bool
     */
    protected $tokenRetrievalAttempted = false;

    /**
     * Create a new Authentication Guard.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Determine if the current User is authenticated.
     *
     * @return bool
     */
    public function check()
    {
        return ! is_null($this->user());
    }

    /**
     * Get the authenticated user.
     *
     * @return \stdClass|null
     */
    public function user()
    {
        if ($this->loggedOut) {
            return null;
        }

        if (! is_null($this->user)) {
            return $this->user;
        }

        $user = null;

        $id = Session::get($this->getName());

        if (! is_null($id)) {
            $user = $this->retrieveUserById($id);
        }

        $recaller = $this->getRecaller();

        if (is_null($user) && ! is_null($recaller)) {
            $user = $this->getUserByRecaller($recaller);

            if (! is_null($user)) {
                $this->updateSession($user);
            }
        }

        return $this->user = $user;
    }

    /**
     * Attempt to authenticate an User using the given credentials.
     *
     * @param  array $credentials
     * @param  bool  $remember
     * @param  bool  $login
     * @return bool
     */
    public function attempt(array $credentials = array(), $remember = false, $login = true)
    {
        $user = $this->retrieveUser($credentials);

        if (! is_null($user) && $this->validateCredentials($user, $credentials)) {
            if ($login) {
                $this->login($user, $remember);
            }

            return true;
        }

        return false;
    }

    /**
     * Log a User in.
     *
     * @param  \stdClass $user
     * @param  bool $remember
     * @return void
     */
    public function login(stdClass $user, $remember = false)
    {
        $this->updateSession($user);

        if ($remember) {
            if (empty($user->remember_token)) {
                $this->refreshRememberToken($user);
            }

            $this->setRecallerCookie($user);
        }

        $this->setUser($user);
    }

    /**
     * Log the user out.
     *
     * @return void
     */
    public function logout()
    {
        if (! is_null($this->user)) {
            $this->refreshRememberToken($this->user);
        }

        // Destroy the Session and Cookie variables.
        Session::destroy($this->getName());

        Cookie::destroy($this->getRecallerName());

        // Reset the instance information.
        $this->user = null;

        $this->loggedOut = true;
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array $credentials
     * @return \stdClass|null
     */
    public function retrieveUser(array $credentials)
    {
        $query = $this->createModel()->newQuery();

        foreach ($credentials as $key => $value) {
            if ($key !== 'password') {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    /**
     * Retrieve a user by the given id.
     *
     * @param  int $id
     * @return \stdClass|null
     */
    public function retrieveUserById($id)
    {
        $keyName = $this->createModel()->getKeyName();

        return $this->retrieveUser(array($keyName => $id));
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \stdClass $user
     * @param  array $credentials
     * @return bool
     */
    protected function validateCredentials(stdClass $user, array $credentials)
    {
        return Password::verify($credentials['password'], $user->password);
    }

    /**
     * Refresh the "Remember me" token for the user.
     *
     * @param  \stdClass $user
     * @return void
     */
    protected function refreshRememberToken(stdClass $user)
    {
        $model = $this->createModel();

        $keyName = $model->getKeyName();

        $keyValue = $user->{$keyName};

        // Get a new Query from Model.
        $query = $model->newQuery();

        // Create a new Token and update it into Database.
        $user->remember_token = createKey(60);

        $query->where($keyName, $keyValue)->update(array('remember_token' => $user->remember_token));
    }

    /**
     * Set the current user.
     *
     * @param  \stdClass $user
     * @return void
     */
    protected function setUser(stdClass $user)
    {
        $this->user = $user;
    }

    /**
     * Update the Session with the given ID.
     *
     * @param  \stdClass $user
     * @return void
     */
    protected function updateSession(stdClass $user)
    {
        $keyName = $this->createModel()->getKeyName();

        $keyValue = $user->{$keyName};

        Session::set($this->getName(), $keyValue);
    }

    /**
     * Set the recaller Cookie.
     *
     * @param  \stdClass $user
     * @return void
     */
    protected function setRecallerCookie(stdClass $user)
    {
        $keyName = $this->createModel()->getKeyName();

        $keyValue = $user->{$keyName};

        // Prepare the value and set the remembering Cookie.
        $value = $keyValue .'|' .$user->remember_token;

        Cookie::set($this->getRecallerName(), Encrypter::encrypt($value));
    }

    /**
     * Get a user by its recaller ID.
     *
     * @param  string $recaller
     * @return mixed
     */
    protected function getUserByRecaller($recaller)
    {
        if (! $this->validRecaller($recaller)) {
            Cookie::destroy($this->getRecallerName());
        } else if (! $this->tokenRetrievalAttempted) {
            $this->tokenRetrievalAttempted = true;

            list($id, $remember_token) = explode('|', $recaller, 2);

            return $this->retrieveUser(compact('id', 'remember_token'));
        }
    }

    /**
     * Determine if the recaller Cookie is in a valid format.
     *
     * @param  string $recaller
     * @return bool
     */
    protected function validRecaller($recaller)
    {
        if (is_string($recaller) && (strpos($recaller, '|') !== false)) {
            $segments = explode('|', $recaller);

            return ((count($segments) == 2) && (trim($segments[0]) !== '') && (trim($segments[1]) !== ''));
        }

        return false;
    }

    /**
     * Get the decrypted Recaller cookie.
     *
     * @return string|null
     */
    protected function getRecaller()
    {
        $payload = Cookie::get($this->getRecallerName());

        try {
            $recaller = Encrypter::decrypt($payload);
        } catch (\Exception $e) {
            $recaller = null;

            Cookie::destroy($this->getRecallerName());
        }

        return $recaller;
    }

    /**
     * Get the name of the Cookie used to store the "recaller".
     *
     * @return string
     */
    public function getRecallerName()
    {
        return 'remember_' .md5(get_class($this));
    }

    /**
     * Get a unique identifier for the Auth session value.
     *
     * @return string
     */
    public function getName()
    {
        return 'login_' .md5(get_class($this));
    }

    /**
     * Create a new instance of the Model.
     *
     * @return \Auth\Model
     */
    public function createModel()
    {
        if(is_null($this->model)) {
            $config = Config::get('authentication');

            $className = '\\' .ltrim($config['model'], '\\');

            // Create the specified Model instance.
            $this->model = new $className($config);
        }

        return $this->model;
    }
}
