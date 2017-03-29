<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\route;

use think\Route;
use think\route\dispatch\Callback as CallbackDispatch;
use think\route\dispatch\Controller as ControllerDispatch;
use think\route\dispatch\Module as ModuleDispatch;

class Domain extends RuleGroup
{

    public function check($request, $url, $depr = '/')
    {
        // 检测别名路由
        if ($this->router->getAlias($url) || $this->router->getAlias(strstr($url, '|', true))) {
            // 检测路由别名
            $result = $this->checkRouteAlias($request, $url, $depr);
            if (false !== $result) {
                return $result;
            }
        }

        // 检测URL绑定
        $result = $this->checkUrlBind($url, $depr);

        if (false !== $result) {
            return $result;
        }

        return parent::check($request, $url, $depr);
    }

    /**
     * 检测路由别名
     * @access private
     * @param Request   $request
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @return mixed
     */
    private function checkRouteAlias($request, $url, $depr)
    {
        $array = explode('|', $url);
        $alias = array_shift($array);
        $item  = $this->router->getAlias($alias);

        if (is_array($item)) {
            list($rule, $option) = $item;
            $action              = $array[0];

            if (isset($option['allow']) && !in_array($action, explode(',', $option['allow']))) {
                // 允许操作
                return false;
            } elseif (isset($option['except']) && in_array($action, explode(',', $option['except']))) {
                // 排除操作
                return false;
            }

            if (isset($option['method'][$action])) {
                $option['method'] = $option['method'][$action];
            }
        } else {
            $rule = $item;
        }

        $bind = implode('|', $array);

        // 参数有效性检查
        if (isset($option) && !$this->checkOption($option, $request)) {
            // 路由不匹配
            return false;
        } elseif (0 === strpos($rule, '\\')) {
            // 路由到类
            return $this->bindToClass($bind, substr($rule, 1), $depr);
        } elseif (0 === strpos($rule, '@')) {
            // 路由到控制器类
            return $this->bindToController($bind, substr($rule, 1), $depr);
        } else {
            // 路由到模块/控制器
            return $this->bindToModule($bind, $rule, $depr);
        }
    }

    /**
     * 检测URL绑定
     * @access private
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @return mixed
     */
    private function checkUrlBind(&$url, $depr = '/')
    {
        if (!empty($this->bind)) {
            $bind = $this->bind;

            // 记录绑定信息
            $this->app->log('[ BIND ] ' . var_export($bind, true));

            // 如果有URL绑定 则进行绑定检测
            if (0 === strpos($bind, '\\')) {
                // 绑定到类
                return $this->bindToClass($url, substr($bind, 1), $depr);
            } elseif (0 === strpos($bind, '@')) {
                // 绑定到控制器类
                return $this->bindToController($url, substr($bind, 1), $depr);
            } elseif (0 === strpos($bind, ':')) {
                // 绑定到命名空间
                return $this->bindToNamespace($url, substr($bind, 1), $depr);
            }
        }

        return false;
    }

    /**
     * 绑定到类
     * @access public
     * @param string    $url URL地址
     * @param string    $class 类名（带命名空间）
     * @param string    $depr URL分隔符
     * @return array
     */
    public function bindToClass($url, $class, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->app['config']->get('default_action');

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1]);
        }

        return new CallbackDispatch([$class, $action]);
    }

    /**
     * 绑定到命名空间
     * @access public
     * @param string    $url URL地址
     * @param string    $namespace 命名空间
     * @param string    $depr URL分隔符
     * @return array
     */
    public function bindToNamespace($url, $namespace, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 3);
        $class  = !empty($array[0]) ? $array[0] : $this->app['config']->get('default_controller');
        $method = !empty($array[1]) ? $array[1] : $this->app['config']->get('default_action');

        if (!empty($array[2])) {
            $this->parseUrlParams($array[2]);
        }

        return new CallbackDispatch([$namespace . '\\' . Loader::parseName($class, 1), $method]);
    }

    /**
     * 绑定到控制器类
     * @access public
     * @param string    $url URL地址
     * @param string    $controller 控制器名 （支持带模块名 index/user ）
     * @param string    $depr URL分隔符
     * @return array
     */
    public function bindToController($url, $controller, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->app['config']->get('default_action');

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1]);
        }

        return new ControllerDispatch($controller . '/' . $action);
    }

    /**
     * 绑定到模块/控制器
     * @access public
     * @param string    $url URL地址
     * @param string    $controller 控制器类名（带命名空间）
     * @param string    $depr URL分隔符
     * @return array
     */
    public function bindToModule($url, $controller, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->app['config']->get('default_action');

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1]);
        }

        return new ModuleDispatch($controller . '/' . $action);
    }

    /**
     * 解析URL地址中的参数Request对象
     * @access private
     * @param string    $rule 路由规则
     * @param array     $var 变量
     * @return void
     */
    public function parseUrlParams($url, &$var = [])
    {
        if ($url) {
            if ($this->app['config']->get('url_param_type')) {
                $var += explode('|', $url);
            } else {
                preg_replace_callback('/(\w+)\|([^\|]+)/', function ($match) use (&$var) {
                    $var[$match[1]] = strip_tags($match[2]);
                }, $url);
            }
            // 设置当前请求的参数
            $this->app['request']->route($var);
        }

    }
}
