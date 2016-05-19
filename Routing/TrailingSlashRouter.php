<?php
App::uses('Router', 'Routing');

class TrailingSlashRouter extends Router {

/**
 * If a trailing slash should be appended to extensionless urls
 *
 * @var boolean
 */
 	protected static $_addTrailingSlash = false;

/**
 * Set the route class to enable/disable trailing slashs or return the current behavior
 *
 * @param boolean $value If trailing slashes should be used or not
 * @return mixed void|boolean
 */
	public static function addTrailingSlash($value = null) {
		if (is_null($value)) {
			return self::$_addTrailingSlash;
		}
		self::$_addTrailingSlash = $value;
	}

/**
 * Finds URL for specified action.
 *
 * Returns a URL pointing to a combination of controller and action. Param
 * $url can be:
 *
 * - Empty - the method will find address to actual controller/action.
 * - '/' - the method will find base URL of application.
 * - A combination of controller/action - the method will find URL for it.
 *
 * There are a few 'special' parameters that can change the final URL string that is generated
 *
 * - `base` - Set to false to remove the base path from the generated URL. If your application
 *   is not in the root directory, this can be used to generate URLs that are 'cake relative'.
 *   cake relative URLs are required when using requestAction.
 * - `?` - Takes an array of query string parameters
 * - `#` - Allows you to set URL hash fragments.
 * - `full_base` - If true the `Router::fullBaseUrl()` value will be prepended to generated URLs.
 *
 * @overwrite
 * @param string|array $url Cake-relative URL, like "/products/edit/92" or "/presidents/elect/4"
 *   or an array specifying any of the following: 'controller', 'action',
 *   and/or 'plugin', in addition to named arguments (keyed array elements),
 *   and standard URL arguments (indexed array elements)
 * @param bool|array $full If (bool) true, the full base URL will be prepended to the result.
 *   If an array accepts the following keys
 *    - escape - used when making URLs embedded in html escapes query string '&'
 *    - full - if true the full base URL will be prepended.
 * @return string Full translated URL with base path.
 */
	public static function url($url = null, $full = false) {
		if (!static::$initialized) {
			static::_loadRoutes();
		}

		$params = array('plugin' => null, 'controller' => null, 'action' => 'index');

		if (is_bool($full)) {
			$escape = false;
		} else {
			extract($full + array('escape' => false, 'full' => false));
		}

		$path = array('base' => null);
		if (!empty(static::$_requests)) {
			$request = static::$_requests[count(static::$_requests) - 1];
			$params = $request->params;
			$path = array('base' => $request->base, 'here' => $request->here);
		}
		if (empty($path['base'])) {
			$path['base'] = Configure::read('App.base');
		}

		$base = $path['base'];
		$extension = $output = $q = $frag = null;

		if (empty($url)) {
			$output = isset($path['here']) ? $path['here'] : '/';
			if ($full) {
				$output = static::fullBaseUrl() . $output;
			}
			return $output;
		} elseif (is_array($url)) {
			if (isset($url['base']) && $url['base'] === false) {
				$base = null;
				unset($url['base']);
			}
			if (isset($url['full_base']) && $url['full_base'] === true) {
				$full = true;
				unset($url['full_base']);
			}
			if (isset($url['?'])) {
				$q = $url['?'];
				unset($url['?']);
			}
			if (isset($url['#'])) {
				$frag = '#' . $url['#'];
				unset($url['#']);
			}
			if (isset($url['ext'])) {
				$extension = '.' . $url['ext'];
				unset($url['ext']);
			}
			if (empty($url['action'])) {
				if (empty($url['controller']) || $params['controller'] === $url['controller']) {
					$url['action'] = $params['action'];
				} else {
					$url['action'] = 'index';
				}
			}

			$prefixExists = (array_intersect_key($url, array_flip(static::$_prefixes)));
			foreach (static::$_prefixes as $prefix) {
				if (!empty($params[$prefix]) && !$prefixExists) {
					$url[$prefix] = true;
				} elseif (isset($url[$prefix]) && !$url[$prefix]) {
					unset($url[$prefix]);
				}
				if (isset($url[$prefix]) && strpos($url['action'], $prefix . '_') === 0) {
					$url['action'] = substr($url['action'], strlen($prefix) + 1);
				}
			}

			$url += array('controller' => $params['controller'], 'plugin' => $params['plugin']);

			$match = false;

			foreach (static::$routes as $route) {
				$originalUrl = $url;

				$url = $route->persistParams($url, $params);

				if ($match = $route->match($url)) {
					$output = trim($match, '/');
					break;
				}
				$url = $originalUrl;
			}
			if ($match === false) {
				$output = static::_handleNoRoute($url);
			}
			if (empty($extension) && self::$_addTrailingSlash && substr($output, -1, 1) !== '/') {
				$output .= '/';
			}
		} else {
			if (preg_match('/^([a-z][a-z0-9.+\-]+:|:?\/\/|[#?])/i', $url)) {
				return $url;
			}
			if (substr($url, 0, 1) === '/') {
				$output = substr($url, 1);
			} else {
				foreach (static::$_prefixes as $prefix) {
					if (isset($params[$prefix])) {
						$output .= $prefix . '/';
						break;
					}
				}
				if (!empty($params['plugin']) && $params['plugin'] !== $params['controller']) {
					$output .= Inflector::underscore($params['plugin']) . '/';
				}
				$output .= Inflector::underscore($params['controller']) . '/' . $url;
			}
		}
		$protocol = preg_match('#^[a-z][a-z0-9+\-.]*\://#i', $output);
		if ($protocol === 0) {
			$output = str_replace('//', '/', $base . '/' . $output);

			if ($full) {
				$output = static::fullBaseUrl() . $output;
			}
			if (!empty($extension)) {
				$output = rtrim($output, '/');
			}
		}
		return $output . $extension . static::queryString($q, array(), $escape) . $frag;
	}

}
