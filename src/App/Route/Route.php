<?php


namespace Loxodo\App\Route;


use Loxodo\App\Users\Guard;
use Loxodo\App\Route\InjectionContainer;
use Loxodo\App\Route\Injector;
use ReflectionClass;

class Route
{

    public $method, $uri;
    protected $folders = array(), $params = array(), $controllerParams = array(), $injections = array();
    protected $controller = "", $function = "", $hasAccess = true, $countUriInjections = 0, $countSystemInjections = 0;


    public function __construct($method, $uri, Guard $guard, InjectionContainer $injectionContainer)
    {
        $this->uri = $uri;
        $this->method = $method;
        $this->parseUri($guard);
        if($this->hasAccess() || $guard->getPortalController(implode('/',$this->folders)) == $this->controller){
            if(empty($this->controller)){
                $this->controller = DEFAULT_CONTROLLER;
            }
            $this->hasAccess = true;
            $this->setFunction();
            $this->setInjections($injectionContainer);
        }

    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    protected function parseUri(Guard $guard)
    {
        foreach (explode('/', $this->uri) as $piece) {
            if (!empty($piece)) {
                $dir = $this->getDir().'/'.ucfirst($piece);
                if(is_dir($dir)){
                    $this->controller = "";
                    $this->folders[] = ucfirst($piece);
                    $protectionPath = implode('/',$this->folders);
                    if($guard->isGuarded($protectionPath)){
                        if($guard->hasRedirect($protectionPath)){
                            $this->folders = explode('/',$guard->getDestination($protectionPath));
                        } else {
                            $this->hasAccess = false;
                        }
                    }
                    $this->definesController($this->getDir().'/'.ucfirst($piece), $piece);
                } elseif($this->definesController($dir, $piece)){

                }else{
                    $this->params[] = $piece;
                    $this->controllerParams[] = $piece;
                }
            }
        }
    }

    protected function definesController($path, $controllerName)
    {
            if (file_exists($path . CONTROLLER_SUFFIX . '.php')) {
                $this->controller = ucfirst($controllerName);
                $this->controllerParams = array();
                return true;
            }
        return false;

    }

    public function getDir()
    {
        return PROJECT_ROOT. CONTROLLER_PATH . $this->collapse($this->folders) . '/';
    }

    public function getFolders()
    {
        return implode('/',$this->folders);
    }

    protected function collapse($array, $separator = "/")
    {
        return count($array) > 0 ? $separator . implode($separator, $array) : '';
    }

    public function getController()
    {
        return APPLICATION_NAMESPACE_PREFIX.'\Controllers'.$this->collapse($this->folders, '\\'). '\\' . $this->controller . CONTROLLER_SUFFIX;
    }

    protected function setFunction()
    {
        $class = new ReflectionClass($this->getController());
        if($class->implementsInterface('Loxodo\App\Route\WildCartControllerInterface')){
            if(count($this->params) == count($this->controllerParams)){
                $this->function = 'index';
                $this->params = array(implode('/', $this->controllerParams));
            }
        } elseif (!$this->setFunctionFromUri($class)) {
            $this->setDefaultRestFunction();
        }
    }

    protected function setFunctionFromUri(ReflectionClass $class)
    {
        foreach ($this->params as $key => $param) {
            if (in_array($param, $this->controllerParams) && $class->hasMethod($param)) {
                $this->function = $param;
                unset($this->params[$key]);
                return true;
            }
        }
        return false;
    }

    protected function setDefaultRestFunction()
    {
        if ($this->method === "GET") {
            $function = $this->function = count($this->controllerParams) > 0 ? "view" : "index";
            $class = new ReflectionClass($this->getController());
            if($function == "view"){
                $method = $class->hasMethod('view') ? $class->getMethod('view') : null;
                if(is_null($method) || !$method->isPublic()){
                    $this->function = "edit";
                }
            }
        }else{
            $defaults = array('PUT' => 'update', 'POST' => 'store', 'PATCH' => 'update', 'DELETE' => 'delete');
            $this->function =  isset($defaults[$this->method]) ? $defaults[$this->method] : '';
        }
    }

    protected function setInjections(InjectionContainer $injectionContainer)
    {
        $injector = new Injector($injectionContainer);
        $this->injections = $injector->getControllerParameters($this->getController(), $this->getFunction(), $this->params);
        $this->countSystemInjections = $injector->getCountSystemInjections();
        $this->countUriInjections = $injector->getCountUriInjections();
    }

    /**
     * @return bool
     */
    public function isValidRoute()
    {
        $function = $this->getFunction();
        if(empty($this->controller) || empty($function)){
            return false;
        }
        $class = new ReflectionClass($this->getController());
        if ($class->hasMethod($function)) {
            $method = $class->getMethod($function);
            return $method->isPublic() &&
            count($this->params) <= $method->getNumberOfParameters() - $this->countSystemInjections &&
            count($this->params) >= $method->getNumberOfRequiredParameters() - $this->countSystemInjections;
        }
        return false;
    }

    public function getFunction()
    {
        return $this->function;
    }

    public function getInjections()
    {
        return $this->injections;
    }

    public function hasAccess()
    {
        return $this->hasAccess;
    }

}