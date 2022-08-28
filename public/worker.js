
const workerChannel = new BroadcastChannel('wordpress-wasm');

// Polyfills for the emscripten loader
document = {}
class XMLHttpRequest {
    constructor() {
        this.onprogress = function() {};
        this.onload = function() {};
        this.onerror = function() {};
    }

    async open(method, url, flag) {
        try {
            const response = await fetch(url, { method: method });
            this.response = await response.arrayBuffer();
            this.status = response.status;
            this.onload({});
        } catch (e) {
            this.onerror(e);
        }
    }

    send() { }

}

class UniqueIndex
{
	constructor()
	{
		const map = new Map();
		const set = new WeakMap();

		let id = 0;

		Object.defineProperty(this, 'add', {
			configurable: false
			, writable:   false
			, value: (callback) => {

				const existing = set.has(callback);

				if(existing)
				{
					return existing;
				}

				const newid = ++id;

				set.set(callback, newid);
				map.set(newid, callback);

				return newid;
			}
		});

		Object.defineProperty(this, 'has', {
			configurable: false
			, writable:   false
			, value: (callback) => {
				if(set.has(callback))
				{
					return set.get(callback);
				}
			}
		});

		Object.defineProperty(this, 'get', {
			configurable: false
			, writable:   false
			, value: (id) => {
				if(map.has(id))
				{
					return map.get(id);
				}
			}
		});

		Object.defineProperty(this, 'remove', {
			configurable: false
			, writable:   false
			, value: (id) => {

				const callback = map.get(id);

				if(callback)
				{
					set.delete(callback)
					map.delete(id)
				}
			}
		});
	}
}

const STR = 'string';
const NUM = 'number';

class PhpBase extends EventTarget
{
	constructor(PhpBinary, args = {})
	{
		super();

		const FLAGS = {};

		this.onerror  = function () {};
		this.onoutput = function () {};
		this.onready  = function () {};

		const callbacks = new UniqueIndex;
		const targets   = new UniqueIndex;

		const defaults  = {

			callbacks, targets,

			postRun:  () => {
				const event = new CustomEvent('ready');
				this.onready(event);
				this.dispatchEvent(event);
			},

			print: (...chunks) =>{
				const event = new CustomEvent('output', {detail: chunks.map(c=>c+"\n")});
				this.onoutput(event);
				this.dispatchEvent(event);
			},

			printErr: (...chunks) => {
				const event = new CustomEvent('error', {detail: chunks.map(c=>c+"\n")});
				this.onerror(event);
				this.dispatchEvent(event);
			}

		};

		this.binary = new PhpBinary(Object.assign({}, defaults, args)).then(php=>{

			const retVal = php.ccall(
				'pib_init'
				, NUM
				, [STR]
				, []
			);

			return php;

		}).catch(error => console.error(error));
	}

	run(phpCode)
	{
		return this.binary.then(php => php.ccall(
			'pib_run'
			, NUM
			, [STR]
			, [`?>${phpCode}`]
		));
	}

	exec(phpCode)
	{
		return this.binary.then(php => php.ccall(
			'pib_exec'
			, STR
			, [STR]
			, [phpCode]
		));
	}

	refresh()
	{
		const call = this.binary.then(php => php.ccall(
			'pib_refresh'
			, NUM
			, []
			, []
		));

		call.catch(error => console.error(error));

		return call;
	}
}

class PhpWithWP extends PhpBase
{
	constructor(args = {})
	{
		super(PHP, args);
		this.stdout = [];
		this.stderr = [];

		this.onoutput = function (event) {
			this.stdout.push(event.detail);
		};
		this.onerror  = function (event) {
			this.stderr.push(event.detail);
		};
		this.onready  = function () {
			console.log("READY!", arguments);
		};
	}

    async run(phpCode) {
        this.stdout = [];
        this.stderr = [];
        const exitCode = await super.run(phpCode);
        await this.refresh();
        return {
            exitCode,
            stdout: this.stdout.join(""),
            stderr: this.stderr,
        }
    }
}


