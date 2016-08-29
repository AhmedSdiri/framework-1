<?php
/**
 * Controller - A base controller with legacy Middleware support.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace App\Core;

use Core\Controller as BaseController;
use Routing\Route;
use Support\Contracts\RenderableInterface as Renderable;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

use BadMethodCallException;


/**
 * Core controller, all other controllers extend this base controller.
 */
abstract class Controller extends BaseController
{
    /**
     * The requested Method by Router.
     *
     * @var string|null
     */
    private $method = null;

    /**
     * The parameters given by Router.
     *
     * @var array
     */
    private $params = array();


    /**
     * On the initial run, create an instance of the config class and the view class.
     */
    public function __construct()
    {
        parent::__construct();

        // Setup the Controller Middleware; preserve the legacy before/after methods.
        $this->beforeFilter('@callLegacyBefore');

        $this->afterFilter('@callLegacyAfter');
    }

    /**
     * Call the (legacy) Controller Middleware - Before Stage.
     *
     * @param \Routing\Route $route
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return mixed|void
     * @throw \BadMethodCallException
     */
    public function callLegacyBefore(Route $route, SymfonyRequest $request)
    {
        // Setup the call parameters from the Route instance.
        $this->params = $route->getParams();

        // Setup the called method from the Route instance.
        $action = $route->getAction();

        if (isset($action['controller'])) {
            list(, $method) = explode('@', $action['controller']);

            // Store the called method name.
            $this->method = $method;
        } else {
            throw new BadMethodCallException('No controller found on Route instance');
        }

        // Execute the legacy Before Stage.
        return $this->before();
    }

    /**
     * Call the (legacy) Controller Middleware - After Stage.
     *
     * @param \Routing\Route $route
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param mixed $response
     *
     * @return void
     */
    public function callLegacyAfter(Route $route, SymfonyRequest $request, $response)
    {
        // Execute the legacy After Stage.
        $this->after($response);
    }

    /**
     * The (legacy) Middleware called before the Action execution.
     *
     * @return mixed|void
     */
    protected function before()
    {
        //
    }

    /**
     * The (legacy) Middleware called after the Action execution.
     *
     * @param mixed $response
     *
     * @return void
     */
    protected function after($response)
    {
        //
    }

    /**
     * @return mixed
     */
    protected function getMethod()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    protected function getParams()
    {
        return $this->params;
    }

}
