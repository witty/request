## parse http request

### basic usage

	require '/path/to/witty.php';
	witty::init();

	$request = Witty::instance('Request');

	var_dump('foo => '.$request->get_param('foo'));
	var_dump('get_base_url => '.$request->get_base_url(true));
	var_dump('get_host_info => '.$request->get_host_info());
	var_dump('get_url => '.$request->get_url());
	var_dump('get_query_string => '.$request->get_query_string());
	var_dump('get_pathinfo => '.$request->get_pathinfo());
	var_dump('get_request_uri => '.$request->get_request_uri());
	var_dump('get_script_url => '.$request->get_script_url());