class WP {
	DOCROOT = '/preload/wordpress';
	SCHEMA = 'http';
	HOSTNAME = 'localhost';
	PORT = 8777;
	ABSOLUTE_URL = `${this.SCHEMA}://${this.HOSTNAME}:${this.PORT}`

	constructor(php) {
		this.php = php;
	}

	async init() {
		await this.php.refresh();
		await this.noteBrowserOrigin();
		const result = await this.php.run(`<?php
			${this._setupErrorReportingCode()}
			${this._setupWordPressVariables()}
			${this._patchWordPressCode()}
		`);
		if(result.exitCode !== 0) {
			throw new Error(
				{
					message: 'WordPress setup failed',
					result
				},
				result.exitCode
			);
		}
	}

	async noteBrowserOrigin() {
		const allClients = await clients.matchAll({
			includeUncontrolled: true
		});

		// If there are no clients, keep the defaults.
		if (allClients.length === 0) {
			return;
		}

		const url = new URL(allClients[0].url);
		this.HOSTNAME = url.hostname;
		this.POST = url.port;
		this.ABSOLUTE_URL = url.origin;
	}

	async request(request) {
		await this.php.refresh();
		const output = await this.php.run(`<?php	
			${this._setupErrorReportingCode()}
			${this._setupWordPressVariables()}
			${this._setupRequestCode( request )}
			${this._runWordPressCode( request.path )}
		`);
        return this.parseResponse(output);
	}

	parseResponse( result ) {
		const response = {
			body: result.stdout,
			headers: {},
			exitCode: result.exitCode,
			rawError: result.stderr
		}
		for(const [row] of result.stderr) {
			if ( ! row || ! row.trim() ) {
				continue;
			}
			try {
				const [name, value] = JSON.parse(row);
				if(name === "headers") {
					response.headers = this.parseHeaders(value);
					break;
				}
			} catch(e) {
				// console.error(e);
				// break;
			}
		}
		return response;
	}

	parseHeaders(rawHeaders) {
		const parsed = {};
		for(const header of rawHeaders)
		{
			const splitAt = header.indexOf(':')
			const [name, value] = [
				header.substring(0,splitAt).toLowerCase(),
				header.substring(splitAt + 2)
			];
			if(!(name in parsed)) {
				parsed[name] = [];
			}
			parsed[name].push(value);
		}
		return parsed;
	}

	_patchWordPressCode() {
		return `
			if ( ! file_exists( WP_HOME . ".wordpress-patched" ) ) {
				touch(WP_HOME . ".wordpress-patched");
				
				// Patching WordPress in the worker provides a faster feedback loop than
				// rebuilding it every time. Follow the example below to patch WordPress
				// before the first request is dispatched:
				// 
				// file_put_contents(
				// 	WP_HOME . 'wp-content/db.php',
				// 	str_replace(
				// 		'$exploded_parts = $values_data;',
				// 		'$exploded_parts = array( $values_data );',
				// 		file_get_contents(WP_HOME . 'wp-content/db.php')
				// 	)
				// );

				// WORKAROUND:
                // For some reason, the in-browser WordPress is eager to redirect the
				// browser to http://127.0.0.1 when the site URL is http://127.0.0.1:8000.
				file_put_contents(
					WP_HOME . 'wp-includes/canonical.php',
					str_replace(
						'function redirect_canonical( $requested_url = null, $do_redirect = true ) {',
						'function redirect_canonical( $requested_url = null, $do_redirect = true ) {return;',
						file_get_contents(WP_HOME . 'wp-includes/canonical.php')
					)
				);

				// WORKAROUND:
                // For some reason, the in-browser WordPress doesn't respect the site
				// URL preset during the installation. Also, it disables the block editing
				// experience by default.
				file_put_contents(
					WP_HOME . 'wp-includes/plugin.php',
					file_get_contents(WP_HOME . 'wp-includes/plugin.php') . "\n"
					.'add_filter( "option_home", function($url) { return "${this.ABSOLUTE_URL}"; }, 10000 );' . "\n"
					.'add_filter( "option_siteurl", function($url) { return "${this.ABSOLUTE_URL}"; }, 10000 );' . "\n"
				);
			}
		`;
	}

	_setupErrorReportingCode() {
		return `
			$stdErr = fopen('php://stderr', 'w');
			$errors = [];
			register_shutdown_function(function() use($stdErr){
				fwrite($stdErr, json_encode(['session_id', session_id()]) . "\n");
				fwrite($stdErr, json_encode(['headers', headers_list()]) . "\n");
				fwrite($stdErr, json_encode(['errors', error_get_last()]) . "\n");
				fwrite($stdErr, json_encode(['session', $_SESSION]) . "\n");
			});
			
			set_error_handler(function(...$args) use($stdErr){
				fwrite($stdErr, print_r($args,1));
			});
			error_reporting(E_ALL);
		`;
	}

	_setupRequestCode({
		path = '/wp-login.php',
		method = 'GET',
		headers,
		_GET = '',
		_POST = {},
		_COOKIE = {},
		_SESSION = {}
	} = {}) {
		const request = {
			path,
			method,
			headers,
			_GET,
			_POST,
			_COOKIE,
			_SESSION,
		};

		console.log("WP request", request);
		
		return `
			$request = (object) json_decode(
				'${ JSON.stringify(request) }'
				, JSON_OBJECT_AS_ARRAY
			);
			
			parse_str(substr($request->_GET, 1), $_GET);
			
			$_POST = $request->_POST;

			if ( !is_null($request->_COOKIE) ) {
				foreach ($request->_COOKIE as $key => $value) {
					fwrite($stdErr, 'Setting Cookie: ' . $key . " => " . $value . "\n");
					$_COOKIE[$key] = urldecode($value);
				}
			}

			$_SESSION = $request->_SESSION;

			foreach( $request->headers as $name => $value ) {
				$server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
				$_SERVER[$server_key] = $value;
			}

			ini_set('session.save_path', '/home/web_user');
			session_id('fake-cookie');
			session_start();
			
			fwrite($stdErr, json_encode(['session' => $_SESSION]) . "\n");
			
			$origin  = '${this.SCHEMA}://${this.HOSTNAME}:${this.PORT}';
			$docroot = '${this.DOCROOT}';
			
			$script  = ltrim($request->path, '/');
			
			$path = $request->path;
			$path = preg_replace('/^\\/php-wasm/', '', $path);
			
			$_SERVER['PATH']     = '/';
			$_SERVER['REQUEST_URI']     = $path;
			$_SERVER['HTTP_HOST']       = '${this.HOSTNAME}:${this.PORT}';
			$_SERVER['REMOTE_ADDR']     = '${this.HOSTNAME}';
			$_SERVER['SERVER_NAME']     = $origin;
			$_SERVER['SERVER_PORT']     = ${this.PORT};
			$_SERVER['REQUEST_METHOD']  = $request->method;
			$_SERVER['SCRIPT_FILENAME'] = $docroot . '/' . $script;
			$_SERVER['SCRIPT_NAME']     = $docroot . '/' . $script;
			$_SERVER['PHP_SELF']        = $docroot . '/' . $script;
			$_SERVER['DOCUMENT_ROOT']   = '/';
			$_SERVER['HTTPS']           = '';
			chdir($docroot);
		`;
	}

	_setupWordPressVariables() {
		return `
			$docroot = '${this.DOCROOT}';
			$table_prefix = 'wp_';
			define('WP_HOME', $docroot . '/');
			define('WP_SITEURL', '/');
		`;
	}

	_runWordPressCode( path ) {
		return `
		require_once WP_HOME . ltrim('${path}', '/');
		`;
	}
}

class WPBrowser {
	constructor(wp) {
		this.wp = wp;
		this.cookies = {};
	}

	async request(path, method, postData = {}, requestHeaders = {}, redirects = 0) {
		const parsedUrl = new URL(path, this.wp.ABSOLUTE_URL);
		let pathname = parsedUrl.pathname;
		// Fix the URLs not ending with .php
		if ( pathname.endsWith('/') ) {
			pathname += "index.php";
		}
		if ( ! pathname.endsWith('.php') ) {
			pathname += ".php";
		};

		const response = await this.wp.request({
			path: pathname,
			method,
			headers: requestHeaders,
			_GET: parsedUrl.search,
			_POST: postData,
			_COOKIE: this.cookies,
		});

		if ( response.headers['set-cookie'] ) {
			this.setCookies(response.headers['set-cookie']);
		}

		if (response.headers.location && redirects < 4) {
			console.log('WP RESPONSE', response);
			return this.request(response.headers.location[0], 'GET', {}, {}, redirects + 1 );
		}

		// console.log('response', response);
		// console.log('stderr', response.errors);
		// console.log('headers', response.headers);
		// console.log('exitCode', response.exitCode);

		return response;
	}

	setCookies(cookies) {
		for(const cookie of cookies) {
			try {
				let value = cookie.split("=")[1].split(";")[0];
				let name = cookie.split("=")[0];
				this.cookies[name] = value;
			} catch(e) {
				console.error(e);
			}
		}
	}
}

importScripts("/php-web-wordpress.js");
console.log("Imported wordpress, yay");

function importClassicScript(url) {
    const script = document.createElement("script")
    script.type = "text/javascript";
    script.src = url;
    
    const p = new Promise(resolve => script.onload = resolve);
    document.body.appendChild(script);
    return p;
}

async function init() {
    const php = new PhpWithWP();
	const wp = new WP(new PhpWithWP());
	await wp.init();

	return new WPBrowser(wp);
};

const browser = init();

browser.then((_browser) => {
	workerChannel.addEventListener('message', async (event) => {
		if (event.data.type === 'run_php') {
			console.debug('"run_php" event received', event);
			const result = await _browser.wp.php.run(event.data.code);
			console.debug('"run_php" event processed', { result });
			if (event.data.requestId) {
				workerChannel.postMessage({
					type: 'response',
					result,
					requestId: event.data.requestId
				});
			}
		}
	});
})


/**
 * The main method. It captures the requests and loop them back to the main
 * application using the Loopback request
 */
self.addEventListener("fetch", event => {
	event.preventDefault();
    return event.respondWith(
        new Promise(async accept => {
            const post = await parsePost(event.request);

            const url = new URL(event.request.url);
            const isInternalRequest = url.pathname.endsWith("/") || url.pathname.endsWith(".php");
            if (!isInternalRequest) {
                console.log(`[ServiceWorker] Ignoring request: ${url.pathname}`);
                accept(fetch(event.request));
                return;
			}

			const requestHeaders = {};
			for (const pair of event.request.headers.entries()) {
				requestHeaders[pair[0]] = pair[1];
			}

            console.log(`[ServiceWorker] Serving request: ${url.pathname}?${url.search}`);
            let wpResponse;
            try {
                wpResponse = await (await browser).request(
                    url.pathname + url.search,
                    event.request.method,
					post,
					requestHeaders
                );
                console.log({ wpResponse })
            } catch (e) {
                console.error(e);
                throw e;
            }

            accept(new Response(
                wpResponse.body,
                {
                    headers: wpResponse.headers
                }
            ));
        })
    )
});

async function parsePost(request) {
	if (request.method !== 'POST') {
		return undefined;
	}
	// Try to parse the body as form data
	try {
		const formData = await request.clone().formData();
		const post = {};

		for (var key of formData.keys()) {
			post[key] = formData.get(key);
		}

		return post;
	} catch (e) { }

	// Try to parse the body as JSON
	return await request.clone().json();
}
	


// (async function test() {
// 	const wp = new WP(new PhpWithWP());
// 	const results = await wp.php.run(`<?php 
// 		$p = new PDO("sqlite:/preload/wordpress-6.0.1/wp-content/database/.ht.sqlite");
// 		try {
// 			// $query = $p->query("SELECT * from wp_users");
// 			// print_r($query->fetchAll(PDO::FETCH_ASSOC));

// 			$query = $p->query("SELECT * from wp_options where option_name='home'");
// 			print_r($query->fetchAll(PDO::FETCH_ASSOC));
// 			echo "A";
// 		} catch(Exception $e) {
// 			echo $e->getMessage();
// 		}
// 	`);
// 	console.log(results.stdout);
// })()

